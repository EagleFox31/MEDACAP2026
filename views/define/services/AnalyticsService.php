<?php
use MongoDB\BSON\ObjectId;
/**
 * AnalyticsService.php
 * PHP 7.3 — MongoDB 4.2
 *
 * Rôle : fournir les métriques/agrégats “métier” du dashboard,
 *        à partir de filtres déjà validés/normalisés par le contrôleur de filtres.
 *
 * Dépendances attendues :
 *  - Composer autoload déjà chargé en amont (MongoDB\Client, etc.)
 *  - Base MongoDB "academy" passée au constructeur
 *
 * IMPORTANT :
 *  - On utilise $collection->count($query) (compat PHP 7.3 / MongoDB 4.2)
 *  - On accepte brand en string OU array<string> (multi sélection)
 *  - On accepte technicianId en string OU array<string> (multi sélection)
 */

class AnalyticsService
{
    /** @var \MongoDB\Database */
    private $db;

    public function __construct($academyDb)
    {
        $this->db = $academyDb;
    }

    /* ============================================================
     *                         HELPERS
     * ============================================================ */

    /** Clause “Technicien OU Manager(test=true)” pour certains comptages élargis */
    private function getTechOrTestManagerClause(): array
    {
        return array(
            '$or' => array(
                array('profile' => 'Technicien'),
                array('profile' => 'Manager', 'test' => true),
            ),
        );
    }

    /** ObjectId helper (accepte string|ObjectId) */
    private function toObjectId($id): \MongoDB\BSON\ObjectId
    {
        return ($id instanceof \MongoDB\BSON\ObjectId)
            ? $id
            : new \MongoDB\BSON\ObjectId((string)$id);
    }

    /**
     * Normalise un champ “array de marques” (BSONArray => array, trim, unique)
     * @param mixed $raw
     * @return string[]
     */
    private function normalizeBrands($raw): array
    {
        if ($raw instanceof \MongoDB\Model\BSONArray) {
            $raw = $raw->getArrayCopy();
        }
        if (!is_array($raw)) {
            return array();
        }
        $out = array();
        foreach ($raw as $b) {
            $t = trim((string)$b);
            if ($t !== '' && !in_array($t, $out, true)) {
                $out[] = $t;
            }
        }
        return $out;
    }

    /** Hiérarchie des niveaux “minimum” (Junior ⇒ J,S,E ; Senior ⇒ S,E ; Expert ⇒ E) */
    private function niveauxMin(string $level): array
    {
        switch ($level) {
            case 'Junior': return array('Junior', 'Senior', 'Expert');
            case 'Senior': return array('Senior', 'Expert');
            case 'Expert': return array('Expert');
            default:       return array(); // 'all' ou inconnu → pas de contrainte
        }
    }

    /**
     * Construit la clause hiérarchique sur users :
     *  - subsidiary, agency
     *  - managerId (champ users.manager si présent dans ton schéma)
     *  - technicianId (string|array)
     */
    private function buildHierarchyFilters(array $filters): array
    {
        $q = array();

        if (!empty($filters['subsidiary']) && $filters['subsidiary'] !== 'all') {
            $q['subsidiary'] = (string)$filters['subsidiary'];
        }
        if (!empty($filters['agency']) && $filters['agency'] !== 'all') {
            $q['agency'] = (string)$filters['agency'];
        }
        if (!empty($filters['managerId']) && $filters['managerId'] !== 'all') {
            // selon ton schéma actuel (users.manager ?)
            $q['manager'] = $this->toObjectId($filters['managerId']);
        }

        if (!empty($filters['technicianId']) && $filters['technicianId'] !== 'all') {
            if (is_array($filters['technicianId'])) {
                $ids = array();
                foreach ($filters['technicianId'] as $tid) {
                    $ids[] = $this->toObjectId($tid);
                }
                $q['_id'] = array('$in' => $ids);
            } else {
                $q['_id'] = $this->toObjectId($filters['technicianId']);
            }
        }

        return $q;
    }

    /** Construit une sous-clause égalité/IN pour la marque sur un champ donné */
    private function buildBrandMatch(string $field, $brandFilterOrAll): array
    {
        if (!isset($brandFilterOrAll) || $brandFilterOrAll === 'all') {
            return array();
        }
        if (is_array($brandFilterOrAll)) {
            $in = array();
            foreach ($brandFilterOrAll as $b) {
                $t = trim((string)$b);
                if ($t !== '') $in[] = $t;
            }
            return empty($in) ? array() : array($field => array('$in' => $in));
        }
        $b = trim((string)$brandFilterOrAll);
        return ($b === '') ? array() : array($field => $b);
    }

    /* ============================================================
     *                SÉLECTEURS D’IDs / NIVEAUX
     * ============================================================ */

    /**
     * IDs (ObjectId) des techniciens correspondant aux filtres donnés.
     * NOTE : on cible uniquement les Techniciens actifs ici.
     */
    private function getTechnicianIds(array $filters): array
    {
        $users = $this->db->users;

        $query = array_merge(
            array('profile' => 'Technicien', 'active' => true),
            $this->buildHierarchyFilters($filters)
        );

        // Filtre "level" (hiérarchie minimum)
        if (!empty($filters['level']) && $filters['level'] !== 'all') {
            $query['level'] = array('$in' => $this->niveauxMin($filters['level']));
        }

        // Filtre "brand" (sur les 3 champs)
        if (!empty($filters['brand']) && $filters['brand'] !== 'all') {
            if (is_array($filters['brand'])) {
                $in = array();
                foreach ($filters['brand'] as $b) {
                    $t = trim((string)$b);
                    if ($t !== '') $in[] = $t;
                }
                if (!empty($in)) {
                    $query['$or'] = array(
                        array('brandJunior' => array('$in' => $in)),
                        array('brandSenior' => array('$in' => $in)),
                        array('brandExpert' => array('$in' => $in)),
                    );
                }
            } else {
                $b = trim((string)$filters['brand']);
                if ($b !== '') {
                    $query['$or'] = array(
                        array('brandJunior' => $b),
                        array('brandSenior' => $b),
                        array('brandExpert' => $b),
                    );
                }
            }
        }

        $cursor = $users->find($query, array('projection' => array('_id' => 1)));
        $out = array();
        foreach ($cursor as $doc) {
            $out[] = $doc['_id'];
        }
        return $out;
    }

    /** Compte par niveau (Junior/Senior/Expert) pour une base de requête donnée */
    private function levelCounts(array $baseQuery): array
    {
        $users = $this->db->users;
        return array(
            'juniorCount' => (int)$users->count(array_merge($baseQuery, array('level' => 'Junior'))),
            'seniorCount' => (int)$users->count(array_merge($baseQuery, array('level' => 'Senior'))),
            'expertCount' => (int)$users->count(array_merge($baseQuery, array('level' => 'Expert'))),
        );
    }

    /** Liste des filiales (distinct) — utile pour des résumés par filiale */
    private function getSubsidiaries(): array
    {
        try {
            $vals = $this->db->users->distinct('subsidiary', array('active' => true));
            // clean
            $out = array();
            foreach ($vals as $v) {
                $t = trim((string)$v);
                if ($t !== '' && !in_array($t, $out, true)) $out[] = $t;
            }
            sort($out, SORT_FLAG_CASE | SORT_STRING);
            return $out;
        } catch (\Exception $e) {
            error_log("AnalyticsService::getSubsidiaries error: " . $e->getMessage());
            return array();
        }
    }

    /* ============================================================
     *                      KPIs GLOBAUX
     * ============================================================ */

    /**
     * Global stats :
     *  - totalTechnicians, counts par level
     *  - measuredCount (users.evaluationResults existe & non vide)
     *  - withTrainingCount (users.recommendedTrainings existe & non vide)
     *  - validatedTrainingCount (nb d’items status=validated dans recommendedTrainings)
     */
    public function getGlobalStats(array $filters = array()): array
    {
        $users = $this->db->users;

        $query = array_merge(
            $this->getTechOrTestManagerClause(),
            array('active' => true)
        );

        // Hiérarchie
        $hier = $this->buildHierarchyFilters($filters);
        foreach ($hier as $k => $v) { $query[$k] = $v; }

        // Level min
        if (!empty($filters['level']) && $filters['level'] !== 'all') {
            $query['level'] = array('$in' => $this->niveauxMin($filters['level']));
        }

        // Brand
        if (!empty($filters['brand']) && $filters['brand'] !== 'all') {
            if (is_array($filters['brand'])) {
                $in = array();
                foreach ($filters['brand'] as $b) {
                    $t = trim((string)$b);
                    if ($t !== '') $in[] = $t;
                }
                if (!empty($in)) {
                    $query['$or'] = array(
                        array('brandJunior' => array('$in' => $in)),
                        array('brandSenior' => array('$in' => $in)),
                        array('brandExpert' => array('$in' => $in)),
                    );
                }
            } else {
                $b = trim((string)$filters['brand']);
                if ($b !== '') {
                    $query['$or'] = array(
                        array('brandJunior' => $b),
                        array('brandSenior' => $b),
                        array('brandExpert' => $b),
                    );
                }
            }
        }

        // Niveaux
        $levels = $this->levelCounts($query);
        $total  = $levels['juniorCount'] + $levels['seniorCount'] + $levels['expertCount'];

        // Mesures / trainings
        $measured = (int)$users->count(array_merge($query, array('evaluationResults'   => array('$exists' => true, '$ne' => array()))));
        $withTr   = (int)$users->count(array_merge($query, array('recommendedTrainings'=> array('$exists' => true, '$ne' => array()))));

        // Validés (parcours applicatif minimal)
        $validatedCount = 0;
        $cursor = $users->find(
            array_merge($query, array('recommendedTrainings' => array('$exists' => true, '$ne' => array()))),
            array('projection' => array('recommendedTrainings' => 1))
        );
        foreach ($cursor as $u) {
            if (isset($u['recommendedTrainings']) && is_array($u['recommendedTrainings'])) {
                foreach ($u['recommendedTrainings'] as $t) {
                    if (isset($t['status']) && $t['status'] === 'validated') {
                        $validatedCount++;
                    }
                }
            }
        }

        return array(
            'totalTechnicians'       => $total,
            'juniorCount'            => $levels['juniorCount'],
            'seniorCount'            => $levels['seniorCount'],
            'expertCount'            => $levels['expertCount'],
            'measuredCount'          => $measured,
            'withTrainingCount'      => $withTr,
            'validatedTrainingCount' => $validatedCount,
        );
    }

    /* ============================================================
     *              TRAININGS & VALIDATIONS (AGRÉGATS)
     * ============================================================ */

    /** Public minimal demandé par le DirectorDashboard */
    public function getTrainingStats(array $filters = array()): array
    {
        $agg = $this->getTrainingAggregates($filters);
        return array(
            'totalTrainings' => (int)$agg['totalTrainings'],
            'trainingDays'   => (int)$agg['trainingDays'],
        );
    }

    /**
     * Calcule, par marque :
     *  - trainingsCounts : # modules “proposés” (collection trainings, DISTINCT brand+_id)
     *  - validationsCounts : # modules “validés” (collection validations -> trainings)
     */
    public function getTrainingValidationStats(array $filters = array()): array
    {
        $result    = array('trainingsCounts' => array(), 'validationsCounts' => array());
        $usersColl = $this->db->users;
        $trainColl = $this->db->trainings;
        $validColl = $this->db->validations;

        // 1) Techniciens concernés (ObjectId[])
        $techIds = $this->getTechnicianIds($filters);
        if (empty($techIds)) {
            return $result;
        }

        // 2) Préparation filtre marque (direct ou “brands du technicien”)
        $brandFilter = null;       // string|array|null
        $brandByTech = null;       // array<string>|null

        if (!empty($filters['brand']) && $filters['brand'] !== 'all') {
            $brandFilter = $filters['brand']; // accepte string | array
        } elseif (!empty($filters['technicianId']) && $filters['technicianId'] !== 'all') {
            $techId = is_array($filters['technicianId']) ? $filters['technicianId'][0] : $filters['technicianId'];
            $docTech = $usersColl->findOne(
                array('_id' => $this->toObjectId($techId)),
                array('projection' => array('brandJunior' => 1, 'brandSenior' => 1, 'brandExpert' => 1))
            );
            if ($docTech) {
                $brandByTech = array_values(array_unique(array_merge(
                    $this->normalizeBrands($docTech['brandJunior'] ?? array()),
                    $this->normalizeBrands($docTech['brandSenior'] ?? array()),
                    $this->normalizeBrands($docTech['brandExpert'] ?? array())
                )));
            }
        }

        // Helper pour $match brand dans pipeline
        $matchBrand = function (string $field) use ($brandFilter, $brandByTech): array {
            if ($brandFilter !== null) {
                return $this->buildBrandMatch($field, $brandFilter);
            }
            if ($brandByTech !== null) {
                return empty($brandByTech) ? array() : array($field => array('$in' => $brandByTech));
            }
            return array();
        };

        // Filtre niveau (trainings.level / t.level)
        $levelClause = (!empty($filters['level']) && $filters['level'] !== 'all')
            ? array('level' => (string)$filters['level'])
            : array();

        // 3) AGRÉGATION trainings (proposés)
        $pipeTrain = array(
            array('$match' => array_merge(
                array('active' => true, 'users' => array('$in' => $techIds)),
                $levelClause,
                $matchBrand('brand')
            )),
            // distinct (brand + trainingId)
            array('$group' => array('_id' => array('brand' => '$brand', 'trainingId' => '$_id'))),
            // puis on recompte par marque
            array('$group' => array('_id' => '$_id.brand', 'count' => array('$sum' => 1))),
        );

        foreach ($trainColl->aggregate($pipeTrain) as $doc) {
            // $doc->_id peut être null si brand manquant, on skip dans ce cas
            $brand = isset($doc->_id) ? (string)$doc->_id : '';
            if ($brand !== '') {
                $result['trainingsCounts'][$brand] = (int)$doc->count;
            }
        }

        // 4) AGRÉGATION validations (validées)
        $pipeValid = array(
            array('$match'  => array('status' => 'Validé', 'user' => array('$in' => $techIds))),
            array('$lookup' => array(
                'from'         => 'trainings',
                'localField'   => 'training',
                'foreignField' => '_id',
                'as'           => 't'
            )),
            array('$unwind' => '$t'),
        );

        if (!empty($levelClause)) {
            // $levelClause est ['level' => 'X'] pour trainings ; ici c’est t.level
            $pipeValid[] = array('$match' => array('t.level' => $levelClause['level']));
        }
        $brandMatch = $matchBrand('t.brand');
        if (!empty($brandMatch)) {
            $pipeValid[] = array('$match' => $brandMatch);
        }

        // distinct (brand, trainingId)
        $pipeValid[] = array('$group' => array('_id' => array('brand' => '$t.brand', 'trainingId' => '$t._id')));
        // recompte par brand
        $pipeValid[] = array('$group' => array('_id' => '$_id.brand', 'count' => array('$sum' => 1)));

        foreach ($validColl->aggregate($pipeValid) as $doc) {
            $brand = isset($doc->_id) ? (string)$doc->_id : '';
            if ($brand !== '') {
                $result['validationsCounts'][$brand] = (int)$doc->count;
            }
        }

        return $result;
    }

    /**
     * Détail agrégé “global” sur trainings :
     *  - totalTrainings  : nb de trainings DISTINCTS (actifs, filtrés)
     *  - trainingDays    : nb de couples (tech, training) × 5 (jours)
     */
    private function getTrainingAggregates(array $filters): array
    {
        $trainings = $this->db->trainings;
        $techIds   = $this->getTechnicianIds($filters);

        if (empty($techIds)) {
            return array('totalTrainings' => 0, 'trainingDays' => 0);
        }

        // $match commun
        $match = array(
            'active' => true,
            'users'  => array('$in' => $techIds),
        );
        if (!empty($filters['level']) && $filters['level'] !== 'all') {
            $match['level'] = (string)$filters['level'];
        }
        if (!empty($filters['brand']) && $filters['brand'] !== 'all') {
            $brandMatch = $this->buildBrandMatch('brand', $filters['brand']);
            foreach ($brandMatch as $k => $v) { $match[$k] = $v; }
        }

        $pipeline = array(
            array('$match' => $match),
            array('$addFields' => array(
                'nbTechInScope' => array(
                    '$size' => array(
                        '$filter' => array(
                            'input' => '$users',
                            'as'    => 'u',
                            'cond'  => array('$in' => array('$$u', $techIds))
                        )
                    )
                )
            )),
            array('$group' => array(
                '_id'            => null,
                'totalTrainings' => array('$sum' => 1),
                'totalPairs'     => array('$sum' => '$nbTechInScope')
            )),
            array('$project' => array(
                '_id'            => 0,
                'totalTrainings' => 1,
                'trainingDays'   => array('$multiply' => array('$totalPairs', 5))
            )),
        );

        $cursor = $trainings->aggregate($pipeline);
        $res = array();
        foreach ($cursor as $doc) {
            $res[] = $doc;
        }
        if (empty($res)) {
            return array('totalTrainings' => 0, 'trainingDays' => 0);
        }

        // BSONDocument → array
        /** @var \MongoDB\Model\BSONDocument $doc */
        $doc  = $res[0];
        $data = $doc->getArrayCopy();

        return array(
            'totalTrainings' => (int)($data['totalTrainings'] ?? 0),
            'trainingDays'   => (int)($data['trainingDays']   ?? 0),
        );
        // Si un jour tu veux utiliser trainings.duration (heures) et duree_jours :
        // adapte ici le calcul (aujourd’hui la règle est fixe : 1 couple = 5 jours).
    }

    /* ============================================================
     *                 SCORES / BRAND (graphes)
     * ============================================================ */

    /**
     * getBrandScores :
     *  - Calcule pour chaque brand un score moyen (sur users.evaluationResults[*].score)
     *  - Retourne un tableau [{x: brand, y: averageScore, fillColor: #hex}]
     *    avec code couleur : >=80 vert, >=60 jaune, sinon rouge.
     */
    public function getBrandScores(array $filters = array()): array
    {
        $users = $this->db->users;
        $scores = array();

        // Récupérer la liste des brands disponibles dans le scope (simple union via distinct)
        // NB: tu peux aussi injecter une liste depuis le contrôleur si tu l’as déjà.
        $brandQuery = array('active' => true);
        $hier = $this->buildHierarchyFilters($filters);
        foreach ($hier as $k => $v) { $brandQuery[$k] = $v; }
        // level ?
        if (!empty($filters['level']) && $filters['level'] !== 'all') {
            $brandQuery['level'] = array('$in' => $this->niveauxMin($filters['level']));
        }

        $bJ = $users->distinct('brandJunior', $brandQuery);
        $bS = $users->distinct('brandSenior', $brandQuery);
        $bE = $users->distinct('brandExpert', $brandQuery);
        $all = array_merge($bJ, $bS, $bE);

        $brands = array();
        foreach ($all as $b) {
            $t = trim((string)$b);
            if ($t !== '' && !in_array($t, $brands, true)) $brands[] = $t;
        }
        sort($brands, SORT_FLAG_CASE | SORT_STRING);

        foreach ($brands as $brand) {
            // Query pour les users de cette brand + évaluations
            $q = array_merge(
                $this->getTechOrTestManagerClause(),
                array('active' => true),
                $this->buildHierarchyFilters($filters)
            );

            if (!empty($filters['level']) && $filters['level'] !== 'all') {
                $q['level'] = array('$in' => $this->niveauxMin($filters['level']));
            }

            $q['$or'] = array(
                array('brandJunior' => $brand),
                array('brandSenior' => $brand),
                array('brandExpert' => $brand),
            );
            $q['evaluationResults'] = array('$exists' => true, '$ne' => array());

            $cursor = $users->find($q, array('projection' => array('evaluationResults' => 1)));
            $totalScore = 0;
            $count      = 0;

            foreach ($cursor as $u) {
                if (isset($u['evaluationResults']) && is_array($u['evaluationResults'])) {
                    foreach ($u['evaluationResults'] as $ev) {
                        if (isset($ev['brand']) && $ev['brand'] === $brand && isset($ev['score'])) {
                            $totalScore += (int)$ev['score'];
                            $count++;
                        }
                    }
                }
            }

            $avg = ($count > 0) ? (int)round($totalScore / $count) : 0;

            // palette
            $color = '#dc3545'; // rouge
            if ($avg >= 80)      $color = '#198754'; // vert
            elseif ($avg >= 60)  $color = '#ffc107'; // jaune

            $scores[] = array('x' => $brand, 'y' => $avg, 'fillColor' => $color);
        }

        // tri décroissant sur le score
        usort($scores, function ($a, $b) {
            return $b['y'] <=> $a['y'];
        });

        return $scores;
    }

    /* ============================================================
     *                 RÉSUMÉS & LISTES D’AIDE
     * ============================================================ */

    /**
     * Résumé techniciens par filiale :
     *  - totalTechnicians
     *  - juniorCount / seniorCount / expertCount
     *
     * S’appuie sur users.distinct('subsidiary'), puis compte par niveau
     * pour chaque filiale en respectant les autres filtres (agency/manager/brand/level/technician).
     */
    public function getTechnicianSummary(array $filters = array()): array
    {
        $summary = array();

        // Si une filiale précise est déjà fixée, on ne calcule que celle-ci
        $subsList = array();
        if (!empty($filters['subsidiary']) && $filters['subsidiary'] !== 'all') {
            $subsList = array((string)$filters['subsidiary']);
        } else {
            $subsList = $this->getSubsidiaries();
        }

        foreach ($subsList as $subs) {
            // base query : techniciens actifs, + hiérarchie
            $base = array(
                'profile' => 'Technicien',
                'active'  => true,
                'subsidiary' => $subs
            );

            $hier = $this->buildHierarchyFilters(array_merge($filters, array('subsidiary' => $subs)));
            foreach ($hier as $k => $v) { $base[$k] = $v; }

            // brand & level éventuels
            if (!empty($filters['brand']) && $filters['brand'] !== 'all') {
                if (is_array($filters['brand'])) {
                    $in = array();
                    foreach ($filters['brand'] as $b) {
                        $t = trim((string)$b);
                        if ($t !== '') $in[] = $t;
                    }
                    if (!empty($in)) {
                        $base['$or'] = array(
                            array('brandJunior' => array('$in' => $in)),
                            array('brandSenior' => array('$in' => $in)),
                            array('brandExpert' => array('$in' => $in)),
                        );
                    }
                } else {
                    $b = trim((string)$filters['brand']);
                    if ($b !== '') {
                        $base['$or'] = array(
                            array('brandJunior' => $b),
                            array('brandSenior' => $b),
                            array('brandExpert' => $b),
                        );
                    }
                }
            }
            if (!empty($filters['level']) && $filters['level'] !== 'all') {
                $base['level'] = array('$in' => $this->niveauxMin($filters['level']));
            }

            $levels = $this->levelCounts($base);
            $total  = $levels['juniorCount'] + $levels['seniorCount'] + $levels['expertCount'];

            if ($total > 0) {
                $summary[$subs] = array(
                    'totalTechnicians' => $total,
                    'juniorCount'      => $levels['juniorCount'],
                    'seniorCount'      => $levels['seniorCount'],
                    'expertCount'      => $levels['expertCount'],
                );
            }
        }

        return $summary;
    }

    /**
     * Liste des managers (id => "First Last") pour une filiale / agence.
     * NB : si ton schéma ne stocke pas manager dans users, adapte ici.
     */
    public function getManagers($subsidiary, $agency = 'all'): array
    {
        $out = array();
        if (empty($subsidiary) || $subsidiary === 'all') {
            return $out;
        }

        try {
            $query = array(
                'subsidiary' => (string)$subsidiary,
                'profile'    => 'Manager',
                'active'     => true
            );
            if (!empty($agency) && $agency !== 'all') {
                $query['agency'] = (string)$agency;
            }

            $cursor = $this->db->users->find($query, array(
                'projection' => array('_id' => 1, 'firstName' => 1, 'lastName' => 1)
            ));
            foreach ($cursor as $m) {
                $out[(string)$m['_id']] = trim(($m['firstName'] ?? '') . ' ' . ($m['lastName'] ?? ''));
            }
        } catch (\Exception $e) {
            error_log("AnalyticsService::getManagers error: " . $e->getMessage());
        }

        return $out;
    }
}
