<?php
require_once __DIR__ . '/../../core/medacap_config.php';
session_start();

include_once "../language.php";
include_once "../partials/background-manager.php"; // Background
include_once "partials/header.php";               // Doit définir $agencies
include_once "getValidatedResults.php";           // Logique des résultats
include_once "../userFilters.php";                // Filtrage utilisateurs

setPageBackground("bg-dashboard");

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION["profile"])) {
    header("Location: ../../");
    exit();
} else {

    // =========================
    // Params URL
    // =========================
    $selectedLevel   = isset($_GET['level'])   ? $_GET['level']   : 'Junior';
    $selectedCountry = isset($_GET['country']) ? $_GET['country'] : null; // 'all' = pas de filtre
    $selectedAgency  = isset($_GET['agency'])  ? $_GET['agency']  : null; // 'all' = pas de filtre
    $selectedUser    = isset($_GET['user'])    ? $_GET['user']    : null;

    // =========================
    // Contexte session
    // =========================
    $profile     = $_SESSION['profile'];
    $userCountry = isset($_SESSION['country']) ? $_SESSION['country'] : null;

    // =========================
    // Mongo + collections
    // =========================
    $conn    = new MongoDB\Client(medacapMongoUri());
    $academy = $conn->academy;
    $questionsCol = $academy->questions;

    require_once __DIR__ . '/../../core/user_access.php';
    if ($selectedUser !== null) {
        medacapRequireUserAccess($academy->users, $selectedUser);
    }

    // =========================
    // Pays / agences disponibles selon profil
    // =========================
    if ($profile == 'Directeur Groupe' || $profile == 'Super Admin') {
        $availableCountries = array_keys($agencies);
    } elseif ($profile == 'Directeur Général' && $userCountry) {
        $availableCountries = [$userCountry];
    } elseif ((($profile == 'Directeur Pièce et Service') || ($profile == 'Directeur des Opérations') || ($profile == 'Admin')) && $userCountry) {
        $availableCountries = [$userCountry];
    } else {
        $availableCountries = [];
    }

    // Niveau par défaut
    if (!$selectedLevel) $selectedLevel = 'Junior';

    // Pays par défaut
    if (!$selectedCountry) {
        if (($profile == 'Directeur Général' || $profile == 'Directeur Pièce et Service' || $profile == 'Directeur des Opérations' || $profile == 'Admin') && $userCountry) {
            $selectedCountry = $userCountry;
        } else {
            $selectedCountry = 'all';
        }
    }

    // Agences accessibles si "directeur filiale"
    if (($profile == 'Directeur Général' || $profile == 'Directeur Pièce et Service' || $profile == 'Directeur des Opérations' || $profile == 'Admin') && $userCountry) {
        $availableAgencies = isset($agencies[$userCountry]) ? $agencies[$userCountry] : [];
    } else {
        $availableAgencies = [];
    }

    // Helper: transformer 'all' → null (pas de filtre)
    $countryForQuery = ($selectedCountry === 'all' || empty($selectedCountry)) ? null : $selectedCountry;
    $agencyForQuery  = ($selectedAgency  === 'all' || empty($selectedAgency))  ? null : $selectedAgency;

    // =========================
    // Questions (déclaratives, actives, niveau sélectionné)
    // =========================
    $questionDecla = $questionsCol->find([
        "type"   => "Declarative",
        "level"  => $selectedLevel,
        "active" => true
    ])->toArray();

    $totalQuestions = count($questionDecla);

    // =========================
    // Résultats validés par niveau
    // =========================
    $tableauResultats       = getValidatedResults($selectedLevel);
    $tableauResultatsJunior = getValidatedResults('Junior');
    $tableauResultatsSenior = getValidatedResults('Senior');
    $tableauResultatsExpert = getValidatedResults('Expert');

    // =========================
    // Filtrage techniciens (niveau sélectionné, pays/agence)
    // =========================
    $technicians = filterUsersByProfile($academy, $profile, $countryForQuery, $selectedLevel, $agencyForQuery);

    // =========================
    // Libellé titre (selon niveau)
    // =========================
    $taux_de_cover = "";
    switch ($selectedLevel) {
        case 'Junior':
            $taux_de_cover = $taux_de_couverture_ju;
            break;
        case 'Senior':
            $taux_de_cover = $taux_de_couverture_se;
            break;
        case 'Expert':
            $taux_de_cover = $taux_de_couverture_ex;
            break;
        default:
            $taux_de_cover = $taux_de_couverture_ju;
            break;
    }

    // =========================
    // Stats maîtrise par question
    // =========================
function calculateQuestionMasteryStats($academy, $level, $country, $tableauResultats, $agency = null)
{
    $countryForQuery = ($country === 'all' || empty($country)) ? null : $country;
    $agencyForQuery  = ($agency  === 'all' || empty($agency))  ? null : $agency;

    $questions = $academy->questions->find([
        "type"   => "Declarative",
        "level"  => $level,
        "active" => true
    ])->toArray();

    $technicians = filterUsersByProfile($academy, $_SESSION['profile'], $countryForQuery, $level, $agencyForQuery);

    $totalQuestions                = count($questions);
    $totalNonMaitriseQuestions     = 0;
    $totalSingleMaitriseQuestions  = 0;
    $totalDoubleMaitriseQuestions  = 0;

    // >>> AJOUT
    $hasAnyResult = false;

    foreach ($questions as $question) {
        $totalMaitrise = 0;
        $questionId    = (string)$question['_id'];

        foreach ($technicians as $technician) {
            $techId = (string)$technician['_id'];

            if (isset($tableauResultats[$techId][$questionId])) {
                $status = $tableauResultats[$techId][$questionId];
                $hasAnyResult = true; // on a AU MOINS un résultat réel enregistré
            } else {
                $status = 'Non maîtrisé';
            }

            if ($status === "Maîtrisé") $totalMaitrise++;
        }

        if     ($totalMaitrise == 0) $totalNonMaitriseQuestions++;
        elseif ($totalMaitrise == 1) $totalSingleMaitriseQuestions++;
        elseif ($totalMaitrise == 2) $totalDoubleMaitriseQuestions++;
    }

    $numTechnicians = count($technicians);

    if ($numTechnicians < 3) {
        return [
            'totalQuestions'     => $totalQuestions,
            'nonMaitrise'        => $totalNonMaitriseQuestions,
            'singleMaitrise'     => $totalSingleMaitriseQuestions,
            'doubleMaitrise'     => 0,
            'othersCount'        => 0,
            'insufficientSample' => true,
            // >>> AJOUT
            'hasAnyResult'       => $hasAnyResult,
        ];
    }

    return [
        'totalQuestions'     => $totalQuestions,
        'nonMaitrise'        => $totalNonMaitriseQuestions,
        'singleMaitrise'     => $totalSingleMaitriseQuestions,
        'doubleMaitrise'     => $totalDoubleMaitriseQuestions,
        'othersCount'        => $totalQuestions - ($totalNonMaitriseQuestions + $totalSingleMaitriseQuestions + $totalDoubleMaitriseQuestions),
        'insufficientSample' => false,
        // >>> AJOUT
        'hasAnyResult'       => $hasAnyResult,
    ];
}


    function calculatePercentages($stats)
    {
        $total = $stats['totalQuestions'];
        if (isset($stats['insufficientSample']) && $stats['insufficientSample']) {
            $nm = ($total > 0) ? round(($stats['nonMaitrise']    / $total) * 100) : 0;
            $sm = ($total > 0) ? round(($stats['singleMaitrise'] / $total) * 100) : 0;
            return [
                'nonMaitrise'    => $nm,
                'singleMaitrise' => $sm,
                'doubleMaitrise' => 0,
                'others'         => max(0, 100 - ($nm + $sm))
            ];
        }
        $nm = ($total > 0) ? round(($stats['nonMaitrise']    / $total) * 100) : 0;
        $sm = ($total > 0) ? round(($stats['singleMaitrise'] / $total) * 100) : 0;
        $dm = ($total > 0) ? round(($stats['doubleMaitrise'] / $total) * 100) : 0;
        return [
            'nonMaitrise'    => $nm,
            'singleMaitrise' => $sm,
            'doubleMaitrise' => $dm,
            'others'         => max(0, 100 - ($nm + $sm + $dm))
        ];
    }

    // Stats par niveau
    $statsJunior = calculateQuestionMasteryStats($academy, 'Junior', $selectedCountry, $tableauResultatsJunior, $selectedAgency);
    $statsSenior = calculateQuestionMasteryStats($academy, 'Senior', $selectedCountry, $tableauResultatsSenior, $selectedAgency);
    $statsExpert = calculateQuestionMasteryStats($academy, 'Expert', $selectedCountry, $tableauResultatsExpert, $selectedAgency);

    $percentagesJunior = calculatePercentages($statsJunior);
    $percentagesSenior = calculatePercentages($statsSenior);
    $percentagesExpert = calculatePercentages($statsExpert);

    // Total (tous niveaux)
    $totalQuestionsAllLevels     = $statsJunior['totalQuestions'] + $statsSenior['totalQuestions'] + $statsExpert['totalQuestions'];
    $totalNonMaitriseAllLevels   = $statsJunior['nonMaitrise']    + $statsSenior['nonMaitrise']    + $statsExpert['nonMaitrise'];
    $totalSingleMaitriseAllLevels = $statsJunior['singleMaitrise'] + $statsSenior['singleMaitrise'] + $statsExpert['singleMaitrise'];
    $totalDoubleMaitriseAllLevels = $statsJunior['doubleMaitrise'] + $statsSenior['doubleMaitrise'] + $statsExpert['doubleMaitrise'];

    // Techs par niveau (pour compter)
    $techniciansJunior = filterUsersByProfile($academy, $profile, $countryForQuery, 'Junior', $agencyForQuery);
    $techniciansSenior = filterUsersByProfile($academy, $profile, $countryForQuery, 'Senior', $agencyForQuery);
    $techniciansExpert = filterUsersByProfile($academy, $profile, $countryForQuery, 'Expert',  $agencyForQuery);

    $numberOfTechniciansJunior = count($techniciansJunior);
    $numberOfTechniciansSenior = count($techniciansSenior);
    $numberOfTechniciansExpert = count($techniciansExpert);

    // Total techniciens uniques
    $allTechnicians = array_merge($techniciansJunior, $techniciansSenior, $techniciansExpert);
    $technicianIds  = [];
    foreach ($allTechnicians as $technician) $technicianIds[] = (string)$technician['_id'];
    $numberOfTechniciansTotal = count(array_unique($technicianIds));

    $statsTotal = [
        'totalQuestions'    => $totalQuestionsAllLevels,
        'nonMaitrise'       => $totalNonMaitriseAllLevels,
        'singleMaitrise'    => $totalSingleMaitriseAllLevels,
        'doubleMaitrise'    => $totalDoubleMaitriseAllLevels,
        'insufficientSample' => ($numberOfTechniciansTotal <= 3)
    ];

    // Compléments
    $statsJunior['othersCount'] = $statsJunior['totalQuestions'] - ($statsJunior['nonMaitrise'] + $statsJunior['singleMaitrise'] + $statsJunior['doubleMaitrise']);
    $statsSenior['othersCount'] = $statsSenior['totalQuestions'] - ($statsSenior['nonMaitrise'] + $statsSenior['singleMaitrise'] + $statsSenior['doubleMaitrise']);
    $statsExpert['othersCount'] = $statsExpert['totalQuestions'] - ($statsExpert['nonMaitrise'] + $statsExpert['singleMaitrise'] + $statsExpert['doubleMaitrise']);
    $statsTotal['othersCount']  = $statsTotal['totalQuestions']  - ($statsTotal['nonMaitrise']  + $statsTotal['singleMaitrise']  + $statsTotal['doubleMaitrise']);

    $statsJunior['numTechnicians'] = $numberOfTechniciansJunior;
    $statsSenior['numTechnicians'] = $numberOfTechniciansSenior;
    $statsExpert['numTechnicians'] = $numberOfTechniciansExpert;
    $statsTotal['numTechnicians']  = $numberOfTechniciansTotal;

    $percentagesTotal = calculatePercentages($statsTotal);

    // Libellés
    $junior     = 'Junior';
    $senior = 'Senior';
    $expert = 'Expert';
    $total = 'Total';
    $junior_tp  = $junior_tp ?? 'Junior';
    $senior_tp  = $senior_tp ?? 'Senior';
    $expert_tp  = $expert_tp ?? 'Expert';
    $total_tp   = $total_tp  ?? 'Total';

    // Nombre total de tâches par niveau
    $numberOfTasksJunior = $statsJunior['totalQuestions'];
    $numberOfTasksSenior = $statsSenior['totalQuestions'];
    $numberOfTasksExpert = $statsExpert['totalQuestions'];
    $numberOfTasksTotal  = $statsTotal['totalQuestions'];

    // Légendes selon nb de techniciens
    function getLegendMessage($numTechnicians)
    {
        if ($numTechnicians == 1) {
            return [
                'nonMaitrise' => "Aucun technicien maîtrise",
                'singleMaitrise' => "Le technicien maîtrise"
            ];
        } elseif ($numTechnicians == 2) {
            return [
                'nonMaitrise' => "Aucun technicien maîtrise",
                'singleMaitrise' => "1 technicien maîtrise",
                'allMaitrise' => "Les 2 techniciens maîtrisent"
            ];
        } elseif ($numTechnicians == 3) {
            return [
                'nonMaitrise' => "Aucun technicien maîtrise",
                'singleMaitrise' => "1 seul technicien maîtrise",
                'doubleMaitrise' => "Seuls 2 techniciens maîtrisent",
                'allMaitrise' => "Les 3 techniciens maîtrisent"
            ];
        } else {
            return [
                'nonMaitrise' => "Aucun technicien maîtrise",
                'singleMaitrise' => "1 seul technicien maîtrise",
                'doubleMaitrise' => "Seuls 2 techniciens maîtrisent",
                'moreMaitrise' => "Plus de 3 techniciens maîtrisent"
            ];
        }
    }

    $legendsJunior = getLegendMessage($numberOfTechniciansJunior);
    $legendsSenior = getLegendMessage($numberOfTechniciansSenior);
    $legendsExpert = getLegendMessage($numberOfTechniciansExpert);
    $legendsTotal  = getLegendMessage($numberOfTechniciansTotal);

    // Tooltips / textes
    $Tasks_Professional_Singular = "tâche";
    $Tasks_Professional_Plural   = "tâches";
    $Maitrise_techniciens        = "Maîtrise des techniciens";
    $nbreTechie                  = "Nombre de techniciens";
    $nbre_task                   = "Nombre de tâches";
    $task_list                   = "Liste des tâches";

    // =========================
// === KPI TOP PAYS / AGENCES (selon contexte) ===
// =========================

// mapping nom pays -> code ISO2 (inchangé)
function countryCodeFromName($name)
{
    static $map = [
        'Côte d\'Ivoire' => 'ci', 'Cote d\'Ivoire' => 'ci', 'Côte d’Ivoire' => 'ci', 'Ivory Coast' => 'ci',
        'Sénégal' => 'sn', 'Senegal' => 'sn', 'Cameroun' => 'cm', 'Cameroon' => 'cm', 'Gabon' => 'ga',
        'Ghana' => 'gh', 'Nigeria' => 'ng', 'Kenya' => 'ke', 'Togo' => 'tg', 'Bénin' => 'bj', 'Benin' => 'bj',
        'Congo' => 'cg', 'République du Congo' => 'cg', 'RDC' => 'cd', 'République Démocratique du Congo' => 'cd',
        'Mali' => 'ml', 'Burkina Faso' => 'bf', 'Niger' => 'ne', 'Tchad' => 'td', 'Madagascar' => 'mg',
        'Mauritanie' => 'mr', 'Maroc' => 'ma', 'Tunisie' => 'tn', 'Algérie' => 'dz', 'Sierra Leone' => 'sl',
        'Liberia' => 'lr', 'Ouganda' => 'ug', 'Uganda' => 'ug', 'Tanzanie' => 'tz', 'Tanzania' => 'tz',
        'Ethiopie' => 'et', 'Éthiopie' => 'et', 'Ethiopia' => 'et', 'Afrique du Sud' => 'za', 'South Africa' => 'za',
        'Guinée' => 'gn', 'Guinea' => 'gn', 'Guinée-Bissau' => 'gw', 'Guinea-Bissau' => 'gw',
        'Guinée équatoriale' => 'gq', 'Equatorial Guinea' => 'gq', 'RCA' => 'cf',
        'République Centrafricaine' => 'cf', 'Central African Republic' => 'cf', 'Rwanda' => 'rw'
    ];

    if (isset($map[$name])) return $map[$name];
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $name);
    $code  = strtolower(substr(preg_replace('/[^A-Za-z]/', '', $ascii ?: $name), 0, 2));
    return $code;
}
function getFlagWebPath($countryName, $ratio = '4x3')
{
    $code = countryCodeFromName($countryName);
    $baseFs  = __DIR__ . "/../define/flags/$ratio/$code";
    $baseWeb = "../define/flags/$ratio/$code";
    $exts = [".svg", ".png", ".jpg", ".webp"];
    foreach ($exts as $ext) {
        if (file_exists($baseFs . $ext)) return $baseWeb . $ext;
    }
    return null;
}

// Choisir la table de résultats correspondant au niveau sélectionné
$resultsByLevel = [
    'Junior' => $tableauResultatsJunior,
    'Senior' => $tableauResultatsSenior,
    'Expert' => $tableauResultatsExpert
];
$resultsForKpiLevel = isset($resultsByLevel[$selectedLevel]) ? $resultsByLevel[$selectedLevel] : $tableauResultatsJunior;

// — Mode KPI : pays vs agences —
// Si un pays est fixé (sélection explicite OU profil sans filtre pays), on passe en "agences".
// Sinon (vue globale), on reste en "pays".
$canChooseCountry = ($profile === 'Directeur Groupe' || $profile === 'Super Admin');
$countryContext = null;
if (!empty($selectedCountry) && $selectedCountry !== 'all') {
    $countryContext = $selectedCountry;
} elseif (!$canChooseCountry && !empty($userCountry)) {
    $countryContext = $userCountry;
}

$kpiWinners = [
    'nonMaitrise'    => ['label' => null, 'percent' => -1],
    'singleMaitrise' => ['label' => null, 'percent' => -1],
    'doubleMaitrise' => ['label' => null, 'percent' => -1],
    'others'         => ['label' => null, 'percent' => -1],
];
$kpiFlags = [];

if ($countryContext) {
    // ===== Mode AGENCES : on cherche les meilleures agences DU pays $countryContext =====
    $iterAgencies = isset($agencies[$countryContext]) ? $agencies[$countryContext] : [];

    // garder uniquement les agences ayant au moins 1 technicien au niveau
    $eligibleAgencies = [];
    foreach ($iterAgencies as $a) {
        $techs = filterUsersByProfile($academy, $profile, $countryContext, $selectedLevel, $a);
        if (!empty($techs)) $eligibleAgencies[] = $a;
    }

    if (!empty($eligibleAgencies)) {
        foreach ($eligibleAgencies as $a) {
            $st = calculateQuestionMasteryStats($academy, $selectedLevel, $countryContext, $resultsForKpiLevel, $a);

            // >>> AJOUT : ignorer l’agence si aucun résultat réel
            if (empty($st['hasAnyResult'])) continue;

            $pc = calculatePercentages($st);
            foreach (['nonMaitrise','singleMaitrise','doubleMaitrise','others'] as $key) {
                if ($pc[$key] > $kpiWinners[$key]['percent']) {
                    $kpiWinners[$key] = ['label' => $a, 'percent' => $pc[$key]];
                }
            }
        }

    }

    // Un seul drapeau : celui du pays de contexte
    $commonFlag = getFlagWebPath($countryContext, '4x3');
    foreach (['nonMaitrise','singleMaitrise','doubleMaitrise','others'] as $key) {
        $kpiFlags[$key] = $commonFlag;
    }

} else {
    // ===== Mode PAYS : comportement global (toutes filiales ayant au moins 1 technicien au niveau) =====
    $iterCountriesRaw = !empty($availableCountries) ? $availableCountries : array_keys($agencies);
    $iterCountries = [];
    foreach ($iterCountriesRaw as $c) {
        $techsAtLevel = filterUsersByProfile($academy, $profile, $c, $selectedLevel, null);
        if (!empty($techsAtLevel)) $iterCountries[] = $c;
    }

    if (!empty($iterCountries)) {
        foreach ($iterCountries as $c) {
            $st = calculateQuestionMasteryStats($academy, $selectedLevel, $c, $resultsForKpiLevel, null);

            // >>> AJOUT : ignorer le pays si aucun résultat réel
            if (empty($st['hasAnyResult'])) continue;

            $pc = calculatePercentages($st);
            foreach (['nonMaitrise','singleMaitrise','doubleMaitrise','others'] as $key) {
                if ($pc[$key] > $kpiWinners[$key]['percent']) {
                    $kpiWinners[$key] = ['label' => $c, 'percent' => $pc[$key]];
                }
            }
        }
    }

    // Un drapeau par gagnant (pays)
    foreach ($kpiWinners as $key => $info) {
        $kpiFlags[$key] = (!empty($info['label'])) ? getFlagWebPath($info['label'], '4x3') : null;
    }
}

?>
    <!--begin::Title-->
    <title><?php echo $taux_de_cover ?> | CFAO Mobility Academy</title>
    <link href="../../public/assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
    <link href="../../public/assets/css/style.bundle.css" rel="stylesheet" type="text/css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-theme/0.1.0-beta.10/select2-bootstrap.min.css" rel="stylesheet" />

    <style>

        /* =========================
   Base "glassmorphism" + depth
   ========================= */
        .glass-effect {
            background: rgba(255, 255, 255, .72) !important;
            backdrop-filter: blur(18px) !important;
            -webkit-backdrop-filter: blur(18px) !important;
            border: 1px solid rgba(255, 255, 255, .18) !important;
            box-shadow: 0 8px 32px rgba(31, 38, 135, .15) !important;
            border-radius: 16px !important;
            transition: transform .25s ease, box-shadow .25s ease, background .25s ease !important;
        }

        .glass-effect:hover {
            background: rgba(255, 255, 255, .82) !important;
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(31, 38, 135, .20) !important;
        }

        .chart-glass-effect {
            /* pour tes charts doughnut */
            background: rgba(255, 255, 255, .72) !important;
            backdrop-filter: blur(25px) !important;
            -webkit-backdrop-filter: blur(25px) !important;
            border: 1px solid rgba(255, 255, 255, .18) !important;
            border-radius: 16px !important;
            box-shadow: 0 8px 32px rgba(31, 38, 135, .15) !important;
            transition: transform .25s ease, box-shadow .25s ease, background .25s ease !important;
        }

        .depth-effect {
            transform: translateZ(0);
            box-shadow:
                0 4px 6px -1px rgba(0, 0, 0, .10),
                0 2px 4px -1px rgba(0, 0, 0, .06),
                0 12px 20px -2px rgba(0, 0, 0, .05) !important;
            transition: transform .25s ease, box-shadow .25s ease !important;
        }

        .depth-effect:hover {
            transform: translateY(-5px) translateZ(0);
            box-shadow:
                0 10px 15px -3px rgba(0, 0, 0, .10),
                0 4px 6px -2px rgba(0, 0, 0, .05),
                0 20px 25px -5px rgba(0, 0, 0, .03) !important;
        }

        /* =========================
   Cartes + tableaux + charts
   ========================= */
        .card {
            background: transparent;
            border: none;
        }

        .card-body {
            padding: .75rem;
        }

        .chart-box {
            max-width: 400px;
            max-height: 550px;
            margin: auto;
            padding: 20px;
        }

        .stats-frame {
            border-top: 1px solid white;
            padding-top: 10px;
            display: flex;
            justify-content: space-around;
            margin-top: 15px;
        }

        .stats-frame p {
            margin: 0;
            font-size: 1.8em;
            font-weight: 800;
            color: white !important;
        }

        .stats-frame span {
            display: block;
            font-size: .95em;
            color: white !important;
        }

        /* Select2 tweaks */
        .select2-container--bootstrap4 .select2-results__option[aria-selected="true"] { opacity: .5; }
        /* Gabarit de hauteur + centrage du contenu dans la zone sélectionnée */
        .select2-container--bootstrap4 .select2-selection--single {
            position: relative;
            height: calc(2.25rem + 2px);   /* hauteur bootstrap form-select */
            min-height: calc(2.25rem + 2px);
            display: flex; align-items: center;
            padding: 0 .75rem;             /* cohérent avec form-select */
            box-sizing: border-box;
        }
        .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
            line-height: normal !important;
            display: flex; align-items: center; gap: 8px;
            padding-left: 0;               /* on gère le padding sur le conteneur */
            margin-right: 0;               /* pas de décalage inutile */
        }
        .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
            right: .75rem;              /* écart droite */
            width: 1.25rem;             /* largeur flèche */
            top: 0; bottom: 0;          /* centre vertical robuste */
            transform: none;
            display: flex; align-items: center; height: 100%;
        }
        .select2-container--bootstrap4 .select2-selection--single .select2-selection__clear {
            position: absolute;
            right: calc(.75rem + 1.25rem + .25rem); /* à gauche de la flèche, avec un petit gap */
            top: 0; bottom: 0;                       /* centre vertical robuste */
            transform: none;
            display: flex; align-items: center;      /* centre le “x” dans toute la hauteur */
            height: 100%;
            font-weight: 700; color: #777; margin: 0;
        }
        /* réserver assez d'espace côté droit pour le texte (clear + flèche) */
        .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
            padding-right: calc(.75rem + 1.25rem + 1.25rem + .75rem);
        }

        /* Chart empty overlay */
        .chart-rel { position: relative; }
        .chart-rel .no-data-overlay {
            position: absolute; inset: 0; display: none; align-items: center; justify-content: center;
            color: #555; font-weight: 700; background: linear-gradient(180deg, rgba(255,255,255,.65), rgba(255,255,255,.4));
            backdrop-filter: blur(2px); border-radius: 12px; pointer-events: none;
        }

        /* =========================
   KPI — design commun
   ========================= */
        .kpi-row .col-md-3>.card {
            padding: 16px 14px;
        }

        .kpi-card {
            position: relative;
            overflow: hidden;
        }

        .kpi-card::before {
            /* fine barre de couleur en haut */
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 4px;
            background: var(--kpi-color, #999);
        }

        .kpi-card .card-header {
            background: transparent !important;
            border: 0 !important;
            padding: 10px 6px 6px 6px;
            text-align: center;
            display: flex;               /* centrage robuste */
            align-items: center;
            justify-content: center;
        }

        .kpi-icon-container {
            margin-bottom: .5rem;
        }

        .kpi-icon-container i {
            transition: transform .25s ease;
        }

        .kpi-card:hover .kpi-icon-container i {
            transform: scale(1.08);
        }

        .kpi-title {
            font-size: .95rem;
            line-height: 1.35;
            font-weight: 800;
            margin: 0;
            text-align: center !important;
        }

        .kpi-chip {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            display: inline-block;
            margin-right: 8px;
        }

        .kpi-value {
            font-size: 2.6rem;
            font-weight: 800;
            line-height: 1;
        }

        .kpi-suffix {
            font-size: 1.15rem;
            font-weight: 700;
            margin-left: .25rem;
            opacity: .85;
        }

        .kpi-flag {
            width: 28px;
            height: 18px;
            object-fit: cover;
            border-radius: 3px;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, .08), 0 2px 6px rgba(0, 0, 0, .15);
        }

        .kpi-country {
            font-weight: 700;
            font-size: 1rem;
            text-align: center;
        }

        /* palettes */
        .kpi-red {
            --kpi-color: #dc3545;
        }

        .kpi-red .kpi-title,
        .kpi-red .kpi-suffix {
            color: #dc3545;
        }

        .kpi-red .kpi-chip {
            background: #dc3545;
        }

        .kpi-orange {
            --kpi-color: #fd7e14;
        }

        .kpi-orange .kpi-title,
        .kpi-orange .kpi-suffix {
            color: #fd7e14;
        }

        .kpi-orange .kpi-chip {
            background: #fd7e14;
        }

        .kpi-green {
            --kpi-color: #198754;
        }

        .kpi-green .kpi-title,
        .kpi-green .kpi-suffix {
            color: #198754;
        }

        .kpi-green .kpi-chip {
            background: #198754;
        }

        /* Gris (non maîtrisé) */
        .kpi-gray {
            --kpi-color: #6c757d; /* bootstrap secondary */
        }
        .kpi-gray .kpi-title,
        .kpi-gray .kpi-suffix {
            color: #6c757d;
        }
        .kpi-gray .kpi-chip { background: #6c757d; }

        /* Cartouche explainer au-dessus des KPI */
        .kpi-explainer .card-body {
            padding: 16px !important;
        }

        .kpi-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
            font-size: .95rem;
        }

        .kpi-legend .dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            display: inline-block;
            margin-right: 6px;
        }

        .kpi-legend .red {
            background: #dc3545;
        }

        .kpi-legend .orange {
            background: #fd7e14;
        }

        .kpi-legend .green {
            background: #198754;
        }

        /* Petits breakpoints : garder les KPI lisibles */
        @media (max-width: 991.98px) {
            .kpi-row .col-md-3 {
                margin-bottom: .75rem;
            }
        }

        /* =============== Skeleton loader (shimmer) =============== */
.section { position: relative; }
.section .section-content { transition: opacity .2s ease; }
.section.loading .section-content { opacity: 0; }

.skeleton-holder { display: none; }
.section.loading .skeleton-holder { display: block; }

.skel {
  position: relative;
  overflow: hidden;
  background: #eef2f7;              /* gris clair */
  border-radius: 10px;
  min-height: 28px;
}
.skel::after {
  content: "";
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(90deg, rgba(255,255,255,0) 0%,
                                     rgba(255,255,255,.6) 50%,
                                     rgba(255,255,255,0) 100%);
  animation: shimmer 1.2s infinite;
}
@keyframes shimmer { 100% { transform: translateX(100%); } }

/* Squelettes prêts-à-l’emploi pour nos 3 zones */
.skel-card { height: 260px; margin: 14px; }
.skel-chip { height: 18px; width: 60%; margin: 8px auto; }
.skel-line { height: 16px; margin: 10px 0; }
.skel-line.sm { height: 12px; }
.skel-line.lg { height: 22px; }

/* Table skeleton: lignes */
.table-skel-row { display: grid; grid-template-columns: 2fr 1fr repeat(4,1fr); gap: 10px; margin: 8px 0; }

    </style>

    <!--end::Title-->

    <!--begin::Content-->
    <?php openBackgroundContainer(); ?>
    <!--begin::Post-->
    <div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
        <!--begin::Container-->
        <div class=" container-xxl ">
            <!--begin::Title-->
            <div style="margin-top: 25px; margin-bottom: 25px">
                <div class="card " style="background-color: white;">
                    <div class="card-body text-center p-4">

                        <h1 class="text-dark fw-bolder my-1 fs-1">
                            <i class="ki-duotone ki-chart-line-up fs-2x me-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <?php echo $taux_de_cover ?>
                        </h1>


                    </div>
                </div>
            </div>
            <!--end::Title-->

            <!--begin::Filtres -->
            <div style="margin-top: 25px; margin-bottom: 25px">
                <div class="card glass-effect depth-effect">
                    <div class="card-body p-4">
                        <div class="row g-3 justify-content-center align-items-center">
                            <?php if ($profile === "Directeur Groupe" || $profile === "Super Admin") { ?>
                                <!-- Filtre Pays -->
                                <div class="col-md-6">
                                    <label for="country-select" class="form-label d-flex align-items-center justify-content-center">
                                        <i class="bi bi-geo-alt-fill fs-2 me-2 text-primary"></i> Filiales
                                    </label>
                                    <select id="country-select" onchange="applyFilters()" name="country" class="form-select" data-control="select2" data-placeholder="Tous les pays" data-allow-clear="true">
                                        <option value="all" <?php if ($selectedCountry === 'all') echo 'selected'; ?>>Tous les pays</option>
                                        <?php foreach ($availableCountries as $countryOption): ?>
                                            <option value="<?php echo htmlspecialchars($countryOption); ?>" data-flag="<?php echo htmlspecialchars(getFlagWebPath($countryOption, '4x3') ?? ''); ?>"
                                                <?php if ($selectedCountry === $countryOption) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($countryOption); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php } elseif ((($profile == 'Directeur Général') || ($profile == 'Directeur Pièce et Service') || ($profile == 'Directeur des Opérations') || ($profile == 'Admin')) && $userCountry) { ?>
                                <!-- Filtre Agences -->
                                <div class="col-md-6">
                                    <label for="agency-select" class="form-label d-flex align-items-center justify-content-center">
                                        <i class="bi bi-building fs-2 me-2 text-warning"></i> Agence
                                    </label>
                                    <?php $singleAgency = (count($availableAgencies) === 1); ?>
                                    <select id="agency-select" onchange="applyFilters()" name="agency" class="form-select<?php if ($singleAgency) echo ' opacity-50'; ?>" data-control="select2" data-placeholder="Toutes les agences" data-allow-clear="true" <?php if ($singleAgency) echo 'disabled title="Une seule agence disponible"'; ?>>
                                        <?php if (!$singleAgency): ?>
                                        <option value="all" <?php if ($selectedAgency === 'all' || $selectedAgency == null) echo 'selected'; ?>>Toutes les agences</option>
                                        <?php endif; ?>
                                        <?php if (isset($agencies[$userCountry])) {
                                            foreach ($availableAgencies as $agencyOption) { ?>
                                                <option value="<?php echo htmlspecialchars($agencyOption); ?>" data-icon="bi bi-building"
                                                    <?php if ($singleAgency || $selectedAgency === $agencyOption) echo 'selected'; ?>>
                                                    <?php echo htmlspecialchars($agencyOption); ?>
                                                </option>
                                        <?php }
                                        } ?>
                                    </select>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Filtres -->
            <br>

            <!--begin::Row-->
            <div class="row g-6 g-xl-9 mb-6 mb-xl-9">
                <!-- Graphiques -->
                <div class="chart-colors container">
                    <div class="row text-center">
                        <!-- Junior -->
                        <div class="col-md-3">
                            <div class="card chart-glass-effect depth-effect chart-box p-3">
                                <h5 class="mb-4"><?php echo $junior_tp; ?></h5>
                                <div class="chart-rel">
                                    <canvas id="chartJunior" height="300"></canvas>
                                    <div class="no-data-overlay">Aucune donnée</div>
                                </div>
                                <div class="stats-frame">
                                    <div>
                                        <p><span class="countup" data-count="<?= (int)$numberOfTasksJunior; ?>">0</span></p>
                                        <span><?php echo $nbre_task; ?></span>
                                    </div>
                                    <div>
                                        <p><span class="countup" data-count="<?= (int)$numberOfTechniciansJunior; ?>">0</span></p>
                                        <span><?php echo $nbreTechie; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Senior -->
                        <div class="col-md-3">
                            <div class="card chart-glass-effect depth-effect chart-box p-3">
                                <h5 class="mb-4"><?php echo $senior_tp; ?></h5>
                                <div class="chart-rel">
                                    <canvas id="chartSenior" height="300"></canvas>
                                    <div class="no-data-overlay">Aucune donnée</div>
                                </div>
                                <div class="stats-frame">
                                    <div>
                                        <p><span class="countup" data-count="<?= (int)$numberOfTasksSenior; ?>">0</span></p>
                                        <span><?php echo $nbre_task; ?></span>
                                    </div>
                                    <div>
                                        <p><span class="countup" data-count="<?= (int)$numberOfTechniciansSenior; ?>">0</span></p>
                                        <span><?php echo $nbreTechie; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Expert -->
                        <div class="col-md-3">
                            <div class="card chart-glass-effect depth-effect chart-box p-3">
                                <h5 class="mb-4"><?php echo $expert_tp; ?></h5>
                                <div class="chart-rel">
                                    <canvas id="chartExpert" height="300"></canvas>
                                    <div class="no-data-overlay">Aucune donnée</div>
                                </div>
                                <div class="stats-frame">
                                    <div>
                                        <p><span class="countup" data-count="<?= (int)$numberOfTasksExpert; ?>">0</span></p>
                                        <span><?php echo $nbre_task; ?></span>
                                    </div>
                                    <div>
                                        <p><span class="countup" data-count="<?= (int)$numberOfTechniciansExpert; ?>">0</span></p>
                                        <span><?php echo $nbreTechie; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Total -->
                        <div class="col-md-3">
                            <div class="card chart-glass-effect depth-effect chart-box p-3">
                                <h5 class="mb-4"><?php echo $total_tp; ?></h5>
                                <div class="chart-rel">
                                    <canvas id="chartTotal" height="300"></canvas>
                                    <div class="no-data-overlay">Aucune donnée</div>
                                </div>
                                <div class="stats-frame">
                                    <div>
                                        <p><span class="countup" data-count="<?= (int)$numberOfTasksTotal; ?>">0</span></p>
                                        <span><?php echo $nbre_task; ?></span>
                                    </div>
                                    <div>
                                        <p><span class="countup" data-count="<?= (int)$numberOfTechniciansTotal; ?>">0</span></p>
                                        <span><?php echo $nbreTechie; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- row -->
                </div>
                <!-- Fin graphiques -->
            </div>
   <!-- Filtre Niveaux -->
            <div style="margin-top: 55px; margin-bottom: 25px">
                <div class="card glass-effect depth-effect">
                    <div class="card-body p-4">
                        <div class="row justify-content-center">
                            <div class="col-md-6">
                                <label for="level-select" class="form-label d-flex align-items-center justify-content-center">
                                    <i class="bi bi-bar-chart-fill fs-2 me-2 text-warning"></i> Niveaux
                                </label>
                                <select id="level-select" onchange="applyFilters()" name="level" class="form-select" data-control="select2" data-placeholder="Niveau" data-allow-clear="true">
                                    <?php foreach (['Junior', 'Senior', 'Expert'] as $levelOption) { ?>
                                        <option value="<?php echo htmlspecialchars($levelOption); ?>" data-icon="bi bi-bar-chart-fill"
                                            <?php if ($selectedLevel === $levelOption) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($levelOption); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- end Filtre Niveaux -->
           <!-- CSS (à placer une seule fois dans votre feuille de style) -->
            <style>
            .kpi-explainer.card { border: 0; }
            .kpi-legend { display:flex; flex-wrap:wrap; gap:.75rem; justify-content:center; }
            .kpi-legend li {
                list-style:none; display:inline-flex; align-items:center; gap:.5rem;
                padding:.35rem .6rem; border:1px solid rgba(0,0,0,.07); border-radius:999px;
                background:#fff;
            }
            .kpi-dot {
                width:.85rem; height:.85rem; border-radius:50%; display:inline-block;
                box-shadow: inset 0 0 0 2px #fff; outline:1px solid rgba(0,0,0,.15);
            }
            .dot-red-a { background:#dc3545; } /* Rouge A : TP non maîtrisées */
            /* Rouge B : même teinte mais motif pour distinction visuelle */
            .dot-red-b {
                background:
                repeating-linear-gradient(45deg, #dc3545 0 .22rem, #c71f30 .22rem .44rem);
            }
            .dot-grey { background:#6c757d; }.dot-orange { background:#fd7e14; }
            .dot-green  { background:#198754; }
            </style>

            <!-- ====== Indicateurs de performance des filiales (cartouche explicatif) ====== -->
            <!-- <section class="my-2" role="note" aria-label="Légende des indicateurs de performance">
            <div class="card kpi-explainer bg-white shadow-sm">
                <div class="card-body text-center">
                <h5 class="fw-bold mb-2">
                    Indicateurs de performance des filiales — Couverture des Tâches Professionnelles (TP) par niveau
                </h5>
                <br>
                <span>Légende : La Filiale / Agence  ayant : </span>

                <ul class="kpi-legend mb-0">
                    <li>
                    <span class="kpi-dot dot-grey" aria-hidden="true"></span>
                    <span><strong>Gris</strong> : Le Plus de TP non maîtrisées</span>
                    </li>
                    <li>
                    <span class="kpi-dot dot-red-b" aria-hidden="true"></span>
                    <span><strong>Rouge B</strong> : Le Plus de TP maîtrisées par 1 seul technicien</span>
                    </li>
                    <li>
                    <span class="kpi-dot dot-orange" aria-hidden="true"></span>
                    <span><strong>Orange</strong> : Le Plus de TP maîtrisées uniquement par 2 techniciens</span>
                    </li>
                    <li>
                    <span class="kpi-dot dot-green" aria-hidden="true"></span>
                    <span><strong>Vert</strong> : Le Plus de TP maîtrisées par au moins 3 techniciens</span>
                    </li>
                </ul>
                </div>
            </div>
            </section> -->

            <!-- ====== KPI — TOP PAYS PAR SEGMENT ====== -->
            <div class="kpi-row" style="margin-top: 8px; margin-bottom: 20px;">
                <div class="row g-3">

                    


                  



                   

                    


                </div>
            </div>
            <!-- ====== /KPI ====== -->

            <!-- Tableau -->
            <div class="card glass-effect depth-effect">
                <div class="card-body pt-0">
                    <div id="kt_customers_table_wrapper" class="dataTables_wrapper dt-bootstrap4 no-footer">
                        <div class="table-responsive">
                            <table class="table align-middle table-bordered table-row-dashed fs-6 gy-5 dataTable no-footer" id="kt_customers_table">
                                <thead>
                                    <tr class="text-start text-black fw-bold fs-7 text-uppercase gs-0">
                                        <th class="min-w-200px sorting text-center text-uppercase" style="background-color: #EDF2F7; position: sticky; left: 0; z-index: 2;">
                                            <?php echo htmlspecialchars($titre_question); ?>
                                        </th>
                                        <th class="min-w-200px sorting text-center text-uppercase" style="background-color: #EDF2F7;">
                                            <?php echo htmlspecialchars($groupe_fonctionnel); ?>
                                        </th>
                                        <?php foreach ($technicians as $technician) { ?>
                                            <th class="min-w-150px sorting text-center text-uppercase" style="background-color: #EDF2F7;">
                                                <?php echo htmlspecialchars($technician['firstName'] . ' ' . $technician['lastName']); ?>
                                            </th>
                                        <?php } ?>
                                        <th class="min-w-150px sorting text-center text-uppercase" style="background-color: #EDF2F7;"><?php echo 'Totaux' ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    function getBootstrapClass($pourcentage)
                                    {
                                        if ($pourcentage >= 50) return 'text-danger';
                                        if ($pourcentage >= 10) return 'text-warning';
                                        return 'text-success';
                                    }

                                    $totalNonMaitriseQuestions = 0;
                                    $totalTechniciansCount     = count($technicians);

                                    foreach ($questionDecla as $question) {
                                        $totalMaitrise = 0;
                                        $questionId    = (string)$question['_id'];
                                    ?>
                                        <tr>
                                            <td class="text-center" style="background-color: #EDF2F7; position: sticky; left: 0;"><?php echo htmlspecialchars($question['label']); ?></td>
                                            <td class="text-center"><?php echo htmlspecialchars($question['speciality']); ?></td>

                                            <?php
                                            foreach ($technicians as $technician) {
                                                $techId = (string)$technician['_id'];
                                                $status = isset($tableauResultats[$techId][$questionId]) ? $tableauResultats[$techId][$questionId] : $non_maitrise;
                                                if ($status == "Maîtrisé") $totalMaitrise++;
                                                echo "<td class='text-center'>" . htmlspecialchars($status) . "</td>";
                                            } ?>

                                            <td class='text-center'>
                                                <?php echo "$totalMaitrise / $totalTechniciansCount"; ?>
                                            </td>
                                        </tr>
                                    <?php
                                        if ($totalMaitrise == 0) $totalNonMaitriseQuestions++;
                                    } ?>

                                    <tr class='lastrow'>
                                        <td colspan="<?php echo count($technicians) + 2; ?>" class="text-center fw-bold" style="background-color: #EDF2F7; position: sticky; bottom: 0; z-index: 2;">POURCENTAGE DE TÂCHES NON COUVERTES</td>
                                        <?php
                                        $pourcentageNonCouverts = ($totalQuestions > 0) ? round(($totalNonMaitriseQuestions / $totalQuestions) * 100) : 0;
                                        $bootstrapClass = getBootstrapClass($pourcentageNonCouverts);
                                        ?>
                                        <td class='text-center fw-bold <?php echo $bootstrapClass; ?>' style='background-color: #EDF2F7; position: sticky; bottom: 0; z-index: 2;'>
                                            <span class="countup" data-count="<?= (int)$pourcentageNonCouverts; ?>">0</span>%
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                        </div>
                    </div>
                </div>
            </div>
            <!-- fin Tableau -->


        </div>
        <!--end::Container-->
    </div>
    <!--end::Post-->
    <?php closeBackgroundContainer(); ?>
    <!--end::Content-->

    <!-- Modal Questions -->
    <div class="modal fade" id="questionsModal" tabindex="-1" aria-labelledby="questionsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="questionsModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="questionsContent"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts (une seule fois) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1"></script>
    <script src="../public/js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include "./partials/footer.php"; ?>
    <script>
        // Données PHP → JS
        var percentagesData = {
            'Junior': <?php echo json_encode($percentagesJunior); ?>,
            'Senior': <?php echo json_encode($percentagesSenior); ?>,
            'Expert': <?php echo json_encode($percentagesExpert); ?>,
            'Total': <?php echo json_encode($percentagesTotal); ?>
        };
        var statsData = {
            'chartJunior': <?php echo json_encode($statsJunior); ?>,
            'chartSenior': <?php echo json_encode($statsSenior); ?>,
            'chartExpert': <?php echo json_encode($statsExpert); ?>,
            'chartTotal': <?php echo json_encode($statsTotal); ?>
        };
        var legendsData = {
            'chartJunior': <?php echo json_encode($legendsJunior); ?>,
            'chartSenior': <?php echo json_encode($legendsSenior); ?>,
            'chartExpert': <?php echo json_encode($legendsExpert); ?>,
            'chartTotal': <?php echo json_encode($legendsTotal); ?>
        };

        var backgroundColors = [
            '#d3d3d3', // Aucun technicien
            '#fddde6', // 1 seul technicien
            '#f8d7da', // 2 techniciens
            '#f5c6cb' // 3+ techniciens
        ];

        function createDoughnutChart(ctx, percentages, chartId, stats, legends) {
            var segmentInfo = [];
            var n = stats.numTechnicians;

            if (n == 1) {
                segmentInfo.push({
                    type: 'nonMaitrise',
                    label: legends.nonMaitrise,
                    value: percentages.nonMaitrise,
                    color: backgroundColors[0]
                });
                if (percentages.singleMaitrise > 0)
                    segmentInfo.push({
                        type: 'singleMaitrise',
                        label: legends.singleMaitrise,
                        value: percentages.singleMaitrise,
                        color: backgroundColors[3]
                    });
            } else if (n == 2) {
                segmentInfo.push({
                    type: 'nonMaitrise',
                    label: legends.nonMaitrise,
                    value: percentages.nonMaitrise,
                    color: backgroundColors[0]
                });
                if (percentages.singleMaitrise > 0)
                    segmentInfo.push({
                        type: 'singleMaitrise',
                        label: legends.singleMaitrise,
                        value: percentages.singleMaitrise,
                        color: backgroundColors[1]
                    });
                if (percentages.others > 0)
                    segmentInfo.push({
                        type: 'allMaitrise',
                        label: legends.allMaitrise,
                        value: percentages.others,
                        color: backgroundColors[3]
                    });
            } else if (n == 3) {
                segmentInfo.push({
                    type: 'nonMaitrise',
                    label: legends.nonMaitrise,
                    value: percentages.nonMaitrise,
                    color: backgroundColors[0]
                });
                if (percentages.singleMaitrise > 0)
                    segmentInfo.push({
                        type: 'singleMaitrise',
                        label: legends.singleMaitrise,
                        value: percentages.singleMaitrise,
                        color: backgroundColors[1]
                    });
                if (percentages.doubleMaitrise > 0)
                    segmentInfo.push({
                        type: 'doubleMaitrise',
                        label: legends.doubleMaitrise,
                        value: percentages.doubleMaitrise,
                        color: backgroundColors[2]
                    });
                if (percentages.others > 0)
                    segmentInfo.push({
                        type: 'allMaitrise',
                        label: legends.allMaitrise,
                        value: percentages.others,
                        color: backgroundColors[3]
                    });
            } else {
                segmentInfo.push({
                    type: 'nonMaitrise',
                    label: legends.nonMaitrise,
                    value: percentages.nonMaitrise,
                    color: backgroundColors[0]
                });
                if (percentages.singleMaitrise > 0)
                    segmentInfo.push({
                        type: 'singleMaitrise',
                        label: legends.singleMaitrise,
                        value: percentages.singleMaitrise,
                        color: backgroundColors[1]
                    });
                if (percentages.doubleMaitrise > 0)
                    segmentInfo.push({
                        type: 'doubleMaitrise',
                        label: legends.doubleMaitrise,
                        value: percentages.doubleMaitrise,
                        color: backgroundColors[2]
                    });
                if (percentages.others > 0)
                    segmentInfo.push({
                        type: 'moreMaitrise',
                        label: legends.moreMaitrise,
                        value: percentages.others,
                        color: backgroundColors[3]
                    });
            }

            var data = [],
                labels = [],
                colors = [],
                types = [];
            segmentInfo.forEach(s => {
                if (s.value !== 0) {
                    data.push(s.value);
                    labels.push(s.label);
                    colors.push(s.color);
                    types.push(s.type);
                }
            });

            // calcul data/labels/colors/types
            var sum = data.reduce(function(a,b){ return a + (parseFloat(b)||0); }, 0);

            // overlay état vide
            try {
                var overlay = ctx.canvas && ctx.canvas.parentElement ? ctx.canvas.parentElement.querySelector('.no-data-overlay') : null;
                if (overlay) overlay.style.display = sum > 0 ? 'none' : 'flex';
            } catch(e) {}

            var chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors
                    }]
                },
                options: commonOptions,
                plugins: [{
                    id: 'donutText',
                    beforeDraw: function(chart) {
                        var width = chart.width,
                            height = chart.height,
                            ctx = chart.ctx;
                        ctx.restore();
                        var fontSize = (height / 114).toFixed(2);
                        ctx.font = "bold " + fontSize + "em sans-serif";
                        ctx.textBaseline = "middle";
                        var text = Math.round(100 - percentages.nonMaitrise) + "%",
                            textX = Math.round((width - ctx.measureText(text).width) / 2),
                            textY = height / 2;
                        ctx.fillStyle = "#333";
                        ctx.fillText(text, textX, textY);
                        ctx.save();
                    }
                }]
            });
            chart.segmentTypes = types;
            return chart;
        }

        function generateLegendLabels(chart) {
            var data = chart.data;
            if (!(data.labels.length && data.datasets.length)) return [];
            var items = [];
            for (var i = 0; i < data.labels.length; i++) {
                var v = data.datasets[0].data[i];
                items.push({
                    text: data.labels[i] + ' ' + v + '% T.P',
                    fillStyle: data.datasets[0].backgroundColor[i],
                    hidden: !chart.getDataVisibility(i),
                    index: i
                });
            }
            return items;
        }

        function tooltipLabelCallback(context) {
            var label = context.label || '';
            var chart = context.chart;
            var index = context.dataIndex;
            var value = context.parsed !== undefined ? context.parsed : 0;
            var segmentType = chart.segmentTypes[index];
            var chartId = chart.canvas.id;

            var taskCount = 0;
            if (statsData[chartId]) {
                if (segmentType === 'others' || segmentType === 'moreMaitrise' || segmentType === 'allMaitrise') {
                    taskCount = statsData[chartId]['othersCount'];
                } else {
                    taskCount = statsData[chartId][segmentType];
                }
            }
            return [label + ' ' + value + '% T.P', taskCount + ' <?php echo $Tasks_Professional_Plural; ?>'];
        }

        var commonOptions = {
            onClick: chartClickEvent,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: tooltipLabelCallback
                    }
                },
                legend: {
                    display: false
                }
            },
            animation: {
                duration: 700,
                easing: 'easeInOutCubic',
                animateRotate: true,
                animateScale: true
            },
            responsive: true,
            maintainAspectRatio: false
        };

        window.onload = function() {
            var ctxJunior = document.getElementById('chartJunior').getContext('2d');
            createDoughnutChart(ctxJunior, percentagesData['Junior'], 'chartJunior', statsData['chartJunior'], legendsData['chartJunior']);

            var ctxSenior = document.getElementById('chartSenior').getContext('2d');
            createDoughnutChart(ctxSenior, percentagesData['Senior'], 'chartSenior', statsData['chartSenior'], legendsData['chartSenior']);

            var ctxExpert = document.getElementById('chartExpert').getContext('2d');
            createDoughnutChart(ctxExpert, percentagesData['Expert'], 'chartExpert', statsData['chartExpert'], legendsData['chartExpert']);

            var ctxTotal = document.getElementById('chartTotal').getContext('2d');
            createDoughnutChart(ctxTotal, percentagesData['Total'], 'chartTotal', statsData['chartTotal'], legendsData['chartTotal']);

            // Déplacer les compteurs (stats-frame) en dehors des cards chart-box, juste après chaque card
            try {
                document.querySelectorAll('.chart-box .stats-frame').forEach(function(sf){
                    var card = sf.closest('.chart-box');
                    var col  = card ? card.parentElement : null;
                    if (card && col) {
                        col.appendChild(sf); // move after the card within the same column
                    }
                });
            } catch(e) {}
        };

        // Select2 init + templates (drapeaux/icônes)
        (function(){
            function templateCountry (state) {
                if (!state.id) return state.text;
                var $opt = $(state.element), flag = $opt.data('flag');
                var img = flag ? '<img src="'+flag+'" style="width:18px;height:12px;border-radius:2px;box-shadow:0 0 0 1px rgba(0,0,0,.15);margin-right:8px;object-fit:cover;"/>' : '';
                return $('<span style="display:inline-flex;align-items:center;gap:8px;">'+img+'<span>'+state.text+'</span></span>');
            }
            function templateIcon (state) {
                if (!state.id) return state.text;
                var $opt = $(state.element), icon = $opt.data('icon');
                var icn = icon ? '<i class="'+icon+'" style="width:18px;text-align:center;margin-right:8px;color:#8B5CF6"></i>' : '';
                return $('<span style="display:inline-flex;align-items:center;gap:8px;">'+icn+'<span>'+state.text+'</span></span>');
            }
            $(function(){
                if ($('#country-select').length) {
                    $('#country-select').select2({ theme:'bootstrap4', width:'100%', placeholder: $('#country-select').data('placeholder')||'Tous les pays', allowClear:true, templateResult: templateCountry, templateSelection: templateCountry, escapeMarkup: function(m){return m;} });
                }
                if ($('#agency-select').length) {
                    $('#agency-select').select2({ theme:'bootstrap4', width:'100%', placeholder: $('#agency-select').data('placeholder')||'Toutes les agences', allowClear:true, templateResult: templateIcon, templateSelection: templateIcon, escapeMarkup: function(m){return m;} });
                }
                if ($('#level-select').length) {
                    $('#level-select').select2({ theme:'bootstrap4', width:'100%', placeholder: $('#level-select').data('placeholder')||'Niveau', allowClear:true, templateResult: templateIcon, templateSelection: templateIcon, escapeMarkup: function(m){return m;} });
                }
            });
        })();

        function chartClickEvent(event, elements) {
            if (elements.length > 0) {
                var chart = this;
                var elementIndex = elements[0].index;
                var segmentType = chart.segmentTypes[elementIndex];
                var chartId = chart.canvas.id;

                var level = (chartId === 'chartJunior') ? 'Junior' :
                    (chartId === 'chartSenior') ? 'Senior' :
                    (chartId === 'chartExpert') ? 'Expert' : 'Total';

                var country = '<?php echo urlencode((string) $selectedCountry); ?>';
                var agency = '<?php echo urlencode((string) $selectedAgency); ?>';

                $.ajax({
                    url: 'listQuestions.php',
                    type: 'GET',
                    data: {
                        level: level,
                        type: segmentType,
                        country: country,
                        agency: agency
                    },
                    success: function(response) {
                        $('#questionsContent').html(response);
                        $('#questionsModalLabel').text('<?php echo $task_list; ?>');
                        $('#questionsModal').modal('show');
                    }
                });
            }
        }

        // Appliquer filtres (gère "all" => supprime le param)
        function applyFilters() {
            var level = document.querySelector("#level-select") ? document.querySelector("#level-select").value : '<?php echo $selectedLevel; ?>';
            var country = document.querySelector("#country-select") ? document.querySelector("#country-select").value : null;
            var agency = document.querySelector("#agency-select") ? document.querySelector("#agency-select").value : null;

            var urlParams = new URLSearchParams(window.location.search);
            urlParams.set('level', level);

            <?php if ($profile === "Directeur Groupe" || $profile === "Super Admin") { ?>
                if (country && country !== "all") {
                    urlParams.set('country', country);
                } else {
                    urlParams.delete('country');
                }
                urlParams.delete('agency');
            <?php } elseif (($profile == 'Directeur Pièce et Service') || ($profile == 'Directeur des Opérations') || ($profile == 'Admin')) { ?>
                if (agency && agency !== "all") {
                    urlParams.set('agency', agency);
                } else {
                    urlParams.delete('agency');
                }
                urlParams.set('country', '<?php echo $userCountry; ?>');
            <?php } ?>

            window.location.search = urlParams.toString();
        }
    </script>

    <!-- CountUp minimaliste -->
    <script>
        (function() {
            function animateCountUp(el, duration = 1200) {
                const target = parseInt(el.dataset.count, 10) || 0;
                const start = 0;
                const startTime = performance.now();

                function tick(now) {
                    const p = Math.min((now - startTime) / duration, 1);
                    const value = Math.floor(start + (target - start) * p);
                    el.textContent = value.toLocaleString();
                    if (p < 1) requestAnimationFrame(tick);
                }
                requestAnimationFrame(tick);
            }

            function initCountUps(root = document) {
                const els = Array.from(root.querySelectorAll('.countup'));
                if (!('IntersectionObserver' in window)) {
                    els.forEach(el => animateCountUp(el));
                    return;
                }
                const io = new IntersectionObserver(entries => {
                    entries.forEach(e => {
                        if (e.isIntersecting) {
                            animateCountUp(e.target);
                            io.unobserve(e.target);
                        }
                    });
                }, {
                    threshold: 0.3
                });
                els.forEach(el => io.observe(el));
            }

            document.addEventListener('DOMContentLoaded', function() { initCountUps(); });

            // Adaptateur optionnel pour data-kt-countup-value (si existant dans la page)
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('[data-kt-countup][data-kt-countup-value]').forEach(function(el) {
                    if (!el.classList.contains('countup')) {
                        el.classList.add('countup');
                        el.setAttribute('data-count', el.getAttribute('data-kt-countup-value'));
                        el.textContent = '0';
                    }
                });
                initCountUps();
            });
        })();
    </script>

    <?php include_once "partials/footer.php"; ?>
<?php } ?>





