<?php
/**
 * dashboard.php – version “tout-en-un” mais organisée
 * ---------------------------------------------------
 * 1. Boot & config
 * 2. Helpers & sécurité
 * 3. Accès bases (DAO)
 * 4. Services métier
 * 5. Contrôleurs rôle-dépendants
 * 6. Vue / rendu final
 */

/* --------------------------------------------------------------------------
 | 1. Boot & config
 |--------------------------------------------------------------------------*/
 session_start();
 require_once __DIR__ . '/../../vendor/autoload.php';
 
 /* ------------------------------------------------------------------
  *  Connexion MongoDB (URI + nom de base) – adapter via .env si besoin
  * -----------------------------------------------------------------*/
 const MONGO_URI = 'mongodb://localhost:27017';
 const DB_NAME   = 'academy';
 
 /* ------------------------------------------------------------------
  *  Mapping marque ➜ nom de fichier logo (public/img/)
  * -----------------------------------------------------------------*/
 $BRAND_LOGOS = [
     'RENAULT TRUCK'   => 'renaultTrucks.png',
     'HINO'            => 'Hino_logo.png',
     'TOYOTA BT'       => 'bt.png',
     'SINOTRUK'        => 'sinotruk.png',
     'JCB'             => 'jcb.png',
     'MERCEDES TRUCK'  => 'mercedestruck.png',
     'TOYOTA FORKLIFT' => 'forklift.png',
     'FUSO'            => 'fuso.png',
     'LOVOL'           => 'lovol.png',
     'KING LONG'       => 'kl2.png',
     'MERCEDES'        => 'mercedestruck.png',
     'TOYOTA'          => 'toyota-logo.png',
     'SUZUKI'          => 'suzuki-logo.png',
     'MITSUBISHI'      => 'mitsubishi-logo.png',
     'BYD'             => 'byd-logo.png',
     'CITROEN'         => 'citroen-logo.png',
     'PEUGEOT'         => 'peugeot-logo.png',
 ];

/* --------------------------------------------------------------------------
 | 2. Helpers & sécurité
 |--------------------------------------------------------------------------*/
function safeObjectId(string $id): ?MongoDB\BSON\ObjectId
{
    try { return new MongoDB\BSON\ObjectId($id); }
    catch (Throwable $e) { return null; }
}

function levelsToInclude(string $level): array
{
    switch ($level) {
        case 'Junior':
            return ['Junior'];
        case 'Senior':
            return ['Junior', 'Senior'];
        case 'Expert':
            return ['Junior', 'Senior', 'Expert'];
        default:
            return ['Junior', 'Senior', 'Expert'];
    }
}

/* --------------------------------------------------------------------------
 | 3. Accès données (DAO “inline”)
 |--------------------------------------------------------------------------*/
/**
 *  Repo  —  Data-access “mini layer” (compatible PHP 7.4)
 *  ------------------------------------------------------
 *  • Centralise tous les accès collection-orientés utilisés dans le dashboard.
 *  • Les méthodes retournent soit un document (stdClass|array),
 *    soit un MongoDB\Driver\Cursor, soit un array PHP déjà materialisé.
 */
final class Repo
{
    /** @var MongoDB\Database */
    private $db;

    public function __construct()
    {
        $client   = new MongoDB\Client(MONGO_URI);
        $this->db = $client->selectDatabase(DB_NAME);
    }

    /* ---------- USERS -------------------------------------------------- */

    /** Retourne UN utilisateur par _id ou null */
    public function findUser(string $id)
    {
        return $this->db->users->findOne(['_id' => safeObjectId($id)]);
    }

    /** Liste des techniciens (+ filtres additionnels éventuels) */
    public function findTechnicians(array $cond = [])
    {
        return $this->db->users->find(array_merge(['profile' => 'Technicien'], $cond));
    }

    /** Liste des managers (+ filtres) */
    public function findManagers(array $cond = [])
    {
        return $this->db->users->find(array_merge(['profile' => 'Manager'], $cond));
    }

    /** Pipeline agrégé sur la collection users */
    public function aggregateUsers(array $pipeline) : array
    {
        return $this->db->users->aggregate($pipeline)->toArray();
    }

    /* ---------- TRAININGS ---------------------------------------------- */

    /** Un training spécifique */
    public function findTraining(string $id)
    {
        return $this->db->trainings->findOne(['_id' => safeObjectId($id)]);
    }

    /** Liste simple des trainings (filtre libre) */
    public function listTrainings(array $filter = [])
    {
        return $this->db->trainings->find($filter);
    }

    /** Trainings d’un utilisateur précis (+ filtre optionnel brand/level/active …) */
    public function trainingsForUser(string $uid, array $filter = [])
    {
        $oid = safeObjectId($uid);
        if (!$oid) {
            // Renvoie un curseur vide pour garder une interface homogène
            return new ArrayIterator([]);
        }
        $filter['users'] = $oid;
        return $this->db->trainings->find($filter);
    }

    /** Pipeline agrégé (lookup, facet) sur trainings */
    public function aggregateTrainings(array $pipeline) : array
    {
        return $this->db->trainings->aggregate($pipeline)->toArray();
    }

    /* ---------- APPLICATIONS / ALLOCATIONS / VALIDATIONS --------------- */

    public function findApplications(array $filter = [])
    {
        return $this->db->applications->find($filter);
    }

    public function aggregateApplications(array $pipeline) : array
    {
        return $this->db->applications->aggregate($pipeline)->toArray();
    }

    public function findAllocations(array $filter = [])
    {
        return $this->db->allocations->find($filter);
    }

    public function findAllocation(array $filter = [])
    {
        return $this->db->allocations->findOne($filter);
    }

    public function findValidations(array $filter = [])
    {
        return $this->db->validations->find($filter);
    }

    /* ---------- MANAGERS / SCORES CACHES ------------------------------- */

    /** Snapshot hiérarchique (managersBySubsidiaryAgency) */
    public function hierarchySnapshot()
    {
        return $this->db->managersBySubsidiaryAgency->find();
    }

    /** Scores mis en cache (technicianBrandScores) pour un tech ou all */
    public function cachedScores(array $filter = [])
    {
        return $this->db->technicianBrandScores->find($filter);
    }
}


/* --------------------------------------------------------------------------
 | 4. Services métier (calculs)
 |--------------------------------------------------------------------------*/
/**
 * ------------------------------------------------------------
 *  ScoreService   – agrège les scores Factuel / Déclaratif
 * ------------------------------------------------------------
 *  $rawScores attendu :
 *      [
 *          'Junior' => [
 *              'RENAULT TRUCK' => ['Factuel'=>72,'Declaratif'=>68],
 *              'HINO'         => ['Factuel'=>81]                 ,
 *          ],
 *          'Senior' => [ … ],
 *      ]
 *  $filters optionnels : ['level' => 'Junior|Senior|Expert|all',
 *                         'brand' => 'TOYOTA|all'               ]
 *  Sortie : tableau prêt pour ChartJS / ApexCharts :
 *      [
 *          ['brand'=>'HINO','avg'=>81,'color'=>'#198754'],
 *          ['brand'=>'RENAULT TRUCK','avg'=>70,'color'=>'#ffc107'],
 *      ]
 */
final class ScoreService
{
    public function computeBrandScores(array $rawScores, array $filters = []) : array
    {
        $wantLevel  = $filters['level']  ?? 'all';
        $wantBrand  = isset($filters['brand']) ? strtolower($filters['brand']) : 'all';

        /* ---- Agrégation par marque ----------------------------------- */
        $bucket = [];  // brand => ['sum'=>…, 'count'=>…]
        foreach ($rawScores as $level => $brands) {
            if ($wantLevel !== 'all' && $level !== $wantLevel) {
                continue;          // on skippe les autres niveaux
            }
            foreach ($brands as $brand => $vals) {
                if ($wantBrand !== 'all' && strtolower($brand) !== $wantBrand) {
                    continue;      // on skippe les autres marques
                }

                $fact = $vals['Factuel']    ?? null;
                $decl = $vals['Declaratif'] ?? null;
                if ($fact === null && $decl === null) {
                    continue;      // pas de score du tout
                }

                $avg = ($fact !== null && $decl !== null)
                        ? ($fact + $decl) / 2
                        : ($fact ?? $decl);

                if (!isset($bucket[$brand])) {
                    $bucket[$brand] = ['sum' => 0, 'count' => 0];
                }
                $bucket[$brand]['sum']   += $avg;
                $bucket[$brand]['count'] += 1;
            }
        }

        /* ---- Transformation finale ---------------------------------- */
        $dataset = [];
        foreach ($bucket as $brand => $info) {
            $avg = round($info['sum'] / max(1, $info['count']), 2);
            $color = ($avg >= 80)
                        ? '#198754'       // vert
                        : (($avg >= 60) ? '#ffc107' : '#dc3545'); // jaune ou rouge

            $dataset[] = [
                'brand' => $brand,
                'avg'   => $avg,
                'color' => $color,
            ];
        }

        // On trie par moyenne décroissante pour un rendu plus lisible
        usort($dataset, static function ($a, $b) {
            return $b['avg'] <=> $a['avg'];
        });

        return $dataset;
    }
}


/**
 * ------------------------------------------------------------
 *  TrainingStatsService – KPIs globaux Formations & Tests
 * ------------------------------------------------------------
 *  $allocations → curseur/array de docs `allocations`
 *  $applications → idem pour `applications` (demandes)
 *
 *  La méthode renvoie :
 *      [
 *          'testsDone'            => 13,
 *          'factuel'              => 20,
 *          'declaratif'           => 18,
 *          'trainingsRecommended' => 12,
 *          'trainingsCompleted'   => 7,
 *      ]
 */
final class TrainingStatsService
{
    public function kpi(array $allocations, array $applications) : array
    {
        $stats = [
            'factuel'              => 0,
            'declaratif'           => 0,
            'testsDone'            => 0,
            'trainingsRecommended' => count($applications),
            'trainingsCompleted'   => 0,
        ];

        /* ---- Traqueur pour savoir si Factuel + Declaratif faits ------- */
        $testTracker = [];      // "userId|level" => ['F'=>bool,'D'=>bool]

        foreach ($allocations as $alloc) {
            $type = $alloc['type']   ?? '';
            $uid  = (string)($alloc['user']  ?? '');
            $lvl  = (string)($alloc['level'] ?? '');
            $key  = $uid . '|' . $lvl;

            switch ($type) {
                case 'Factuel':
                    $stats['factuel']++;
                    $testTracker[$key]['F'] = true;
                    break;

                case 'Declaratif':
                    $stats['declaratif']++;
                    $testTracker[$key]['D'] = true;
                    break;

                case 'Training':
                    // on considère "completed" quand active === false
                    if (isset($alloc['active']) && $alloc['active'] === false) {
                        $stats['trainingsCompleted']++;
                    }
                    break;
            }
        }

        /* ---- Comptage tests terminés (Factuel + Declaratif) ----------- */
        foreach ($testTracker as $flags) {
            if (!empty($flags['F']) && !empty($flags['D'])) {
                $stats['testsDone']++;
            }
        }

        return $stats;
    }
}


/* --------------------------------------------------------------------------
 | 5. Contrôleurs rôle-dépendants
 |--------------------------------------------------------------------------*/
/* =======================================================================
 *  Flows : orchestration métier selon le rôle
 * =====================================================================*/

/**
 *  TECHNICIAN FLOW
 *  ---------------
 *  • Récupère son doc user
 *  • Lit ses scores (cache technicianBrandScores)
 *  • Construit KPI formations / tests
 */
function technicianFlow(string $techId, Repo $repo): array
{
    /* -------- User -------- */
    $user = $repo->findUser($techId);
    if (!$user || ($user['profile'] ?? '') !== 'Technicien') {
        throw new RuntimeException('Technicien introuvable');
    }

    /* -------- Scores (cache) -------- */
    $cacheDoc = $repo->cachedScores(['userId' => safeObjectId($techId)])->toArray();
    $rawScores = $cacheDoc ? $cacheDoc[0]['scores'] : [];

    $filters = [
        'level' => $_GET['level']  ?? 'all',
        'brand' => $_GET['brand']  ?? 'all',
    ];
    $scores = (new ScoreService())->computeBrandScores($rawScores, $filters);

    /* -------- KPI -------- */
    $allocations  = iterator_to_array(
        $repo->findAllocations(['user' => safeObjectId($techId)])
    );
    $applications = iterator_to_array(
        $repo->findApplications(['user' => safeObjectId($techId)])
    );
    $kpis = (new TrainingStatsService())->kpi($allocations, $applications);

    return compact('user', 'scores', 'kpis'); // => $user, $scores, $kpis
}

/**
 *  MANAGER / DIRECTOR FLOW
 *  -----------------------
 *  $ctx : ['managerId'=>'all|<id>', 'filiale'=>'all|...', 'agence'=>'all|...']
 *  • Construit la hiérarchie via managersBySubsidiaryAgency
 *  • Agrège les scores de tous les techniciens concernés
 *  • Calcule KPI globaux
 */
function managerFlow(array $ctx, Repo $repo): array
{
    $managerId = $ctx['managerId'];
    $wantFil   = strtolower($ctx['filiale']);
    $wantAg    = strtolower($ctx['agence']);

    /* -------- Snapshot hiérarchie -------- */
    $tree = iterator_to_array($repo->hierarchySnapshot());

    /* -------- Récup liste techniciens concernés -------- */
    $techIds = [];
    $managerDoc = null;

    foreach ($tree as $filiale) {
        $filName = strtolower($filiale['subsidiary'] ?? '');
        if ($wantFil !== 'all' && $filName !== $wantFil) continue;

        foreach (($filiale['agencies'] ?? []) as $agency) {
            $agName = strtolower($agency['_id'] ?? '');
            if ($wantAg !== 'all' && $agName !== $wantAg) continue;

            foreach (($agency['managers'] ?? []) as $mgr) {
                $isWantedMgr = ($managerId === 'all') || ((string)$mgr['_id'] === $managerId);
                if (!$isWantedMgr) continue;

                if ($managerId !== 'all') $managerDoc = $mgr;
                foreach (($mgr['technicians'] ?? []) as $tech) {
                    $techIds[] = (string)$tech['_id'];
                }
            }
        }
    }
    $techIds = array_unique($techIds);

    if (empty($techIds)) {
        throw new RuntimeException('Aucun technicien dans le périmètre sélectionné');
    }

    /* -------- Agrégation des scores -------- */
    $rawScores = []; // level ⇒ brand ⇒ sum/count

    foreach ($techIds as $tid) {
        $cache = $repo->cachedScores(['userId' => safeObjectId($tid)])->toArray();
        if (!$cache) continue;
        foreach ($cache[0]['scores'] as $lvl => $brands) {
            foreach ($brands as $br => $val) {
                $avg = $val['averageTotalWithPenalty'] ?? $val['averageTotal'] ?? null;
                if ($avg === null) continue;

                if (!isset($rawScores[$lvl][$br])) {
                    $rawScores[$lvl][$br] = ['sum' => 0, 'cnt' => 0];
                }
                $rawScores[$lvl][$br]['sum'] += $avg;
                $rawScores[$lvl][$br]['cnt'] += 1;
            }
        }
    }

    /*  normalise -> format attendu par ScoreService */
    $normalized = [];
    foreach ($rawScores as $lvl => $brands) {
        foreach ($brands as $br => $info) {
            $normalized[$lvl][$br] = [
                'Factuel'     => $info['sum'] / $info['cnt'], // on stocke tout dans Factuel pour la simplicité
                'Declaratif'  => $info['sum'] / $info['cnt'],
            ];
        }
    }

    $filters = [
        'level' => $_GET['level']  ?? 'all',
        'brand' => $_GET['brand']  ?? 'all',
    ];
    $scores = (new ScoreService())->computeBrandScores($normalized, $filters);

    /* -------- KPI globaux -------- */
    $allocFilter = ['user' => ['$in' => array_map('safeObjectId', $techIds)]];
    $allocations  = iterator_to_array($repo->findAllocations($allocFilter));
    $applications = iterator_to_array($repo->findApplications($allocFilter));
    $kpis = (new TrainingStatsService())->kpi($allocations, $applications);

    return [
        'manager' => $managerDoc,
        'technicians' => $techIds,
        'scores'  => $scores,
        'kpis'    => $kpis,
    ];
}


/* --------------------------------------------------------------------------
 | 6. Front-controller
 |--------------------------------------------------------------------------*/
/* ===============================================================
 *  Front-Controller : choix du flow + rendu
 * ===============================================================*/
$repo    = new Repo();
$profile = $_SESSION['profile'] ?? null;

if (!$profile) {
    header('Location: /');
    exit;
}

try {
    /* -------- ORCHESTRATION PAR RÔLE -------------------------- */
    switch ($profile) {

        case 'Technicien':
            $data = technicianFlow($_SESSION['id'], $repo);
            break;

        /* ----- Profils “manageriaux” qui partagent le même flow --- */
        case 'Manager':
        case 'Directeur Général':
        case 'Directeur Pièce et Service':
        case 'Directeur des Opérations':
        case 'Directeur Groupe':
        case 'Super Admin':
        case 'Admin':
            $data = managerFlow(
                [
                    'managerId' => $_GET['managerId'] ?? 'all',
                    'filiale'   => $_GET['filiale']   ?? 'all',
                    'agence'    => $_GET['agence']    ?? 'all',
                ],
                $repo
            );
            break;

        default:
            throw new RuntimeException('Rôle non autorisé : ' . $profile);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo "Erreur : " . htmlspecialchars($e->getMessage());
    exit;
}

/* ===============================================================
 *  Rendu “inline” (Bootstrap 5 + Chart.js) — à remplacer par Twig
 * ===============================================================*/
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Dashboard – <?=htmlspecialchars($profile)?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <style>
        body{background:#f7f9fc;}
    </style>
</head>
<body class="container py-4">

<?php if ($profile === 'Technicien'): ?>
    <?php
        /** @var array $user  @var array $scores  @var array $kpis */
        extract($data); // crée $user, $scores, $kpis
    ?>
    <h1 class="mb-4">Bonjour <?=htmlspecialchars($user['firstName']??'')?> !</h1>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="card text-center border-success">
            <div class="card-body">
                <h2 class="card-title"><?=$kpis['testsDone']?></h2>
                <p class="card-text small">Tests complétés</p>
            </div></div></div>
        <div class="col-6 col-lg-3"><div class="card text-center border-primary">
            <div class="card-body">
                <h2 class="card-title"><?=$kpis['trainingsRecommended']?></h2>
                <p class="card-text small">Formations proposées</p>
            </div></div></div>
        <div class="col-6 col-lg-3"><div class="card text-center border-warning">
            <div class="card-body">
                <h2 class="card-title"><?=$kpis['trainingsCompleted']?></h2>
                <p class="card-text small">Formations réalisées</p>
            </div></div></div>
    </div>

    <!-- Scores Chart -->
    <canvas id="scoresChart"></canvas>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('scoresChart');
        new Chart(ctx,{
            type:'bar',
            data:{
                labels: <?=json_encode(array_map(function($s){return $s['brand'];},$scores))?>,
                datasets:[{
                    data: <?=json_encode(array_map(function($s){return $s['avg'];},$scores))?>,
                    backgroundColor: <?=json_encode(array_map(function($s){return $s['color'];},$scores))?>
                }]
            },
            options:{
                plugins:{legend:{display:false}},
                scales:{y:{beginAtZero:true,max:100}}
            }
        });
    </script>

<?php else: /* --------------------- Manager & Co --------------------- */ ?>
    <?php
        /** @var array $scores  @var array $kpis  @var array $technicians */
        extract($data);
        $managerName = $data['manager']['name'] ?? 'Tous les managers';
    ?>
    <h1 class="mb-4"><?=htmlspecialchars($managerName)?></h1>

    <!-- KPI résumé -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3"><div class="card border-info">
            <div class="card-body text-center">
                <h2 class="card-title"><?=count($technicians)?></h2>
                <p class="card-text small">Techniciens suivis</p>
            </div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card border-success">
            <div class="card-body text-center">
                <h2 class="card-title"><?=$kpis['testsDone']?></h2>
                <p class="card-text small">Tests complets</p>
            </div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card border-warning">
            <div class="card-body text-center">
                <h2 class="card-title"><?=$kpis['trainingsRecommended']?></h2>
                <p class="card-text small">Formations proposées</p>
            </div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card border-primary">
            <div class="card-body text-center">
                <h2 class="card-title"><?=$kpis['trainingsCompleted']?></h2>
                <p class="card-text small">Formations réalisées</p>
            </div></div></div>
    </div>

    <!-- Scores Chart -->
    <canvas id="scoresChart"></canvas>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('scoresChart');
        new Chart(ctx,{
            type:'bar',
            data:{
                labels: <?=json_encode(array_map(function($s){return $s['brand'];},$scores))?>,
                datasets:[{
                    data: <?=json_encode(array_map(function($s){return $s['avg'];},$scores))?>,
                    backgroundColor: <?=json_encode(array_map(function($s){return $s['color'];},$scores))?>
                }]
            },
            options:{
                plugins:{legend:{display:false}},
                scales:{y:{beginAtZero:true,max:100}}
            }
        });
    </script>
<?php endif; ?>

</body>
</html>

