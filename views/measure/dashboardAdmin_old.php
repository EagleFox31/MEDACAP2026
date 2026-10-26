<?php
require_once __DIR__ . '/../../core/medacap_config.php';
/**
 * Dashboard unifié pour participation et résultats aux quizzes/tests
 * Affichage conditionnel basé sur le profil utilisateur.
 */

// Démarrage de la session et inclusions communes
session_start();
include_once "./includes/language.php";
require_once "../../vendor/autoload.php";

include_once "./includes/background-manager.php";
require_once "./components/ui/filterHelpers.php";
require_once "./components/ui/title-main.php";
require_once "./components/ui/title-section.php";

setPageBackground("bg-dashboard");

// Vérification authentification
if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
}


// Nettoyage filtres GET communes
if(isset($_GET['brand']) && $_GET['brand'] == "Toutes les marques"){
    unset($_GET['brand']);
}
if(isset($_GET['subsidiary']) && $_GET['subsidiary'] == "Toutes les filiales"){
    unset($_GET['subsidiary']);
}

// ===== CONNEXION MONGODB ET COLLECTIONS COMMUNES =====
$conn = new MongoDB\Client(medacapMongoUri());
$academy = $conn->academy;
$users = $academy->users;
$allocations = $academy->allocations;
$resultsCollection = $academy->results;
$exams = $academy->exams;
$tests = $academy->tests;
$questionsCollection = $academy->questions;

// Variables filtres communes
$selectedBrand = $_GET['brand'] ?? '';
$selectedSubsidiary = $_GET['subsidiary'] ?? '';

// Fonctions communes
function completedPercentage($done, $total) {
    return $total ? round(($done * 100) / $total) : 0;
}

// ===== LOGIQUE CONDITIONNELLE SELON PROFIL =====
$profile = $_SESSION["profile"] ?? '';
$isQCMProfile = in_array($profile, ["Manager", "Technicien"]);
$isAdminProfile = in_array($profile, ["Super Admin", "Admin", "Ressource Humaine", "Directeur Pièce et Service", "Directeur des Opérations", "Directeur Général", "Directeur Groupe"]);

// Section QCM (Manager/Technicien)
if ($isQCMProfile) {
    // ===== CLASSES ADAPTÉES POUR QCM =====
    /**
     * Classe pour la connexion MongoDB (singleton)
     */
    class DatabaseConnection {
        private static $instance = null;
        private $conn, $collections = [];

        private function __construct() {
            $this->conn = new MongoDB\Client(medacapMongoUri());
            $academy = $this->conn->academy;
            $this->collections = [
                'users' => $academy->users,
                'allocations' => $academy->allocations
            ];
        }

        public static function getInstance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function getCollection($name) {
            return $this->collections[$name] ?? null;
        }
    }

    /**
     * Gestionnaire d'autorisation pour profils
     */
    class AuthorizationManager {
        private static $authorizedProfiles = [
            "Manager",
            "Technicien"
        ];

        public static function isAuthorized($userProfile) {
            return in_array($userProfile, self::$authorizedProfiles);
        }

        public static function requireLogin() {
            if (!isset($_SESSION["id"])) {
                header("Location: ../../");
                exit();
            }
        }

        public static function requireAuthorization() {
            self::requireLogin();
            if (!self::isAuthorized($_SESSION['profile'])) {
                header("Location: ../../unauthorized.php");
                exit();
            }
        }
    }

    /**
     * Gestionnaire QCM pour récupération et traitement
     */
    class QCMManager {
        private $db, $collections;

        public function __construct() {
            $this->db = DatabaseConnection::getInstance();
            $this->collections = [
                'users' => $this->db->getCollection('users'),
                'allocations' => $this->db->getCollection('allocations')
            ];
        }

        /**
         * Récupère IDs techniciens par profil
         */
        public function getTechnicians($userProfile, $userSession) {
            if ($userProfile == "Manager") {
                $objId = new MongoDB\BSON\ObjectId($userSession["id"]);
                $manager = $this->collections['users']->findOne(["_id" => $objId, "active" => true]);
                return $manager['users'] ?? [];
            }
            return [];
        }

        /**
         * Calcule stats QCM par niveau pour techniciens
         */
        public function getQCMStatsByLevel($technicianIds, $level) {
            $done = $pending = $total = 0;
            foreach ($technicianIds as $userId) {
                $objId = new MongoDB\BSON\ObjectId($userId);
                $declaratifs = $this->collections['allocations']->find([
                    'user' => $objId,
                    'type' => 'Declaratif',
                    'level' => $level
                ]);
                foreach ($declaratifs as $declaratif) {
                    $total++;
                    if (isset($declaratif['activeManager']) && $declaratif['activeManager']) {
                        $done++;
                    } else {
                        $pending++;
                    }
                }
            }
            return ['done' => $done, 'pending' => $pending, 'total' => $total];
        }

        /**
         * Stats pour profil Manager test=true
         */
        public function getManagerTestStats($objId) {
            $factuels = $this->collections['allocations']->find(['user' => $objId, 'type' => 'Factuel']);
            $declaratifs = $this->collections['allocations']->find(['user' => $objId, 'type' => 'Declaratif']);
            $factuelDone = $factuelPending = $factuelTotal = 0;
            $declaratifDone = $declaratifPending = $declaratifTotal = 0;
            foreach ($factuels as $factuel) {
                $factuelTotal++;
                if (isset($factuel['active']) && $factuel['active']) $factuelDone++; else $factuelPending++;
            }
            foreach ($declaratifs as $declaratif) {
                $declaratifTotal++;
                if (isset($declaratif['active']) && $declaratif['active']) $declaratifDone++; else $declaratifPending++;
            }
            return [
                'factuelDone' => $factuelDone, 'factuelPending' => $factuelPending, 'factuelTotal' => $factuelTotal,
                'declaratifDone' => $declaratifDone, 'declaratifPending' => $declaratifPending, 'declaratifTotal' => $declaratifTotal
            ];
        }

        /**
         * Stats pour profil non-Manager
         */
        public function getUserStats($objId) {
            return $this->getManagerTestStats($objId); // Logique similaire
        }
    }

    // ===== LOGIQUE PRINCIPALE QCM =====
    AuthorizationManager::requireAuthorization();

    $qcmManager = new QCMManager();
    $technicians = $qcmManager->getTechnicians($_SESSION["profile"], $_SESSION);

    $cards = [];
    $title_icon = 'ki-duotone ki-chart fs-2 me-3';
    $secondary_title = '';

    if ($_SESSION["profile"] == "Manager" && !$_SESSION["test"]) {
        $resultJu = $qcmManager->getQCMStatsByLevel($technicians, 'Junior');
        $resultSe = $qcmManager->getQCMStatsByLevel($technicians, 'Senior');
        $resultEx = $qcmManager->getQCMStatsByLevel($technicians, 'Expert');
        $cards = [
            ['id' => 'junior', 'value' => $resultJu['total'], 'label' => $nbre_qcm_junior, 'color' => 'none'],
            ['id' => 'senior', 'value' => $resultSe['total'], 'label' => $nbre_qcm_senior, 'color' => 'none'],
            ['id' => 'expert', 'value' => $resultEx['total'], 'label' => $nbre_qcm_expert, 'color' => 'none'],
            ['id' => 'total', 'value' => $resultJu['total'] + $resultSe['total'] + $resultEx['total'], 'label' => $nbre_qcm_tache_pro_manager_total, 'color' => 'none'],
        ];
        $secondary_title = $etat_avancement_qcm_tache_pro_manager;
        $chartData = [
            [
                'title' => sprintf('%s %d / %d QCM', $nbre_qcm_junior, $resultJu['done'], $resultJu['total']),
                'total' => 100,
                'completed' => completedPercentage($resultJu['done'], $resultJu['total']),
                'data' => [completedPercentage($resultJu['done'], $resultJu['total']), 100 - completedPercentage($resultJu['done'], $resultJu['total'])],
                'labels' => [completedPercentage($resultJu['done'], $resultJu['total']) . ' ' . $tache_pro_junior_realise, (100 - completedPercentage($resultJu['done'], $resultJu['total'])) . ' ' . $tache_pro_junior_a_realiser],
                'backgroundColor' => ['#4303ec', '#D3D3D3']
            ],
            [
                'title' => sprintf('%s %d / %d QCM', $nbre_qcm_senior, $resultSe['done'], $resultSe['total']),
                'total' => 100,
                'completed' => completedPercentage($resultSe['done'], $resultSe['total']),
                'data' => [completedPercentage($resultSe['done'], $resultSe['total']), 100 - completedPercentage($resultSe['done'], $resultSe['total'])],
                'labels' => [completedPercentage($resultSe['done'], $resultSe['total']) . ' ' . $tache_pro_senior_realise, (100 - completedPercentage($resultSe['done'], $resultSe['total'])) . ' ' . $tache_pro_senior_a_realiser],
                'backgroundColor' => ['#4303ec', '#D3D3D3']
            ],
            [
                'title' => sprintf('%s %d / %d QCM', $nbre_qcm_expert, $resultEx['done'], $resultEx['total']),
                'total' => 100,
                'completed' => completedPercentage($resultEx['done'], $resultEx['total']),
                'data' => [completedPercentage($resultEx['done'], $resultEx['total']), 100 - completedPercentage($resultEx['done'], $resultEx['total'])],
                'labels' => [completedPercentage($resultEx['done'], $resultEx['total']) . ' ' . $tache_pro_expert_realise, (100 - completedPercentage($resultEx['done'], $resultEx['total'])) . ' ' . $tache_pro_expert_a_realiser],
                'backgroundColor' => ['#4303ec', '#D3D3D3']
            ],
            [
                'title' => sprintf('%s %d / %d QCM', $nbre_qcm_tache_pro_manager, ($resultJu['done'] + $resultSe['done'] + $resultEx['done']), ($resultJu['total'] + $resultSe['total'] + $resultEx['total'])),
                'total' => 100,
                'completed' => completedPercentage(($resultJu['done'] + $resultSe['done'] + $resultEx['done']), ($resultJu['total'] + $resultSe['total'] + $resultEx['total'])),
                'data' => [completedPercentage(($resultJu['done'] + $resultSe['done'] + $resultEx['done']), ($resultJu['total'] + $resultSe['total'] + $resultEx['total'])), 100 - completedPercentage(($resultJu['done'] + $resultSe['done'] + $resultEx['done']), ($resultJu['total'] + $resultSe['total'] + $resultEx['total']))],
                'labels' => [completedPercentage(($resultJu['done'] + $resultSe['done'] + $resultEx['done']), ($resultJu['total'] + $resultSe['total'] + $resultEx['total'])) . ' ' . $tache_pro_tous_niveaux_realise, (100 - completedPercentage(($resultJu['done'] + $resultSe['done'] + $resultEx['done']), ($resultJu['total'] + $resultSe['total'] + $resultEx['total']))) . ' ' . $tache_pro_tous_niveaux_a_realiser],
                'backgroundColor' => ['#4303ec', '#D3D3D3']
            ]
        ];
    } elseif ($_SESSION["profile"] == "Manager" && $_SESSION["test"]) {
        $objId = new MongoDB\BSON\ObjectId($_SESSION["id"]);
        $results = $qcmManager->getManagerTestStats($objId);
        $resultJu = $qcmManager->getQCMStatsByLevel($technicians, 'Junior');
        $resultSe = $qcmManager->getQCMStatsByLevel($technicians, 'Senior');
        $resultEx = $qcmManager->getQCMStatsByLevel($technicians, 'Expert');
        $cards = [
            ['id' => 'knowledge', 'value' => $results['factuelTotal'], 'label' => $nbre_qcm_connaissance, 'color' => 'none'],
            ['id' => 'pro_tech', 'value' => $results['declaratifTotal'], 'label' => $nbre_qcm_tache_pro_tech, 'color' => 'none'],
            ['id' => 'pro_manager', 'value' => $resultJu['total'] + $resultSe['total'] + $resultEx['total'], 'label' => $nbre_qcm_tache_pro_manager, 'color' => 'none'],
        ];
        $secondary_title = $etat_avancement_qcm;
        // Chart data similaire, adapté
        $chartData = [
            [
                'title' => sprintf('%s %d / %d QCM', $nbre_qcm_connaissance, $results['factuelDone'], $results['factuelTotal']),
                'total' => 100,
                'completed' => completedPercentage($results['factuelDone'], $results['factuelTotal']),
                'data' => [completedPercentage($results['factuelDone'], $results['factuelTotal']), 100 - completedPercentage($results['factuelDone'], $results['factuelTotal'])],
                'labels' => [completedPercentage($results['factuelDone'], $results['factuelTotal']) . ' ' . $qcm_connaissance_realise, (100 - completedPercentage($results['factuelDone'], $results['factuelTotal'])) . ' ' . $qcm_connaissance_a_realiser],
                'backgroundColor' => ['#4303ec', '#D3D3D3']
            ],
            [
                'title' => sprintf('%s %d / %d QCM', $nbre_qcm_tache_pro_tech, $results['declaratifDone'], $results['declaratifTotal']),
                'total' => 100,
                'completed' => completedPercentage($results['declaratifDone'], $results['declaratifTotal']),
                'data' => [completedPercentage($results['declaratifDone'], $results['declaratifTotal']), 100 - completedPercentage($results['declaratifDone'], $results['declaratifTotal'])],
                'labels' => [completedPercentage($results['declaratifDone'], $results['declaratifTotal']) . ' ' . $tache_pro_realise, (100 - completedPercentage($results['declaratifDone'], $results['declaratifTotal'])) . ' ' . $tache_pro_a_realiser],
                'backgroundColor' => ['#4303ec', '#D3D3D3']
            ],
            [
                'title' => sprintf('%s %d / %d QCM', $nbre_qcm_tache_pro_manager, ($resultJu['done'] + $resultSe['done'] + $resultEx['done']), ($resultJu['total'] + $resultSe['total'] + $resultEx['total'])),
                'total' => 100,
                'completed' => completedPercentage(($resultJu['done'] + $resultSe['done'] + $resultEx['done']), ($resultJu['total'] + $resultSe['total'] + $resultEx['total'])),
                'data' => [completedPercentage(($resultJu['done'] + $resultSe['done'] + $resultEx['done']), ($resultJu['total'] + $resultSe['total'] + $resultEx['total'])), 100 - completedPercentage(($resultJu['done'] + $resultSe['done'] + $resultEx['done']), ($resultJu['total'] + $resultSe['total'] + $resultEx['total']))],
                'labels' => [completedPercentage(($resultJu['done'] + $resultSe['done'] + $resultEx['done']), ($resultJu['total'] + $resultSe['total'] + $resultEx['total'])) . ' ' . $tache_pro_manager_realise, (100 - completedPercentage(($resultJu['done'] + $resultSe['done'] + $resultEx['done']), ($resultJu['total'] + $resultSe['total'] + $resultEx['total']))) . ' ' . $tache_pro_manager_a_realiser],
                'backgroundColor' => ['#4303ec', '#D3D3D3']
            ]
        ];
    } else {
        $objId = new MongoDB\BSON\ObjectId($_SESSION["id"]);
        $results = $qcmManager->getUserStats($objId);
        $cards = [
            ['id' => 'knowledge', 'value' => $results['factuelTotal'], 'label' => $nbre_qcm_connaissance, 'color' => 'none'],
            ['id' => 'pro', 'value' => $results['declaratifTotal'], 'label' => $nbre_qcm_tache_pro, 'color' => 'none'],
            ['id' => 'total', 'value' => $results['factuelTotal'] + $results['declaratifTotal'], 'label' => $nbre_test, 'color' => 'none'],
        ];
        $secondary_title = $etat_avancement_qcm;
        // Chart data pour non-manager
        $chartData = [
            [
                'title' => sprintf('%s %d / %d QCM', $nbre_qcm_connaissance, $results['factuelDone'], $results['factuelTotal']),
                'total' => 100,
                'completed' => completedPercentage($results['factuelDone'], $results['factuelTotal']),
                'data' => [completedPercentage($results['factuelDone'], $results['factuelTotal']), 100 - completedPercentage($results['factuelDone'], $results['factuelTotal'])],
                'labels' => [completedPercentage($results['factuelDone'], $results['factuelTotal']) . ' ' . $qcm_connaissance_realise, (100 - completedPercentage($results['factuelDone'], $results['factuelTotal'])) . ' ' . $qcm_connaissance_a_realiser],
                'backgroundColor' => ['#4303ec', '#D3D3D3']
            ],
            [
                'title' => sprintf('%s %d / %d QCM', $nbre_qcm_tache_pro, $results['declaratifDone'], $results['declaratifTotal']),
                'total' => 100,
                'completed' => completedPercentage($results['declaratifDone'], $results['declaratifTotal']),
                'data' => [completedPercentage($results['declaratifDone'], $results['declaratifTotal']), 100 - completedPercentage($results['declaratifDone'], $results['declaratifTotal'])],
                'labels' => [completedPercentage($results['declaratifDone'], $results['declaratifTotal']) . ' ' . $tache_pro_realise, (100 - completedPercentage($results['declaratifDone'], $results['declaratifTotal'])) . ' ' . $tache_pro_a_realiser],
                'backgroundColor' => ['#4303ec', '#D3D3D3']
            ],
            [
                'title' => sprintf('%s %d / %d QCM', $nbre_test, ($results['factuelDone'] + $results['declaratifDone']), ($results['factuelTotal'] + $results['declaratifTotal'])),
                'total' => 100,
                'completed' => completedPercentage(($results['factuelDone'] + $results['declaratifDone']), ($results['factuelTotal'] + $results['declaratifTotal'])),
                'data' => [completedPercentage(($results['factuelDone'] + $results['declaratifDone']), ($results['factuelTotal'] + $results['declaratifTotal'])), 100 - completedPercentage(($results['factuelDone'] + $results['declaratifDone']), ($results['factuelTotal'] + $results['declaratifTotal']))],
                'labels' => [completedPercentage(($results['factuelDone'] + $results['declaratifDone']), ($results['factuelTotal'] + $results['declaratifTotal'])) . ' ' . $tests_completes, (100 - completedPercentage(($results['factuelDone'] + $results['declaratifDone']), ($results['factuelTotal'] + $results['declaratifTotal']))) . ' ' . $tests_restants_completer],
                'backgroundColor' => ['#4303ec', '#D3D3D3']
            ]
        ];
    }

    $numCards = count($cards);
    $colClassCards = ($numCards == 4) ? 'col-6 col-lg-3' : 'col-12 col-sm-6 col-md-4';
    $numCharts = count($chartData);
    $colClassCharts = ($numCharts == 4) ? 'col-md-6 col-lg-3' : 'col-12 col-sm-6 col-md-4';

    $pageTitle = $tableau_measure . ' | CFAO Mobility Academy';
    $mainTitleIcon = 'ki-duotone ki-chart-line fs-2 me-5';
    $mainTitle = $tableau_measure;
    $chartContainerId = 'chartQCM';
    $chartDataVar = $chartData;
    $colClassChartsVar = $colClassCharts;
    $cardsVar = $cards;
    $secondaryTitleVar = $secondary_title;
    $titleIconVar = $title_icon;
    $isQCM = true;

} elseif ($isAdminProfile) {
    // Inclusions spécifiques admin
    include_once "../explore/getValidatedResults.php";
    include_once "../explore/score_decla.php";
    include_once "../explore/score_fact.php";

    // ===== CLASSES POUR LE TABLEAU (ADMIN) =====
    /**
     * Classe pour la connexion à la base de données MongoDB (singleton)
     */
    class DatabaseConnection {
        private static $instance = null;
        private $conn, $collections = [];

        private function __construct() {
            $this->conn = new MongoDB\Client(medacapMongoUri());
            $academy = $this->conn->academy;
            $this->collections = [
                'users' => $academy->users,
                'results' => $academy->results,
                'exams' => $academy->exams,
                'tests' => $academy->tests,
                'allocations' => $academy->allocations
            ];
        }

        public static function getInstance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function getCollection($name) {
            return $this->collections[$name] ?? null;
        }
    }

    /**
     * Gestionnaire d'autorisation pour les profils utilisateurs
     */
    class AuthorizationManager {
        private static $authorizedProfiles = [
            "Super Admin", "Admin", "Ressource Humaine",
            "Directeur Pièce et Service", "Directeur des Opérations",
            "Directeur Général", "Directeur Groupe"
        ];

        public static function isAuthorized($userProfile) {
            return in_array($userProfile, self::$authorizedProfiles);
        }

        public static function requireLogin() {
            if (!isset($_SESSION["id"])) {
                header("Location: ../../");
                exit();
            }
        }

        public static function requireAuthorization() {
            self::requireLogin();
            if (!self::isAuthorized($_SESSION['profile'])) {
                header("Location: ../../unauthorized.php");
                exit();
            }
        }
    }

    /**
     * Gestionnaire des techniciens pour récupération et traitement des données
     */
    class TechnicianManager {
        private $db, $collections;

        public function __construct() {
            $this->db = DatabaseConnection::getInstance();
            $this->collections = [
                'users' => $this->db->getCollection('users'),
                'results' => $this->db->getCollection('results'),
                'exams' => $this->db->getCollection('exams'),
                'tests' => $this->db->getCollection('tests'),
                'allocations' => $this->db->getCollection('allocations')
            ];
        }

        /**
         * Récupère les IDs des techniciens en fonction du profil utilisateur
         */
        public function getTechnicians($userProfile, $userSession, $subsidiaryFilter = null, $brand = '') {
            switch ($userProfile) {
                case "Super Admin":
                case "Directeur Groupe":
                    return $this->getSuperAdminTechnicians($userSession, $subsidiaryFilter, $brand);
                case "Admin":
                case "Ressource Humaine":
                case "Directeur Pièce et Service":
                case "Directeur des Opérations":
                case "Directeur Général":
                    return $this->getRegularAdminTechnicians($userSession, $subsidiaryFilter, $brand);
                default:
                    return [];
            }
        }

        /**
         * Récupération pour profils super admin/groupe
         */
        private function getSuperAdminTechnicians($userSession, $subsidiaryFilter, $brand) {
            $query = ["active" => true];
            if ($subsidiaryFilter && $subsidiaryFilter !== 'all' && $subsidiaryFilter !== null) {
                $query['subsidiary'] = is_array($subsidiaryFilter) ? ['$in' => $subsidiaryFilter] : $subsidiaryFilter;
            }
            $this->addDepartmentFilter($query, $userSession);
            if ($brand !== '') {
                $query['$or'] = [
                    ['brandJunior' => ['$in' => [$brand]]],
                    ['brandSenior' => ['$in' => [$brand]]],
                    ['brandExpert' => ['$in' => [$brand]]]
                ];
            }
            return $this->filterTechnicians($query);
        }

        /**
         * Récupération pour profils admin/filiale
         */
        private function getRegularAdminTechnicians($userSession, $subsidiaryFilter, $brand) {
            $query = [
                "subsidiary" => $subsidiaryFilter ?? $userSession['subsidiary'],
                "active" => true
            ];
            $this->addDepartmentFilter($query, $userSession);
            if ($brand !== '') {
                $query['$or'] = [
                    ['brandJunior' => ['$in' => [$brand]]],
                    ['brandSenior' => ['$in' => [$brand]]],
                    ['brandExpert' => ['$in' => [$brand]]]
                ];
            }
            return $this->filterTechnicians($query);
        }

        /**
         * Ajoute le filtre département si applicable
         */
        private function addDepartmentFilter(&$query, $userSession) {
            $userDoc = $this->collections['users']->findOne(["_id" => new MongoDB\BSON\ObjectId($userSession['id'])]);
            $department = $userDoc['department'] ?? $userSession['department'] ?? null;
            if ($department && $department !== 'null' && trim($department) !== '') {
                $departements = explode(' & ', $department);
                if (!empty($departements)) {
                    $orConditions = array_map(function($dep) {
                        return ['department' => new MongoDB\BSON\Regex(trim($dep), 'i')];
                    }, $departements);
                    $query['$or'] = $orConditions;
                }
            } elseif ($userSession["department"] != 'Equipment & Motors') {
                $query['department'] = $userSession["department"];
            }
        }

        /**
         * Filtre les documents pour ne garder que les techniciens/managers test
         */
        private function filterTechnicians($query) {
            $techIds = [];
            foreach ($this->collections['users']->find($query) as $tech) {
                if ($tech["profile"] == "Technicien" || ($tech["profile"] == "Manager" && $tech["test"] == true)) {
                    try {
                        $techIds[] = new MongoDB\BSON\ObjectId($tech['_id']);
                    } catch (Exception $e) {
                        continue;
                    }
                }
            }
            return $techIds;
        }

        /**
         * Récupère les données détaillées des techniciens en bulk
         */
        public function getTechnicianDataBulk($technicianIds) {
            if (empty($technicianIds)) return [];

            $validIds = array_filter($technicianIds, function($id) {
                return $id instanceof MongoDB\BSON\ObjectId;
            });
            if (empty($validIds)) return [];

            $users = $this->fetchUsersByIds($validIds);
            $managers = $this->fetchManagers($users);
            $allAllocations = $this->fetchAllocs($validIds);
            $allTests = $this->fetchTests($validIds);
            $allExams = $this->fetchExams($validIds);
            $allResults = $this->fetchResults($validIds);

            $techniciansData = [];
            foreach ($validIds as $techId) {
                $uid = (string)$techId;
                if (!isset($users[$uid])) continue;
                $user = $users[$uid];
                $managerId = $user['manager'] ?? null;
                $manager = $managerId ? ($managers[(string)$managerId] ?? null) : null;
                $evaluations = $this->buildEvaluationsBulk($uid, $allTests[$uid] ?? [], $allAllocations[$uid] ?? [], $allExams[$uid] ?? [], $allResults[$uid] ?? []);
                $techniciansData[] = ['user' => $user, 'manager' => $manager, 'evaluations' => $evaluations];
            }
            return $techniciansData;
        }

        /**
         * Récupère allocations et résultats en bulk pour stats
         */
        public function getBulkDataForStats($technicianIds) {
            $validIds = array_filter($technicianIds, function($id) {
                return $id instanceof MongoDB\BSON\ObjectId;
            });
            if (empty($validIds)) return ['allAllocs' => [], 'allResults' => []];

            return [
                'allAllocs' => $this->fetchAllocs($validIds),
                'allResults' => $this->fetchResults($validIds)
            ];
        }

        // Méthodes privées pour fetch des données
        private function fetchUsersByIds($validIds) {
            $users = [];
            foreach ($this->collections['users']->find(['_id' => ['$in' => $validIds], 'active' => true]) as $user) {
                $users[(string)$user['_id']] = $user;
            }
            return $users;
        }

        private function fetchManagers($users) {
            $managerIds = array_unique(array_filter(array_map(function($u) {
                return $u['manager'] ?? null;
            }, $users)));
            $managers = [];
            if (!empty($managerIds)) {
                $validManagerIds = [];
                foreach ($managerIds as $mgrId) {
                    try {
                        $validManagerIds[] = (is_a($mgrId, 'MongoDB\BSON\ObjectId')) ? $mgrId : new MongoDB\BSON\ObjectId($mgrId);
                    } catch (Exception $e) {
                        continue;
                    }
                }
                if (!empty($validManagerIds)) {
                    foreach ($this->collections['users']->find(['_id' => ['$in' => $validManagerIds], 'active' => true]) as $mgr) {
                        $managers[(string)$mgr['_id']] = $mgr;
                    }
                }
            }
            return $managers;
        }

        private function fetchAllocs($validIds) {
            $allAllocations = [];
            foreach ($this->collections['allocations']->find(['user' => ['$in' => $validIds]]) as $alloc) {
                $uid = (string)$alloc['user'];
                $allAllocations[$uid][$alloc['level']][$alloc['type']] = $alloc;
            }
            return $allAllocations;
        }

        private function fetchTests($validIds) {
            $allTests = [];
            foreach ($this->collections['tests']->find(['user' => ['$in' => $validIds], 'active' => true]) as $test) {
                $uid = (string)$test['user'];
                $allTests[$uid][$test['level']][$test['type']] = $test;
            }
            return $allTests;
        }

        private function fetchExams($validIds) {
            $allExams = [];
            foreach ($this->collections['exams']->find(['user' => ['$in' => $validIds]]) as $exam) {
                $uid = (string)$exam['user'];
                if (isset($exam['test'])) {
                    $allExams[$uid][(string)$exam['test']] = $exam;
                }
            }
            return $allExams;
        }

        private function fetchResults($validIds) {
            $allResults = [];
            foreach ($this->collections['results']->find(['user' => ['$in' => $validIds], 'active' => true]) as $result) {
                $uid = (string)$result['user'];
                $allResults[$uid][$result['level']][$result['type']] = $result;
            }
            return $allResults;
        }

        /**
         * Construit les évaluations pour un technicien
         */
        private function buildEvaluationsBulk($uid, $tests, $allocations, $exams, $results) {
            $evaluations = [];
            foreach (["Junior", "Senior", "Expert"] as $level) {
                $factual = $this->buildTestEvaluationBulk($uid, $level, "Factuel", $tests, $allocations, $exams, $results);
                $declarative = $this->buildTestEvaluationBulk($uid, $level, "Declaratif", $tests, $allocations, $exams, $results);
                $evaluations[$level] = ['factual' => $factual, 'declarative' => $declarative];
            }
            return $evaluations;
        }

        private function buildTestEvaluationBulk($uid, $level, $type, $tests, $allocations, $exams, $results) {
            $test = $tests[$level][$type] ?? null;
            $allocation = $allocations[$level][$type] ?? null;
            $exam = null;
            $managerExam = null;
            if ($test) {
                $testId = (string)$test['_id'];
                $exam = $exams[$uid][$testId] ?? null;
                if ($type == "Declaratif") {
                    $managerExam = $exams[$uid][$testId . '_Manager'] ?? null;
                }
            }
            $result = $results[$level][$type] ?? null;
            return [
                'test' => $test,
                'allocation' => $allocation,
                'exam' => $exam,
                'managerExam' => $managerExam,
                'result' => $result,
                'percentage' => $this->calculatePercentage($result)
            ];
        }

        /**
         * Calcule le pourcentage de réussite d'un test
         */
        private function calculatePercentage($result) {
            return isset($result["score"]) && isset($result["total"]) && $result["total"] > 0
                ? ceil(($result["score"] * 100) / $result["total"])
                : null;
        }

        /**
         * Calcule les stats bulk pour les niveaux
         */
        public function getBulkStats($technicianIdsByLevel, $subsidiaryFilter) {
            $allTechIds = array_unique(array_merge(...array_values($technicianIdsByLevel)));
            if (empty($allTechIds)) return [];

            $validAllIds = array_filter($allTechIds, function($id) {
                return $id instanceof MongoDB\BSON\ObjectId;
            });
            if (empty($validAllIds)) return [];

            $allAllocs = $this->fetchAllocs($validAllIds);
            $allResults = $this->fetchResults($validAllIds);

            $stats = $this->computeLevelStats($technicianIdsByLevel, $allAllocs, $allResults);
            $qcmCounters = $this->computeQcmCounters($validAllIds, $allAllocs);
            $qcmTodo = $this->computeQcmTodo($validAllIds, $allAllocs);

            return [
                'stats' => $stats,
                'qcmCounters' => $qcmCounters,
                'qcmTodo' => $qcmTodo
            ];
        }

        /**
         * Calcule les stats par niveau (scores, totaux, tests faits)
         */
        private function computeLevelStats($technicianIdsByLevel, $allAllocs, $allResults) {
            $stats = ['facScores' => [], 'declaScores' => [], 'facTotals' => [], 'declaTotals' => [], 'doneTests' => []];
            foreach (['Junior', 'Senior', 'Expert'] as $level) {
                $techIds = $technicianIdsByLevel[$level] ?? [];
                $validTechIds = array_filter($techIds, function($id) {
                    return $id instanceof MongoDB\BSON\ObjectId;
                });
                $facScores = $declaScores = $facTotals = $declaTotals = $doneTests = [];

                foreach ($validTechIds as $techId) {
                    $uid = (string)$techId;
                    $alloFac = $allAllocs[$uid][$level]['Factuel'] ?? null;
                    $alloDecla = $allAllocs[$uid][$level]['Declaratif'] ?? null;
                    if ($alloFac && $alloDecla) {
                        if (isset($allResults[$uid][$level]['Factuel'])) {
                            $resFac = $allResults[$uid][$level]['Factuel'];
                            $facScores[] = $resFac['score'];
                            $facTotals[] = $resFac['total'];
                        }
                        if (isset($allResults[$uid][$level]['Declaratif'])) {
                            $resDecla = $allResults[$uid][$level]['Declaratif'];
                            $declaScores[] = $resDecla['score'];
                            $declaTotals[] = $resDecla['total'];
                        }
                        $doneTests[] = $techId;
                    }
                }

                $stats['facScores'][$level] = $facScores;
                $stats['declaScores'][$level] = $declaScores;
                $stats['facTotals'][$level] = $facTotals;
                $stats['declaTotals'][$level] = $declaTotals;
                $stats['doneTests'][$level] = $doneTests;
            }
            return $stats;
        }

        /**
         * Calcule les compteurs QCM
         */
        private function computeQcmCounters($validAllIds, $allAllocs) {
            $levels = ['Junior', 'Senior', 'Expert'];
            $qcmCounters = [
                'countSavoirs' => [], 'countMaSavFais' => [], 'countTechSavFais' => [],
                'countSavFais' => [], 'tests' => [], 'testTotal' => []
            ];
            foreach ($levels as $level) {
                $code = substr($level, 0, 2);
                foreach (['countSavoirs', 'countMaSavFais', 'countTechSavFais', 'countSavFais', 'tests', 'testTotal'] as $key) {
                    $qcmCounters[$key][$code] = [];
                }
            }

            foreach ($validAllIds as $techId) {
                $uid = (string)$techId;
                foreach ($levels as $level) {
                    $code = substr($level, 0, 2);
                    $factuel = $allAllocs[$uid][$level]['Factuel'] ?? null;
                    $declaratif = $allAllocs[$uid][$level]['Declaratif'] ?? null;
                    if ($factuel && $factuel['active']) $qcmCounters['countSavoirs'][$code][] = $factuel;
                    if ($declaratif && $declaratif['activeManager']) $qcmCounters['countMaSavFais'][$code][] = $declaratif;
                    if ($declaratif && $declaratif['active']) {
                        $qcmCounters['countTechSavFais'][$code][] = $declaratif;
                        $qcmCounters['countSavFais'][$code][] = $declaratif;
                    }
                    if ($factuel && $declaratif && $factuel['active'] && $declaratif['active'] && $declaratif['activeManager']) {
                        $qcmCounters['tests'][$code][] = $techId;
                    }
                    if ($factuel && $declaratif) $qcmCounters['testTotal'][$code][] = $techId;
                }
            }
            return $qcmCounters;
        }

        /**
         * Calcule les tâches QCM à faire
         */
        private function computeQcmTodo($validAllIds, $allAllocs) {
            $levels = ['Junior', 'Senior', 'Expert'];
            $codes = array_map(function($l) {
                return substr($l, 0, 2);
            }, $levels);
            $todoCon = $doneCon = $todoTpT = $doneTpT = $todoTpM = $doneTpM = array_fill_keys($codes, []);

            foreach ($validAllIds as $techId) {
                $uid = (string)$techId;
                foreach ($levels as $level) {
                    $code = substr($level, 0, 2);
                    if ($allAllocs[$uid][$level]['Factuel'] ?? null) {
                        $todoCon[$code][] = 1;
                        if (($allAllocs[$uid][$level]['Factuel'] ?? null)['active']) $doneCon[$code][] = 1;
                    }
                    if ($decla = $allAllocs[$uid][$level]['Declaratif'] ?? null) {
                        $todoTpT[$code][] = 1;
                        if ($decla['active']) $doneTpT[$code][] = 1;
                        $todoTpM[$code][] = 1;
                        if ($decla['activeManager']) $doneTpM[$code][] = 1;
                    }
                }
            }
            return compact('todoCon', 'doneCon', 'todoTpT', 'doneTpT', 'todoTpM', 'doneTpM');
        }
    }

    /**
     * Rendu du tableau des techniciens
     */
    class TableRenderer {
        /**
         * Génère les en-têtes du tableau en fonction du profil
         */
        public function renderTableHeaders($userProfile) {
            $headers = '';
            if ($userProfile == "Super Admin" || $userProfile == "Directeur Groupe") {
                $headers .= '<th class="min-w-200px sorting text-center text-black fw-bold">' . (isset($GLOBALS['prenomsNoms']) ? $GLOBALS['prenomsNoms'] : 'Name') . '</th>';
                $headers .= '<th class="min-w-200px sorting text-center text-black fw-bold">' . (isset($GLOBALS['manager']) ? $GLOBALS['manager'] : 'Manager') . '</th>';
                $headers .= '<th class="min-w-200px sorting text-center text-black fw-bold">' . (isset($GLOBALS['pays']) ? $GLOBALS['pays'] : 'Country') . '</th>';
            } elseif ($userProfile == "Admin") {
                $headers .= '<th class="min-w-200px sorting text-center text-black fw-bold">' . (isset($GLOBALS['prenomsNoms']) ? $GLOBALS['prenomsNoms'] : 'Name') . '</th>';
                $headers .= '<th class="min-w-100px sorting text-center text-black fw-bold">' . (isset($GLOBALS['Level']) ? $GLOBALS['Level'] : 'Level') . '</th>';
                $headers .= '<th class="min-w-150px sorting text-center text-black fw-bold">' . (isset($GLOBALS['agence']) ? $GLOBALS['agence'] : 'Agency') . '</th>';
            } else {
                $headers .= '<th class="min-w-200px sorting text-center text-black fw-bold">' . (isset($GLOBALS['prenomsNoms']) ? $GLOBALS['prenomsNoms'] : 'Name') . '</th>';
                $headers .= '<th class="min-w-200px sorting text-center text-black fw-bold">' . (isset($GLOBALS['agence']) ? $GLOBALS['agence'] : 'Agency') . '</th>';
            }
            foreach (['Junior', 'Senior', 'Expert'] as $level) $headers .= $this->renderLevelHeaders();
            return $headers;
        }

        /**
         * En-têtes pour les niveaux de test
         */
        private function renderLevelHeaders() {
            $testHeaders = [isset($GLOBALS['qcm_connaissances_tech']) ? $GLOBALS['qcm_connaissances_tech'] : 'Technical Knowledge', isset($GLOBALS['qcm_tache_pro_tech']) ? $GLOBALS['qcm_tache_pro_tech'] : 'Professional Tasks Tech', isset($GLOBALS['qcm_tache_pro_manager']) ? $GLOBALS['qcm_tache_pro_manager'] : 'Professional Tasks Manager'];
            $headers = '';
            foreach ($testHeaders as $header) $headers .= '<th class="min-w-80px sorting text-center text-black fw-bold">' . htmlspecialchars($header) . '</th>';
            return $headers;
        }

        /**
         * Génère une ligne du tableau
         */
        public function renderTableRow($techData, $userProfile) {
            $user = $techData['user']; $manager = $techData['manager']; $evaluations = $techData['evaluations'];
            $row = '<tr class="odd">';
            $row .= '<td class="text-center">' . htmlspecialchars($user["firstName"] . ' ' . $user["lastName"]) . '</td>';
            $row .= $this->renderProfileSpecificColumns($user, $manager, $userProfile);
            foreach (['Junior', 'Senior', 'Expert'] as $level) $row .= $this->renderEvaluationColumns($evaluations[$level]);
            $row .= '</tr>';
            return $row;
        }

        /**
         * Colonnes spécifiques au profil
         */
        private function renderProfileSpecificColumns($user, $manager, $userProfile) {
            $columns = '';
            if ($userProfile == "Super Admin" || $userProfile == "Directeur Groupe") {
                $columns .= $manager ? '<td class="text-center">' . htmlspecialchars($manager["firstName"] . ' ' . $manager["lastName"]) . '</td>' : '<td class="text-center">-</td>';
                $columns .= '<td class="text-center">' . htmlspecialchars($user["country"] ?? '-') . '</td>';
            } elseif ($userProfile == "Admin") {
                $columns .= '<td class="text-center">' . htmlspecialchars($user["level"]) . '</td><td class="text-center">' . htmlspecialchars($user["agency"]) . '</td>';
            } else $columns .= '<td class="text-center">' . htmlspecialchars($user["agency"]) . '</td>';
            return $columns;
        }

        /**
         * Colonnes d'évaluation par niveau
         */
        private function renderEvaluationColumns($levelEvaluations) {
            $columns = $this->renderEvaluationCell($levelEvaluations['factual']);
            $columns .= $this->renderEvaluationCell($levelEvaluations['declarative']) . $this->renderManagerEvaluationCell($levelEvaluations['declarative']);
            return $columns;
        }

        /**
         * Cellule d'évaluation standard
         */
        private function renderEvaluationCell($evaluation) {
            if (!$evaluation['allocation'] || !$evaluation['allocation']['active']) return '<td class="text-center transparent-cell"></td>';
            if ($evaluation['exam'] && $evaluation['exam']['active']) return '<td class="text-center"><span class="badge bg-warning text-white fs-7 m-1">' . (isset($GLOBALS['en_cours']) ? $GLOBALS['en_cours'] : 'En cours') . '</span></td>';
            if ($evaluation['percentage'] !== null) return '<td class="text-center"><span class="badge bg-success text-white fs-7 m-1">' . (isset($GLOBALS['completed']) ? $GLOBALS['completed'] : 'Complète') . '</span></td>';
            return '<td class="text-center"><span class="badge bg-danger text-white fs-7 m-1">' . (isset($GLOBALS['completer']) ? $GLOBALS['completer'] : 'À compléter') . '</span></td>';
        }

        /**
         * Cellule d'évaluation manager
         */
        private function renderManagerEvaluationCell($evaluation) {
            if (!$evaluation['allocation'] || !isset($evaluation['allocation']['activeManager']) || !$evaluation['allocation']['activeManager']) return '<td class="text-center transparent-cell"></td>';
            if ($evaluation['managerExam'] && $evaluation['managerExam']['active']) return '<td class="text-center"><span class="badge bg-warning text-white fs-7 m-1">' . (isset($GLOBALS['en_cours']) ? $GLOBALS['en_cours'] : 'En cours') . '</span></td>';
            return '<td class="text-center"><span class="badge bg-danger text-white fs-7 m-1">' . (isset($GLOBALS['completer']) ? $GLOBALS['completer'] : 'À compléter') . '</span></td>';
        }
    }

    $technicianManager = new TechnicianManager();

    // ===== VARIABLES DE FILTRES ADMIN =====
    $selectedAgency = $_GET['agency'] ?? null;
    $selectedCountry = $_GET['country'] ?? null;
    $selectedLevel = $_GET['level'] ?? 'Junior';

    // ===== FONCTIONS COMMUNES ADMIN =====
    /**
     * Calcule la moyenne pondérée des scores
     */
    function calculateAverage($scores, $totals) {
        $scoreSum = array_sum($scores);
        $totalSum = array_sum($totals);
        return $totalSum === 0 ? 0 : ($scoreSum * 100 / $totalSum);
    }

    /**
     * Récupère les IDs des utilisateurs par niveau
     */
    function getUsersByLevel($users, $level = null, $brand = '', $subsidiaryFilter = null) {
        $query = [
            'active' => true,
            '$or' => [
                ['profile' => 'Technicien'],
                ['profile' => 'Manager', 'test' => true]
            ]
        ];

        if ($level !== null) {
            $query['level'] = $level;
        }

        if ($brand !== '') {
            $brandField = $level ? "brand{$level}" : null;
            if ($brandField) {
                $query[$brandField] = ['$in' => [$brand]];
            } else {
                $query['$or'][] = ['brandJunior' => ['$in' => [$brand]]];
                $query['$or'][] = ['brandSenior' => ['$in' => [$brand]]];
                $query['$or'][] = ['brandExpert' => ['$in' => [$brand]]];
            }
        }

        if ($subsidiaryFilter !== null) {
            if (is_array($subsidiaryFilter)) {
                $query['subsidiary'] = ['$in' => $subsidiaryFilter];
            } else {
                $query['subsidiary'] = $subsidiaryFilter;
            }
        }

        $ids = [];
        foreach ($users->find($query, ['projection' => ['_id' => 1]]) as $doc) {
            $ids[] = $doc->_id;
        }
        return $ids;
    }

    // ===== LOGIQUE DE FILTRAGE ADMIN =====
    $allowedSubsidiaryProfiles = ['Super Admin', 'Directeur des Opérations', 'Directeur Pièce et Service', 'Directeur Groupe'];
    $canFilterSubsidiary = in_array($_SESSION['profile'], $allowedSubsidiaryProfiles);

    $subsidiaryFilter = null;
    if (!$canFilterSubsidiary) {
        $subsidiaryFilter = $_SESSION['subsidiary'];
    } else {
        if (in_array($_SESSION['profile'], ['Directeur Groupe', 'Super Admin']) && $selectedSubsidiary === '') {
            $subsidiaryFilter = null;
        } elseif ($selectedSubsidiary !== 'all' && $selectedSubsidiary !== '') {
            $subsidiaryFilter = $selectedSubsidiary;
        } elseif ($selectedAgency !== null && $selectedAgency !== 'all') {
            $subsidiaryFilter = $selectedAgency;
        } else {
            $subsidiaryFilter = $_SESSION['subsidiary'];
        }
    }

    // ===== CALCULS PRINCIPAUX - NOUVELLE LOGIQUE ADMIN =====
    $allowedProfiles = ['Super Admin', 'Directeur Général', 'Directeur Pièce et Service', 'Directeur des Opérations', 'Admin', 'Ressource Humaine'];
    $profilesForCards = array_merge($allowedProfiles, ['Directeur Groupe']);

    // Initialisation des variables
    $techniciansByLevel = ['Junior' => [], 'Senior' => [], 'Expert' => [], 'All' => []];
    $percentageData = [];
    $QCM = [];
    $chartDataFiliale = [];

    if (in_array($_SESSION['profile'], $profilesForCards)) {
        // ===== 1. RÉCUPÉRATION DES TECHNICIENS PAR NIVEAU =====
        $techniciansByLevel['Junior'] = getUsersByLevel($users, 'Junior', $selectedBrand, $subsidiaryFilter);
        $techniciansByLevel['Senior'] = getUsersByLevel($users, 'Senior', $selectedBrand, $subsidiaryFilter);
        $techniciansByLevel['Expert'] = getUsersByLevel($users, 'Expert', $selectedBrand, $subsidiaryFilter);
        $techniciansByLevel['All'] = array_merge($techniciansByLevel['Junior'], $techniciansByLevel['Senior'], $techniciansByLevel['Expert']);

        // Bulk fetch pour optimiser
        $allTechIds = array_merge($techniciansByLevel['Junior'], $techniciansByLevel['Senior'], $techniciansByLevel['Expert']);
        $allTechIds = array_unique($allTechIds);
        $bulkData = $technicianManager->getBulkDataForStats($allTechIds);
        $allAllocs = $bulkData['allAllocs'];
        $allResults = $bulkData['allResults'];

        // ===== 2. CALCUL DES TESTS PAR NIVEAU DE TEST =====
        $levels = ['Junior' => 'Ju', 'Senior' => 'Se', 'Expert' => 'Ex'];
        $allocationData = [];
        $resultData = [];

        // Initialisation des structures
        foreach ($levels as $level => $code) {
            $allocationData[$code] = [
                'testsUser' => [],    // Techniciens ayant réussi le test
                'testsTotal' => []    // Techniciens devant passer le test
            ];
            $resultData[$code] = [
                'FacScore' => [], 'FacTotal' => [],
                'DeclaScore' => [], 'DeclaTotal' => []
            ];
        }

        // TEST JUNIOR : Tous les techniciens doivent le passer
        $allocationData['Ju']['testsTotal'] = $techniciansByLevel['All'];
        foreach ($techniciansByLevel['All'] as $tech) {
            $uid = (string)$tech;
            $factuel = $allAllocs[$uid]['Junior']['Factuel'] ?? null;
            $declaratif = $allAllocs[$uid]['Junior']['Declaratif'] ?? null;
            if ($factuel && $factuel['active'] && $declaratif && $declaratif['active'] && $declaratif['activeManager']) {
                $allocationData['Ju']['testsUser'][] = $tech;

                // Récupération des résultats
                $resultFac = $allResults[$uid]['Junior']['Factuel'] ?? null;
                if ($resultFac && isset($resultFac['score']) && isset($resultFac['total'])) {
                    $resultData['Ju']['FacScore'][] = $resultFac['score'];
                    $resultData['Ju']['FacTotal'][] = $resultFac['total'];
                }
                $resultDecla = $allResults[$uid]['Junior']['Declaratif'] ?? null;
                if ($resultDecla && isset($resultDecla['score']) && isset($resultDecla['total'])) {
                    $resultData['Ju']['DeclaScore'][] = $resultDecla['score'];
                    $resultData['Ju']['DeclaTotal'][] = $resultDecla['total'];
                }
            }
        }

        // TEST SENIOR : Seulement Senior et Expert doivent le passer
        $seniorExpert = array_merge($techniciansByLevel['Senior'], $techniciansByLevel['Expert']);
        $allocationData['Se']['testsTotal'] = $seniorExpert;
        foreach ($seniorExpert as $tech) {
            $uid = (string)$tech;
            $factuel = $allAllocs[$uid]['Senior']['Factuel'] ?? null;
            $declaratif = $allAllocs[$uid]['Senior']['Declaratif'] ?? null;
            if ($factuel && $factuel['active'] && $declaratif && $declaratif['active'] && $declaratif['activeManager']) {
                $allocationData['Se']['testsUser'][] = $tech;

                // Récupération des résultats
                $resultFac = $allResults[$uid]['Senior']['Factuel'] ?? null;
                if ($resultFac && isset($resultFac['score']) && isset($resultFac['total'])) {
                    $resultData['Se']['FacScore'][] = $resultFac['score'];
                    $resultData['Se']['FacTotal'][] = $resultFac['total'];
                }
                $resultDecla = $allResults[$uid]['Senior']['Declaratif'] ?? null;
                if ($resultDecla && isset($resultDecla['score']) && isset($resultDecla['total'])) {
                    $resultData['Se']['DeclaScore'][] = $resultDecla['score'];
                    $resultData['Se']['DeclaTotal'][] = $resultDecla['total'];
                }
            }
        }

        // TEST EXPERT : Seulement Expert doivent le passer
        $allocationData['Ex']['testsTotal'] = $techniciansByLevel['Expert'];
        foreach ($techniciansByLevel['Expert'] as $tech) {
            $uid = (string)$tech;
            $factuel = $allAllocs[$uid]['Expert']['Factuel'] ?? null;
            $declaratif = $allAllocs[$uid]['Expert']['Declaratif'] ?? null;
            if ($factuel && $factuel['active'] && $declaratif && $declaratif['active'] && $declaratif['activeManager']) {
                $allocationData['Ex']['testsUser'][] = $tech;

                // Récupération des résultats
                $resultFac = $allResults[$uid]['Expert']['Factuel'] ?? null;
                if ($resultFac && isset($resultFac['score']) && isset($resultFac['total'])) {
                    $resultData['Ex']['FacScore'][] = $resultFac['score'];
                    $resultData['Ex']['FacTotal'][] = $resultFac['total'];
                }
                $resultDecla = $allResults[$uid]['Expert']['Declaratif'] ?? null;
                if ($resultDecla && isset($resultDecla['score']) && isset($resultDecla['total'])) {
                    $resultData['Ex']['DeclaScore'][] = $resultDecla['score'];
                    $resultData['Ex']['DeclaTotal'][] = $resultDecla['total'];
                }
            }
        }

        // ===== 3. CALCUL DES POURCENTAGES DE RÉSULTATS =====
        foreach ($levels as $level => $code) {
            $percentageData[$level] = [
                'fac' => calculateAverage($resultData[$code]['FacScore'] ?? [], $resultData[$code]['FacTotal'] ?? []),
                'decla' => calculateAverage($resultData[$code]['DeclaScore'] ?? [], $resultData[$code]['DeclaTotal'] ?? [])
            ];
            $percentageData[$level]['average'] = round(($percentageData[$level]['fac'] + $percentageData[$level]['decla']) / 2);
        }

        // ===== 4. CALCUL DES STATISTIQUES QCM (NOUVELLE LOGIQUE CORRIGÉE) =====

        // Initialisation des compteurs pour chaque niveau
        $qcmCounters = [
            'Ju' => [
                'savoirs' => [],      // Allocations factuelles actives
                'savFais' => [],      // Allocations déclaratives avec activeManager
                'testsUser' => []     // Tests complétés
            ],
            'Se' => [
                'savoirs' => [],
                'savFais' => [],
                'testsUser' => []
            ],
            'Ex' => [
                'savoirs' => [],
                'savFais' => [],
                'testsUser' => []
            ]
        ];

        // === LOGIQUE CORRIGÉE : PAR NIVEAU DE TEST ===

        // Pour TEST JUNIOR (tous les techniciens)
        foreach ($techniciansByLevel['All'] as $tech) {
            $uid = (string)$tech;
            // Connaissances (Factuel)
            $factuelJu = $allAllocs[$uid]['Junior']['Factuel'] ?? null;
            if ($factuelJu && $factuelJu['active']) {
                $qcmCounters['Ju']['savoirs'][] = $tech;
            }

            // Tâches Professionnelles (Déclaratif avec activeManager)
            $declaratifJu = $allAllocs[$uid]['Junior']['Declaratif'] ?? null;
            if ($declaratifJu && $declaratifJu['active'] && $declaratifJu['activeManager']) {
                $qcmCounters['Ju']['savFais'][] = $tech;
            }

            // Tests complétés (les deux allocations)
            $factuelJu = $allAllocs[$uid]['Junior']['Factuel'] ?? null;
            $declaratifJu = $allAllocs[$uid]['Junior']['Declaratif'] ?? null;
            if ($factuelJu && $factuelJu['active'] && $declaratifJu && $declaratifJu['active'] && $declaratifJu['activeManager']) {
                $qcmCounters['Ju']['testsUser'][] = $tech;
            }
        }

        // Pour TEST SENIOR (Senior + Expert seulement)
        $seniorExpertTechs = array_merge($techniciansByLevel['Senior'], $techniciansByLevel['Expert']);
        foreach ($seniorExpertTechs as $tech) {
            $uid = (string)$tech;
            // Connaissances (Factuel)
            $factuelSe = $allAllocs[$uid]['Senior']['Factuel'] ?? null;
            if ($factuelSe && $factuelSe['active']) {
                $qcmCounters['Se']['savoirs'][] = $tech;
            }

            // Tâches Professionnelles (Déclaratif avec activeManager)
            $declaratifSe = $allAllocs[$uid]['Senior']['Declaratif'] ?? null;
            if ($declaratifSe && $declaratifSe['active'] && $declaratifSe['activeManager']) {
                $qcmCounters['Se']['savFais'][] = $tech;
            }

            // Tests complétés (les deux allocations)
            $factuelSe = $allAllocs[$uid]['Senior']['Factuel'] ?? null;
            $declaratifSe = $allAllocs[$uid]['Senior']['Declaratif'] ?? null;
            if ($factuelSe && $factuelSe['active'] && $declaratifSe && $declaratifSe['active'] && $declaratifSe['activeManager']) {
                $qcmCounters['Se']['testsUser'][] = $tech;
            }
        }

        // Pour TEST EXPERT (Expert seulement)
        foreach ($techniciansByLevel['Expert'] as $tech) {
            $uid = (string)$tech;
            // Connaissances (Factuel)
            $factuelEx = $allAllocs[$uid]['Expert']['Factuel'] ?? null;
            if ($factuelEx && $factuelEx['active']) {
                $qcmCounters['Ex']['savoirs'][] = $tech;
            }

            // Tâches Professionnelles (Déclaratif avec activeManager)
            $declaratifEx = $allAllocs[$uid]['Expert']['Declaratif'] ?? null;
            if ($declaratifEx && $declaratifEx['active'] && $declaratifEx['activeManager']) {
                $qcmCounters['Ex']['savFais'][] = $tech;
            }

            // Tests complétés (les deux allocations)
            $factuelEx = $allAllocs[$uid]['Expert']['Factuel'] ?? null;
            $declaratifEx = $allAllocs[$uid]['Expert']['Declaratif'] ?? null;
            if ($factuelEx && $factuelEx['active'] && $declaratifEx && $declaratifEx['active'] && $declaratifEx['activeManager']) {
                $qcmCounters['Ex']['testsUser'][] = $tech;
            }
        }

        // ===== CALCUL DES POURCENTAGES QCM CORRIGÉS =====

        // Dénominateurs alignés avec la logique du premier script
        $totalJunior = count($techniciansByLevel['All']);
        $totalSeniorExpert = count($techniciansByLevel['Senior']) + count($techniciansByLevel['Expert']);
        $totalExpert = count($techniciansByLevel['Expert']);

        // Pourcentages par niveau
        $QCM = [
            'Ju' => [
                completedPercentage(count($qcmCounters['Ju']['savoirs']), $totalJunior),           // Connaissances Junior
                completedPercentage(count($qcmCounters['Ju']['savFais']), $totalJunior),           // Tâches Professionnelles Junior
                completedPercentage(count($qcmCounters['Ju']['testsUser']), count($allocationData['Ju']['testsTotal'])) // Compétences Junior
            ],
            'Se' => [
                completedPercentage(count($qcmCounters['Se']['savoirs']), $totalSeniorExpert),     // Connaissances Senior
                completedPercentage(count($qcmCounters['Se']['savFais']), $totalSeniorExpert),     // Tâches Professionnelles Senior
                completedPercentage(count($qcmCounters['Se']['testsUser']), count($allocationData['Se']['testsTotal'])) // Compétences Senior
            ],
            'Ex' => [
                completedPercentage(count($qcmCounters['Ex']['savoirs']), $totalExpert),           // Connaissances Expert
                completedPercentage(count($qcmCounters['Ex']['savFais']), $totalExpert),           // Tâches Professionnelles Expert
                completedPercentage(count($qcmCounters['Ex']['testsUser']), count($allocationData['Ex']['testsTotal'])) // Compétences Expert
            ]
        ];

        // ===== CALCUL DU TOTAL CORRIGÉ (agrégation pondérée) =====

        // Calcul des poids pour chaque niveau basé sur le nombre de techniciens
        $poidsJunior = $totalJunior;
        $poidsSenior = $totalSeniorExpert;
        $poidsExpert = $totalExpert;
        $poidsTotal = $poidsJunior + $poidsSenior + $poidsExpert;

        // Pour les Connaissances et Tâches : moyenne pondérée par le nombre de techniciens
        if ($poidsTotal > 0) {
            $QCM['To'][0] = round(
                ($QCM['Ju'][0] * $poidsJunior + $QCM['Se'][0] * $poidsSenior + $QCM['Ex'][0] * $poidsExpert) / $poidsTotal
            );
            $QCM['To'][1] = round(
                ($QCM['Ju'][1] * $poidsJunior + $QCM['Se'][1] * $poidsSenior + $QCM['Ex'][1] * $poidsExpert) / $poidsTotal
            );
        } else {
            $QCM['To'][0] = 0;
            $QCM['To'][1] = 0;
        }

        // Pour les Compétences : calcul direct basé sur les totaux réels
        $totalTestsCompletes = count($allocationData['Ju']['testsUser']) +
            count($allocationData['Se']['testsUser']) +
            count($allocationData['Ex']['testsUser']);

        $totalTestsAttendus = count($allocationData['Ju']['testsTotal']) +
            count($allocationData['Se']['testsTotal']) +
            count($allocationData['Ex']['testsTotal']);

        $QCM['To'][2] = $totalTestsAttendus > 0 ? round(($totalTestsCompletes / $totalTestsAttendus) * 100) : 0;

        // ===== 5. PRÉPARATION DES DONNÉES POUR LES GRAPHIQUES =====
        $chartDataFiliale = [
            [
                'title' => 'Test Niveau Junior',
                'total' => count($allocationData['Ju']['testsTotal']),
                'completed' => count($allocationData['Ju']['testsUser']),
                'data' => [
                    count($allocationData['Ju']['testsUser']),
                    count($allocationData['Ju']['testsTotal']) - count($allocationData['Ju']['testsUser'])
                ],
                'labels' => [
                    count($allocationData['Ju']['testsUser']) . ' Tests Complétés',
                    (count($allocationData['Ju']['testsTotal']) - count($allocationData['Ju']['testsUser'])) . ' Tests à Compléter'
                ],
                'backgroundColor' => ['#4303EC', '#DCDCDC']
            ],
            [
                'title' => 'Test Niveau Senior',
                'total' => count($allocationData['Se']['testsTotal']),
                'completed' => count($allocationData['Se']['testsUser']),
                'data' => [
                    count($allocationData['Se']['testsUser']),
                    count($allocationData['Se']['testsTotal']) - count($allocationData['Se']['testsUser'])
                ],
                'labels' => [
                    count($allocationData['Se']['testsUser']) . ' Tests Complétés',
                    (count($allocationData['Se']['testsTotal']) - count($allocationData['Se']['testsUser'])) . ' Tests à Compléter'
                ],
                'backgroundColor' => ['#4303EC', '#DCDCDC']
            ],
            [
                'title' => 'Test Niveau Expert',
                'total' => count($allocationData['Ex']['testsTotal']),
                'completed' => count($allocationData['Ex']['testsUser']),
                'data' => [
                    count($allocationData['Ex']['testsUser']),
                    count($allocationData['Ex']['testsTotal']) - count($allocationData['Ex']['testsUser'])
                ],
                'labels' => [
                    count($allocationData['Ex']['testsUser']) . ' Tests Complétés',
                    (count($allocationData['Ex']['testsTotal']) - count($allocationData['Ex']['testsUser'])) . ' Tests à Compléter'
                ],
                'backgroundColor' => ['#4303EC', '#DCDCDC']
            ],
            [
                'title' => 'Total : 03 Niveaux',
                'total' => count($allocationData['Ju']['testsTotal']) + count($allocationData['Se']['testsTotal']) + count($allocationData['Ex']['testsTotal']),
                'completed' => count($allocationData['Ju']['testsUser']) + count($allocationData['Se']['testsUser']) + count($allocationData['Ex']['testsUser']),
                'data' => [
                    count($allocationData['Ju']['testsUser']) + count($allocationData['Se']['testsUser']) + count($allocationData['Ex']['testsUser']),
                    (count($allocationData['Ju']['testsTotal']) + count($allocationData['Se']['testsTotal']) + count($allocationData['Ex']['testsTotal'])) -
                    (count($allocationData['Ju']['testsUser']) + count($allocationData['Se']['testsUser']) + count($allocationData['Ex']['testsUser']))
                ],
                'labels' => [
                    (count($allocationData['Ju']['testsUser']) + count($allocationData['Se']['testsUser']) + count($allocationData['Ex']['testsUser'])) . ' Tests Complétés',
                    ((count($allocationData['Ju']['testsTotal']) + count($allocationData['Se']['testsTotal']) + count($allocationData['Ex']['testsTotal'])) -
                        (count($allocationData['Ju']['testsUser']) + count($allocationData['Se']['testsUser']) + count($allocationData['Ex']['testsUser']))) . ' Tests à Compléter'
                ],
                'backgroundColor' => ['#4303EC', '#DCDCDC']
            ]
        ];
    }

    // ===== FILTRES POUR L'INTERFACE ADMIN =====
    $baseFilter = ['active' => true, '$or' => [['profile' => 'Technicien'], ['profile' => 'Manager', 'test' => true]]];
    if ($subsidiaryFilter !== null) {
        if (is_array($subsidiaryFilter)) {
            $baseFilter['subsidiary'] = ['$in' => $subsidiaryFilter];
        } else {
            $baseFilter['subsidiary'] = $subsidiaryFilter;
        }
    }

    $brandPipeline = [
        ['$match' => $baseFilter],
        ['$project' => ['brands' => ['$setUnion' => [['$ifNull' => ['$brandJunior', []]], ['$ifNull' => ['$brandSenior', []]], ['$ifNull' => ['$brandExpert', []]]]]]],
        ['$unwind' => '$brands'],
        ['$match' => ['brands' => ['$ne' => '']]],
        ['$group' => ['_id' => '$brands']],
        ['$sort' => ['_id' => 1]],
    ];

    $uniqueBrands = array_map(function($doc) { return (string)$doc->_id; }, iterator_to_array($users->aggregate($brandPipeline)));

    /**
     * Normalise les noms de marques (corrections orthographiques)
     */
    function normalizeBrandName($brand) {
        $normalized = trim(strtoupper($brand));
        $corrections = [
            'MUTSIBUSHI' => 'MITSUBISHI',
            'SINOTRUCK' => 'SINOTRUK'
        ];
        return $corrections[$normalized] ?? $normalized;
    }

    $uniqueBrands = array_map('normalizeBrandName', $uniqueBrands);
    $uniqueBrands = array_unique($uniqueBrands);
    $uniqueBrands = array_values($uniqueBrands);

    $subsidiaryPipeline = [
        ['$match' => ['active' => true]],
        ['$group' => ['_id' => '$subsidiary']],
        ['$sort' => ['_id' => 1]],
    ];
    $uniqueSubsidiariesRaw = iterator_to_array($users->aggregate($subsidiaryPipeline));
    $uniqueSubsidiaries = array_map(function($doc) { return (string)$doc->_id; }, $uniqueSubsidiariesRaw);
    $uniqueSubsidiaries = array_filter($uniqueSubsidiaries, function($s) { return trim($s) !== ''; });
    $uniqueSubsidiaries = array_filter($uniqueSubsidiaries, function($s) { return !in_array(strtoupper($s), ['CFR', 'CFAO']); });
    sort($uniqueSubsidiaries);
    $uniqueSubsidiaries = array_unique($uniqueSubsidiaries);

    $filters = [
        [
            'key' => 'brand',
            'label' => 'Marques',
            'icon' => 'bi bi-tag',
            'iconColor' => 'text-info',
            'selected' => $selectedBrand ?: 'all',
            'value' => array_merge(['all' => 'Toutes les marques'], $uniqueBrands ?? []),
        ]
    ];

    if ($canFilterSubsidiary) {
        $filters[] = [
            'key' => 'subsidiary',
            'label' => 'Filiales',
            'icon' => 'bi bi-building',
            'iconColor' => 'text-primary',
            'selected' => $selectedSubsidiary ?: 'all',
            'value' => array_merge(['all' => 'Toutes les filiales'], $uniqueSubsidiaries ?? []),
        ];
    }

    $junior = 'Junior'; $senior = 'Senior'; $expert = 'Expert'; $subsidiary = 'Filiale';

    // Bulk data disponible pour la section résultats
    $bulkDataResults = $bulkData ?? ['allAllocs' => [], 'allResults' => []];
    $allAllocsResults = $bulkDataResults['allAllocs'];
    $allResultsResults = $bulkDataResults['allResults'];

    // ===== INTÉGRATION LOGIQUE RÉSULTATS AUX TESTS =====
    $isGroupProfile = in_array($_SESSION['profile'], ['Directeur Groupe', 'Super Admin']);
    $isSubsidiaryProfile = in_array($_SESSION['profile'], ['Directeur Général', 'Directeur Pièce et Service', 'Directeur des Opérations', 'Admin', 'Ressource Humaine']);

    $resultDataResults = [];  // Renommé pour éviter conflit
    $levels = ['Junior', 'Senior', 'Expert'];
    $countUsers = [];

    if ($isGroupProfile || $isSubsidiaryProfile) {
        // Récupération optimisée pour le groupe ou filiale
        foreach ($levels as $level) {
            $technicianIds = $isGroupProfile ? $techniciansByLevel[$level] : getUsersByLevel($users, $level, $selectedBrand, $subsidiaryFilter);

            $resultDataResults[$level] = [
                'FacScore' => [], 'FacTotal' => [],
                'DeclaScore' => [], 'DeclaTotal' => [],
                'doneTests' => [],
                'totalTechs' => count($technicianIds)
            ];

            foreach ($technicianIds as $techId) {
                $uid = (string)$techId;
                $alloFac = $allAllocsResults[$uid][$level]['Factuel'] ?? null;
                $alloDecla = $allAllocsResults[$uid][$level]['Declaratif'] ?? null;

                if ($alloFac && $alloDecla && $alloFac['active'] && $alloDecla['active'] && $alloDecla['activeManager']) {
                    $resultDataResults[$level]['doneTests'][] = $techId;

                    $resultFac = $allResultsResults[$uid][$level]['Factuel'] ?? null;
                    if ($resultFac && isset($resultFac['score']) && isset($resultFac['total'])) {
                        $resultDataResults[$level]['FacScore'][] = $resultFac['score'];
                        $resultDataResults[$level]['FacTotal'][] = $resultFac['total'];
                    }

                    $resultDecla = $allResultsResults[$uid][$level]['Declaratif'] ?? null;
                    if ($resultDecla && isset($resultDecla['score']) && isset($resultDecla['total'])) {
                        $resultDataResults[$level]['DeclaScore'][] = $resultDecla['score'];
                        $resultDataResults[$level]['DeclaTotal'][] = $resultDecla['total'];
                    }
                }
            }

            $resultDataResults[$level]['percentageFac'] = calculateAverage($resultDataResults[$level]['FacScore'], $resultDataResults[$level]['FacTotal']);
            $resultDataResults[$level]['percentageDecla'] = calculateAverage($resultDataResults[$level]['DeclaScore'], $resultDataResults[$level]['DeclaTotal']);
            $resultDataResults[$level]['averageCompetence'] = round(($resultDataResults[$level]['percentageFac'] + $resultDataResults[$level]['percentageDecla']) / 2);
        }

    }

    // ===== CALCUL DES MOYENNES GLOBALES =====
    $validAverages = array_filter(array_column($resultDataResults, 'averageCompetence'), function($v) { return $v > 0; });
    $globalAverage = !empty($validAverages) ? round(array_sum($validAverages) / count($validAverages)) : 0;

    // ===== CALCUL POUR APEX CHARTS =====
    $apexData = [];
    foreach ($levels as $level) {
        $apexData[$level] = [
            round($resultDataResults[$level]['percentageFac'] ?? 0),
            round($resultDataResults[$level]['percentageDecla'] ?? 0),
            $resultDataResults[$level]['averageCompetence'] ?? 0
        ];
    }

    // Moyenne totale
    $totalFac = 0;
    $totalDecla = 0;
    $count = 0;
    foreach ($levels as $level) {
        if ($resultDataResults[$level]['averageCompetence'] > 0) {
            $totalFac += $apexData[$level][0];
            $totalDecla += $apexData[$level][1];
            $count++;
        }
    }
    $apexData['Total'] = [
        $count > 0 ? round($totalFac / $count) : 0,
        $count > 0 ? round($totalDecla / $count) : 0,
        $globalAverage
    ];

    AuthorizationManager::requireAuthorization();

    $pageTitle = isset($list_result) ? $list_result : 'Results List' . ' | CFAO Mobility Academy';
    $mainTitleIcon = 'ki-duotone ki-chart-line fs-2 me-5';
    $mainTitle = $tableau_measure;
    $chartContainerId = 'chartTestFiliale';
    $chartDataVar = $chartDataFiliale ?? [];
    $colClassChartsVar = 'col-md-6 col-lg-3 col-xl-2.5';
    $cardsVar = [
        ['techniciansByLevel', 'Junior', 'Junior'],
        ['techniciansByLevel', 'Senior', 'Senior'],
        ['techniciansByLevel', 'Expert', 'Expert'],
        ['techniciansByLevel', 'All', $subsidiary]
    ];
    $secondaryTitleVar = '';
    $titleIconVar = '';
    $filtersVar = $filters;
    $isQCM = false;

    $tableRenderer = new TableRenderer();
    $technicianIds = $technicianManager->getTechnicians($_SESSION['profile'], $_SESSION, $subsidiaryFilter, $selectedBrand);
    $techniciansData = $technicianManager->getTechnicianDataBulk($technicianIds);

} else {
    header("Location: ../../unauthorized.php");
    exit();
}
?>
<?php include "./partials/header.php"; ?>

    <link rel="stylesheet" href="../../public/css/core/utilities.css">
    <link rel="stylesheet" href="../../public/css/measure/dashboard-admin.css">
    <title><?php echo $pageTitle; ?></title>

<?php openBackgroundContainer(); ?>
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
        <div class="container-xxl">
            <div class="row g-6 g-xl-9 mb-6 mb-xl-9">
                <?php renderTitleMain($mainTitleIcon, 3, $mainTitle); ?>

                <?php if ($isQCM): ?>
                    <?php if (AuthorizationManager::isAuthorized($_SESSION["profile"])) { ?>
                        <div class="row g-6 mb-5 justify-content-center">
                            <?php foreach ($cardsVar as $card): ?>
                                <div class="<?php echo $colClassCards; ?>">
                                    <div class="card h-auto glassmorphism">
                                        <div class="card-body text-center py-3 px-3">
                                            <div class="fs-2x fw-bold text-gray-800" data-kt-countup="true" data-kt-countup-value="<?= $card['value'] ?>">
                                            </div>
                                            <div class="fw-semibold mt-2"><?= $card['label'] ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php renderTitleSection($titleIconVar, 2, $secondaryTitleVar); ?>

                        <div id="<?php echo $chartContainerId; ?>" class="row mb-4 justify-content-center"></div>
                    <?php } ?>
                <?php else: ?>
                    <?php if (in_array($_SESSION["profile"], ['Super Admin', 'Admin', 'Ressource Humaine', 'Directeur Pièce et Service', 'Directeur des Opérations', 'Directeur Général', 'Directeur Groupe'])) { ?>

                        <!-- Overlay de chargement avec animation fluide -->
                        <div id="loadingOverlay" class="loading-overlay">
                            <div class="loading-content">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Chargement...</span>
                                </div>
                                <div class="mt-2">Chargement des données...</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="card p-3 text-center glassmorphism filter-card">
                                    <div class="d-flex justify-content-center align-items-center text-center">
                                        <?php echo renderFilterBox($filtersVar, 'kt_post', 'horizontal');?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (in_array($_SESSION["profile"], $profilesForCards)) { ?>
                            <div class="row row-cols-xxl-4 row-cols-lg-4 row-cols-md-2 g-6 mb-5">
                                <?php
                                foreach ($cardsVar as $data) {
                                    list($var, $key, $label) = $data;
                                    ?>
                                    <div class="col">
                                        <div class="card h-auto glassmorphism">
                                            <div class="card-body text-center py-3 px-3">
                                                <div class="fs-2x fw-bold text-gray-800" data-kt-countup="true" data-kt-countup-value="<?= count($$var[$key] ?? []) ?>">
                                                </div>
                                                <div class="fw-semibold mt-2"><?= $technicienss.' '.$Level.' '.$label ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>

                            <?php if (in_array($_SESSION['profile'], ['Super Admin', 'Directeur des Opérations', 'Directeur Pièce et Service', 'Admin', 'Ressource Humaine', 'Directeur Groupe'])) { ?>
                                <?php renderTitleSection('ki-duotone ki-chart-pie fs-2 me-3', 2, 'Taux de participation aux tests'); ?>
                                <div id="<?php echo $chartContainerId; ?>" class="row mb-4"></div>

                                <?php renderTitleSection('ki-duotone ki-chart-bar fs-2 me-3', 2, 'Taux de participation aux tests par type de QCM'); ?>
                                <div class="row mb-4">
                                    <?php
                                    $qcmCharts = [
                                        ['junior', $test_niveau_junior],
                                        ['senior', $test_niveau_senior],
                                        ['expert', $test_niveau_expert],
                                        ['total', $total_03_niveaux]
                                    ];
                                    foreach ($qcmCharts as $chart) {
                                        list($id, $title) = $chart;
                                        ?>
                                        <div class="col-xl-3">
                                            <div class="card glassmorphism chart-card">
                                                <div class="text-center"><h5 class="mt-2" style="margin-top: 18px !important;"><?= $title ?></h5></div>
                                                <div class="d-flex align-items-center justify-content-center flex-grow-1">
                                                    <div id="qcm_<?= $id ?>_filiale" class="chart-container"></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>

                                <?php renderTitleSection('ki-duotone ki-users fs-2 me-3', 2, 'Participation aux tests par technicien'); ?>

                                <div class="row">
                                    <div class="col-12">
                                        <div class="table-responsive glassmorphism">
                                            <table class="table align-middle table-bordered fs-6 gy-5 dataTable no-footer" id="kt_customers_table">
                                                <thead>
                                                <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">
                                                    <?php $colspan = (($_SESSION['profile'] == "Super Admin" || $_SESSION['profile'] == "Directeur Groupe") ? "3" : ($_SESSION['profile'] == "Admin" ? "3" : "2")); ?>
                                                    <th class="w-100 sorting text-center" colspan="<?php echo $colspan ?>"><?php echo isset($technicienss) ? $technicienss : 'Technicians'; ?></th>
                                                    <th class="w-100 sorting text-center" colspan="3">TEST <?php echo isset($Level) ? $Level : 'Level'; ?> <?php echo isset($junior) ? $junior : 'Junior'; ?></th>
                                                    <th class="w-100 sorting text-center" colspan="3">TEST <?php echo isset($Level) ? $Level : 'Level'; ?> <?php echo isset($senior) ? $senior : 'Senior'; ?></th>
                                                    <th class="w-100 sorting text-center" colspan="3">TEST <?php echo isset($Level) ? $Level : 'Level'; ?> <?php echo isset($expert) ? $expert : 'Expert'; ?></th>
                                                </tr>
                                                <tr><?php echo $tableRenderer->renderTableHeaders($_SESSION['profile']); ?></tr>
                                                </thead>
                                                <tbody class="fw-semibold text-gray-600" id="table">
                                                <?php foreach ($techniciansData as $techData) echo $tableRenderer->renderTableRow($techData, $_SESSION['profile']); ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } ?>

                        <div class="my-5"></div>

                        <!-- ===== SECTION RÉSULTATS DE LA MESURE DES COMPÉTENCES ===== -->
                        <?php if ($isGroupProfile || $isSubsidiaryProfile) { ?>
                            <?php renderTitleMain('ki-duotone ki-users fs-2 me-3', 2, 'RESULTATS AUX TESTS'); ?>
                            <!-- Graphiques Compétences Moyennes -->
                            <div id="chartMoyen" class="row"></div>

                            <div class="legend-container mt-2">
                                <fieldset class="legend-fieldset">
                                    <div class="legend-item">
                                        <canvas id='c1' width="75" height="37.5"></canvas>
                                        <h4 class="legend-text"><?php echo $result_entre_0_60 ?? '0-60%' ?></h4>
                                    </div>
                                    <div class="legend-item">
                                        <canvas id='c2' width="75" height="37.5"></canvas>
                                        <h4 class="legend-text"><?php echo $result_entre_60_80 ?? '60-80%' ?></h4>
                                    </div>
                                    <div class="legend-item">
                                        <canvas id='c3' width="75" height="37.5"></canvas>
                                        <h4 class="legend-text"><?php echo $result_entre_80_100 ?? '80-100%' ?></h4>
                                    </div>
                                </fieldset>
                            </div>

                            <!-- Section Résultats par Type -->
                            <?php renderTitleSection('ki-duotone ki-users fs-2 me-3', 2, 'Résultats aux tests par type de tests'); ?>

                            <div class="row">
                                <div class="col-xl-3">
                                    <div class="card result-card">
                                        <div class="text-center"><h5 class="mt-2" style="margin-top: 18px !important;">Résultats Niveau Junior</h5></div>
                                        <div class="d-flex align-items-center justify-content-center flex-grow-1">
                                            <div id="result_junior" class="result-chart"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3">
                                    <div class="card result-card">
                                        <div class="text-center"><h5 class="mt-2" style="margin-top: 18px !important;">Résultats Niveau Senior</h5></div>
                                        <div class="d-flex align-items-center justify-content-center flex-grow-1">
                                            <div id="result_senior" class="result-chart"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3">
                                    <div class="card result-card">
                                        <div class="text-center"><h5 class="mt-2" style="margin-top: 18px !important;">Résultats Niveau Expert</h5></div>
                                        <div class="d-flex align-items-center justify-content-center flex-grow-1">
                                            <div id="result_expert" class="result-chart"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3">
                                    <div class="card result-card">
                                        <div class="text-center"><h5 class="mt-2" style="margin-top: 18px !important;">Total : 03 Niveaux</h5></div>
                                        <div class="d-flex align-items-center justify-content-center flex-grow-1">
                                            <div id="result_total" class="result-chart"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                        <!-- ===== FIN SECTION RÉSULTATS ===== -->
                    <?php } ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php closeBackgroundContainer(); ?>

    <script src="../../public/js/core/event-manager.js" defer></script>
    <script src="../../public/js/core/ajax-manager.js" defer></script>
    <script src="../../public/js/core/ui-manager.js" defer></script>
    <script src="../../public/js/core/animations.js"></script>
    <script src="../../public/js/applyFilters.js" defer></script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php if ($isQCM): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chartData = <?= json_encode($chartDataVar) ?>;
            const colClassCharts = '<?= $colClassChartsVar ?>';

            const container = document.getElementById('<?php echo $chartContainerId; ?>');
            chartData.forEach((data, index) => {
                const completedPercentage = Math.round(data.completed);
                const cardHtml = `
                <div class="${colClassCharts} mb-4">
                    <div class="glass-effect card h-100">
                        <div class="card-body d-flex justify-content-center text-center flex-column p-4">
                            <h5><?= $qcm_connaissance_realise ?> ${completedPercentage}%</h5>
                            <canvas id="doughnutChart${index}" width="200" height="200"></canvas>
                            <h5 class="mt-2">${data.title}</h5>
                        </div>
                    </div>
                </div>
            `;
                container.insertAdjacentHTML('beforeend', cardHtml);

                new Chart(document.getElementById(`doughnutChart${index}`).getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            data: data.data,
                            backgroundColor: data.backgroundColor,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        cutout: '60%',
                        plugins: {
                            legend: { position: 'top' },
                            datalabels: {
                                formatter: (value, ctx) => {
                                    let sum = ctx.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                    let percentage = Math.round((value / sum) * 100);
                                    return percentage + '%';
                                },
                                color: '#fff',
                                display: true,
                                anchor: 'center',
                                align: 'center',
                                font: { size: 16, weight: 'bold' }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(tooltipItem) {
                                        const value = tooltipItem.raw || 0;
                                        const dataset = tooltipItem.dataset.data;
                                        const sum = dataset.reduce((a, b) => a + b, 0);
                                        const percentage = Math.round((value / sum) * 100);
                                        return `<?= $pourcentages ?> : ${percentage}%`;
                                    }
                                }
                            }
                        }
                    }
                });
            });
        });
    </script>
<?php else: ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.50.0/apexcharts.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Styles CSS pour l'animation de chargement fluide -->
    <style>
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.75);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
        }

        .loading-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .loading-content {
            text-align: center;
            animation: fadeInScale 0.5s ease-out;
        }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
            animation: spinner-border 0.75s linear infinite;
        }

        @keyframes spinner-border {
            to { transform: rotate(360deg); }
        }
    </style>

    <script>
        // ===== DONNÉES QCM CORRIGÉES =====
        const qcmPct = <?= json_encode($QCM ?? []) ?>;
        const dataQcm = {
            Junior: qcmPct.Ju || [0, 0, 0],
            Senior: qcmPct.Se || [0, 0, 0],
            Expert: qcmPct.Ex || [0, 0, 0],
            Total: qcmPct.To || [0, 0, 0]
        };

        const chartDataFiliale = <?= json_encode($chartDataVar ?? []) ?>;

        // Fonction pour afficher/masquer l'overlay de chargement
        function toggleLoading(shouldShow) {
            const overlay = document.getElementById('loadingOverlay');
            if (shouldShow) {
                overlay.classList.add('show');
            } else {
                overlay.classList.remove('show');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // ===== GRAPHIQUES DE PARTICIPATION AUX TESTS =====
            const containerFiliale = document.getElementById('<?php echo $chartContainerId; ?>');
            if (containerFiliale && chartDataFiliale.length > 0) {
                chartDataFiliale.forEach(function(data, index) {
                    const completedPercentage = data.total == 0 ? 0 : Math.round((data.completed / data.total) * 100);
                    const cardHtml = `
                    <div class="<?php echo $colClassChartsVar; ?> mb-4">
                        <div class="card h-100 glassmorphism">
                            <div class="card-body d-flex justify-content-center text-center flex-column p-4">
                                <h5><?= $total_tests_realiser ?>: ${data.total}</h5>
                                <h5><?= $pourcentage_complete ?>: ${completedPercentage}%</h5>
                                <canvas id="doughnutChart${index}" width="150" height="150"></canvas>
                                <h5 class="mt-2">${data.title}</h5>
                            </div>
                        </div>
                    </div>
                `;
                    containerFiliale.insertAdjacentHTML('beforeend', cardHtml);

                    const ctx = document.getElementById(`doughnutChart${index}`).getContext('2d');
                    const totalValue = data.data[0] + data.data[1];
                    let chartDataF;
                    if (totalValue === 0) {
                        chartDataF = {
                            labels: ['<?= $aucun_test_prevu ?>'],
                            datasets: [{
                                data: [1],
                                backgroundColor: ['#D3D3D3'],
                                borderWidth: 0
                            }]
                        };
                    } else {
                        chartDataF = {
                            labels: data.labels,
                            datasets: [{
                                data: data.data,
                                backgroundColor: data.backgroundColor,
                                borderWidth: 1
                            }]
                        };
                    }

                    new Chart(ctx, {
                        type: 'doughnut',
                        data: chartDataF,
                        options: {
                            cutout: '60%',
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'top',
                                    labels: {
                                        generateLabels: function(c) {
                                            const d = c.data;
                                            return d.labels.map(function(l, i) {
                                                return {
                                                    text: `${l}`,
                                                    fillStyle: d.datasets[0].backgroundColor[i],
                                                    strokeStyle: d.datasets[0].backgroundColor[i],
                                                    lineWidth: d.datasets[0].borderWidth,
                                                    hidden: false
                                                };
                                            });
                                        }
                                    }
                                },
                                tooltip: {
                                    enabled: totalValue > 0,
                                    callbacks: {
                                        label: function(t) {
                                            if (totalValue === 0) return '<?= $aucun_test_prevu ?>';
                                            const v = t.raw;
                                            const tot = t.chart.data.datasets[0].data.reduce(function(a, b) { return a + b; }, 0);
                                            const p = Math.round((v / tot) * 100);
                                            return `Nombre: ${v}, Pourcentage: ${p}%`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                });
            }

            // ===== GRAPHIQUES QCM CORRIGÉS =====
            const labelQF = ['Connaissances', 'Tâches Professionnelles', 'Compétences'];

            /**
             * Rendu d'un graphique QCM avec ApexCharts
             */
            function renderQCMChart(chartId, data, title) {
                const container = document.querySelector("#" + chartId);
                if (!container) {
                    console.warn(`Container #${chartId} not found`);
                    return;
                }

                try {
                    const chart = new ApexCharts(container, {
                        chart: {
                            type: 'bar',
                            height: 350,
                            width: 300,
                            toolbar: { show: false }
                        },
                        series: [{
                            name: "Taux de participation",
                            data: data
                        }],
                        xaxis: {
                            categories: labelQF,
                            labels: {
                                rotate: -45,
                                rotateAlways: true,
                                style: {
                                    fontSize: '12px',
                                    fontWeight: 600
                                }
                            }
                        },
                        plotOptions: {
                            bar: {
                                distributed: true,
                                horizontal: false,
                                borderRadius: 4,
                                columnWidth: '60%'
                            }
                        },
                        colors: ['#82CDFF', '#039FFE', '#4303EC'],
                        dataLabels: {
                            enabled: true,
                            formatter: function(val) {
                                return val + '%';
                            },
                            style: {
                                colors: ['#fff'],
                                fontSize: '12px',
                                fontWeight: 'bold'
                            }
                        },
                        tooltip: {
                            enabled: true,
                            y: {
                                formatter: function(val) {
                                    return val + '%';
                                }
                            }
                        },
                        yaxis: {
                            max: 100,
                            labels: {
                                formatter: function(val) {
                                    return val + '%';
                                }
                            }
                        },
                        title: {
                            text: title,
                            align: 'center',
                            style: {
                                fontSize: '14px',
                                fontWeight: 'bold'
                            }
                        }
                    });

                    chart.render();
                } catch (error) {
                    console.error(`Error rendering chart ${chartId}:`, error);
                }
            }

            // Rendu des graphiques QCM avec données corrigées
            if (dataQcm.Junior) renderQCMChart('qcm_junior_filiale', dataQcm.Junior, '<?= $test_niveau_junior ?>');
            if (dataQcm.Senior) renderQCMChart('qcm_senior_filiale', dataQcm.Senior, '<?= $test_niveau_senior ?>');
            if (dataQcm.Expert) renderQCMChart('qcm_expert_filiale', dataQcm.Expert, '<?= $test_niveau_expert ?>');
            if (dataQcm.Total) renderQCMChart('qcm_total_filiale', dataQcm.Total, '<?= $total_03_niveaux ?>');

            // ===== SCRIPT POUR LA SECTION RÉSULTATS =====
            // Enregistrer le plugin datalabels
            if (typeof Chart !== 'undefined' && typeof ChartDataLabels !== 'undefined') {
                Chart.register(ChartDataLabels);
            }

            // Légendes de couleurs
            ['c1', 'c2', 'c3'].forEach((id, i) => {
                const canvas = document.getElementById(id);
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    const colors = ['#f9945e', '#f9f75e', '#6cf95e'];
                    ctx.fillStyle = colors[i];
                    ctx.fillRect(0, 0, 75, 37.5);
                }
            });

            /**
             * Couleur basée sur le pourcentage de complétion
             */
            function getColorForCompletion(percentage) {
                if (percentage >= 80) return '#6CF95D';
                if (percentage >= 60) return '#FAF75A';
                return '#FB9258';
            }

            /**
             * Fond pour les graphiques doughnut
             */
            function getBackgroundColor(percentage) {
                return percentage === 0 ? ['#FFFFFF', '#DCDCDC'] : [getColorForCompletion(percentage), '#DCDCDC'];
            }

            /**
             * Couleur pour les barres Apex
             */
            function determineColor(score) {
                if (score < 60) return '#F9945E';
                if (score < 80) return '#F8F75F';
                return '#63FE5A';
            }

            // Données pour les graphiques Doughnut
            const chartDataMoyen = [
                <?php foreach ($levels as $level): ?>
                {
                    title: 'Résultat <?= count($resultDataResults[$level]['doneTests']) ?> / <?= $resultDataResults[$level]['totalTechs'] ?> Techniciens Niveau <?= $level ?>',
                    data: [<?= $resultDataResults[$level]['averageCompetence'] ?>, <?= 100 - $resultDataResults[$level]['averageCompetence'] ?>],
                    labels: ['<?= $resultDataResults[$level]['averageCompetence'] ?>% Compétences Acquises', '<?= 100 - $resultDataResults[$level]['averageCompetence'] ?>% Compétences à acquérir'],
                    backgroundColor: getBackgroundColor(<?= $resultDataResults[$level]['averageCompetence'] ?>)
                },
                <?php endforeach; ?>
                {
                    title: 'Résultat Total : 03 Niveaux',
                    data: [<?= $globalAverage ?>, <?= 100 - $globalAverage ?>],
                    labels: ['<?= $globalAverage ?>% Compétences Acquises', '<?= 100 - $globalAverage ?>% Compétences à acquérir'],
                    backgroundColor: getBackgroundColor(<?= $globalAverage ?>)
                }
            ];

            // Créer les graphiques Doughnut
            const containerMoyen = document.getElementById('chartMoyen');
            if (containerMoyen) {
                chartDataMoyen.forEach((data, index) => {
                    const cardHtml = `
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card h-100 glass-effect-chart depth-effect">
                            <div class="card-body d-flex justify-content-center text-center flex-column p-4">
                                <canvas id="doughnutChartResult${index}" width="200" height="200"></canvas>
                                <h5 class="mt-2">${data.title}</h5>
                            </div>
                        </div>
                    </div>
                `;
                    containerMoyen.insertAdjacentHTML('beforeend', cardHtml);

                    const ctx = document.getElementById(`doughnutChartResult${index}`);
                    if (ctx) {
                        new Chart(ctx.getContext('2d'), {
                            type: 'doughnut',
                            data: {
                                labels: data.labels,
                                datasets: [{
                                    data: data.data,
                                    backgroundColor: data.backgroundColor,
                                    borderWidth: 0
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: true,
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        labels: {
                                            font: { size: 11 }
                                        }
                                    },
                                    datalabels: {
                                        formatter: (value) => Math.round(value) + '%',
                                        color: '#fff',
                                        font: { size: 16, weight: 'bold' }
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                return context.label + ': ' + context.parsed + '%';
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    }
                });
            }

            // Graphiques ApexCharts
            /**
             * Rendu d'un graphique Apex bar
             */
            function renderApexChart(chartId, data, labels, colors) {
                const chartContainer = document.querySelector("#" + chartId);
                if (!chartContainer) {
                    console.error("Chart container " + chartId + " not found.");
                    return;
                }

                const chartOptions = {
                    chart: {
                        type: 'bar',
                        height: 350,
                        toolbar: { show: false }
                    },
                    series: [{
                        name: "Taux d'acquisition des compétences",
                        data: data
                    }],
                    xaxis: {
                        categories: labels,
                        labels: {
                            style: { fontSize: '12px' }
                        }
                    },
                    plotOptions: {
                        bar: {
                            distributed: true,
                            horizontal: false,
                            borderRadius: 4,
                            columnWidth: '60%'
                        }
                    },
                    colors: colors,
                    dataLabels: {
                        enabled: true,
                        formatter: (value) => value + '%',
                        style: {
                            colors: ['#333'],
                            fontSize: '14px',
                            fontWeight: 'bold'
                        }
                    },
                    yaxis: {
                        max: 100,
                        labels: {
                            formatter: (value) => value + '%'
                        }
                    },
                    legend: { show: false },
                    tooltip: {
                        y: {
                            formatter: (value) => value + '%'
                        }
                    }
                };

                new ApexCharts(chartContainer, chartOptions).render();
            }

            // Créer les graphiques ApexCharts
            const labelsResult = ['Connaissances', 'Tâches Professionnelles', 'Compétence'];

            const apexCharts = {
                'junior': <?= json_encode($apexData['Junior']) ?>,
                'senior': <?= json_encode($apexData['Senior']) ?>,
                'expert': <?= json_encode($apexData['Expert']) ?>,
                'total': <?= json_encode($apexData['Total']) ?>
            };

            Object.keys(apexCharts).forEach(level => {
                const colors = apexCharts[level].map(score => determineColor(score));
                renderApexChart('result_' + level, apexCharts[level], labelsResult, colors);
            });

            // ===== GESTION DU TABLEAU (SEARCH) =====
            /**
             * Initialise la recherche dans le tableau
             */
            function initializeSearch() {
                const searchInput = document.getElementById('mySearchInput');
                if (searchInput) {
                    searchInput.addEventListener('input', function() { filterTable(this.value); });
                    searchInput.addEventListener('searchCleared', function() { filterTable(''); });
                }
            }

            /**
             * Filtre les lignes du tableau par terme de recherche
             */
            function filterTable(searchTerm) {
                const table = document.getElementById('kt_customers_table');
                const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
                for (let i = 0; i < rows.length; i++) {
                    const row = rows[i];
                    const cells = row.getElementsByTagName('td');
                    let found = false;
                    for (let j = 0; j < cells.length; j++) {
                        if (cells[j].textContent.toLowerCase().includes(searchTerm.toLowerCase())) {
                            found = true;
                            break;
                        }
                    }
                    row.style.display = found ? '' : 'none';
                }
            }

            initializeSearch();
        });

        // ===== GESTION DES FILTRES =====
        $(document).ready(function() {
            $('.ajax-filter').on('change', function() {
                // Affichage du chargement lors du changement de filtre
                toggleLoading(true);

                const filters = {};
                $('.ajax-filter').each(function() {
                    const key = $(this).data('filter-key');
                    const value = $(this).val();
                    if (value && value !== 'all') {
                        filters[key] = value;
                    }
                });

                const queryString = new URLSearchParams(filters).toString();
                const newUrl = window.location.pathname + (queryString ? '?' + queryString : '');
                window.history.pushState({}, '', newUrl);
                location.reload();
            });
        });
    </script>
<?php endif; ?>
<?php include "./components/partials/footer.php"; ?>