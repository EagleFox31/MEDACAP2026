<?php
session_start();
include_once "../language.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {

    require_once "../../vendor/autoload.php";

    // Create connection
    $conn = new MongoDB\Client("mongodb://localhost:27017");

    // Récupérer l'ID du technicien depuis l'URL
    $technicianId = isset($_GET['id']) ? $_GET['id'] : null;

    if (!$technicianId) {
        echo "Technicien non spécifié.";
        exit();
    }

    // Connexion à MongoDB
    $academy = $conn->academy;

    // Récupérer les informations du technicien
    $technician = $academy->users->findOne(['_id' => new MongoDB\BSON\ObjectId($technicianId)]);

    if (!$technician) {
        echo "Technicien non trouvé.";
        exit();
    }


    // Récupérer le niveau sélectionné (par défaut le niveau du technicien)
    $selectedLevel = isset($_GET['level']) ? $_GET['level'] : $technician['level'];

    // Déterminer les niveaux disponibles pour le technicien
    $availableLevels = [];
    if ($technician['level'] == 'Junior') {
        $availableLevels = ['Junior'];
    } elseif ($technician['level'] == 'Senior') {
        $availableLevels = ['Junior', 'Senior'];
    } elseif ($technician['level'] == 'Expert') {
        $availableLevels = ['Junior', 'Senior', 'Expert'];
    }

    // Vérifier que le niveau sélectionné est disponible pour le technicien
    if (!in_array($selectedLevel, $availableLevels)) {
        $selectedLevel = $technician['level'];
    }

    // Définition des fonctions manquantes
    function getScoresByTechnicianAndLevel($academy, $technicianId, $level) {
        $resultsCollection = $academy->results;

        // Préparer les critères de requête communs 
        $commonCriteria = [
            'user' => new MongoDB\BSON\ObjectId($technicianId),
            'level' => $level,
            'active' => true
        ];

        // Récupérer les résultats 'Factuel'
        $factuelCriteria = array_merge($commonCriteria, ['type' => 'Factuel']);
        $factuelCursor = $resultsCollection->find($factuelCriteria);

        // Récupérer les résultats 'Declaratif' (pour Technicien et Manager)
        $declaratifCriteria = array_merge($commonCriteria, ['type' => 'Declaratif']);
        $declaratifCursor = $resultsCollection->find($declaratifCriteria);
        $scores = [];

        // Traiter les résultats 'Factuel'
        foreach ($factuelCursor as $result) {
            $speciality = isset($result['speciality']) ? $result['speciality'] : null;

            if (!$speciality) continue; // Ignorer si 'speciality' est absent

            $score = isset($result['score']) ? $result['score'] : 0;
            $total = isset($result['total']) ? $result['total'] : 0;
            $percentage = ($total > 0) ? ($score / $total) * 100 : 0;

            if (!isset($scores[$speciality])) {
                $scores[$speciality] = [];
            }

            $scores[$speciality]['Factuel'] = $percentage;
        }

        // Traiter les résultats 'Declaratif'
        foreach ($declaratifCursor as $result) {
            $speciality = isset($result['speciality']) ? $result['speciality'] : null;
            $role = isset($result['typeR']) ? $result['typeR'] : null; // Récupérer le rôle (Technicien ou Manager)
            $score = isset($result['score']) ? $result['score'] : 0;
            $total = isset($result['total']) ? $result['total'] : 0;
            $percentage = ($total > 0) ? ($score / $total) * 100 : 0;
    
            if (!isset($scores[$speciality])) {
                $scores[$speciality] = [];
            }
    
            // Stocker le score pour le rôle
            if ($role === 'Technicien' || $role === 'Manager') {
                $scores[$speciality]['Declaratif'][$role] = $percentage;
            }
        }
        
            // Calculer la moyenne pour Declaratif si nécessaire
        foreach ($scores as $speciality => $scoreData) {
            if (isset($scoreData['Declaratif']['Technicien']) && isset($scoreData['Declaratif']['Manager'])) {
                $technicienScore = $scoreData['Declaratif']['Technicien'];
                $managerScore = $scoreData['Declaratif']['Manager'];
                $average = ($technicienScore + $managerScore) / 2;

                // Remplacer la structure par la moyenne
                $scores[$speciality]['Declaratif'] = $average;
            } elseif (isset($scoreData['Declaratif']['Technicien'])) {
                // Si seulement Technicien
                $scores[$speciality]['Declaratif'] = $scoreData['Declaratif']['Technicien'];
            } elseif (isset($scoreData['Declaratif']['Manager'])) {
                // Si seulement Manager
                $scores[$speciality]['Declaratif'] = $scoreData['Declaratif']['Manager'];
            } else {
                // Aucun score Declaratif
                $scores[$speciality]['Declaratif'] = null;
            }
        }

        // Retourner uniquement les groupes ayant des scores
        return array_filter($scores, function($score) {
            return isset($score['Factuel']) || isset($score['Declaratif']); // Ne conserver que les groupes avec des résultats
        });

    }


    function getValidBrandsByLevel($technician, $level) {
        // Construct the field name for the given level
        $brandField = 'brand' . ucfirst($level); // E.g., 'brandJunior', 'brandSenior'
    
        // Check if the brand field exists
        if (isset($technician[$brandField])) {
            $brands = $technician[$brandField];
    
            // Convert BSONArray to PHP array if necessary
            if ($brands instanceof MongoDB\Model\BSONArray) {
                $brands = $brands->getArrayCopy();
            }
    
            // Ensure it's an array and filter out invalid values
            if (is_array($brands)) {
                $validBrands = array_filter($brands, function($brand) {
                    return is_string($brand) && trim($brand) !== ''; // Remove empty strings
                });
    
                return array_values($validBrands); // Re-index the array
            }
        }
    
        // Return an empty array if no valid brands exist
        return [];
    }


    
    function getRecommendedTrainings($academy, $technician, $level, $scores) {
        $trainingsCollection = $academy->trainings;

        $recommendations = [];

        foreach ($scores as $speciality => $scoreData) {
            $taskScore = isset($scoreData['Declaratif']) ? $scoreData['Declaratif'] : 0;
            $knowledgeScore = isset($scoreData['Factuel']) ? $scoreData['Factuel'] : 0;

            // Déterminer les types d'accompagnement
            $typesAccompagnement = determineAccompagnement($taskScore, $knowledgeScore);

            if (empty($typesAccompagnement)) {
                continue; // Aucun type d'accompagnement applicable
            }

            $validBrands = getValidBrandsByLevel($technician, $level);
            $validBrandsJson = json_encode($validBrands);
            // var_dump($validBrands);
            // Requête pour récupérer les formations correspondantes
            if (!empty($validBrands)) {
                // Proceed with the query only if there are valid brands
                $query = [
                    'brand' => ['$in' => $validBrands],
                    'speciality' => $speciality, // Replace with your condition
                    'level' => $level,           // Replace with your level
                    'type' => ['$in' => $typesAccompagnement], // Replace with your type array
                    'active' => true
                ];
            
                try {
                    $cursor = $academy->trainings->find($query);
                    // Process the cursor results here
                } catch (Exception $e) {
                    echo "Error executing MongoDB query: " . $e->getMessage();
                    continue; // Continue to the next iteration
                }
            } else {
                // Log or handle cases where no valid brands exist
                echo "No valid brands for the level: $level. Skipping query.";
                continue;
            }

            foreach ($cursor as $training) {
                // Utiliser le code de la formation comme clé pour éliminer les doublons
                $trainingCode = strtolower(trim($training['code'])); // Trim et lowercase pour comparaison
                $recommendations[$trainingCode] = $training;
            }
        }

        // Retourner les formations recommandées sous forme de tableau indexé
        return array_values($recommendations);
    }


    function determineAccompagnement($taskScore, $knowledgeScore) {
        // Correspondances pour les accompagnements
        $accompagnementMatrix = [
            'low' => [
                'low' => ['Présentiel', 'Distanciel', 'E-learning', 'Coaching', 'Mentoring'],
                'mid' => ['Présentiel', 'E-learning', 'Coaching', 'Mentoring'],
                'high' => ['Présentiel', 'Coaching', 'Mentoring'],
            ],
            'mid' => [
                'low' => ['Présentiel', 'Distanciel', 'E-learning', 'Coaching'],
                'mid' => ['Présentiel', 'E-learning', 'Coaching'],
                'high' => ['Présentiel', 'Coaching'],
            ],
            'high' => [
                'low' => ['Distanciel', 'E-learning'],
                'mid' => ['E-learning'],
                'high' => [],
            ],
        ];
    
        // Fonction pour catégoriser les scores
        $categorizeScore = function ($score) {
            if ($score <= 60) return 'low';
            if ($score <= 80) return 'mid';
            return 'high';
        };
    
        // Catégoriser les scores
        $taskCategory = $categorizeScore($taskScore);
        $knowledgeCategory = $categorizeScore($knowledgeScore);
    
        // Retourner l'accompagnement correspondant
        return $accompagnementMatrix[$taskCategory][$knowledgeCategory] ?? [];
    }


    // Récupérer les scores du technicien pour le niveau sélectionné
    $scores = getScoresByTechnicianAndLevel($academy, $technicianId, $selectedLevel);

    // Récupérer les formations recommandées pour le technicien et le niveau sélectionné
    $recommendations = getRecommendedTrainings($academy, $technician, $selectedLevel, $scores);

    ?>
<?php include_once "partials/header.php"; ?>
<!--begin::Title-->
<title><?php echo $list_training ?> | CFAO Mobility Academy</title>
<!--end::Title-->
<!-- Utilisation de Bootstrap 3.4.1 -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
<style>
    /* Styles personnalisés pour le tableau */
    table, th, td {
        border: 1px solid #dee2e6; /* Bordures grises douces */
    }
    .table-bordered {
        border: 1px solid #dee2e6;
    }
    .table-bordered > thead > tr > th,
    .table-bordered > tbody > tr > th,
    .table-bordered > tfoot > tr > th,
    .table-bordered > thead > tr > td,
    .table-bordered > tbody > tr > td,
    .table-bordered > tfoot > tr > td {
        border: 1px solid #dee2e6;
    }
    .table-hover tbody tr:hover {
        background-color: #f5f5f5; /* Effet de survol */
    }
    .table-striped tbody tr:nth-of-type(odd) {
        background-color: #f9f9f9; /* Bandes alternées */
    }
    .badge-danger {
        background-color: #dc3545;
        color: #fff;
    }
    .badge-success {
        background-color: #28a745;
        color: #fff;
    }
    .badge {
        display: inline-block;
        padding: 0.25em 0.4em;
        font-size: 75%;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 0.25rem;
        cursor: pointer;
    }
    .hour-display {
        cursor: pointer;
        color: white;
        /* text-decoration: underline; */ /* Supprimé ou commenté */
    }

    .hour-display:hover {
        color: red; /* Couleur plus sombre au survol pour indiquer l'interactivité */
    }
    .dropdown-menu {
        min-width: 60px;
    }
    .badge-update {
        cursor: pointer;
    }
    .center-text {
        text-align: center;
    }

    .tooltip-inner {
        font-size: 14px;
        color: #ffffff;
        background-color: #444444;
        padding: 8px 12px;
        border-radius: 6px;
    }

    .tooltip.top .tooltip-arrow {
        border-top-color: #444444;
    }

    .tooltip.right .tooltip-arrow {
        border-right-color: #444444;
    }

    .tooltip.bottom .tooltip-arrow {
        border-bottom-color: #444444;
    }

    .tooltip.left .tooltip-arrow {
        border-left-color: #444444;
    }

    table {
        width: 100%;
        border-collapse: collapse; /* Pour éviter les doubles bordures */
    }

    /* Styles supplémentaires pour un aspect moderne */
    th {
        background-color: #e6e6e6;
        text-align: center;
    }

    td {
        vertical-align: middle;
        text-align: center;
    }

    .group-title {
        background-color: #e6e6e6;
        font-weight: bold;
    }

    .table-secondary {
        background-color: #f8f9fa;
    }
</style>
<!--end::Title-->
<!--begin::Body-->
<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content"
    data-select2-id="select2-data-kt_content">
    <!--begin::Toolbar-->
    <div class="toolbar" id="kt_toolbar">
        <div class=" container-fluid  d-flex flex-stack flex-wrap flex-sm-nowrap">
            <!--begin::Info-->
            <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                <!--begin::Title-->
                <h1 class="text-dark fw-bold my-1 fs-2">
                    <?php echo $list_training ?> </h1>
                <!--end::Title-->
                <div class="card-title">
                    <!--begin::Search-->
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5"><span class="path1"></span><span
                                class="path2"></span></i>
                        <input type="text" id="search" class="form-control form-control-solid w-250px ps-12"
                            placeholder="<?php echo $recherche?>">
                    </div>
                    <!--end::Search-->
                </div>
            </div>
            <!--end::Info-->
        </div>
    </div>
    <!--end::Toolbar-->
    <div class="container mt-4 text-center">
        <h1 class="text-center mb-4"><?php echo $list_training ?> de <?php echo htmlspecialchars($technician['firstName'] . ' ' . $technician['lastName']); ?></h1>

        <!-- Filtre par niveau -->
        <form method="get" action="" class="mb-3 d-inline-block">
            <input type="hidden" name="id" value="<?php echo $technicianId; ?>">
            <div class="form-group">
                <label for="level" class="form-label"><?php echo $select_level ?></label>
                <select name="level" id="level" class="form-control" style="width: 250px;" onchange="this.form.submit()">
                    <?php foreach ($availableLevels as $level): ?>
                        <option value="<?php echo $level; ?>" <?php echo ($level == $selectedLevel) ? 'selected' : ''; ?>>
                            <?php echo $level; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post" data-select2-id="select2-data-kt_post">
        <!--begin::Container-->
        <div class=" container-xxl " data-select2-id="select2-data-194-27hh">
            <!--begin::Card-->
            <div class="card">
                <!--begin::Card body-->
                <div class="card-body pt-0">
                    <!--begin::Table-->
                    <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
                        <div class="table-responsive">
                            <table aria-describedby=""
                                class="table table-bordered table-hover table-striped align-middle fs-6 gy-5 dataTable no-footer"
                                id="kt_customers_table">
                                <thead>
                                    <tr class="text-center text-black fw-bold fs-7 text-uppercase gs-0">
                                    
                                        <th class="min-w-125px sorting text-center align-middle" tabindex="0" aria-controls="kt_customers_table"
                                            rowspan="3"
                                            aria-label="Payment Method: activate to sort column ascending"
                                            style="width: 126.516px;"><?php echo $groupes_fonctionnels ?>
                                        </th>
                                        <th class="min-w-125px sorting text-center align-middle" tabindex="0" aria-controls="kt_customers_table"
                                             rowspan="3"
                                            aria-label="Customer Name: activate to sort column ascending"
                                            style="width: 125px;"><?php echo $result ?>
                                        </th>
                                        <?php

                                            // Récupérer les marques du technicien pour le niveau sélectionné
                                            $validBrands = getValidBrandsByLevel($technician, $selectedLevel);

                                            if (empty($validBrands)) {
                                                echo "No valid brands available.";
                                                return;
                                            }


                                            // Récupérer les types de formation
                                            $trainingTypes = ['Présentiel', 'Distanciel', 'E-learning', 'Coaching', 'Mentoring'];
                                            // Récupérer les formations par marque et par type
                                            $trainingsByBrandAndType = [];

                                            foreach ($validBrands as $brand) {
                                                foreach ($trainingTypes as $type) {
                                                    // Récupérer les formations pour chaque combinaison marque/type
                                                    $trainings = $academy->trainings->find([
                                                        'brand' => $brand,
                                                        'level' => $selectedLevel,
                                                        'type' => $type,
                                                        'active' => true
                                                    ])->toArray();
                    
                                                    if (!empty($trainings)) {
                                                        $trainingsByBrandAndType[$type][$brand] = $trainings;
                                                    }
                                                }
                                            }

                                            

                                            // Afficher les en-têtes de colonnes (Types de formation)
                                            foreach ($trainingTypes as $type) {
                                                if (isset($trainingsByBrandAndType[$type])) {
                                                    // Calculer le nombre total de formations pour ce type
                                                    $totalTrainings = 0;
                                                    foreach ($validBrands as $brand) {
                                                        if (isset($trainingsByBrandAndType[$type][$brand])) {
                                                            $totalTrainings += count($trainingsByBrandAndType[$type][$brand]);
                                                        } else {
                                                            // Ajouter 1 pour 'Bientôt' si aucune formation
                                                            $totalTrainings += 1;
                                                        }
                                                    }
                                                    echo '<th class="min-w-125px sorting text-center align-middle" tabindex="0" aria-controls="kt_customers_table"
                                                            
                                                            colspan="' . $totalTrainings . '">' . htmlspecialchars($type) . '</th>';
                                                } else {
                                                    // Si aucun training de ce type, colspan = nombre de marques (pour afficher 'Bientôt')
                                                    echo '<th class="min-w-125px sorting text-center align-middle" tabindex="0" aria-controls="kt_customers_table"
                                                            
                                                            colspan="' . count($validBrands) . '">' . htmlspecialchars($type) . '</th>';
                                                }
                                            }
                                            ?>
                                        </tr>
                                        <tr>
                                            <?php
                                            // Afficher les marques avec colspan égal au nombre de formations ou 1 pour 'Bientôt'
                                            foreach ($trainingTypes as $type) {
                                                foreach ($validBrands as $brand) {
                                                    if (isset($trainingsByBrandAndType[$type][$brand])) {
                                                        $colspan = count($trainingsByBrandAndType[$type][$brand]);
                                                        echo '<th class="min-w-125px sorting text-center align-middle" tabindex="0" aria-controls="kt_customers_table"
                                                           
                                                            colspan= "' . $colspan . '">' . htmlspecialchars($brand) . '</th>';
                                                    } else {
                                                        // Pas de formation, colspan =1 pour 'Bientôt'
                                                        echo '<th class="min-w-125px sorting text-center align-middle" tabindex="0" aria-controls="kt_customers_table"
                                                            
                                                            colspan="1">' . htmlspecialchars($brand) . '</th>';
                                                    }
                                                }
                                            }
                                            ?>
                                        </tr>
                                        <tr>
                                            <?php
                                            // Afficher les codes des formations ou 'Bientôt'
                                            foreach ($trainingTypes as $type) {
                                                foreach ($validBrands as $brand) {
                                                    if (isset($trainingsByBrandAndType[$type][$brand])) {
                                                        foreach ($trainingsByBrandAndType[$type][$brand] as $training) {
                                                            // Convertir $training['speciality'] en tableau
                                                            if ($training['speciality'] instanceof MongoDB\Model\BSONArray) {
                                                                $specialityArray = $training['speciality']->getArrayCopy();
                                                            } else {
                                                                $specialityArray = (array)$training['speciality'];
                                                            }

                                                            // Vérifier si le groupe fonctionnel est couvert par la formation
                                                            // Ici, le groupe n'est pas défini car c'est dans le corps du tableau
                                                            // On doit simplement afficher le code de la formation

                                                            echo '<th class="min-w-125px sorting text-center align-middle">' . htmlspecialchars($training['code']) . '</th>';
                                                        }
                                                    } else {
                                                        // Pas de formation, afficher 'Bientôt'
                                                        echo '<th class="min-w-125px sorting text-center align-middle">Bientôt</th>';
                                                    }
                                                }
                                            }
                                            ?>
                                        </tr>
                                    
                                </thead>
                                <tbody class="fw-semibold text-gray-600" id="table">
                                    <?php
                                        function getBootstrapClass($pourcentage) {
                                            if ($pourcentage <= 59) {
                                                return 'text-danger'; 
                                            } elseif ($pourcentage <= 79) {
                                                return 'text-warning'; 
                                            } else {
                                                return 'text-success';
                                            }
                                        }
                                    // Liste des groupes fonctionnels
                                    $functionalGroupsByLevel = [
                                        'Junior' => [
                                            'Moteur Diesel', 'Moteur Essence', 'Moteur Thermique', 'Moteur Electrique',
                                            'Boite de Vitesse', 'Boite de Vitesse Mécanique', 'Boite de Vitesse Automatique',
                                            'Boite de Vitesse à Variation Continue', 'Boite de Transfert', 'Pont', 'Reducteur',
                                            'Arbre de Transmission', 'Demi Arbre de Roue', 'Direction', 'Freinage',
                                            'Freinage Hydraulique', 'Freinage Electromagnétique', 'Freinage Pneumatique',
                                            'Suspension à Lame', 'Suspension Ressort', 'Suspension Pneumatique', 'Suspension',
                                            'Hydraulique', 'Electricité et Electronique', 'Climatisation', 'Assistance à la Conduite',
                                            'Transversale'
                                        ],
                                        'Senior' => [
                                            'Moteur Diesel', 'Moteur Essence', 'Moteur Thermique', 'Moteur Electrique',
                                            'Boite de Vitesse', 'Boite de Vitesse Mécanique', 'Boite de Vitesse Automatique',
                                            'Boite de Vitesse à Variation Continue', 'Boite de Transfert', 'Pont', 'Reducteur',
                                            'Arbre de Transmission', 'Demi Arbre de Roue', 'Direction', 'Freinage',
                                            'Freinage Hydraulique', 'Freinage Electromagnétique', 'Freinage Pneumatique',
                                            'Suspension à Lame', 'Suspension Ressort', 'Suspension Pneumatique', 'Suspension',
                                            'Hydraulique', 'Electricité et Electronique', 'Climatisation', 'Assistance à la Conduite',
                                            'Transversale', 'Réseaux de Communication'
                                        ],
                                        'Expert' => [
                                            'Moteur Thermique', 'Moteur Electrique', 'Boite de Vitesse Mécanique',
                                            'Boite de Vitesse Automatique', 'Direction', 'Freinage', 'Suspension',
                                            'Hydraulique', 'Electricité et Electronique', 'Climatisation', 'Réseaux de Communication'
                                        ]
                                    ];
                                    
                                    // Récupérer le groupe fonctionnel sélectionné par niveau
                                    $functionalGroups = isset($functionalGroupsByLevel[$selectedLevel]) 
                                    ? $functionalGroupsByLevel[$selectedLevel] 
                                    : [];

                                    // Récupérer les groupes fonctionnels avec des résultats
                                    $functionalGroups = array_keys($scores); // Les clés de $scores contiennent uniquement les groupes évalués

                                    // Trier le tableau par ordre alphabétique
                                    sort($functionalGroups);

                                    // Fonction pour obtenir les groupes non supportés pour une marque
                                    function getNonSupportedGroups($brandName) {
                                        // Les données enregistrées dans la fonction
                                        $data = [
                                            "RENAULT TRUCK" => [
                                                "Count" => 7,
                                                "NonSupportedGroups" => [
                                                    "Moteur Essence",
                                                    "Moteur Electrique",
                                                    "Boite de Vitesse à Variation Continue",
                                                    "Boite de Vitesse Automatique",
                                                    "Freinage Hydraulique",
                                                    "Freinage Electromagnétique",
                                                    "Suspension Ressort"
                                                ]
                                            ],
                                            "SINOTRUK" => [
                                                "Count" => 8,
                                                "NonSupportedGroups" => [
                                                    "Moteur Essence",
                                                    "Moteur Electrique",
                                                    "Boite de Vitesse à Variation Continue",
                                                    "Boite de Vitesse Automatique",
                                                    "Boite de Transfert",
                                                    "Freinage Hydraulique",
                                                    "Freinage Electromagnétique",
                                                    "Suspension Ressort",
                                                    "Suspension Pneumatique"
                                                ]
                                            ],
                                            "MERCEDES TRUCK" => [
                                                "Count" => 7,
                                                "NonSupportedGroups" => [
                                                    "Moteur Essence",
                                                    "Moteur Electrique",
                                                    "Boite de Vitesse à Variation Continue",
                                                    "Boite de Vitesse Automatique",
                                                    "Freinage Hydraulique",
                                                    "Freinage Electromagnétique",
                                                    "Suspension Ressort"
                                                ]
                                            ],
                                            "FUSO" => [
                                                "Count" => 10,
                                                "NonSupportedGroups" => [
                                                    "Moteur Essence",
                                                    "Moteur Electrique",
                                                    "Boite de Vitesse à Variation Continue",
                                                    "Boite de Vitesse Automatique",
                                                    "Boite de Transfert",
                                                    "Freinage Hydraulique",
                                                    "Freinage Electromagnétique",
                                                    "Suspension Ressort",
                                                    "Suspension Pneumatique",
                                                    "Hydraulique"
                                                ]
                                            ],
                                            "HINO" => [
                                                "Count" => 10,
                                                "NonSupportedGroups" => [
                                                    "Moteur Essence",
                                                    "Moteur Electrique",
                                                    "Boite de Vitesse à Variation Continue",
                                                    "Boite de Vitesse Automatique",
                                                    "Boite de Transfert",
                                                    "Freinage Hydraulique",
                                                    "Freinage Electromagnétique",
                                                    "Suspension Ressort",
                                                    "Suspension Pneumatique",
                                                    "Hydraulique"
                                                ]
                                            ],
                                            "KING LONG" => [
                                                "Count" => 10,
                                                "NonSupportedGroups" => [
                                                    "Moteur Essence",
                                                    "Moteur Electrique",
                                                    "Boite de Vitesse à Variation Continue",
                                                    "Boite de Vitesse Automatique",
                                                    "Boite de Transfert",
                                                    "Freinage Hydraulique",
                                                    "Freinage Electromagnétique",
                                                    "Suspension Ressort",
                                                    "Suspension Pneumatique",
                                                    "Hydraulique"
                                                ]
                                            ],
                                            "LOVOL" => [
                                                "Count" => 10,
                                                "NonSupportedGroups" => [
                                                    "Moteur Essence",
                                                    "Moteur Electrique",
                                                    "Boite de Vitesse à Variation Continue",
                                                    "Boite de Vitesse Automatique",
                                                    "Freinage Electromagnétique",
                                                    "Freinage Pneumatique",
                                                    "Suspension à Lame",
                                                    "Suspension Ressort",
                                                    "Suspension Pneumatique",
                                                    "Suspension"
                                                ]
                                            ],
                                            "JCB" => [
                                                "Count" => 10,
                                                "NonSupportedGroups" => [
                                                    "Moteur Essence",
                                                    "Moteur Electrique",
                                                    "Boite de Vitesse à Variation Continue",
                                                    "Boite de Vitesse Automatique",
                                                    "Freinage Electromagnétique",
                                                    "Freinage Pneumatique",
                                                    "Suspension à Lame",
                                                    "Suspension Ressort",
                                                    "Suspension Pneumatique",
                                                    "Suspension"
                                                ]
                                            ],
                                            "TOYOTA BT" => [
                                                "Count" => 16,
                                                "NonSupportedGroups" => [
                                                    "Moteur Diesel",
                                                    "Moteur Essence",
                                                    "Moteur Thermique",
                                                    "Boite de Vitesse Mécanique",
                                                    "Boite de Vitesse à Variation Continue",
                                                    "Boite de Vitesse Automatique",
                                                    "Boite de Transfert",
                                                    "Pont",
                                                    "Arbre de Transmission",
                                                    "Demi Arbre de Roue",
                                                    "Freinage",
                                                    "Freinage Pneumatique",
                                                    "Suspension à Lame",
                                                    "Suspension Ressort",
                                                    "Suspension Pneumatique",
                                                    "Suspension"
                                                ]
                                            ],
                                            "TOYOTA FORKLIFT" => [
                                                "Count" => 9,
                                                "NonSupportedGroups" => [
                                                    "Boite de Vitesse Mécanique",
                                                    "Boite de Vitesse à Variation Continue",
                                                    "Boite de Transfert",
                                                    "Arbre de Transmission",
                                                    "Freinage Pneumatique",
                                                    "Suspension à Lame",
                                                    "Suspension Ressort",
                                                    "Suspension Pneumatique",
                                                    "Suspension"
                                                ]
                                            ]
                                        ];

                                        // Retourner les groupes non supportés ou un tableau vide
                                        return isset($data[$brandName]['NonSupportedGroups']) ? $data[$brandName]['NonSupportedGroups'] : [];
                                    }

                                    // Afficher les lignes du tableau
                                    foreach ($functionalGroups as $group) {
                                        $isGrayedOut = true;
                                        foreach ($validBrands as $brand) {
                                            $nonSupportedGroups = getNonSupportedGroups($brand);
                                            if (!in_array($group, $nonSupportedGroups)) {
                                                $isGrayedOut = false;
                                                break;
                                            }
                                        }

                                        echo '<tr' . ($isGrayedOut ? ' class="table-secondary"' : '') . '>';
                                        echo '<td class="text-center text-uppercase align-middle group-title" rowspan="2" style="background-color: #e6e6e6;"><b>' . htmlspecialchars($group) . '</b></td>';

                                        // Afficher les scores 'Connaissances' et 'Tâches Professionnelles'
                                        $factuelScore = isset($scores[$group]['Factuel']) ? $scores[$group]['Factuel'] : 'N/A';
                                        $declaratifScore = isset($scores[$group]['Declaratif']) ? $scores[$group]['Declaratif'] : 'N/A';

                                        // Appliquer la classe Bootstrap basée sur le pourcentage
                                        $factuelClass = ($factuelScore !== 'N/A') ? getBootstrapClass($factuelScore) : '';
                                        $declaratifClass = ($declaratifScore !== 'N/A') ? getBootstrapClass($declaratifScore) : '';

                                        // Condition pour afficher "-" ou le score pour 'Connaissances'
                                        if ($isGrayedOut) {
                                            echo '<td class="text-center">-</td>';
                                        } else {
                                            if ($factuelScore === 'N/A') {
                                                echo '<td class="text-center" data-toggle="tooltip" data-placement="top" title="' . htmlspecialchars($technician['firstName']) . ' n\'a pas été évalué dans le groupe fonctionnel ' . htmlspecialchars($group) . ' au niveau ' . htmlspecialchars($selectedLevel) . ' (Connaissances).">N/A</td>';
                                            } else {
                                                echo '<td class="text-center ' . htmlspecialchars($factuelClass) . '">' . htmlspecialchars($c) . ': ' . (is_numeric($factuelScore) ? round($factuelScore) : htmlspecialchars($factuelScore)) . '</td>';
                                            }
                                        }


                                        // Afficher les indicateurs pour chaque formation
                                        foreach ($trainingTypes as $type) {
                                            foreach ($validBrands as $brand) {
                                                // Vérifier si le groupe n'est pas supporté par la marque
                                                $nonSupportedGroups = getNonSupportedGroups($brand);
                                                if (in_array($group, $nonSupportedGroups)) {
                                                    echo '<td class="text-center table-secondary">-</td>';
                                                    continue;
                                                }
                                                if (isset($trainingsByBrandAndType[$type][$brand]) && !empty($trainingsByBrandAndType[$type][$brand])) {
                                                    foreach ($trainingsByBrandAndType[$type][$brand] as $training) {
                                                        // Convertir $training['speciality'] en tableau
                                                        if ($training['speciality'] instanceof MongoDB\Model\BSONArray) {
                                                            $specialityArray = $training['speciality']->getArrayCopy();
                                                        } else {
                                                            $specialityArray = (array)$training['speciality'];
                                                        }

                                                        // Vérifier si le groupe fonctionnel est couvert par la formation
                                                        if (in_array($group, $specialityArray)) {
                                                            $c_hours = isset($training['c_hours']) ? intval($training['c_hours']) : 5;
                                                            echo '<td class="text-center"><span class="badge badge-success hour-display" data-training-id="' . htmlspecialchars($training['_id']) . '" data-group="' . htmlspecialchars($group) . '" data-type="c">' . htmlspecialchars($c_hours) . 'h</span></td>';

                                                        } else {
                                                            echo '<td class="text-center"><span class="badge badge-danger">&#10007;</span></td>';
                                                        }
                                                    }
                                                } else {
                                                    // Formation non encore disponible pour cette marque et type
                                                    echo '<td class="text-center"><b>Bientôt</b></td>';
                                                }
                                            }
                                        }
                                        echo '</tr>';


                                        // Deuxième ligne pour 'Declaratif'
                                        echo '<tr' . ($isGrayedOut ? ' class="table-secondary"' : '') . '>';

                                        // Condition pour afficher "-" ou le score pour 'Tâches Professionnelles'
                                        if ($isGrayedOut) {
                                            echo '<td class="text-center">-</td>';
                                        } else {
                                            if ($declaratifScore === 'N/A') {
                                                echo '<td class="text-center" data-toggle="tooltip" data-placement="top" title="' . htmlspecialchars($technician['firstName']) . ' n\'a pas été évalué dans le groupe fonctionnel ' . htmlspecialchars($group) . ' au niveau ' . htmlspecialchars($selectedLevel) . ' (Tâches Professionnelles).">N/A</td>';
                                            } else {
                                                echo '<td class="text-center ' . htmlspecialchars($declaratifClass) . '">' . htmlspecialchars($tp) . ': ' . (is_numeric($declaratifScore) ? round($declaratifScore) : htmlspecialchars($declaratifScore)) . '</td>';
                                            }
                                        }

                                        // Répéter les indicateurs
                                        foreach ($trainingTypes as $type) {
                                            foreach ($validBrands as $brand) {
                                                // Vérifier si le groupe n'est pas supporté par la marque
                                                $nonSupportedGroups = getNonSupportedGroups($brand);
                                                if (in_array($group, $nonSupportedGroups)) {
                                                    echo '<td class="text-center table-secondary">-</td>';
                                                    continue;
                                                }
                                                if (isset($trainingsByBrandAndType[$type][$brand]) && !empty($trainingsByBrandAndType[$type][$brand])) {
                                                    foreach ($trainingsByBrandAndType[$type][$brand] as $training) {
                                                        // Convertir $training['speciality'] en tableau
                                                        if ($training['speciality'] instanceof MongoDB\Model\BSONArray) {
                                                            $specialityArray = $training['speciality']->getArrayCopy();
                                                        } else {
                                                            $specialityArray = (array)$training['speciality'];
                                                        }

                                                        // Vérifier si le groupe fonctionnel est couvert par la formation
                                                        if (in_array($group, $specialityArray)) {
                                                            // Récupérer les heures de Tâches Professionnelles (Declaratif) ou définir à 5 si non défini
                                                            $tp_hours = isset($training['tp_hours']) ? intval($training['tp_hours']) : 5;
                                                            echo '<td class="text-center"><span class="badge badge-success hour-display" data-training-id="' . htmlspecialchars($training['_id']) . '" data-group="' . htmlspecialchars($group) . '" data-type="tp">' . htmlspecialchars($tp_hours) . 'h</span></td>';

                                                        } else {
                                                            echo '<td class="text-center"><span class="badge badge-danger">&#10007;</span></td>';
                                                        }
                                                    }
                                                } else {
                                                    // Formation non encore disponible pour cette marque et type
                                                    echo '<td class="text-center"><b>Bientôt</b></td>';
                                                }
                                            }
                                        }
                                        echo '</tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="row">
                            <div
                                class="col-sm-12 col-md-5 d-flex align-items-center justify-content-center justify-content-md-start">
                                <div class="dataTables_length">
                                    <label><select id="kt_customers_table_length" name="kt_customers_table_length"
                                            class="form-control form-control-sm form-control-solid">
                                            <option value="100">100</option>
                                            <option value="200">200</option>
                                            <option value="300">300</option>
                                            <option value="500">500</option>
                                        </select></label>
                                </div>
                            </div>
                            <div
                                class="col-sm-12 col-md-7 d-flex align-items-center justify-content-center justify-content-md-end">
                                <div class="dataTables_paginate paging_simple_numbers">
                                    <ul class="pagination" id="kt_customers_table_paginate">
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Table-->
                </div>
                <!--end::Card body-->
            </div>
            <!--end::Card-->
            <!--begin::Export dropdown-->
            <div class="d-flex justify-content-end align-items-center" style="margin-top: 20px;">
                <!--begin::Export-->
                <button type="button" id="excel" class="btn btn-light-primary me-3" data-bs-toggle="modal"
                    data-bs-target="#kt_customers_export_modal">
                    <i class="ki-duotone ki-exit-up fs-2"><span class="path1"></span><span class="path2"></span></i>
                    <?php echo $excel ?>
                </button>
                <!--end::Export-->
            </div>
            <!--end::Export dropdown-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::Post-->
</div>
<!--end::Body-->
<!-- Inclure jQuery et Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.3.js" integrity="sha256-nQLuAZGRRcILA+6dMBOvcRh5Pe310sBpanc6+QBmyVM="
    crossorigin="anonymous"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/linways/table-to-excel@v1.0.4/dist/tableToExcel.js"></script>
<script src="../../public/js/main.js"></script>
<script>
 var validBrands = <?php echo json_encode($validBrands, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
console.log("Valid Brands:", validBrands);

function getBootstrapClass(pourcentage) {
    if (pourcentage >= 50) {
        return 'text-danger'; // Rouge pour plus de 50% non maîtrisé
    } else if (pourcentage >= 10) {
        return 'text-warning'; // Orange pour 10-49% non maîtrisé
    } else {
        return 'text-success'; // Vert pour moins de 10% non maîtrisé
    }
}

$(document).ready(function() {
    $("#excel").on("click", function() {
        let table = document.getElementsByTagName("table");
        TableToExcel.convert(table[0], {
            name: Formations_<?php echo $technicianId; ?>.xlsx,
            sheet: {
                name: "Formations"
            }
        });
    });
});

$(document).ready(function () {
    // Activer les tooltips Bootstrap
    $('[data-toggle="tooltip"]').tooltip();
});

$(document).ready(function() {
    // Fonction pour ouvrir la dropdown lors du clic
    $(document).on('click', '.hour-display', function(e) {
        e.stopPropagation(); // Empêche la fermeture immédiate de la dropdown
        var dropdown = $(this).next('.dropdown-menu');
        $('.dropdown-menu').not(dropdown).hide(); // Ferme les autres dropdowns
        dropdown.toggle(); // Ouvre/ferme la dropdown ciblée
    });

    // Générer les dropdowns dynamiquement
    $('.hour-display').each(function() {
        var currentHours = $(this).text().replace('h', '');
        var trainingId = $(this).data('training-id');
        var group = $(this).data('group');
        var type = $(this).data('type');

        var dropdown = $('<ul class="dropdown-menu"></ul>');
        for (var i = 5; i <= 40; i += 5) {
            var li = $('<li></li>');
            var a = $('<a href="#" class="dropdown-item"></a>').text(i + 'h');
            a.click(function(e) {
                e.preventDefault();
                var selectedHours = $(this).text().replace('h', '');
                // Mettre à jour l'affichage
                $(this).closest('.dropdown-menu').prev('.hour-display').text(selectedHours + 'h');
                // Masquer la dropdown
                $(this).closest('.dropdown-menu').hide();
                // Envoyer la mise à jour via AJAX
                $.ajax({
                    url: 'update_hours.php', // Script PHP à créer pour traiter la mise à jour
                    type: 'POST',
                    data: {
                        training_id: trainingId,
                        group: group,
                        type: type,
                        hours: selectedHours
                    },
                    success: function(response) {
                        console.log(response);
                        // Vous pouvez ajouter des notifications ou des validations ici
                    },
                    error: function(xhr, status, error) {
                        console.error(error);
                        alert('Erreur lors de la mise à jour des heures.');
                    }
                });
            });
            li.append(a);
            dropdown.append(li);
        }
        // Ajouter la dropdown après l'élément hour-display
        $(this).after(dropdown);
    });

    // Fermer les dropdowns lorsqu'on clique en dehors
    $(document).click(function() {
        $('.dropdown-menu').hide();
    });
});
</script>
<?php include_once "partials/footer.php"; ?>
<?php
} ?> 