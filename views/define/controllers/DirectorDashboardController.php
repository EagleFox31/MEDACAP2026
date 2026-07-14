<?php
// Fichier : views/define/controllers/DirectorDashboardController.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/BaseController.php";
require_once __DIR__ . "/FilterSystem/Filters.php";
require_once __DIR__ . "/FilterSystem/FilterSystem.php";
require_once __DIR__ . "/FilterSystem/RefactoredFilterController.php";
require_once __DIR__ . "/../services/AnalyticsService.php";
require_once "../../vendor/autoload.php";


/**
 * Contrôleur du tableau de bord Directeur (DG, Groupe, OPS, etc.)
 * - Filtrage & listes via RefactoredFilterController
 * - KPIs & agrégats via AnalyticsService
 */
class DirectorDashboardController extends BaseController
{
    /** @var \MongoDB\Collection */
    private $userCollection;
    private $trainingCollection;
    private $allocationCollection;
    private $applicationCollection;
    private $validationCollection;

    /** @var string */
    private $profile;
    private $subsidiary;

    /** Services */
    /** @var RefactoredFilterController */
    private $filters;
    /** @var AnalyticsService */
    private $analytics;

    /**
     * Constructeur
     * @param \MongoDB\Database $academy
     */
    public function __construct($academy)
    {
        parent::__construct($academy);

        // Autorisations (profils "directeurs")
        $this->checkAuthorization([
            'Directeur Général',
            'Directeur Groupe',
            'Directeur Pièce et Service',
            'Directeur des Opérations',
            'Super Admin',
            'Admin'
        ]);

        // Collections Mongo
        $this->userCollection        = $academy->users;
        $this->trainingCollection    = $academy->trainings;
        $this->allocationCollection  = $academy->allocations;
        $this->applicationCollection = $academy->applications;
        $this->validationCollection  = $academy->validations;

        // Contexte session
        $this->profile    = $_SESSION['profile']    ?? '';
        $this->subsidiary = $_SESSION['subsidiary'] ?? '';

        // Services
        $this->filters   = new RefactoredFilterController($academy);
        $this->analytics = new AnalyticsService($academy);
    }

    /**
     * Rendu du tableau de bord directeur
     * @param array $filters
     */
    public function renderDashboard($filters)
    {
        try {
            // Validation/sanitization d'entrée
            $this->validateAndSanitizeFilters($filters);

            // Préparation data vue (try-catch granulaire interne)
            try {
                $data = $this->prepareViewData($filters);

                // Évite les boucles dans le header
                $GLOBALS['currentPage'] = 'dashboard';

                // Vue
                $this->renderView('director/dashboard.php', $data);

            } catch (MongoDB\Exception\InvalidArgumentException $mongoError) {
                error_log("ERREUR MONGODB (InvalidArgumentException) prepareViewData: " . $mongoError->getMessage() . " | Filtres: " . json_encode($filters));
                $this->loadWithDefaultFilters($filters, 'database_query_error');

            } catch (MongoDB\Exception\RuntimeException $mongoRuntimeError) {
                error_log("ERREUR MONGODB (Runtime) prepareViewData: " . $mongoRuntimeError->getMessage() . " | Filtres: " . json_encode($filters));
                $this->loadWithDefaultFilters($filters, 'database_connection_error');

            } catch (TypeError $typeError) {
                error_log("ERREUR TYPE prepareViewData: " . $typeError->getMessage() . " | Filtres: " . json_encode($filters));
                $this->loadWithDefaultFilters($filters, 'data_type_error');

            } catch (InvalidArgumentException $argError) {
                error_log("ERREUR ARGUMENT prepareViewData: " . $argError->getMessage() . " | Filtres: " . json_encode($filters));
                $this->displayUserFriendlyError('invalid_argument', $argError->getMessage(), $filters);
                return;

            } catch (\Throwable $innerError) {
                error_log("ERREUR GÉNÉRALE prepareViewData [" . get_class($innerError) . "]: " . $innerError->getMessage() . " @ " . $innerError->getFile() . ":" . $innerError->getLine());
                $this->displayUserFriendlyError('general_data_error', $innerError->getMessage(), $filters);
                return;
            }

        } catch (\Throwable $e) {
            error_log("CRITIQUE DirectorDashboardController::renderDashboard [" . get_class($e) . "]: " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine());
            $this->loadWithDefaultFilters($filters, 'critical_error');
        }
    }

    /**
     * Validation & nettoyage des filtres
     * - Convertit technicianId string -> array pour éviter "$in needs an array"
     * @param array &$filters
     */
    private function validateAndSanitizeFilters(&$filters)
    {
        if (isset($filters['technicianId']) && !is_array($filters['technicianId']) && $filters['technicianId'] !== 'all') {
            $originalTechnicianId = $filters['technicianId'];

            if (empty($originalTechnicianId) || (!is_string($originalTechnicianId) && !is_numeric($originalTechnicianId))) {
                throw new InvalidArgumentException("technicianId invalide: chaîne non vide attendue. Reçu: " . var_export($originalTechnicianId, true));
            }

            error_log("VALIDATION - technicianId string -> tableau: '" . $originalTechnicianId . "' -> ['" . $originalTechnicianId . "']");
            $filters['technicianId'] = [$originalTechnicianId];
        }

        // Autres filtres critiques => chaînes
        $requiredStringFilters = ['subsidiary', 'agency', 'managerId', 'level', 'brand'];
        foreach ($requiredStringFilters as $filterKey) {
            if (isset($filters[$filterKey]) && !is_string($filters[$filterKey])) {
                throw new InvalidArgumentException("Filtre '$filterKey' invalide: chaîne attendue. Reçu: " . var_export($filters[$filterKey], true));
            }
        }
    }

    /**
     * Affiche une erreur utilisateur puis tente un rendu de secours.
     */
    private function displayUserFriendlyError($errorType, $detailMessage, $filters)
    {
        error_log("displayUserFriendlyError [$errorType]: $detailMessage");
        $recoveryType = ($errorType === 'invalid_argument') ? 'invalid_filter_error' : 'general_data_error';
        $this->loadWithDefaultFilters($filters, $recoveryType);
    }

    /**
     * Mode récupération : charge le dashboard avec des filtres par défaut
     * @param array $originalFilters
     * @param string $errorType
     */
    private function loadWithDefaultFilters($originalFilters, $errorType = 'unknown_error')
    {
        $defaultFilters = [
            'subsidiary'   => $_SESSION['subsidiary'] ?? 'all',
            'agency'       => 'all',
            'managerId'    => 'all',
            'level'        => 'all',
            'brand'        => 'all',
            'technicianId' => 'all'
        ];

        $errorMessages = [
            'database_query_error' => [
                'title' => 'Problème de requête de données',
                'message' => 'Les filtres sélectionnés ont généré une requête invalide. Le tableau de bord a été réinitialisé.',
                'solution' => 'Vérifiez vos sélections de filtres ou contactez le support.',
                'type' => 'warning'
            ],
            'database_connection_error' => [
                'title' => 'Connexion à la base interrompue',
                'message' => 'La connexion au serveur de données a été interrompue.',
                'solution' => 'Actualisez la page ou contactez l\'administrateur système.',
                'type' => 'danger'
            ],
            'data_type_error' => [
                'title' => 'Format de données incorrect',
                'message' => 'Certains filtres contenaient des données avec un format incorrect.',
                'solution' => 'Les filtres ont été corrigés automatiquement.',
                'type' => 'info'
            ],
            'invalid_filter_error' => [
                'title' => 'Paramètres de filtrage non valides',
                'message' => 'Un ou plusieurs filtres ne correspondent pas aux données disponibles.',
                'solution' => 'Sélectionnez de nouveaux critères de filtrage.',
                'type' => 'warning'
            ],
            'general_data_error' => [
                'title' => 'Erreur de traitement des données',
                'message' => 'Une erreur inattendue est survenue.',
                'solution' => 'Essayez de modifier les filtres ou contactez le support.',
                'type' => 'warning'
            ],
            'critical_error' => [
                'title' => 'Erreur système critique',
                'message' => 'Le système a rencontré une erreur majeure.',
                'solution' => 'Contactez l\'administrateur système.',
                'type' => 'danger'
            ],
        ];

        try {
            error_log("RECOVERY - Type: $errorType | Filtres: " . json_encode($originalFilters) . " | Filtres défaut: " . json_encode($defaultFilters));

            $minimalData = $this->createMinimalDataStructure($defaultFilters);

            try {
                // Enrichissement minimal depuis les services
                $this->enrichMinimalDataWithFilterController($minimalData, $defaultFilters);

            } catch (MongoDB\Exception\RuntimeException $fcMongoError) {
                error_log("RECOVERY - Analytics/Filters Mongo error: " . $fcMongoError->getMessage());
            } catch (\Throwable $fcError) {
                error_log("RECOVERY - Analytics/Filters error: " . $fcError->getMessage());
            }

            $errorInfo = $errorMessages[$errorType] ?? $errorMessages['critical_error'];

            echo "<div class='alert alert-{$errorInfo['type']} alert-dismissible fade show' role='alert'>";
            echo "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>";
            echo "<h5 class='alert-heading'><i class='fas fa-exclamation-triangle me-2'></i>{$errorInfo['title']}</h5>";
            echo "<p class='mb-2'><strong>Problème :</strong> {$errorInfo['message']}</p>";
            echo "<hr>";
            echo "<p class='mb-0'><strong>Solution :</strong> {$errorInfo['solution']}</p>";
            if (!empty($originalFilters)) {
                echo "<details class='mt-2'><summary class='text-muted small'>Détails</summary>";
                echo "<pre class='small mt-1 text-muted'>" . htmlspecialchars(json_encode($originalFilters, JSON_PRETTY_PRINT)) . "</pre>";
                echo "</details>";
            }
            echo "</div>";

            $GLOBALS['currentPage'] = 'dashboard';
            $this->renderView('director/dashboard.php', $minimalData);

        } catch (\Throwable $lastResortError) {
            error_log("FATAL loadWithDefaultFilters: " . $lastResortError->getMessage());

            echo "<div class='alert alert-danger alert-dismissible fade show' role='alert'>";
            echo "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>";
            echo "<h5 class='alert-heading'><i class='fas fa-times-circle me-2'></i>Erreur système majeure</h5>";
            echo "<p class='mb-2'><strong>Problème :</strong> Le tableau de bord ne peut pas être chargé.</p>";
            echo "<hr>";
            echo "<p class='mb-2'><strong>Actions :</strong></p>";
            echo "<ul class='mb-2'><li>Actualisez la page</li><li>Réessayez plus tard</li><li>Contactez le support</li></ul>";
            echo "<small class='text-muted'>Code : DASHBOARD_FATAL_" . date('YmdHis') . "</small>";
            echo "</div>";

            echo "<div class='card'><div class='card-body text-center'>";
            echo "<h2 class='text-muted'>Tableau de Bord Directeur</h2>";
            echo "<div class='my-4'><i class='fas fa-tools fa-3x text-muted mb-3'></i>";
            echo "<p class='lead'>Service temporairement indisponible</p>";
            echo "<p class='text-muted'>Nos équipes travaillent dessus.</p></div></div></div>";
        }
    }

    /**
     * Structure minimale de données pour rendu de secours
     * @param array $filters
     * @return array
     */
    private function createMinimalDataStructure($filters)
    {
        // Stats tests par niveau (si la vue les lit)
        $GLOBALS['statsTests'] = [
            'Junior' => ['done' => 0, 'total' => 0],
            'Senior' => ['done' => 0, 'total' => 0],
            'Expert' => ['done' => 0, 'total' => 0],
            'Total'  => ['done' => 0, 'total' => 0]
        ];

        return [
            'tableau'                  => "Tableau de Bord Directeur",
            'globalStats'              => [
                'totalTechnicians'       => 0,
                'juniorCount'            => 0,
                'seniorCount'            => 0,
                'expertCount'            => 0,
                'measuredCount'          => 0,
                'withTrainingCount'      => 0,
                'validatedTrainingCount' => 0,
                'trainingDays'           => 0
            ],
            'subsidiaryStats'          => [],
            'brandStats'               => [],
            'trainingStats'            => [
                'totalTrainings'         => 0,
                'recommendedTrainings'   => 0,
                'validatedTrainings'     => 0,
                'trainingDays'           => 0
            ],
            'trainingValidationStats'  => [
                'totalValidations'       => 0,
                'pendingValidations'     => 0,
                'approvedValidations'    => 0,
                'rejectedValidations'    => 0
            ],
            'subsidiaries'             => [],
            'brands'                   => [],
            'agencies'                 => [],
            'managers'                 => [],
            'technicians'              => [],
            'technicianSummary'        => [],
            'brandScores'              => [],
            'filters'                  => $filters,
            'translations'             => $this->translations,
            'brandLogos'               => $GLOBALS['brandLogos'] ?? [],
            // compat avec la vue (elle peut appeler des méthodes du contrôleur de filtres)
            'filterController'         => $this->filters
        ];
    }

    /**
     * Enrichit les données minimales via les services (Filters + Analytics)
     * @param array &$minimalData
     * @param array $filters
     */
    private function enrichMinimalDataWithFilterController(&$minimalData, $filters)
    {
        // Listes via RefactoredFilterController
        try {
            $minimalData['subsidiaries'] = $this->filters->getSubsidiaries();
            error_log("RECOVERY - Filiales: " . count($minimalData['subsidiaries']));
        } catch (\Throwable $e) {
            error_log("RECOVERY - getSubsidiaries error: " . $e->getMessage());
        }

        // KPIs via AnalyticsService
        try {
            $minimalData['globalStats'] = $this->analytics->getGlobalStats($filters);
            error_log("RECOVERY - GlobalStats OK");
        } catch (\Throwable $e) {
            error_log("RECOVERY - getGlobalStats error: " . $e->getMessage());
        }

        try {
            $minimalData['trainingStats'] = $this->analytics->getTrainingStats($filters);
            error_log("RECOVERY - TrainingStats OK");
        } catch (\Throwable $e) {
            error_log("RECOVERY - getTrainingStats error: " . $e->getMessage());
        }
    }

    /**
     * Prépare les données pour la vue (exact mapping requis par dashboard.php)
     * @param array $filters
     * @return array
     * @throws \Throwable
     */
    private function prepareViewData($filters)
    {
        try {
            error_log("DirectorDashboardController::prepareViewData - start " . json_encode($filters));

            $filterSubsidiary = $filters['subsidiary'] ?? 'all';
            $filterBrand      = $filters['brand']      ?? 'all';
            $filterLevel      = $filters['level']      ?? 'all';

            // Conserver l’ID technicien sélectionné pour l’UI
            if (isset($filters['technicianId']) && $filters['technicianId'] !== 'all') {
                if (is_array($filters['technicianId'])) {
                    $filters['selectedTechnicianId'] = $filters['technicianId'][0];
                    error_log("prepareViewData - technicianId[]: " . $filters['technicianId'][0]);
                } else {
                    $filters['selectedTechnicianId'] = $filters['technicianId'];
                    error_log("prepareViewData - technicianId: " . $filters['technicianId']);
                }
            }

            // ===== Listes / options (via RefactoredFilterController) =====
            try {
                $subsidiaries = $this->filters->getSubsidiaries();
                error_log("prepareViewData - Subsidiaries: " . count($subsidiaries));
            } catch (\Throwable $e) {
                throw new RuntimeException("Impossible de récupérer la liste des filiales: " . $e->getMessage(), 0, $e);
            }

            try {
                $brands = $this->filters->getBrands($filters);
                error_log("prepareViewData - Brands: " . count($brands));
            } catch (\Throwable $e) {
                throw new RuntimeException("Impossible de récupérer la liste des marques: " . $e->getMessage(), 0, $e);
            }

            try {
                $managers = $this->analytics->getManagers(
                    $filters['subsidiary'] ?? 'all',
                    $filters['agency']     ?? 'all'
                );
                error_log("prepareViewData - Managers: " . count($managers));
            } catch (\Throwable $e) {
                throw new RuntimeException("Impossible de récupérer la liste des managers: " . $e->getMessage(), 0, $e);
            }

            try {
                $agencies = $this->filters->getAgencies($filterSubsidiary);
                error_log("prepareViewData - Agencies: " . count($agencies));
            } catch (\Throwable $e) {
                throw new RuntimeException("Impossible de récupérer la liste des agences: " . $e->getMessage(), 0, $e);
            }

            try {
                $technicians = $this->filters->getTechnicians($filters);
                error_log("prepareViewData - Technicians: " . count($technicians));
            } catch (\Throwable $e) {
                throw new RuntimeException("Impossible de récupérer la liste des techniciens: " . $e->getMessage(), 0, $e);
            }

            // ===== KPIs / Stats (via AnalyticsService) =====
            try {
                $globalStats = $this->analytics->getGlobalStats($filters);
                error_log("prepareViewData - GlobalStats OK");
            } catch (\Throwable $e) {
                throw new RuntimeException("Impossible de calculer les statistiques globales: " . $e->getMessage(), 0, $e);
            }

            try {
                $trainingStats = $this->analytics->getTrainingStats($filters);
                error_log("prepareViewData - TrainingStats OK");
            } catch (\Throwable $e) {
                throw new RuntimeException("Impossible de calculer les statistiques de formation: " . $e->getMessage(), 0, $e);
            }

            try {
                $trainingValidationStats = $this->analytics->getTrainingValidationStats($filters);
                $trainingValidationStats['totalValidations'] = array_sum($trainingValidationStats['validationsCounts'] ?? []);
                error_log("prepareViewData - TrainingValidationStats OK");
            } catch (\Throwable $e) {
                throw new RuntimeException("Impossible de calculer les statistiques de validation: " . $e->getMessage(), 0, $e);
            }

            try {
                $technicianSummary = $this->analytics->getTechnicianSummary($filters);
                error_log("prepareViewData - TechnicianSummary: " . count($technicianSummary));
            } catch (\Throwable $e) {
                throw new RuntimeException("Impossible de générer le résumé des techniciens: " . $e->getMessage(), 0, $e);
            }

            try {
                $brandScores = $this->analytics->getBrandScores($filters);
                error_log("prepareViewData - BrandScores: " . count($brandScores));
            } catch (\Throwable $e) {
                throw new RuntimeException("Impossible de calculer les scores par marque: " . $e->getMessage(), 0, $e);
            }

            // ===== Spécifiques Directeur (agrégations par filiale & marque) =====
            try {
                $subsidiaryStats = $this->getSubsidiaryStatistics($filterSubsidiary);
                error_log("prepareViewData - SubsidiaryStats: " . count($subsidiaryStats));
            } catch (\Throwable $e) {
                throw new RuntimeException("Impossible de calculer les statistiques par filiale: " . $e->getMessage(), 0, $e);
            }

            try {
                $brandStats = $this->getBrandStatistics($filterSubsidiary, $filterBrand);
                error_log("prepareViewData - BrandStats: " . count($brandStats));
            } catch (\Throwable $e) {
                throw new RuntimeException("Impossible de calculer les statistiques par marque: " . $e->getMessage(), 0, $e);
            }

            // Harmonisation champs attendus par la vue
            if (!isset($trainingStats['recommendedTrainings'])) {
                $trainingStats['recommendedTrainings'] = !empty($trainingValidationStats['trainingsCounts'])
                    ? array_sum($trainingValidationStats['trainingsCounts'])
                    : 0;
            }
            if (!isset($trainingStats['validatedTrainings'])) {
                $trainingStats['validatedTrainings'] = !empty($trainingValidationStats['validationsCounts'])
                    ? array_sum($trainingValidationStats['validationsCounts'])
                    : 0;
            }
            if (!isset($trainingStats['totalTrainings'])) {
                $trainingStats['totalTrainings'] = $trainingStats['recommendedTrainings'];
            }

            // Payload final pour la vue
            $data = [
                'tableau'                 => "Tableau de Bord Directeur",
                'globalStats'             => $globalStats,
                'subsidiaryStats'         => $subsidiaryStats,
                'brandStats'              => $brandStats,
                'trainingStats'           => $trainingStats,
                'trainingValidationStats' => $trainingValidationStats,
                'subsidiaries'            => $subsidiaries,
                'brands'                  => $brands,
                'agencies'                => $agencies,
                'managers'                => $managers,
                'technicians'             => $technicians,
                'technicianSummary'       => $technicianSummary,
                'brandScores'             => $brandScores,
                'filters'                 => $filters,
                'translations'            => $this->translations,
                'brandLogos'              => $GLOBALS['brandLogos'] ?? [],
                // Compat : la vue peut appeler des méthodes de filtrage
                'filterController'        => $this->filters
            ];

            error_log("DirectorDashboardController::prepareViewData - SUCCESS");
            return $data;

        } catch (\Throwable $e) {
            error_log("prepareViewData ERROR [" . get_class($e) . "]: " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine());
            throw $e;
        }
    }

    // =========================================================
    //       BLOCS SPÉCIFIQUES DIRECTEUR (agrégés UI)
    // =========================================================

    /**
     * Stats par filiale (toutes ou une seule)
     * @param string $selectedSubsidiary
     * @return array
     */
    private function getSubsidiaryStatistics($selectedSubsidiary)
    {
        $stats = [];

        if ($selectedSubsidiary !== 'all') {
            $stats[$selectedSubsidiary] = $this->getStatsForSubsidiary($selectedSubsidiary);
            return $stats;
        }

        // Liste filiales via contrôleur de filtres
        $subsidiaries = $this->filters->getSubsidiaries();

        foreach ($subsidiaries as $subsidiary) {
            try {
                $stats[$subsidiary] = $this->getStatsForSubsidiary($subsidiary);
            } catch (\Throwable $e) {
                error_log("getSubsidiaryStatistics - erreur '$subsidiary': " . $e->getMessage());
                $stats[$subsidiary] = [
                    'totalTechnicians' => 0,
                    'juniorCount'      => 0,
                    'seniorCount'      => 0,
                    'expertCount'      => 0
                ];
            }
        }

        return $stats;
    }

    /**
     * Stats pour une filiale spécifique (via LevelStats)
     * @param string $subsidiary
     * @return array
     */
    private function getStatsForSubsidiary($subsidiary)
    {
        try {
            $filters = ['subsidiary' => $subsidiary];
            $levelStats = $this->filters->getLevelStats($filters);

            return [
                'totalTechnicians' => (int)$levelStats['juniorCount'] + (int)$levelStats['seniorCount'] + (int)$levelStats['expertCount'],
                'juniorCount'      => (int)$levelStats['juniorCount'],
                'seniorCount'      => (int)$levelStats['seniorCount'],
                'expertCount'      => (int)$levelStats['expertCount']
            ];

        } catch (\Throwable $e) {
            error_log("getStatsForSubsidiary ('$subsidiary') error: " . $e->getMessage());
            throw new RuntimeException("Impossible de récupérer les statistiques de niveau pour la filiale '$subsidiary': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Stats par marque (soit une spécifique, soit toutes de la filiale)
     * @param string $subsidiary
     * @param string $brand
     * @return array
     */
    private function getBrandStatistics($subsidiary, $brand)
    {
        if ($brand !== 'all') {
            return [$brand => $this->getStatsForBrand($brand, $subsidiary)];
        }

        $stats = [];
        try {
            $filters = ['subsidiary' => $subsidiary];
            $brands  = $this->filters->getBrands($filters);

            foreach ($brands as $brandName) {
                try {
                    $stats[$brandName] = $this->getStatsForBrand($brandName, $subsidiary);
                } catch (\Throwable $e) {
                    error_log("getBrandStatistics - erreur brand '$brandName': " . $e->getMessage());
                    $stats[$brandName] = [
                        'juniorCount'  => 0,
                        'seniorCount'  => 0,
                        'expertCount'  => 0,
                        'totalCount'   => 0,
                        'averageScore' => 0
                    ];
                }
            }
        } catch (\Throwable $e) {
            throw new RuntimeException("Impossible de récupérer la liste des marques pour '$subsidiary': " . $e->getMessage(), 0, $e);
        }

        return $stats;
    }

    /**
     * Stats pour une marque donnée dans une filiale :
     * - levelStats via RefactoredFilterController
     * - averageScore via AnalyticsService::getBrandScores
     * @param string $brand
     * @param string $subsidiary
     * @return array
     */
    private function getStatsForBrand($brand, $subsidiary)
    {
        try {
            $filters = [
                'brand'      => $brand,
                'subsidiary' => $subsidiary
            ];

            // Niveaux (counts)
            $levelStats = $this->filters->getLevelStats($filters);

            // Score moyen (on extrait la marque ciblée)
            $brandScores  = $this->analytics->getBrandScores($filters);
            $averageScore = 0;
            foreach ($brandScores as $scoreData) {
                if (isset($scoreData['x']) && $scoreData['x'] === $brand) {
                    $averageScore = (int)$scoreData['y'];
                    break;
                }
            }

            return [
                'juniorCount'  => (int)$levelStats['juniorCount'],
                'seniorCount'  => (int)$levelStats['seniorCount'],
                'expertCount'  => (int)$levelStats['expertCount'],
                'totalCount'   => (int)$levelStats['juniorCount'] + (int)$levelStats['seniorCount'] + (int)$levelStats['expertCount'],
                'averageScore' => $averageScore
            ];

        } catch (\Throwable $e) {
            error_log("getStatsForBrand ('$brand' / '$subsidiary') error: " . $e->getMessage());
            throw new RuntimeException("Impossible de récupérer les statistiques pour la marque '$brand': " . $e->getMessage(), 0, $e);
        }
    }
}
