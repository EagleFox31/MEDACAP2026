<?php
namespace views\define\services;

use MongoDB\BSON\ObjectId;
use MongoDB\Client as MongoClient;

require_once __DIR__ . '../../../../vendor/autoload.php';

class PIFService
{
    protected $academy;
    protected $users;
    protected $allocations;
    protected $results;
    protected $validations;

    public function __construct()
    {
        $client            = new MongoClient("mongodb://localhost:27017");
        $this->academy     = $client->academy;
        $this->users       = $this->academy->users;
        $this->allocations = $this->academy->allocations;
        $this->results     = $this->academy->results;
        $this->validations = $this->academy->validations;
    }

    /* ------------------------------------------------------------------ */
    /*  ----------- 1. UTILS : FILTRES UTILISATEURS / ALLOC ------------- */
    /* ------------------------------------------------------------------ */

    public function getUsersByLevel(?string $level = null): array
    {
        $query = [
            'profile' => ['$in' => ['Technicien', 'Manager']],
            'active'  => true,
            '$or'     => [
                ['profile' => 'Technicien'],
                ['profile' => 'Manager', 'test' => true],
            ],
        ];
        if ($level) {
            $query['level'] = $level;
        }

        $ids = [];
        foreach ($this->users->find($query) as $u) {
            $ids[] = new ObjectId($u['_id']);
        }
        return $ids;
    }

    public function getCountUsersByLevel(): array
    {
        return array_unique(array_merge(
            $this->getUsersByLevel('Junior'),
            $this->getUsersByLevel('Senior'),
            $this->getUsersByLevel('Expert')
        ));
    }

    /* ------------------------------------------------------------------ */
    /*  ----------- 2. RÈGLE « TEST TERMINÉ » (identique ancien code) ---- */
    /* ------------------------------------------------------------------ */

    /**  Vérifie la règle Factuel + Declaratif (manager‑validé) pour 1 niveau */
    protected function hasFinishedLevel(ObjectId $uid, string $level): bool
    {
        $factuel = $this->allocations->findOne([
            'user'   => $uid,
            'level'  => $level,
            'type'   => 'Factuel',
            'active' => true,
        ]);

        $declaratif = $this->allocations->findOne([
            'user'          => $uid,
            'level'         => $level,
            'type'          => 'Declaratif',
            'active'        => true,
            'activeManager' => true,
        ]);

        return $factuel && $declaratif;
    }

    /**  True si l’utilisateur a terminé AU MOINS UN niveau */
    protected function hasFinishedAtLeastOneLevel(ObjectId $uid): bool
    {
        foreach (['Junior', 'Senior', 'Expert'] as $lvl) {
            if ($this->hasFinishedLevel($uid, $lvl)) {
                return true;
            }

        }
        return false;
    }

    /* ------------------------------------------------------------------ */
    /*  ---------------- 3. INDICATEURS PIF (mêmes valeurs) -------------- */
    /* ------------------------------------------------------------------ */

    public function getPIFIndicators(): array
    {
        /* a) Population de référence (inchangé) */
        $allUsersIds         = $this->getCountUsersByLevel();
        $techniciensFiliales = count($allUsersIds);

        /* b) techniciensEvalues (inchangé) */
        $evaluated = [];
        foreach (['Junior', 'Senior', 'Expert'] as $lvl) {
            $candidates = $this->getUsersByLevel($lvl);
            $done       = array_filter(
                $candidates,
                function($uid) use ($lvl) { return $this->hasFinishedLevel($uid, $lvl); }
            );
            $evaluated = array_merge($evaluated, $done);
        }
        $evaluated          = array_unique($evaluated);
        $techniciensEvalues = count($evaluated);

        /* -------- NOUVEAU : techniciensAvecPIF (simple présence Training) ---- */
        $withPIF = $this->users->aggregate([
            ['$match' => ['_id' => ['$in' => $allUsersIds]]],          // toute la population
            ['$lookup' => [                                             // allocations Training
                'from'     => 'allocations',
                'let'      => ['uid' => '$_id'],
                'pipeline' => [[
                    '$match' => [
                        '$expr' => [
                            '$and' => [
                                ['$eq' => ['$user', '$$uid']],
                                ['$eq' => ['$type', 'Training']],
                            ],
                        ],
                    ],
                ]],
                'as'       => 'alloTraining',
            ]],
            ['$match' => ['alloTraining' => ['$ne' => []]]],           // au moins une allocation
            ['$count' => 'total']
        ])->toArray();

        $techniciensAvecPIF = $withPIF ? $withPIF[0]['total'] : 0;

        /* -------- pifValides (inchangé, mais on peut ré‑utiliser $evaluated) -- */
        $pifValides = 0;
        if ($techniciensEvalues > 0) {
            $agg = $this->users->aggregate([
                ['$match' => ['_id' => ['$in' => array_values($evaluated)]]],
                ['$lookup' => [
                    'from'     => 'applications',
                    'let'      => ['uid' => '$_id'],
                    'pipeline' => [[
                        '$match' => [
                            '$expr' => [
                                '$and' => [
                                    ['$eq' => ['$user', '$$uid']],
                                    ['$eq' => ['$active', true]],
                                ],
                            ],
                        ],
                    ]],
                    'as'       => 'validPIF',
                ]],
                ['$match' => ['validPIF' => ['$ne' => []]]],
                ['$count' => 'total']
            ])->toArray();

            $pifValides = $agg ? $agg[0]['total'] : 0;
        }

        /* ---------- Retour --------------------------------------------------- */
        return [
            'techniciensFiliales' => $techniciensFiliales,
            'techniciensEvalues'  => $techniciensEvalues,
            'techniciensAvecPIF'  => $techniciensAvecPIF,
            'pifValides'          => $pifValides,
        ];
    }


    public function getPIFValidationData(): array
    {
        $ind   = $this->getPIFIndicators();
        $with  = $ind['techniciensAvecPIF'];
        $valid = $ind['pifValides'];

        $pctValid = $with ? round($valid / $with * 100) : 0;

        return [
            'labels' => ['% PIF à valider', '% PIF validé'],
            'values' => [100 - $pctValid, $pctValid],
            'colors' => ['#00B0F0', '#92D050'],
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  -------- 4.  GRAPHIQUE  « Proposition par l’academy »  -----------*/
    /* ------------------------------------------------------------------ */

    public function getPIFPropositionData(): array
    {
        $ind   = $this->getPIFIndicators();      // ← récupère les quatre compteurs
        $total = $ind['techniciensFiliales'];
        $with  = $ind['techniciensAvecPIF'];

        $pctWith = $total ? round($with / $total * 100) : 0;

        return [
            'labels' => ['% PIF à créer', '% PIF proposé'],
            'values' => [100 - $pctWith, $pctWith],
            'colors' => ['#DCDCDC', '#00B0F0'],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* ---------------  UTILITAIRE DE CONSTRUCTION DE FILTRE  ----------- */
    /* ------------------------------------------------------------------ */
    /**
     * Construit la requête « users » en appliquant les mêmes
     * filtres que ton FilterController.
     *
     * @param array  $filters  filtres GET/SESSION actuels
     * @param string $level    Junior | Senior | Expert
     * @return array           requête Mongo équivalente
     */
    private function buildUserQuery(array $filters, string $level)
    {
        $query = [
            'active'  => true,
            'level'   => $level,
            'profile' => ['$in' => ['Technicien', 'Manager']],
            '$or'     => [
                ['profile' => 'Technicien'],
                ['profile' => 'Manager', 'test' => true],
            ],
        ];

        // PRIORITÉ : Si on a une liste d'IDs explicite (scope déjà filtré par le contrôleur)
        if (!empty($filters['userIds']) && is_array($filters['userIds'])) {
            $query['_id'] = ['$in' => $filters['userIds']];
            return $query; // On court-circuite les autres filtres car ils ont déjà été appliqués pour obtenir ces IDs
        }

        if (!empty($filters['subsidiary']) && $filters['subsidiary'] !== 'all') {
            $query['subsidiary'] = $filters['subsidiary'];
        }
        if (!empty($filters['agency']) && $filters['agency'] !== 'all') {
            $query['agency']      = $filters['agency'];
        }
        if (!empty($filters['managerId']) && $filters['managerId'] !== 'all') {
            $query['manager']     = $filters['managerId'];
        }
        if (!empty($filters['brand']) && $filters['brand'] !== 'all') {
            $query['$or'][] = ['brandJunior' => $filters['brand']];
            $query['$or'][] = ['brandSenior' => $filters['brand']];
            $query['$or'][] = ['brandExpert' => $filters['brand']];
        }

        return $query;
    }

    /* ------------------------------------------------------------------ */
    /* -------- COMPTE PIF EXACT APRÈS VALIDATION FACTUEL+DECLARATIF ----- */
    /* ------------------------------------------------------------------ */
    /**
     * Nombre de techniciens d’un niveau EXACT (Junior/Senior/Expert)
     * • qui ont terminé la mesure (Factuel + Declaratif ok)  
     * • et qui possèdent AU MOINS un PIF (Training) au même niveau,
     *   le tout en respectant les filtres du dashboard.
     *
     * @param string $level   'Junior' | 'Senior' | 'Expert'
     * @param array  $filters Filtres GET/SESSION actuels
     * @return int
     */
    public function countPIFByExactLevel(string $level, array $filters = [])
    {
        /* 1) Liste des users concernés par les filtres + level exact */
        $cursor = $this->users->find(
            $this->buildUserQuery($filters, $level),
            ['projection' => ['_id' => 1]]
        );

        $eligibleIds = [];
        foreach ($cursor as $u) {
            $oid = new ObjectId($u['_id']);

            /* 1.1) on garde UNIQUEMENT ceux qui ont
                    - Factuel  active = true
                    - Declaratif activeManager = true     */
            if ($this->hasFinishedLevel($oid, $level)) {
                $eligibleIds[] = $oid;
            }
        }
        if (empty($eligibleIds)) {
            return 0;           // aucun tech ne remplit les 2 conditions
        }

        /* 2) DISTINCT des users ayant ≥1 Training du même level */
        $distinctUsers = $this->allocations->distinct('user', [
            'user'  => ['$in' => $eligibleIds],
            'type'  => 'Training',   // PIF proposé
            'level' => $level
        ]);

        return count($distinctUsers);   // 1 seule fois par technicien
    }

    /* ============================================================= */
    /* -----------   VALIDATIONS FILIALE  (Validé / Non Validé) ----- */
    /* ============================================================= */
    /**
     * Retourne le nombre de techniciens d’un niveau EXACT
     * qui ont AU MOINS une action de validation sur l’un de leurs
     * trainings PIF (statut “Validé” OU “Non Validé”),
     * en respectant les filtres du dashboard.
     *
     * @param string $level   'Junior' | 'Senior' | 'Expert'
     * @param array  $filters filtres GET/SESSION
     * @return int
     */
    public function countUsersWithValidation(string $level, array $filters = [])
    {
        /* 1) population filtrée + level exact */
        $cursor = $this->users->find(
            $this->buildUserQuery($filters, $level),
            ['projection' => ['_id' => 1]]
        );

        $uids = [];
        foreach ($cursor as $u) {
            $uids[] = new ObjectId($u['_id']);
        }
        if (empty($uids)) {
            return 0;               // personne dans le périmètre
        }

        /* 2) DISTINCT des users présents dans validations (Validé / Non Validé) */
        $distinct = $this->validations->distinct('user', [
            'user'   => ['$in' => $uids],
            'status' => ['$in' => ['Validé', 'Non Validé']]
            // si le champ level existe dans validations :
            // ,'level'  => $level
        ]);

        return count($distinct);    // 1 seul par technicien
    }






}
      