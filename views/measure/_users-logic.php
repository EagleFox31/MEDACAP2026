<?php
require_once __DIR__ . '/../../core/medacap_config.php';
/* =========================================================
 * LISTE DES UTILISATEURS — logique partagée (PHP 7.3)
 * Inclus par measure/users.php ET explore/users.php
 * Les chemins relatifs résolvent depuis views/measure/
 * ========================================================= */

include_once __DIR__ . '/includes/language.php';
require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/components/ui/table.php';
require_once __DIR__ . '/components/ui/search-bar.php';
require_once __DIR__ . '/components/ui/title-main.php';
include_once __DIR__ . '/includes/background-manager.php';
require_once __DIR__ . '/components/ui/title-section.php';
require_once __DIR__ . '/components/ui/filterHelpers.php';
require_once __DIR__ . '/components/ui/render-level-badge.php';
require_once __DIR__ . '/includes/subsidiaryHelper.php';

setPageBackground("bg-dashboard");

use MongoDB\Client;

if (!defined('VALIDATION_STATUS_EN_ATTENTE')) define('VALIDATION_STATUS_EN_ATTENTE', 'En attente');
if (!defined('VALIDATION_STATUS_VALIDE'))      define('VALIDATION_STATUS_VALIDE',      'Validé');
if (!defined('VALIDATION_STATUS_NON_VALIDE'))  define('VALIDATION_STATUS_NON_VALIDE',  'Non validé');

/* 1. Sécurité session */
if (!isset($_SESSION['profile'])) {
    header('Location: /');
    exit();
}

/* 2. Connexion MongoDB */
$mongo   = new Client(medacapMongoUri());
$db      = $mongo->academy;
$colUser = $db->users;
$colConn = $db->connections;

/* 3. Helpers */
if (!function_exists('canSee')) {
    function canSee(array $profiles) {
        return in_array($_SESSION['profile'], $profiles, true);
    }
}

if (!function_exists('userVisible')) {
    function userVisible($u) {
        $p   = $_SESSION['profile'];
        $sub = $_SESSION['subsidiary'] ?? null;
        switch ($p) {
            case 'Admin':
                return !in_array($u->profile, ['Admin','Directeur Pièce et Service','Directeur des Opérations','Directeur Groupe','Super Admin'], true)
                       && medacapSubsidiariesMatch($u->subsidiary ?? '', $sub);
            case 'Ressource Humaine':
                return !in_array($u->profile, ['Ressource Humaine','Directeur Pièce et Service','Directeur des Opérations','Directeur Groupe','Super Admin','Admin'], true)
                       && medacapSubsidiariesMatch($u->subsidiary ?? '', $sub);
            case 'Super Admin':
                return $u->profile !== 'Super Admin';
            case 'Directeur Groupe':
                return !in_array($u->profile, ['Super Admin','Directeur Groupe'], true);
            case 'Directeur Pièce et Service':
            case 'Directeur des Opérations':
                return !in_array($u->profile, ['Directeur Pièce et Service','Directeur des Opérations','Directeur Groupe','Super Admin'], true)
                       && medacapSubsidiariesMatch($u->subsidiary ?? '', $sub);
            default:
                return false;
        }
    }
}

/* 4. Classe UserManager */
if (!class_exists('UserManager')) {
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
                    if ($key === 'subsidiary') {
                        medacapApplySubsidiaryToQuery($query, $value);
                    } elseif ($key === 'level') {
                        $query[$key] = new MongoDB\BSON\Regex('^' . preg_quote($value, '/') . '$', 'i');
                    } else {
                        $query[$key] = $value;
                    }
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
                medacapApplySubsidiaryToQuery($query, isset($userSession['subsidiary']) ? $userSession['subsidiary'] : null);
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

/* Injecter le count dans les filtres actifs */
$_usersResultCount = count($usersData);
foreach ($filters as &$_f) {
    $_f['resultCount'] = $_usersResultCount;
}
unset($_f);

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

if (!function_exists('updateTestQuizzes')) {
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
