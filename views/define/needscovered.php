<?php
// needsCovered.php

require_once "../../vendor/autoload.php";

// Démarrer la session et inclure le composant de navigation
include_once 'navigation.php';

// Mettre à jour l'historique de navigation
update_navigation_history();

// Charger la configuration
$config = require 'configGF.php';

// Inclure le fichier de traitement
$recommendationData = include "processRecommendations.php";

// Récupérer les données depuis processRecommendations.php
$technicians   = $recommendationData['technicians'];
$scores        = $recommendationData['scores'];
$trainings     = $recommendationData['trainings'];
$missingGroups = $recommendationData['missingGroups'];
$debug         = $recommendationData['debug'];

// Définir l’ordre des niveaux
$levelOrder = [
    'Junior' => 1,
    'Senior' => 2,
    'Expert' => 3
];

// Récupérer les filtres GET si besoin
$selectedCountry   = $_GET['country']  ?? 'all';
$selectedAgency    = $_GET['agency']   ?? 'all';
$selectedLevel     = $_GET['level']    ?? 'all';
$selectedManagerId = $_GET['manager']  ?? 'all';
$selectedBrand     = $_GET['brand']    ?? 'all'; // si pertinent

// Map pays => agences (tiré de votre code)
$agencies = [
    "Burkina Faso"  => ["Ouaga"],
    "Cameroun"      => ["Bafoussam", "Bertoua", "Douala", "Garoua", "Ngaoundere", "Yaoundé"],
    "Cote d'Ivoire" => ["Vridi - Equip"],
    "Gabon"         => ["Libreville"],
    "Mali"          => ["Bamako"],
    "RCA"           => ["Bangui"],
    "RDC"           => ["Kinshasa", "Kolwezi", "Lubumbashi"],
    "Senegal"       => ["Dakar"]
];
$countries = array_keys($agencies);

// Optionnel : Filiales
$subsidiaries = [
    "CFAO MOTORS BURKINA",
    "CAMEROON MOTORS INDUSTRIES",
    "CFAO MOTORS COTE D'IVOIRE",
    "CFAO MOTORS GABON",
    "CFAO MOTORS MALI",
    "CFAO MOTORS CENTRAFRIQUE",
    "CFAO MOTORS RDC",
    "CFAO MOTORS SENEGAL"
];

// ------------ FONCTIONS UTILES POUR L’AFFICHAGE ----------- //

function getLevelOrder($level)
{
    $order = [
        'Junior' => 1,
        'Senior' => 2,
        'Expert' => 3
    ];
    return $order[$level] ?? 1;
}

/**
 * Indique s’il y a un besoin en fonction des scores factuels/déclaratifs.
 */
function hasNeed($fact, $decl)
{
    $threshold = 80;
    $f = ($fact === null) ? 100 : (int)$fact;
    $d = ($decl === null) ? 100 : (int)$decl;
    return ($f < $threshold) || ($d < $threshold);
}

/**
 * Retourne l’icône de drapeau <img>.
 */
function getFlagIcon($country)
{
    $flags = [
        "Burkina Faso"  => "bf.svg",
        "Cameroun"      => "cm.svg",
        "Cote d'Ivoire" => "ci.svg",
        "Gabon"         => "ga.svg",
        "Mali"          => "ml.svg",
        "RCA"           => "cf.svg",
        "RDC"           => "cd.svg",
        "Senegal"       => "sn.svg"
    ];
    $flagFile = $flags[$country] ?? 'xx.svg';
    return '<img src="flags/1x1/' . htmlspecialchars($flagFile) . '" 
                 alt="' . htmlspecialchars($country) . ' Flag" 
                 width="24" height="16">';
}

/**
 * Retourne le nom complet du manager.
 */
function getManagerName($managerId, $technicians)
{
    foreach ($technicians as $tech) {
        if ((string)$tech['_id'] === $managerId) {
            return htmlspecialchars($tech['firstName'] . ' ' . $tech['lastName']);
        }
    }
    return 'N/A';
}

/**
 * Calcule les spécialités applicables pour un technicien (selon config + marques).
 */
function getApplicableSpecialties($technician, $config, $standardizedBrands)
{
    $level = $technician['level'] ?? 'Unknown';
    $levelSpecialties = $config['functionalGroupsByLevel'][$level] ?? [];

    $applicable = [];
    foreach ($levelSpecialties as $spec) {
        $supportedBy = [];
        foreach ($standardizedBrands as $brand) {
            if (!in_array($spec, $config['nonSupportedGroupsByBrand'][$brand] ?? [])) {
                $supportedBy[] = $brand;
            }
        }
        $supportedBy = array_filter($supportedBy, function($b) {
            return !empty(trim($b));
        });
        if (!empty($supportedBy)) {
            $applicable[$spec] = $supportedBy;
        }
    }
    return $applicable;
}

// Mapping spécialité => niveauMinimum
$specialtyToMinLevel = [];
foreach ($config['functionalGroupsByLevel'] as $lvl => $specs) {
    foreach ($specs as $s) {
        if (!isset($specialtyToMinLevel[$s])) {
            $specialtyToMinLevel[$s] = $lvl;
        } else {
            if (getLevelOrder($lvl) < getLevelOrder($specialtyToMinLevel[$s])) {
                $specialtyToMinLevel[$s] = $lvl;
            }
        }
    }
}

// ---------------------------------------
//         AFFICHAGE HTML
// ---------------------------------------
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include_once "partials/header.php"; ?>
    <title><?php echo $train_tech ?> | CFAO Mobility Academy</title>
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet"
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <style>
        /* Vos styles CSS personnalisés */
        .technician-card {
            margin-bottom: 20px;
            cursor: pointer;
            transition: transform 0.2s;
            display: flex;
        }

        .technician-card:hover {
            transform: scale(1.02);
        }

        .technician-card .card {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .card-header {
            background-color: #f8f9fa;
            font-weight: bold;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
            overflow: hidden;
        }

        .card-header span:first-child {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }

        .card-body {
            flex: 1;
        }

        .badge-success {
            background-color: #28a745;
        }

        .badge-warning {
            background-color: #ffc107;
        }

        .badge-danger {
            background-color: #dc3545;
        }

        .progress {
            height: 20px;
            margin-bottom: 10px;
        }

        .modal-body {
            max-height: 80vh;
            overflow-y: auto;
        }

        .filter-section {
            margin-bottom: 20px;
        }

        @media (min-width: 768px) {
            .technician-card {
                display: flex;
            }

            .technician-info {
                flex: 1;
            }

            .technician-details {
                flex: 2;
                padding-left: 20px;
            }
        }

        .badge-besoin {
            background-color:rgb(250, 22, 45);
            color: white;
            font-size: 0.8em;
            padding: 0.2em 0.4em;
            border-radius: 0.25rem;
        }

        /* Styles pour les raisons */
        .reason-text {
            font-style: italic;
            color: #6c757d;
            margin-top: 5px;
        }

    </style>
</head>

<body>
    <!-- Contenu principal -->
    <div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content">
        <?php if ($_SESSION["profile"] == "Super Admin") { ?>
            <!-- Barre d'outils -->
            <div class="toolbar" id="kt_toolbar">
                <div class="container-fluid d-flex flex-stack flex-wrap flex-sm-nowrap">
                    <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                        <!-- Titre principal -->
                        <h1 class="text-dark fw-bold my-1 fs-2">
                            <?php echo $list_training ?>
                        </h1>
                        <!-- Barre de recherche -->
                        <div class="card-title">
                            <div class="d-flex align-items-center position-relative my-1">
                                <i class="bi bi-search fs-3 position-absolute ms-5"></i>
                                <input type="text" id="search"
                                    class="form-control form-control-solid w-250px ps-5"
                                    placeholder="<?php echo $recherche ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Fin barre d'outils -->

            <!-- Publication -->
            <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
                <div class="container-xxl">
                    <!-- Carte des Filtres -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" action="">
                                <div class="filter-section">
                                    <div class="row g-3 align-items-center">
                                        <!-- Filtre Pays -->
                                        <div class="col-md-2">
                                            <label for="country-filter" class="form-label d-flex align-items-center">
                                                <i class="bi bi-geo-alt-fill me-2 text-primary"></i> Pays
                                            </label>
                                            <select id="country-filter" name="country" class="form-select">
                                                <option value="all"
                                                    <?php if ($selectedCountry === 'all') echo 'selected'; ?>>
                                                    Tous les pays
                                                </option>
                                                <?php foreach ($countries as $cOption) : ?>
                                                    <option value="<?php echo htmlspecialchars($cOption); ?>"
                                                        <?php if ($selectedCountry === $cOption) echo 'selected'; ?>>
                                                        <?php echo htmlspecialchars($cOption); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!-- Filtre Agence -->
                                        <div class="col-md-2">
                                            <label for="agency-filter" class="form-label d-flex align-items-center">
                                                <i class="bi bi-building me-2 text-warning"></i> Agence
                                            </label>
                                            <select id="agency-filter" name="agency" class="form-select"
                                                <?php echo ($selectedCountry === 'all') ? 'disabled' : ''; ?>>
                                                <option value="all"
                                                    <?php if ($selectedAgency === 'all') echo 'selected'; ?>>
                                                    Toutes les agences
                                                </option>
                                                <?php
                                                if ($selectedCountry !== 'all' && isset($agencies[$selectedCountry])) {
                                                    foreach ($agencies[$selectedCountry] as $aOpt) {
                                                ?>
                                                        <option value="<?php echo htmlspecialchars($aOpt); ?>"
                                                            <?php if ($selectedAgency === $aOpt) echo 'selected'; ?>>
                                                            <?php echo htmlspecialchars($aOpt); ?>
                                                        </option>
                                                <?php
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>

                                        <!-- Filtre Manager -->
                                        <div class="col-md-3">
                                            <label for="manager-filter" class="form-label d-flex align-items-center">
                                                <i class="bi bi-person-fill me-2 text-info"></i> Manager
                                            </label>
                                            <select id="manager-filter" name="manager" class="form-select">
                                                <option value="all" selected>Tous les managers</option>
                                                <!-- Rempli dynamiquement via JS -->
                                            </select>
                                        </div>

                                        <!-- Filtre Niveau -->
                                        <div class="col-md-2">
                                            <label for="level-filter" class="form-label d-flex align-items-center">
                                                <i class="bi bi-bar-chart-fill me-2 text-success"></i> Niveau
                                            </label>
                                            <select id="level-filter" name="level" class="form-select">
                                                <option value="all"
                                                    <?php if ($selectedLevel === 'all') echo 'selected'; ?>>
                                                    Tous les niveaux
                                                </option>
                                                <option value="Junior"
                                                    <?php if ($selectedLevel === 'Junior') echo 'selected'; ?>>
                                                    Junior
                                                </option>
                                                <option value="Senior"
                                                    <?php if ($selectedLevel === 'Senior') echo 'selected'; ?>>
                                                    Senior
                                                </option>
                                                <option value="Expert"
                                                    <?php if ($selectedLevel === 'Expert') echo 'selected'; ?>>
                                                    Expert
                                                </option>
                                            </select>
                                        </div>

                                        <!-- Filtre Marque -->
                                        <div class="col-md-3">
                                            <label for="brand-filter" class="form-label d-flex align-items-center">
                                                <i class="bi bi-tags-fill me-2 text-secondary"></i> Marque
                                            </label>
                                            <select id="brand-filter" name="brand" class="form-select">
                                                <option value="all" selected>Toutes les marques</option>
                                                <?php
                                                // Dans votre code, vous aviez $allBrands
                                                // si vous désirez filtrer par brand
                                                foreach ($allBrands as $bOption):
                                                    $bOptUpper = strtoupper($bOption);
                                                ?>
                                                    <option value="<?php echo htmlspecialchars($bOptUpper); ?>"
                                                        <?php if (strtoupper($selectedBrand) === $bOptUpper) echo 'selected'; ?>>
                                                        <?php echo htmlspecialchars($bOptUpper); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <!-- Bouton Filtrer -->
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">Filtrer</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- Fin Carte des Filtres -->

                    <!-- Section des Cartes des Techniciens -->
                    <div class="row" id="technician-cards">
                        <?php foreach ($technicians as $tech) :
                            $techId       = (string)$tech['_id'];
                            $tLevel       = $tech['level']   ?? 'Unknown';
                            $tCountry     = $tech['country'] ?? 'Unknown';
                            $tAgency      = $tech['agency']  ?? 'Unknown';
                            $tManagerId   = isset($tech['manager']) ? (string)$tech['manager'] : 'none';
                            $managerName  = getManagerName($tManagerId, $technicians);

                            // Dans votre code, vous stockiez la liste des “marques standardisées” dans $technicianBrandsData
                            // Ici on suppose que vous l’avez déjà, ou vous la générez
                            // On fera simple : $techBrands = ...
                            // Idem pour $techSpecialties
                            // Mais comme vous l’aviez déjà dans le code “needsCovered”, on suppose que
                            // $technicianBrandsData / $technicianSpecialtiesData sont globalement disponibles.
                            // S’il vous faut recalc, vous pouvez le faire ci-dessous :

                            // Ex. juste pour l’exemple (un peu raccourci) :
                            $rawJunior  = !empty($tech['brandJunior']) ? iterator_to_array($tech['brandJunior']) : [];
                            $rawSenior  = !empty($tech['brandSenior']) ? iterator_to_array($tech['brandSenior']) : [];
                            $rawExpert  = !empty($tech['brandExpert']) ? iterator_to_array($tech['brandExpert']) : [];
                            $techBrandsRaw = [];
                            if ($tLevel === 'Expert') {
                                $techBrandsRaw = array_merge($rawJunior, $rawSenior, $rawExpert);
                            } elseif ($tLevel === 'Senior') {
                                $techBrandsRaw = array_merge($rawJunior, $rawSenior);
                            } elseif ($tLevel === 'Junior') {
                                $techBrandsRaw = $rawJunior;
                            }
                            // On vire les valeurs « vides » ou pleines d’espaces
                            $techBrandsRaw = array_filter($techBrandsRaw, function ($x) {
                                return !empty(trim($x));
                            });

                            $techBrands = [];

                            foreach ($techBrandsRaw as $b) {
                                $bUpper = strtoupper(trim($b));

                                if (isset($config['brandMappings'][$bUpper])) {
                                    // Mapping métier défini dans ta conf → on normalise
                                    $techBrands[] = $config['brandMappings'][$bUpper];
                                } else {
                                    // Pas de mapping ? On garde la marque en majuscules, propre et nette
                                    $techBrands[] = $bUpper;
                                }
                            }

                            $techBrands = array_unique($techBrands);
                            sort($techBrands);

                            // Spécialités
                            $techSpecialties = getApplicableSpecialties($tech, $config, $techBrands);
                        ?>
                            <div class="col-md-4 technician-card" data-technician-id="<?php echo htmlspecialchars($techId); ?>">
                                <div class="card h-100" data-bs-toggle="modal"
                                    data-bs-target="#technicianModal<?php echo htmlspecialchars($techId); ?>">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <span>
                                            <?php echo htmlspecialchars($tech['firstName'] . ' ' . $tech['lastName']); ?>
                                        </span>
                                        <span><?php echo getFlagIcon($tCountry); ?></span>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Pays :</strong> <?php echo htmlspecialchars($tCountry); ?></p>
                                        <p><strong>Agence :</strong> <?php echo htmlspecialchars($tAgency); ?></p>
                                        <p><strong>Niveau :</strong>
                                            <span class="badge 
                                        <?php
                                        if ($tLevel === 'Junior') echo 'badge-success';
                                        elseif ($tLevel === 'Senior') echo 'badge-warning';
                                        elseif ($tLevel === 'Expert') echo 'badge-danger';
                                        else echo 'badge-secondary';
                                        ?>">
                                                <?php echo htmlspecialchars($tLevel); ?>
                                            </span>
                                        </p>
                                        <p><strong>Manager :</strong> <?php echo htmlspecialchars($managerName); ?></p>
                                        <p><strong>Marques :</strong>
                                            <?php if (!empty($techBrands)):
                                                foreach ($techBrands as $b): ?>
                                                    <span class="badge badge-light"><?php echo htmlspecialchars($b); ?></span>
                                                <?php endforeach;
                                            else: ?>
                                                <span>Aucune marque assignée</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal pour chaque Technicien -->
                            <div class="modal fade"
                                id="technicianModal<?php echo htmlspecialchars($techId); ?>"
                                tabindex="-1"
                                aria-labelledby="technicianModalLabel<?php echo htmlspecialchars($techId); ?>"
                                aria-hidden="true">
                                <div class="modal-dialog modal-xl">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="technicianModalLabel<?php echo htmlspecialchars($techId); ?>">
                                                <?php echo htmlspecialchars($tech['firstName'] . ' ' . $tech['lastName']); ?>
                                                <span><?php echo getFlagIcon($tCountry); ?></span>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <!-- Infos de Base -->
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <p>
                                                        <strong>Pays : </strong>
                                                        <?php echo getFlagIcon($tCountry) . ' ' . htmlspecialchars($tCountry); ?>
                                                    </p>
                                                    <p>
                                                        <strong>Agence : </strong>
                                                        <?php echo htmlspecialchars($tAgency); ?>
                                                    </p>
                                                    <p>
                                                        <strong>Niveau : </strong>
                                                        <span class="badge 
                                                    <?php
                                                    if ($tLevel === 'Junior') echo 'badge-success';
                                                    elseif ($tLevel === 'Senior') echo 'badge-warning';
                                                    elseif ($tLevel === 'Expert') echo 'badge-danger';
                                                    else echo 'badge-light';
                                                    ?>">
                                                            <?php echo htmlspecialchars($tLevel); ?>
                                                        </span>
                                                    </p>
                                                    <p>
                                                        <strong>Manager : </strong>
                                                        <?php echo htmlspecialchars($managerName); ?>
                                                    </p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Marques Assignées :</strong>
                                                        <?php if (!empty($techBrands)):
                                                            foreach ($techBrands as $b): ?>
                                                                <span class="badge badge-light"><?php echo htmlspecialchars($b); ?></span>
                                                            <?php endforeach;
                                                        else: ?>
                                                            <span>Aucune marque assignée</span>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- Filtres internes (Niveau / Marque) -->
                                            <div class="row mb-4">
                                                <div class="col-md-6">
                                                    <label for="level-specific-filter<?php echo htmlspecialchars($techId); ?>" class="form-label">Niveau</label>
                                                    <select id="level-specific-filter<?php echo htmlspecialchars($techId); ?>"
                                                        class="form-select level-specific-filter"
                                                        data-technician-id="<?php echo htmlspecialchars($techId); ?>">
                                                        <?php
                                                        // Ajuster la liste de niveaux possibles
                                                        $lvlsAvailable = [];
                                                        if ($tLevel === 'Expert') {
                                                            $lvlsAvailable = ['Junior', 'Senior', 'Expert'];
                                                        } elseif ($tLevel === 'Senior') {
                                                            $lvlsAvailable = ['Junior', 'Senior'];
                                                        } else {
                                                            $lvlsAvailable = ['Junior'];
                                                        }
                                                        foreach ($lvlsAvailable as $lvl) {
                                                            $sel = ($lvl === $tLevel) ? 'selected' : '';
                                                            echo "<option value='" . htmlspecialchars($lvl) . "' $sel>" .
                                                                htmlspecialchars($lvl) . "</option>";
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="brand-specific-filter<?php echo htmlspecialchars($techId); ?>" class="form-label">Marque</label>
                                                    <select id="brand-specific-filter<?php echo htmlspecialchars($techId); ?>"
                                                        class="form-select brand-specific-filter"
                                                        data-technician-id="<?php echo htmlspecialchars($techId); ?>">
                                                        <option value="all">Toutes les marques</option>
                                                        <?php
                                                        // Extraire les marques uniques qui supportent au moins une spécialité
                                                        $uniqueBrands = [];
                                                        foreach ($techSpecialties as $spec => $brands) {
                                                            foreach ($brands as $br) {
                                                                $uniqueBrands[strtolower($br)] = $br;
                                                            }
                                                        }
                                                        asort($uniqueBrands);
                                                        foreach ($uniqueBrands as $br) {
                                                            echo '<option value="' . htmlspecialchars($br) . '">' . htmlspecialchars($br) . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Résultats Par Spécialité -->
                                            <div id="results-section<?php echo htmlspecialchars($techId); ?>">
                                                <?php
                                                // Parcourir les spécialités
                                                if (!empty($techSpecialties)) {
                                                    foreach ($techSpecialties as $spec => $supportedBrands) {
                                                        // Récupérer scores
                                                        $allLvls = ['Junior', 'Senior', 'Expert'];
                                                        $scoresForSpec = [];

                                                        // 1) On calcule tous les scores
                                                        foreach ($allLvls as $lv) {
                                                            $score = $scores[$techId][$lv][$spec] ?? ['Factuel' => null, 'Declaratif' => null];
                                                            $scoresForSpec[$lv] = [
                                                                'Factuel'    => $score['Factuel']    ?? null,
                                                                'Declaratif' => $score['Declaratif'] ?? null
                                                            ];
                                                        }
                                                        // 2) On décide en PHP, en se basant sur $tLevel :
                                                        // $need = hasNeed($scoresForSpec[$tLevel]['Factuel'], $scoresForSpec[$tLevel]['Declaratif']);
                                                        $scoresJson = htmlspecialchars(json_encode($scoresForSpec));
                                                        $displayedFact = $scoresForSpec[$tLevel]['Factuel'] ?? null;
                                                        $displayedDecl = $scoresForSpec[$tLevel]['Declaratif'] ?? null;

                                                        $initialNeed = hasNeed($displayedFact, $displayedDecl);

                                                ?>
                                                <hr><hr>
                                                        <div class="specialty-item"
                                                            data-scores="<?php echo $scoresJson; ?>"
                                                            data-spec="<?php echo htmlspecialchars($spec); ?>"
                                                            data-brands="<?php echo htmlspecialchars(implode(',', $supportedBrands)); ?>">
                                                            <h5>
                                                                <?php echo htmlspecialchars($spec); ?>
                                                                <?php if ($initialNeed): ?>
                                                                    <span class="badge-besoin">Besoin</span>
                                                                <?php else: ?>
                                                                    <span class="badge badge-secondary">Aucun besoin (score élevé)</span>
                                                                <?php endif; ?>
                                                            </h5>

                                                            <div class="mb-2">
                                                                <strong>Factuel :</strong>
                                                                <span class="factuel-score">
                                                                    <?php
                                                                    echo ($displayedFact !== null)
                                                                        ? htmlspecialchars($displayedFact)
                                                                        : 'N/A';
                                                                    ?>
                                                                </span>
                                                                |
                                                                <strong>Déclaratif :</strong>
                                                                <span class="declaratif-score">
                                                                    <?php
                                                                    echo ($displayedDecl !== null)
                                                                        ? htmlspecialchars($displayedDecl)
                                                                        : 'N/A';
                                                                    ?>
                                                                </span>
                                                            </div>

                                                            <!-- Barres de progression -->
                                                            <div class="progress mb-2 factuel-progress">
                                                                <?php
                                                                $factVal = (int)($displayedFact ?? 0);
                                                                $factClass = ($factVal >= 80) ? 'bg-success'
                                                                    : (($factVal >= 60) ? 'bg-warning' : 'bg-danger');
                                                                ?>
                                                                <div class="progress-bar <?php echo $factClass; ?>"
                                                                    role="progressbar"
                                                                    style="width: <?php echo $factVal; ?>%;"
                                                                    aria-valuenow="<?php echo $factVal; ?>"
                                                                    aria-valuemin="0" aria-valuemax="100">
                                                                    Factuel : <?php echo $factVal; ?>%
                                                                </div>
                                                            </div>
                                                            <div class="progress mb-2 declaratif-progress">
                                                                <?php
                                                                $declVal = (int)($displayedDecl ?? 0);
                                                                $declClass = ($declVal >= 80) ? 'bg-success'
                                                                    : (($declVal >= 60) ? 'bg-warning' : 'bg-danger');
                                                                ?>
                                                                <div class="progress-bar <?php echo $declClass; ?>"
                                                                    role="progressbar"
                                                                    style="width: <?php echo $declVal; ?>%;"
                                                                    aria-valuenow="<?php echo $declVal; ?>"
                                                                    aria-valuemin="0" aria-valuemax="100">
                                                                    Déclaratif : <?php echo $declVal; ?>%
                                                                </div>
                                                            </div>

                                                            <!-- Section Recommandations -->
                                                            <div>
                                                                <strong>Recommandations :</strong>
                                                                <div class="recommendations-container">
                                                                    <!-- Remplie dynamiquement via JS -->
                                                                </div>
                                                            </div>

                                                            <!-- Section Besoins à Couvrir -->
                                                            <div>
                                                                <strong>Formation à faire en cours de production :</strong>
                                                                <div class="needs-container">
                                                                    <!-- Remplie dynamiquement via JS -->
                                                                </div>
                                                            </div>
                                                            <div class="mt-2">
                                                                <strong>Marques Supportant cette Spécialité :</strong>
                                                                <?php
                                                                foreach ($supportedBrands as $b) {
                                                                    echo '<span class="badge bg-light">' . htmlspecialchars($b) . '</span> ';
                                                                }
                                                                ?>
                                                            </div>
                                                        </div>
                                                <?php
                                                    }
                                                } else {
                                                    echo '<p>Aucune spécialité détectée.</p>';
                                                }
                                                ?>
                                            </div>
                                            <div id="synthese-section<?php echo htmlspecialchars($techId); ?>" class="mt-4">
                                                <h5>Synthèse des Formations et Besoins</h5>

                                                <!-- Formations trouvées -->
                                                <div class="found-trainings-section mb-3">
                                                    <h6>Formations trouvées :</h6>
                                                    <table class="table table-sm table-bordered">
                                                        <thead>
                                                            <tr>
                                                                <th>Type</th>
                                                                <th>Marque</th>
                                                                <th>Spécialité</th>
                                                                <th>Code</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="found-trainings-tbody">
                                                            <!-- On remplira dynamiquement via JS -->
                                                        </tbody>
                                                    </table>
                                                </div>

                                                <!-- Besoins manquants -->
                                                <div class="missing-trainings-section">
                                                    <h6>Besoins complémentaires (non trouvés) :</h6>
                                                    <table class="table table-sm table-bordered">
                                                        <thead>
                                                            <tr>
                                                                <th>Type</th>
                                                                <th>Marque</th>
                                                                <th>Spécialité</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="missing-trainings-tbody">
                                                            <!-- On remplira dynamiquement via JS -->
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Fin Section Cartes Tech -->

                    <!-- Export CSV -->
                    <div class="d-flex justify-content-end align-items-center mt-4">
                        <button type="button" id="excel" class="btn btn-light-primary me-3">
                            <i class="bi bi-file-earmark-excel-fill fs-2"></i>
                            <?php echo $excel ?>
                        </button>
                    </div>
                    <!-- Fin export -->
                </div>
            </div>
            <!-- Fin Publication -->
        <?php } ?>
    </div>
    <!-- Fin contenu principal -->

    <?php include_once "partials/footer.php"; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.3.js"
        integrity="sha256-nQLuAZGRRcILA+6dMBOvcRh5Pe310sBpanc6+QBmyVM="
        crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Données renvoyées par processRecommendations
        const recommendationData = <?php echo json_encode($recommendationData); ?>;
        console.log("Trainings:", recommendationData.trainings);
        console.log("Missing Groups:", recommendationData.missingGroups);

        $(document).ready(function() {
            // Recherche par saisie
            $("#search").on("keyup", function() {
                const val = $(this).val().toLowerCase();
                $("#technician-cards .technician-card").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
                });
            });

            // Export CSV
            $("#excel").on("click", function() {
                let cards = document.querySelectorAll(".technician-card .card");
                let tableContent = "Nom Prénom,Pays,Agence,Niveau,Manager,Marques,Recommandations\n";

                cards.forEach(card => {
                    let header = card.querySelector(".card-header").innerText.trim();
                    let body = card.querySelector(".card-body");
                    let pays = body.querySelector('p:nth-child(1)').innerText.split(': ')[1] ?? '';
                    let agence = body.querySelector('p:nth-child(2)').innerText.split(': ')[1] ?? '';
                    let niveau = body.querySelector('p:nth-child(3)').innerText.split(': ')[1] ?? '';
                    let manager = body.querySelector('p:nth-child(4)').innerText.split(': ')[1] ?? '';

                    // Marques (extraction texte brut)
                    let marquesHTML = body.querySelector('p:nth-child(5)').innerHTML;
                    let marques = marquesHTML.replace(/<\/?span[^>]*>/g, '').trim();

                    // Recommandations => “Détails dans le modal” pour l’export
                    let reco = "Détails dans le modal";

                    tableContent += `"${header}","${pays}","${agence}","${niveau}","${manager}","${marques}","${reco}"\n`;
                });

                let blob = new Blob([tableContent], {
                    type: "text/csv;charset=utf-8;"
                });
                let url = URL.createObjectURL(blob);
                let a = document.createElement("a");
                a.href = url;
                a.download = "Technicians.csv";
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            });

            // Remplir dynamiquement la liste des managers
            let managers = {};
            <?php foreach ($technicians as $t):
                $mId = (string)$t['manager'] ?? 'none';
                $mNm = getManagerName($mId, $technicians);
                if ($mId !== 'none'): ?>
                    managers["<?php echo htmlspecialchars($mId); ?>"] = "<?php echo addslashes($mNm); ?>";
            <?php endif;
            endforeach; ?>

            let managerFilter = $('#manager-filter');
            managerFilter.empty();
            managerFilter.append('<option value="all">Tous les managers</option>');
            $.each(managers, function(id, name) {
                managerFilter.append('<option value="' + id + '">' + name + '</option>');
            });

            // Filtrer l’agence quand on change le pays
            $('#country-filter').on('change', function() {
                let selCountry = $(this).val();
                let agencySel = $('#agency-filter');
                agencySel.empty();
                agencySel.append('<option value="all">Toutes les agences</option>');
                if (selCountry !== 'all') {
                    let agencies = <?php echo json_encode($agencies); ?>;
                    if (agencies[selCountry]) {
                        agencies[selCountry].forEach(function(a) {
                            agencySel.append('<option value="' + a + '">' + a + '</option>');
                        });
                        agencySel.prop('disabled', false);
                    }
                } else {
                    agencySel.prop('disabled', true);
                }
            });
        });

        // Gestion des filtres dans le modal (spécialité, brand, etc.)
        document.addEventListener('DOMContentLoaded', function() {
            // ---------------------------------------------------------------------
            // 1) Déclarations (s’il y a besoin d’initialiser globalement)
            // ---------------------------------------------------------------------
            const specialtyToMinLevel = <?php echo json_encode($specialtyToMinLevel); ?>;
            const scoresData = <?php echo json_encode($scores); ?>;
            const recommendationData = <?php echo json_encode($recommendationData); ?>;

            // Pour être sûr qu'on a nos trainings, missingGroups, etc.
            console.log("Scores:", recommendationData.scores);
            console.log("Trainings:", recommendationData.trainings);
            console.log("Missing Groups:", recommendationData.missingGroups);
            console.log("Debug:", recommendationData.debug);

            // “levelOrder” pour comparer Junior / Senior / Expert
            const levelOrder = {
                junior: 1,
                senior: 2,
                expert: 3
            };

            // ---------------------------------------------------------------------
            // 2) setupLevelAndBrandFiltersInModal : Applique les écouteurs
            // ---------------------------------------------------------------------
            setupLevelAndBrandFiltersInModal();

            // (Optionnel) Gérer le “pays => agence”
            setupCountryAgencyCascade();

            // ---------------------------------------------------------------------
            // 3) Fonctions Principales
            // ---------------------------------------------------------------------

            /**
             * Gère les select “niveau” dans les modals
             * et les select “marque” : on re-filtre l’affichage.
             */
            function setupLevelAndBrandFiltersInModal() {
                // Sur toutes les “level-specific-filter”
                document.querySelectorAll('.level-specific-filter').forEach(sel => {
                    sel.addEventListener('change', function() {
                        let techId = this.getAttribute('data-technician-id');
                        let selLevel = this.value;

                        let brandSel = document.getElementById('brand-specific-filter' + techId);
                        updateBrandFilterOptions(techId, selLevel, brandSel);

                        let selBrand = brandSel.value;
                        filterSpecialties(techId, selLevel, selBrand);
                    });
                });

                // Sur toutes les “brand-specific-filter”
                document.querySelectorAll('.brand-specific-filter').forEach(sel => {
                    sel.addEventListener('change', function() {
                        let techId = this.getAttribute('data-technician-id');
                        let selBrand = this.value;

                        let lvlSel = document.getElementById('level-specific-filter' + techId);
                        let selLvl = lvlSel.value;

                        filterSpecialties(techId, selLvl, selBrand);
                    });
                });
            }

            /**
             * Met à jour la liste de marques dans le <select brand-specific-filter>,
             * en se basant sur les spécialités visibles/pertinentes.
             */
            function updateBrandFilterOptions(techId, selLevel, brandSelect) {
                if (!brandSelect) return;

                // On vide d’abord
                brandSelect.innerHTML = '<option value="all">Toutes les marques</option>';

                // Sélectionner toutes les “.specialty-item” du modal => on regarde data-brands
                let containerId = `#results-section${techId}`;
                let specialtyItems = document.querySelectorAll(containerId + ' .specialty-item');

                let brandSet = new Set();

                specialtyItems.forEach(item => {
                    let scoresForSpec = JSON.parse(item.getAttribute('data-scores') || '{}');
                    let brandStr = item.getAttribute('data-brands') || '';
                    // On pourrait aussi vérifier si “selLevel” existe dans scoresForSpec...
                    let arrBrands = brandStr.split(',').map(b => b.trim()).filter(b => b.length > 0);
                    arrBrands.forEach(b => brandSet.add(b));
                });

                // Tri
                let arrSorted = [...brandSet].sort();
                arrSorted.forEach(b => {
                    let opt = document.createElement('option');
                    opt.value = b;
                    opt.textContent = b;
                    brandSelect.appendChild(opt);
                });
            }

            /**
             * Filtre l’affichage des spécialités selon le “niveau” + “marque” choisis,
             * met à jour scores, besoin, etc.
             */
            function filterSpecialties(techId, selectedLvl, selectedBrand) {
                // 1) On récupère toutes les spécialités
                let containerId = `#results-section${techId}`;
                let specialtyItems = document.querySelectorAll(containerId + ' .specialty-item');

                // 2) Préparer deux tableaux pour la synthèse
                let foundTrainingsAll = [];
                let missingTrainingsAll = [];

                // 3) Parcourir chaque spécialité
                specialtyItems.forEach(item => {
                    let specName = item.getAttribute('data-spec');
                    let specBrandsStr = item.getAttribute('data-brands');
                    let specBrands = specBrandsStr ? specBrandsStr.split(',').map(b => b.trim()) : [];

                    let scoresForSpec = JSON.parse(item.getAttribute('data-scores') || '{}');
                    let sObj = scoresForSpec[selectedLvl] || {
                        Factuel: null,
                        Declaratif: null
                    };

                    // Décider si on affiche ou non
                    let showSpec = shouldShowSpecialty(specName, specBrands, selectedLvl, selectedBrand);
                    item.style.display = showSpec ? 'block' : 'none';

                    if (showSpec) {
                        // Met à jour les “scores” + “besoin ou pas”
                        updateScoresAndNeed(item, sObj);

                        // Récupérer les containers pour recommandations et besoins
                        let recContainer = item.querySelector('.recommendations-container');
                        let needsContainer = item.querySelector('.needs-container');

                        if (recContainer && needsContainer) {
                            updateRecommendationsAndMissing(
                                techId,
                                selectedLvl,
                                specName,
                                selectedBrand,
                                recContainer,
                                needsContainer,
                                foundTrainingsAll,
                                missingTrainingsAll
                            );
                        }
                    }
                });

                // 4) Au final => remplir la synthèse
                updateSyntheseSection(techId, foundTrainingsAll, missingTrainingsAll);
            }

            /**
             * Vérifie si on doit afficher la spécialité => compare le level minimal et la marque.
             */
            function shouldShowSpecialty(specName, specBrands, selLevel, selBrand) {
                // 1) Comparer “selLevel” vs “specName” min-level
                if (selLevel !== 'all') {
                    let specMin = specialtyToMinLevel[specName] || 'Junior';
                    let userLvlO = levelOrder[selLevel.toLowerCase()] || 1;
                    let specLvlO = levelOrder[specMin.toLowerCase()] || 1;
                    if (userLvlO < specLvlO) {
                        return false;
                    }
                }

                // 2) Filtre par marque
                if (selBrand !== 'all') {
                    let brandUp = selBrand.toUpperCase();
                    let arrUp = specBrands.map(x => x.toUpperCase());
                    if (!arrUp.includes(brandUp)) {
                        return false;
                    }
                }
                return true;
            }

            /**
             * Met à jour l’affichage des “scores” factuel/déclaratif + barres de progression
             * + le badge “Besoin” ou “Aucun besoin”.
             */
            function updateScoresAndNeed(item, scoreObj) {
                let fEl = item.querySelector('.factuel-score');
                let dEl = item.querySelector('.declaratif-score');
                if (fEl) fEl.textContent = (scoreObj.Factuel != null) ? scoreObj.Factuel : 'N/A';
                if (dEl) dEl.textContent = (scoreObj.Declaratif != null) ? scoreObj.Declaratif : 'N/A';

                // Progress bars
                let factBar = item.querySelector('.factuel-progress .progress-bar');
                let declBar = item.querySelector('.declaratif-progress .progress-bar');
                updateProgressBar(factBar, scoreObj.Factuel, 'Factuel');
                updateProgressBar(declBar, scoreObj.Declaratif, 'Déclaratif');

                // Besoin ?
                let need = hasNeed(scoreObj.Factuel, scoreObj.Declaratif);

                // Retirer l’ancien badge
                let h5 = item.querySelector('h5');
                let oldBadge = h5.querySelector('.badge-besoin, .badge-secondary');
                if (oldBadge) oldBadge.remove();

                if (need) {
                    let besoinSpan = document.createElement('span');
                    besoinSpan.className = 'badge-besoin ms-2';
                    besoinSpan.textContent = 'Besoin';
                    h5.appendChild(besoinSpan);
                } else {
                    let noNeedSpan = document.createElement('span');
                    noNeedSpan.className = 'badge badge-secondary ms-2';
                    noNeedSpan.textContent = 'Aucun besoin (score élevé)';
                    h5.appendChild(noNeedSpan);
                }
            }

            /**
             * Ajoute dans recContainer la liste des formations trouvées ET des manques,
             * tout en remplissant foundTrainingsRef[] et missingTrainingsRef[] pour la synthèse.
             */
            function updateRecommendationsAndMissing(
                techId,
                level,
                specName,
                selectedBrand,
                recommendationsContainer, // container for recommendations
                needsContainer, // container for needs
                foundTrainingsRef, // array
                missingTrainingsRef // array
            ) {
                // Vider les containers
                recommendationsContainer.innerHTML = '';
                needsContainer.innerHTML = '';

                // 1) Récupérer les recommandations
                let recs = (recommendationData.trainings &&
                        recommendationData.trainings[techId] &&
                        recommendationData.trainings[techId][level]) ?
                    recommendationData.trainings[techId][level] : {};
                let foundRecommendations = false;

                // Parcourir les recommandations
                for (let code in recs) {
                    let trainInfo = recs[code];
                    // Filtrer par spécialité
                    let arrSpec = Array.isArray(trainInfo.speciality) ?
                        trainInfo.speciality.map(s => s.toLowerCase()) : [];
                    if (!arrSpec.includes(specName.toLowerCase())) {
                        continue;
                    }
                    // Filtrer par marque
                    if (selectedBrand !== 'all') {
                        let brandUp = (trainInfo.brand || '').toUpperCase();
                        let chosenUp = selectedBrand.toUpperCase();
                        if (brandUp !== chosenUp) {
                            continue;
                        }
                    }
                    // Ajouter la recommandation
                    foundRecommendations = true;

                    // Créer le badge
                    let span = document.createElement('span');
                    span.className = 'badge badge-light-info me-1';
                    span.textContent = `${trainInfo.trainingType} - ${trainInfo.code}`;
                    recommendationsContainer.appendChild(span);

                    // Ajouter la raison
                    let reasonPara = document.createElement('p');
                    reasonPara.className = 'mb-2 reason-text';
                    // Gérer le cas où la raison est undefined
                    let reasonText = trainInfo.reason || 'Raison non spécifiée';
                    reasonPara.textContent = `Raison : ${reasonText}`;
                    recommendationsContainer.appendChild(reasonPara);

                    // Ajouter à la synthèse
                    foundTrainingsRef.push({
                        code: trainInfo.code,
                        trainingType: trainInfo.trainingType,
                        brand: trainInfo.brand,
                        spec: specName,
                        reason: trainInfo.reason || 'Raison non spécifiée'
                    });
                }

                if (!foundRecommendations) {
                    let span = document.createElement('span');
                    span.className = 'badge badge-light-secondary';
                    span.textContent = 'Aucune recommandation disponible.';
                    recommendationsContainer.appendChild(span);
                }

                // 2) Récupérer les besoins manquants
                let missingObj = (recommendationData.missingGroups &&
                        recommendationData.missingGroups[techId] &&
                        recommendationData.missingGroups[techId][level] &&
                        recommendationData.missingGroups[techId][level][specName]) ?
                    recommendationData.missingGroups[techId][level][specName] :
                    null;

                if (missingObj) {
                    // Parcourir chaque marque
                    for (let brand in missingObj) {
                        // Filtrer par marque
                        if (selectedBrand !== 'all' && brand.toUpperCase() !== selectedBrand.toUpperCase()) {
                            continue;
                        }
                        let info = missingObj[brand];
                        if (!info || !Array.isArray(info.trainingTypes)) {
                            continue;
                        }
                        // Parcourir chaque type de besoin
                        info.trainingTypes.forEach(tt => {
                            // Créer le badge pour le besoin
                            let span = document.createElement('span');
                            span.className = 'badge badge-light-warning me-1';
                            span.textContent = `${tt} (${brand})`;
                            needsContainer.appendChild(span);

                            // Ajouter la raison
                            let reasonPara = document.createElement('p');
                            reasonPara.className = 'mb-2 reason-text';
                            let reasonText = info.reason || 'Raison non spécifiée';
                            reasonPara.textContent = `Raison : ${reasonText}`;
                            needsContainer.appendChild(reasonPara);

                            // Ajouter à la synthèse
                            missingTrainingsRef.push({
                                trainingType: tt,
                                brand: brand,
                                spec: specName,
                                reason: info.reason || 'Raison non spécifiée'
                            });
                        });
                    }
                } else {
                    let span = document.createElement('span');
                    span.className = 'badge badge-light-secondary';
                    span.textContent = 'Aucun besoin complémentaire.';
                    needsContainer.appendChild(span);
                }
            }



            /**
             * Met à jour la synthèse globale (ul.synthese-list) en bas du modal
             */
            function updateSyntheseSection(techId, foundTrainingsAll, missingTrainingsAll) {
                let synthContainer = document.getElementById(`synthese-section${techId}`);
                if (!synthContainer) return;

                // Récupérer les deux <tbody> :
                let foundTbody = synthContainer.querySelector('.found-trainings-tbody');
                let missingTbody = synthContainer.querySelector('.missing-trainings-tbody');
                if (!foundTbody || !missingTbody) return;

                // Vider
                foundTbody.innerHTML = '';
                missingTbody.innerHTML = '';

                // 1) Formations trouvées
                if (foundTrainingsAll.length > 0) {
                    foundTrainingsAll.forEach(item => {
                        // item = { trainingType, brand, spec, code }
                        const tr = document.createElement('tr');

                        const tdType = document.createElement('td');
                        tdType.textContent = item.trainingType;

                        const tdBrand = document.createElement('td');
                        tdBrand.textContent = item.brand;

                        const tdSpec = document.createElement('td');
                        tdSpec.textContent = item.spec;

                        const tdCode = document.createElement('td');
                        tdCode.textContent = item.code; // ex. "TS-123", ou ce qu’il y a

                        const tdReason = document.createElement('td');
                        tdReason.textContent = item.reason; // **Ajouter la Raison**

                        tr.appendChild(tdType);
                        tr.appendChild(tdBrand);
                        tr.appendChild(tdSpec);
                        tr.appendChild(tdCode);
                        tr.appendChild(tdReason);

                        foundTbody.appendChild(tr);
                    });
                } else {
                    // Aucune formation trouvée => on ajoute une ligne
                    const tr = document.createElement('tr');
                    const td = document.createElement('td');
                    td.setAttribute('colspan', '5');
                    td.innerHTML = '<em>Aucune formation trouvée en base.</em>';
                    tr.appendChild(td);
                    foundTbody.appendChild(tr);
                }

                // 2) Besoins / Manques
                if (missingTrainingsAll.length > 0) {
                    missingTrainingsAll.forEach(item => {
                        // item = { trainingType, brand, spec }
                        const tr = document.createElement('tr');

                        const tdType = document.createElement('td');
                        tdType.textContent = item.trainingType;

                        const tdBrand = document.createElement('td');
                        tdBrand.textContent = item.brand;

                        const tdSpec = document.createElement('td');
                        tdSpec.textContent = item.spec;

                        const tdReason = document.createElement('td');
                        tdReason.textContent = item.reason; // **Ajouter la Raison**

                        tr.appendChild(tdType);
                        tr.appendChild(tdBrand);
                        tr.appendChild(tdSpec);
                        tr.appendChild(tdReason);

                        missingTbody.appendChild(tr);
                    });
                } else {
                    // Aucun besoin manquant
                    const tr = document.createElement('tr');
                    const td = document.createElement('td');
                    td.setAttribute('colspan', '4');
                    td.innerHTML = '<em>Aucun besoin complémentaire.</em>';
                    tr.appendChild(td);
                    missingTbody.appendChild(tr);
                }
            }


            /**
             * Complément : barres de progression
             */
            function updateProgressBar(barEl, val, label) {
                if (!barEl) return;
                let score = (val == null) ? 0 : parseInt(val, 10);
                if (isNaN(score)) score = 0;

                let cssClass = 'progress-bar bg-danger';
                if (score >= 80) cssClass = 'progress-bar bg-success';
                else if (score >= 60) cssClass = 'progress-bar bg-warning';

                barEl.className = cssClass;
                barEl.style.width = score + '%';
                barEl.setAttribute('aria-valuenow', score);
                barEl.innerText = `${label}: ${score}%`;
            }

            /**
             * Détermine si (f < 80) OU (d < 80)
             */
            function hasNeed(fact, decl) {
                let thr = 80;
                let f = (fact == null) ? 100 : parseInt(fact, 10);
                let d = (decl == null) ? 100 : parseInt(decl, 10);
                return (f < thr) || (d < thr);
            }

            /**
             * Gère le “pays => agence”
             */
            function setupCountryAgencyCascade() {
                let countrySel = document.getElementById('country-filter');
                let agencySel = document.getElementById('agency-filter');
                if (!countrySel || !agencySel) return;

                countrySel.addEventListener('change', function() {
                    let selCountry = countrySel.value;
                    agencySel.innerHTML = '<option value="all">Toutes les agences</option>';
                    if (selCountry === 'all') {
                        agencySel.disabled = true;
                    } else {
                        agencySel.disabled = false;
                        let agenciesMap = <?php echo json_encode($agencies); ?>;
                        if (agenciesMap[selCountry]) {
                            agenciesMap[selCountry].forEach(a => {
                                let opt = document.createElement('option');
                                opt.value = a;
                                opt.textContent = a;
                                agencySel.appendChild(opt);
                            });
                        }
                    }
                });
            }
        });
    </script>
</body>

</html>