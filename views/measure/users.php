<?php
/* =========================================================
 * LISTE DES UTILISATEURS — MEDACAP  (PHP 7.3 compatible)
 * ========================================================= */

session_start();
include_once "./includes/language.php";
require_once "../../vendor/autoload.php";

require_once "./components/ui/table.php";
require_once "./components/ui/search-bar.php";
require_once "./components/ui/title-main.php";
include_once "./includes/background-manager.php";
require_once "./components/ui/title-section.php";
require_once "./components/ui/filterHelpers.php";
require_once "./components/ui/render-level-badge.php";
require_once "./includes/subsidiaryHelper.php";

setPageBackground("bg-dashboard");

use MongoDB\Client;

if (!defined('VALIDATION_STATUS_EN_ATTENTE')) define('VALIDATION_STATUS_EN_ATTENTE', 'En attente');
if (!defined('VALIDATION_STATUS_VALIDE'))      define('VALIDATION_STATUS_VALIDE',      'Validé');
if (!defined('VALIDATION_STATUS_NON_VALIDE'))  define('VALIDATION_STATUS_NON_VALIDE',  'Non validé');

/* 1. Sécurité session */
if (!isset($_SESSION['profile'])) {
    header('Location: ../../');
    exit();
}

/* 2. Connexion MongoDB */
$mongo   = new Client("mongodb://localhost:27017");
$db      = $mongo->academy;
$colUser = $db->users;
$colConn = $db->connections;

/* 3. Helpers */
function canSee(array $profiles) {
    return in_array($_SESSION['profile'], $profiles, true);
}

function userVisible($u) {
    $p   = $_SESSION['profile'];
    $sub = $_SESSION['subsidiary'] ?? null;
    switch ($p) {
        case 'Admin':
            return !in_array($u->profile, ['Admin','Directeur Pièce et Service','Directeur des Opérations','Directeur Groupe','Super Admin'], true)
                   && $u->subsidiary === $sub;
        case 'Ressource Humaine':
            return !in_array($u->profile, ['Ressource Humaine','Directeur Pièce et Service','Directeur des Opérations','Directeur Groupe','Super Admin','Admin'], true)
                   && $u->subsidiary === $sub;
        case 'Super Admin':
            return $u->profile !== 'Super Admin';
        case 'Directeur Groupe':
            return !in_array($u->profile, ['Super Admin','Directeur Groupe'], true);
        case 'Directeur Pièce et Service':
        case 'Directeur des Opérations':
            return !in_array($u->profile, ['Directeur Pièce et Service','Directeur des Opérations','Directeur Groupe','Super Admin'], true)
                   && $u->subsidiary === $sub;
        default:
            return false;
    }
}

/* 4. Classe UserManager */
class UserManager {
    private $collections;

    public function __construct($colUser, $colConn) {
        $this->collections = ['users' => $colUser, 'connections' => $colConn];
    }

    public function getUsers($userProfile, $userSession, $mode, $managerId = null, $filterValue = '', $additionalFilters = array()) {
        $query = ['active' => true];
        $this->applySubsidiaryFilter($query, $userProfile, $userSession);
        $this->applyDepartmentFilter($query, $userProfile, $userSession);

        foreach ($additionalFilters as $key => $value) {
            if (!empty($value) && $value !== 'all') {
                $query[$key] = ($key === 'level')
                    ? new MongoDB\BSON\Regex('^' . preg_quote($value, '/') . '$', 'i')
                    : $value;
            }
        }

        if ($managerId) {
            $manager = $this->collections['users']->findOne([
                '_id'    => new MongoDB\BSON\ObjectId($managerId),
                'active' => true,
            ]);
            if ($manager && isset($manager->users)) {
                $query['_id'] = ['$in' => $manager->users];
            } else {
                return [];
            }
        }

        $cursor = $this->collections['users']->find($query, ['projection' => $this->getProjection($mode)]);
        $users  = iterator_to_array($cursor, false);

        if ($mode !== 'collaborateurs') {
            $users = array_values(array_filter($users, 'userVisible'));
        }

        if (!empty($filterValue)) {
            $search = $filterValue;
            $users  = array_values(array_filter($users, function($u) use ($search) {
                foreach (['firstName','lastName','email','phone','profile','level','department','username','subsidiary'] as $f) {
                    if (stripos($u->$f ?? '', $search) !== false) return true;
                }
                return false;
            }));
        }

        return $users;
    }

    private function applySubsidiaryFilter(&$query, $userProfile, $userSession) {
        $restricted = ['Admin','Ressource Humaine','Directeur Pièce et Service','Directeur des Opérations'];
        if (!in_array($userProfile, ['Super Admin','Directeur Groupe']) && in_array($userProfile, $restricted)) {
            $query['subsidiary'] = $userSession['subsidiary'] ?? null;
        }
    }

    private function applyDepartmentFilter(&$query, $userProfile, $userSession) {
        if (in_array($userProfile, ['Super Admin','Directeur Groupe'])) return;
        $userDoc    = $this->collections['users']->findOne(['_id' => new MongoDB\BSON\ObjectId($userSession['id'])]);
        $department = $userDoc['department'] ?? $userSession['department'] ?? null;
        if (!$department) return;
        if (strpos($department, ' & ') !== false) {
            $depts = array_map('trim', explode(' & ', $department));
            $or    = [];
            foreach ($depts as $d) {
                $or[] = ['department' => $d];
            }
            $or[]           = ['department' => $department];
            $query['$or']   = $or;
        } else {
            $query['department'] = $department;
        }
    }

    private function getProjection($mode) {
        $p = [
            'firstName'=>1,'lastName'=>1,'email'=>1,'country'=>1,'profile'=>1,
            'department'=>1,'level'=>1,'username'=>1,'visiblePassword'=>1,
            'subsidiary'=>1,'users'=>1,'active'=>1,'phone'=>1,
        ];
        if ($mode === 'collaborateurs') $p['test'] = 1;
        return $p;
    }

    public function enrichUsers($users, $mode) {
        if ($mode === 'tous' && !empty($users)) {
            $ids    = array_map(function($u) { return $u->_id; }, $users);
            $online = [];
            foreach ($this->collections['connections']->find(['user' => ['$in' => $ids], 'status' => 'Online'], ['projection' => ['user' => 1]]) as $c) {
                $online[(string)$c->user] = true;
            }
            foreach ($users as $u) {
                $u->online = !empty($online[(string)$u->_id]);
            }
        }
        return $users;
    }
}

/* 5. Modes disponibles */
$modesDisponibles = [];
if (in_array($_SESSION['profile'], ['Admin','Super Admin','Directeur Groupe','Directeur Pièce et Service','Directeur des Opérations','Ressource Humaine'])) {
    $modesDisponibles['tous'] = isset($list_user) ? $list_user : 'Liste des utilisateurs';
}
if ($_SESSION['profile'] === 'Manager' || in_array($_SESSION['profile'], ['Admin','Super Admin','Directeur Groupe'])) {
    $modesDisponibles['collaborateurs'] = isset($title_collaborators) ? $title_collaborators : 'Collaborateurs';
}

$modeActuel = isset($_GET['mode']) ? $_GET['mode'] : '';
if (!$modeActuel || !isset($modesDisponibles[$modeActuel])) {
    reset($modesDisponibles);
    $modeActuel = key($modesDisponibles);
    if (!$modeActuel) $modeActuel = 'tous';
}

/* 6. Filtres */
$selectedSubsidiary       = isset($_GET['subsidiary']) ? $_GET['subsidiary'] : 'all';
$selectedLevel            = isset($_GET['level'])      ? $_GET['level']      : 'all';
$selectedProfileFilter    = isset($_GET['profile'])    ? $_GET['profile']    : 'all';
$selectedDepartmentFilter = isset($_GET['department']) ? $_GET['department'] : 'all';
$filter_value             = isset($_GET['filter_value']) ? strtolower(trim($_GET['filter_value'])) : '';
$manager_id               = isset($_GET['manager_id']) ? $_GET['manager_id'] : null;

$additionalFilters = [
    'subsidiary' => $selectedSubsidiary,
    'level'      => $selectedLevel,
    'profile'    => $selectedProfileFilter,
    'department' => $selectedDepartmentFilter,
];

// Subsidiaires uniques
$subRaw             = iterator_to_array($colUser->aggregate([
    ['$match' => ['active' => true]],
    ['$group' => ['_id' => '$subsidiary']],
    ['$sort'  => ['_id' => 1]],
]));
$uniqueSubsidiaries = [];
foreach ($subRaw as $doc) {
    $s = (string)$doc->_id;
    if (trim($s) !== '') $uniqueSubsidiaries[] = $s;
}
$uniqueSubsidiaries = array_unique($uniqueSubsidiaries);

// Profils uniques
$uniqueProfiles = array_values(array_unique(array_filter(
    array_map('strval', $colUser->distinct('profile', ['active' => true])),
    function($p) { return trim($p) !== ''; }
)));
sort($uniqueProfiles);

// Départements uniques
$uniqueDepartments = array_values(array_unique(array_filter(
    array_map('strval', $colUser->distinct('department', ['active' => true])),
    function($d) { return trim($d) !== ''; }
)));
sort($uniqueDepartments);

$canFilterSubsidiary = in_array($_SESSION['profile'], ['Super Admin','Directeur des Opérations','Directeur Pièce et Service','Directeur Groupe']);

$filters = [];
if ($canFilterSubsidiary)   $filters[] = ['key' => 'subsidiary', 'label' => 'Filtrer par Filiales',     'icon' => 'bi bi-building',     'iconColor' => 'text-primary', 'selected' => $selectedSubsidiary,       'value' => array_merge(['all' => 'Toutes les filiales'],   buildSubsidiaryFilterOptions($uniqueSubsidiaries))];
$filters[]                             = ['key' => 'level',      'label' => 'Filtrer par niveau',       'icon' => 'bi bi-star',         'iconColor' => 'text-warning', 'selected' => $selectedLevel,            'value' => ['all' => 'Tous les niveaux', 'Junior' => 'Junior', 'Senior' => 'Senior', 'Expert' => 'Expert']];
if (!empty($uniqueProfiles))   $filters[] = ['key' => 'profile',    'label' => 'Filtrer par profil',       'icon' => 'bi bi-person-badge', 'iconColor' => 'text-info',    'selected' => $selectedProfileFilter,    'value' => array_merge(['all' => 'Tous les profils'],      array_combine($uniqueProfiles,    $uniqueProfiles))];
if (!empty($uniqueDepartments)) $filters[] = ['key' => 'department', 'label' => 'Filtrer par département', 'icon' => 'bi bi-diagram-3',    'iconColor' => 'text-success', 'selected' => $selectedDepartmentFilter, 'value' => array_merge(['all' => 'Tous les départements'], array_combine($uniqueDepartments, $uniqueDepartments))];

/* 7. Configuration des colonnes */
$configColonnes = [
    'tous' => [
        ['label' => isset($prenomsNoms) ? $prenomsNoms : 'Prénoms & Noms',     'key' => 'name',              'type' => 'text',      'visible' => ['Admin','Super Admin','Directeur Groupe','Directeur Pièce et Service','Directeur des Opérations','Ressource Humaine']],
        ['label' => isset($email)       ? $email       : 'Email',               'key' => 'email',             'type' => 'text',      'visible' => ['Admin','Ressource Humaine']],
        ['label' => isset($pays)        ? $pays        : 'Filiale',             'key' => 'subsidiary',        'type' => 'text',      'visible' => ['Super Admin','Directeur Groupe','Directeur Pièce et Service','Directeur des Opérations']],
        ['label' => isset($profil)      ? $profil      : 'Profil',              'key' => 'profile',           'type' => 'text',      'visible' => ['Admin','Super Admin','Directeur Groupe','Directeur Pièce et Service','Directeur des Opérations','Ressource Humaine']],
        ['label' => isset($department)  ? $department  : 'Département',         'key' => 'department',        'type' => 'text',      'visible' => ['Admin','Super Admin','Directeur Groupe','Directeur Pièce et Service','Directeur des Opérations','Ressource Humaine']],
        ['label' => isset($levelTech)   ? $levelTech   : 'Niveau technique',    'key' => 'level',             'type' => 'component', 'visible' => ['Admin','Super Admin','Directeur Groupe','Directeur Pièce et Service','Directeur des Opérations','Ressource Humaine']],
        ['label' => isset($username)    ? $username    : 'Username',            'key' => 'username',          'type' => 'text',      'visible' => ['Admin','Super Admin']],
        ['label' => isset($Password)    ? $Password    : 'Mot de passe',        'key' => 'visiblePassword',   'type' => 'text',      'visible' => ['Admin','Super Admin']],
        ['label' => isset($status)      ? $status      : 'Statut',              'key' => 'status',            'type' => 'component', 'visible' => ['Super Admin']],
        ['label' => isset($title_collaborators) ? $title_collaborators : 'Collaborateurs', 'key' => 'nbr_collaborators', 'type' => 'component', 'visible' => ['Admin','Super Admin','Directeur Groupe','Directeur Pièce et Service','Directeur des Opérations','Ressource Humaine']],
    ],
    'collaborateurs' => [
        ['label' => isset($prenomsNoms) ? $prenomsNoms : 'Prénoms & Noms', 'key' => 'fullname',   'type' => 'text'],
        ['label' => isset($email)       ? $email       : 'Email',           'key' => 'email',      'type' => 'text'],
        ['label' => isset($phoneNumber) ? $phoneNumber : 'Téléphone',       'key' => 'phone',      'type' => 'text'],
        ['label' => isset($profil)      ? $profil      : 'Profil',          'key' => 'profile',    'type' => 'text'],
        ['label' => isset($levelTech)   ? $levelTech   : 'Niveau',          'key' => 'level',      'type' => 'text'],
        ['label' => isset($department)  ? $department  : 'Département',     'key' => 'department', 'type' => 'text'],
        ['label' => isset($edit)        ? $edit        : 'Éditer',          'key' => 'edit',       'type' => 'component'],
        ['label' => isset($delete)      ? $delete      : 'Supprimer',       'key' => 'delete',     'type' => 'component'],
    ],
];

/* 8. Récupération des données */
$userManager = new UserManager($colUser, $colConn);
$usersData   = [];

switch ($modeActuel) {
    case 'tous':
        $usersData = $userManager->getUsers($_SESSION['profile'], $_SESSION, $modeActuel, null, $filter_value, $additionalFilters);
        $usersData = $userManager->enrichUsers($usersData, $modeActuel);
        break;
    case 'collaborateurs':
        $actual_manager_id = $manager_id ? $manager_id : $_SESSION['id'];
        $usersData = $userManager->getUsers($_SESSION['profile'], $_SESSION, $modeActuel, $actual_manager_id, $filter_value, $additionalFilters);
        $usersData = $userManager->enrichUsers($usersData, $modeActuel);
        break;
}

/* 9. Préparation des lignes */
$headers = array_values(array_filter($configColonnes[$modeActuel], function($h) {
    return !isset($h['visible']) || canSee($h['visible']);
}));

$rows = [];

foreach ($usersData as $u) {
    if ($modeActuel === 'tous') {
        $lvl = strtolower($u->level ?? '');
        if ($lvl === 'junior') {
            $levelBadge = renderLevelBadge(1, $u->level);
        } elseif ($lvl === 'senior') {
            $levelBadge = renderLevelBadge(2, $u->level);
        } elseif ($lvl === 'expert') {
            $levelBadge = renderLevelBadge(3, $u->level);
        } else {
            $levelBadge = '';
        }

        $statusBadge = ($u->online ?? false)
            ? '<span class="badge badge-light-success fs-7 m-1">Online</span>'
            : '<span class="badge badge-light-danger fs-7 m-1">Offline</span>';

        $collaborators = [];
        if ($u->profile === 'Manager' && canSee(['Admin','Super Admin','Directeur Groupe','Directeur Pièce et Service','Directeur des Opérations'])) {
            foreach ($colUser->find(['_id' => ['$in' => $u->users ?? []], 'active' => true], ['projection' => ['firstName'=>1,'lastName'=>1]]) as $c) {
                $collaborators[] = [
                    'id'         => (string)$c->_id,
                    'name'       => trim(($c->firstName ?? '') . ' ' . ($c->lastName ?? '')),
                    'validation' => VALIDATION_STATUS_EN_ATTENTE,
                ];
            }
            usort($collaborators, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
        }

        $rows[] = [
            'id'               => (string)$u->_id,
            'name'             => htmlspecialchars(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')),
            'email'            => htmlspecialchars($u->email ?? ''),
            'subsidiary'       => htmlspecialchars($u->subsidiary ?? ''),
            'profile'          => htmlspecialchars(($u->profile === 'Manager' && ($u->test ?? false)) ? 'Manager - Technicien' : ($u->profile ?? '')),
            'department'       => htmlspecialchars($u->department ?? ''),
            'level'            => $levelBadge,
            'username'         => htmlspecialchars($u->username ?? ''),
            'visiblePassword'  => htmlspecialchars($u->visiblePassword ?? ''),
            'status'           => $statusBadge,
            'nbr_collaborators'=> count($collaborators),
            'collaborators'    => [
                'headers'      => [['label' => isset($title_collaborators) ? $title_collaborators : 'Collaborateurs', 'key' => 'name', 'type' => 'text']],
                'rows'         => $collaborators,
                'subRowAction' => false,
            ],
        ];

    } elseif ($modeActuel === 'collaborateurs') {
        $id = (string)$u->_id;
        $rows[] = [
            'id'         => $id,
            'fullname'   => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')),
            'email'      => $u->email ?? '',
            'phone'      => $u->phone ?? '',
            'profile'    => ($u->profile === 'Manager' && ($u->test ?? false)) ? 'Manager' : ($u->profile ?? ''),
            'level'      => $u->level ?? '',
            'department' => $u->department ?? '',
            'edit'       => '<button class="btn btn-icon btn-light-success w-30px h-30px me-3" data-bs-toggle="modal" data-bs-target="#modal_edit_' . $id . '"><i class="fas fa-edit fs-5"></i></button>',
            'delete'     => '<button class="btn btn-icon btn-light-danger  w-30px h-30px"     data-bs-toggle="modal" data-bs-target="#modal_del_'  . $id . '"><i class="fas fa-trash fs-5"></i></button>',
        ];
    }
}

/* 10. Colonnes à ignorer à l'export */
if ($modeActuel === 'tous') {
    $columnsToIgnore = ['collaborators','level','status'];
} elseif ($modeActuel === 'collaborateurs') {
    $columnsToIgnore = ['edit','delete'];
} else {
    $columnsToIgnore = [];
}

/* 11. Traitement formulaires (mode collaborateurs) */
$vehicles    = $db->vehicles;
$allocations = $db->allocations;

function updateTestQuizzes($tests, $vehicles, $userId, $brands, $type, $level, $testId = null) {
    if (!$testId) {
        $ins    = $tests->insertOne(['quizzes'=>[],'user'=>new MongoDB\BSON\ObjectId($userId),'brand'=>$brands??[],'type'=>$type,'level'=>$level,'total'=>0,'active'=>true,'created'=>date('d-m-Y H:i:s')]);
        $testId = $ins->getInsertedId();
    }
    $tests->updateOne(['_id'=>new MongoDB\BSON\ObjectId($testId)], ['$set'=>['quizzes'=>[],'brand'=>$brands??[]]]);
    foreach ($brands ?? [] as $brand) {
        $vehicle = $vehicles->findOne(['$and'=>[['brand'=>$brand],['type'=>$type],['level'=>$level],['active'=>true]]]);
        if ($vehicle && !empty($vehicle->quizzes)) {
            foreach ($vehicle->quizzes as $quiz) {
                $tests->updateOne(['_id'=>new MongoDB\BSON\ObjectId($testId)], ['$addToSet'=>['quizzes'=>$quiz]]);
            }
        }
    }
    $test = $tests->findOne(['_id'=>new MongoDB\BSON\ObjectId($testId)]);
    $tests->updateOne(['_id'=>new MongoDB\BSON\ObjectId($testId)], ['$set'=>['total'=>count($test['quizzes']??[])]]);
    return $testId;
}

$success_msg = null;
$error_msg   = null;

if ($modeActuel === 'collaborateurs') {
    if (isset($_POST['update'])) {
        $id     = $_POST['userID'] ?? '';
        $fields = ['updated' => date('d-m-Y H:i:s')];

        if (!empty($_POST['firstName']))      $fields['firstName']      = ucfirst($_POST['firstName']);
        if (!empty($_POST['lastName']))       $fields['lastName']       = strtoupper($_POST['lastName']);
        if (!empty($_POST['email']))          $fields['email']          = $_POST['email'];
        if (!empty($_POST['phone']))          $fields['phone']          = $_POST['phone'];
        if (!empty($_POST['gender']))         $fields['gender']         = $_POST['gender'];
        if (!empty($_POST['level']))          $fields['level']          = $_POST['level'];
        if (!empty($_POST['department']))     $fields['department']     = $_POST['department'];
        if (!empty($_POST['username']))       $fields['username']       = $_POST['username'];
        if (!empty($_POST['matricule']))      $fields['matricule']      = $_POST['matricule'];
        if (!empty($_POST['certificate']))    $fields['certificate']    = ucfirst($_POST['certificate']);
        if (!empty($_POST['subsidiary']))     $fields['subsidiary']     = ucfirst($_POST['subsidiary']);
        if (!empty($_POST['agency']))         $fields['agency']         = ucfirst($_POST['agency']);
        if (!empty($_POST['role']))           $fields['role']           = ucfirst($_POST['role']);
        if (!empty($_POST['birthdate']))      $fields['birthdate']      = date('d-m-Y', strtotime($_POST['birthdate']));
        if (!empty($_POST['recrutmentDate'])) $fields['recrutmentDate'] = date('d-m-Y', strtotime($_POST['recrutmentDate']));
        if (!empty($_POST['brandJu']))        $fields['brandJunior']    = $_POST['brandJu'];
        if (!empty($_POST['brandSe']))        $fields['brandSenior']    = $_POST['brandSe'];
        if (!empty($_POST['brandEx']))        $fields['brandExpert']    = $_POST['brandEx'];
        if (!empty($_POST['specialitySenior'])) $fields['specialitySenior'] = $_POST['specialitySenior'];
        if (!empty($_POST['specialityExpert'])) $fields['specialityExpert'] = $_POST['specialityExpert'];

        if (count($fields) > 1) {
            $colUser->updateOne(['_id' => new MongoDB\BSON\ObjectId($id)], ['$set' => $fields]);
            $tests      = $db->tests;
            $levelMap   = ['brandJu' => 'Junior', 'brandSe' => 'Senior', 'brandEx' => 'Expert'];
            foreach ($levelMap as $pk => $lvl) {
                if (!empty($_POST[$pk])) {
                    foreach (['Factuel','Declaratif'] as $type) {
                        $existing = $tests->findOne(['$and' => [['user'=>new MongoDB\BSON\ObjectId($id)],['type'=>$type],['level'=>$lvl],['active'=>true]]]);
                        updateTestQuizzes($tests, $vehicles, $id, $_POST[$pk], $type, $lvl, isset($existing['_id']) ? $existing['_id'] : null);
                    }
                }
            }
            $success_msg = isset($success_user_edit) ? $success_user_edit : 'Utilisateur mis à jour avec succès';
        }
    }

    if (isset($_POST['delete'])) {
        $id = $_POST['userID'] ?? '';
        if (!empty($id)) {
            $member = $colUser->findOne(['_id' => new MongoDB\BSON\ObjectId($id), 'active' => true]);
            $colUser->updateOne(['_id' => new MongoDB\BSON\ObjectId($id)], ['$set' => ['active' => false, 'deleted' => date('d-m-Y H:i:s')]]);
            if (($member['profile'] ?? '') === 'Technicien') {
                $colUser->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($member['manager'] ?? '')],
                    ['$pull' => ['users' => new MongoDB\BSON\ObjectId($id)]]
                );
            }
            $success_msg = isset($success_user_delet) ? $success_user_delet : 'Utilisateur supprimé avec succès';
        }
    }
}
?>
<?php include_once "partials/header.php"; ?>

<title><?= htmlspecialchars(isset($modesDisponibles[$modeActuel]) ? $modesDisponibles[$modeActuel] : 'Gestion Utilisateurs') ?> | CFAO Mobility Academy</title>
<link rel="stylesheet" href="../../public/css/measure/user-list.css">

<script>(function(){var e=document.getElementById('spinner');if(e){e.classList.remove('show');e.style.display='none';}document.body.classList.remove('loading-active');})();</script>

<?php openBackgroundContainer(); ?>

<div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
    <div class="container-xxl">
        <div class="row g-3 g-xl-6 mb-3 mb-xl-6">

            <?php renderTitleMain('ki-duotone ki-people fs-2', 3, isset($modesDisponibles[$modeActuel]) ? $modesDisponibles[$modeActuel] : 'Gestion Utilisateurs'); ?>

            <!-- Filtres + Export -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card glassmorphism filter-card">
                        <form method="GET" action="" id="filters-form">
                            <input type="hidden" name="mode" value="<?= htmlspecialchars($modeActuel) ?>">
                            <?php if ($manager_id): ?><input type="hidden" name="manager_id" value="<?= htmlspecialchars($manager_id) ?>"><?php endif; ?>
                            <div id="users-filter-box" class="d-flex flex-wrap gap-3 w-100 align-items-end" data-ajax-filters="true">
                                <?php foreach ($filters as $filter) { renderFilterSelectAjax($filter); } ?>
                                <div class="ms-auto" style="padding-bottom:1px;">
                                    <button type="button" id="export-excel"
                                            class="btn btn-icon btn-light-danger"
                                            title="<?= isset($export_to_excel) ? htmlspecialchars($export_to_excel) : 'Exporter Excel' ?>"
                                            data-ignore='<?= htmlspecialchars(json_encode($columnsToIgnore)) ?>'>
                                        <i class="ki-duotone ki-exit-up fs-2"><span class="path1"></span><span class="path2"></span></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Alertes -->
            <?php if ($success_msg): ?>
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="alert alert-success alert-dismissible fade show glassmorphism" role="alert">
                            <strong class="text-center d-block"><?= htmlspecialchars($success_msg) ?></strong>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="alert alert-danger alert-dismissible fade show glassmorphism" role="alert">
                            <strong class="text-center d-block"><?= htmlspecialchars($error_msg) ?></strong>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tableau -->
            <div class="row">
                <div class="col-12">
                    <div class="card glassmorphism">
                        <div class="card-body p-0">
                            <div id="users-container" class="table-responsive">
                                <?php
                                $subRowConfig = ($modeActuel === 'tous') ? 'collaborators' : false;
                                echo renderTableComponent($headers, $rows, $subRowConfig);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modales (mode collaborateurs) -->
<?php if ($modeActuel === 'collaborateurs' && !empty($usersData)): ?>
    <?php foreach ($usersData as $user): ?>
        <?php $uid = (string)$user->_id; ?>

        <!-- Modal suppression -->
        <div class="modal fade" id="modal_del_<?= $uid ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered mw-450px">
                <div class="modal-content glass-effect">
                    <form method="POST">
                        <input type="hidden" name="userID" value="<?= $uid ?>">
                        <div class="modal-header">
                            <h2 class="fs-2 fw-bolder"><?= isset($delet) ? $delet : 'Supprimer' ?></h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body py-10 px-lg-17">
                            <h4><?= isset($delet_text) ? $delet_text : 'Confirmer la suppression ?' ?></h4>
                        </div>
                        <div class="modal-footer flex-center">
                            <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal"><?= isset($non) ? $non : 'Non' ?></button>
                            <button type="submit" name="delete" class="btn btn-danger"><?= isset($oui) ? $oui : 'Oui' ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal édition -->
        <div class="modal fade" id="modal_edit_<?= $uid ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered mw-650px">
                <div class="modal-content glass-effect">
                    <form method="POST">
                        <input type="hidden" name="userID" value="<?= $uid ?>">
                        <div class="modal-header">
                            <h2 class="fs-2 fw-bolder"><?= isset($editer_data) ? $editer_data : 'Éditer' ?></h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body py-10 px-lg-17">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label"><?= isset($prenomsNoms) ? $prenomsNoms : 'Prénom' ?></label>
                                    <input type="text" name="firstName" class="form-control" value="<?= htmlspecialchars($user->firstName ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nom</label>
                                    <input type="text" name="lastName" class="form-control" value="<?= htmlspecialchars($user->lastName ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><?= isset($email) ? $email : 'Email' ?></label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user->email ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><?= isset($phoneNumber) ? $phoneNumber : 'Téléphone' ?></label>
                                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user->phone ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><?= isset($levelTech) ? $levelTech : 'Niveau' ?></label>
                                    <select name="level" class="form-select">
                                        <?php foreach (['Junior','Senior','Expert'] as $lvl): ?>
                                            <option value="<?= $lvl ?>" <?= ($user->level ?? '') === $lvl ? 'selected' : '' ?>><?= $lvl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><?= isset($department) ? $department : 'Département' ?></label>
                                    <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($user->department ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer flex-center">
                            <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal"><?= isset($annuler) ? $annuler : 'Annuler' ?></button>
                            <button type="submit" name="update" class="btn btn-primary"><?= isset($valider) ? $valider : 'Valider' ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php closeBackgroundContainer(); ?>
<?php include_once "partials/footer.php"; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // Export Excel
    var exportBtn = document.getElementById('export-excel');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            if (typeof XLSX === 'undefined') { alert('Bibliothèque XLSX non chargée'); return; }
            var ignore   = JSON.parse(this.dataset.ignore || '[]');
            var headers  = <?= json_encode(array_values($headers)) ?>;
            var rows     = <?= json_encode(array_values($rows)) ?>;
            var filtered = headers.filter(function (h) { return !ignore.includes(h.key); });
            var data     = [filtered.map(function (h) { return h.label; })];
            rows.forEach(function (row) {
                data.push(filtered.map(function (h) {
                    var val = row[h.key] != null ? String(row[h.key]) : '';
                    if (val.includes('<')) {
                        var d = document.createElement('div');
                        d.innerHTML = val;
                        val = d.textContent || d.innerText || '';
                    }
                    return val;
                }));
            });
            var wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(data), 'Utilisateurs');
            XLSX.writeFile(wb, 'utilisateurs.xlsx');
        });
    }

    // Auto-fermeture des alertes après 5s
    setTimeout(function () {
        document.querySelectorAll('.alert').forEach(function (a) { a.remove(); });
    }, 5000);
    document.querySelectorAll('.alert .btn-close').forEach(function (btn) {
        btn.addEventListener('click', function () { this.closest('.alert').remove(); });
    });

});
</script>
