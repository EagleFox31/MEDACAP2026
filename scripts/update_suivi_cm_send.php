<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Met à jour le classeur de suivi complet (avec Tableau de bord) pour CFAO Cameroun.
 * Prend le modèle `suivi_qcm_techniciens_cm_2026-08-27_151656-send.xlsx`,
 * réactualise les statuts des 37 techniciens depuis Mongo prod,
 * et recalcule tous les indicateurs du Tableau de bord.
 */
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/mongo_compat.php';
require_once __DIR__ . '/includes/cfao_cm_cohort.php';
require_once __DIR__ . '/export_techniciens_progress_cm.php'; // Pour les constantes et helpers si besoin

use MongoDB\Client;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$template = __DIR__ . '/../exports/suivi_qcm_techniciens_cm_2026-08-27_151656-send.xlsx';
if (!is_file($template)) {
    $template = __DIR__ . '/../exports/suivi_qcm_techniciens_cm_2026-08-27_151656.xlsx';
}

$dateStr = date('Y-m-d_His');
$todayHuman = date('d/m/Y H:i');
$outPath = __DIR__ . '/../exports/suivi_qcm_techniciens_cm_' . $dateStr . '-send.xlsx';
$outFixed = __DIR__ . '/../exports/suivi_qcm_techniciens_cm_' . date('Y-m-d') . '-send.xlsx';

$uri = medacapMongoUri('mongodb://10.68.0.7:27017');
$db = (new Client($uri))->academy;
$usersCol = $db->users;
$testsCol = $db->tests;
$resultsCol = $db->results;
$allocCol = $db->allocations;
$examsCol = $db->exams;

$cohort = cfaoCmLoadCohort();
$spreadsheet = IOFactory::load($template);

$sheetSuivi = $spreadsheet->getSheetByName('Suivi QCM');
$sheetLegende = $spreadsheet->getSheetByName('Legende');

// Mettre à jour la date de génération dans Legende!B14
if ($sheetLegende) {
    $sheetLegende->setCellValue('B14', $todayHuman);
}

$levels = array('Junior', 'Senior', 'Expert');
$modalities = array(
    'theorique' => array('type' => 'Factuel', 'label' => 'Théorique'),
    'pratique' => array('type' => 'Declaratif', 'label' => 'Pratique'),
    'manager' => array('type' => 'Declaratif', 'label' => 'Évaluation manager'),
);
$testCols = array();
foreach ($levels as $level) {
    foreach ($modalities as $key => $mod) {
        $testCols[] = array(
            'key' => $level . '_' . $key,
            'level' => $level,
            'modality' => $key,
            'header' => $level . ' — ' . $mod['label'],
        );
    }
}

// Mise à jour de chaque ligne dans Suivi QCM
foreach ($cohort as $i => $entry) {
    $r = $i + 2; // Lignes 2 à 38
    $user = cfaoCmResolveUser($usersCol, $entry);
    $cells = buildRowCells($user, $testsCol, $resultsCol, $allocCol, $examsCol, $usersCol, $testCols);
    // Manager actuel (le modèle date d'août, des managers ont changé depuis)
    if ($user) {
        $sheetSuivi->setCellValue('H' . $r, getPersonName($usersCol, $personCache, $user['manager'] ?? null));
    }

    // Mettre à jour les colonnes L à T (colonnes 12 à 20)
    $colIdx = 12;
    foreach ($testCols as $meta) {
        $cell = $cells[$meta['key']];
        $sheetSuivi->setCellValueByColumnAndRow($colIdx, $r, $cell['label']);
        $rgb = cellColor($cell['sort'], $cell['label']);
        $sheetSuivi->getStyleByColumnAndRow($colIdx, $r)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
        $colIdx++;
    }
}

// Tableau de bord, « Charge par manager » (A31:A39) : managers actuels, les plus chargés d'abord
$dashboardSheet = $spreadsheet->getSheetByName('Tableau de bord');
$followed = array();
for ($r = 2; $r <= 38; $r++) {
    $name = trim((string) $sheetSuivi->getCell('H' . $r)->getValue());
    if ($name !== '') {
        $followed[$name] = isset($followed[$name]) ? $followed[$name] + 1 : 1;
    }
}
arsort($followed);
if (count($followed) > 9) {
    echo '[ATTENTION] ' . count($followed) . " managers, le tableau de bord n'en affiche que 9 : "
        . implode(', ', array_slice(array_keys($followed), 9)) . " absents\n";
}
$names = array_slice(array_keys($followed), 0, 9);
for ($i = 0; $i < 9; $i++) {
    $dashboardSheet->setCellValue('A' . (31 + $i), isset($names[$i]) ? $names[$i] : '');
}

// Sauvegarde du fichier
$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->setPreCalculateFormulas(false); // Laisser Excel calculer les formules fraîches
$writer->save($outPath);
copy($outPath, $outFixed);

echo "Mise à jour enregistrée :\n";
echo "  -> {$outPath}\n";
echo "  -> {$outFixed}\n\n";

// Recalcul des valeurs pour la synthèse texte
$calcSpreadsheet = IOFactory::load($outPath);
$dashboard = $calcSpreadsheet->getSheetByName('Tableau de bord');

echo "=== SYNTHÈSE ACTUALISÉE AU {$todayHuman} ===\n\n";

$inscrits = $dashboard->getCell('B6')->getCalculatedValue();
$techs = $dashboard->getCell('B7')->getCalculatedValue();
$managers = $dashboard->getCell('B8')->getCalculatedValue();

$finiTout = $dashboard->getCell('B9')->getCalculatedValue();
$auMoinsUn = $dashboard->getCell('B10')->getCalculatedValue();
$rienCommence = $dashboard->getCell('B11')->getCalculatedValue();

$qcmApplicables = $dashboard->getCell('E10')->getCalculatedValue();
$qcmTermines = $dashboard->getCell('E6')->getCalculatedValue();
$pctAvancement = round($dashboard->getCell('F6')->getCalculatedValue() * 100, 1);

echo "- {$inscrits} inscrits ({$techs} techniciens et {$managers} managers), répartis sur 6 agences.\n";
echo "- Avancement global : {$pctAvancement} %, soit {$qcmTermines} QCM terminés sur {$qcmApplicables} applicables.\n";
echo "- {$finiTout} personnes ont terminé l'intégralité de leurs tests ; {$auMoinsUn} ont terminé au moins un QCM ; {$rienCommence} n'ont encore rien entamé.\n\n";

echo "Points d'attention — par agence :\n";
$syntheseAg = $calcSpreadsheet->getSheetByName('Synthese agences');
for ($ar = 2; $ar <= 7; $ar++) {
    $ag = $syntheseAg->getCell("A{$ar}")->getValue();
    $pctAg = round($syntheseAg->getCell("H{$ar}")->getCalculatedValue() * 100, 1);
    $doneAg = $syntheseAg->getCell("E{$ar}")->getCalculatedValue();
    $totAg = $syntheseAg->getCell("B{$ar}")->getCalculatedValue();
    echo "  - {$ag} : {$pctAg} % d'avancement\n";
}

echo "\nQCM d'évaluation manager en attente :\n";
$totalMgrAttente = 0;
$mgrLines = [];
for ($mr = 31; $mr <= 39; $mr++) {
    $mgrName = $dashboard->getCell("A{$mr}")->getValue();
    $aFaire = $dashboard->getCell("C{$mr}")->getCalculatedValue();
    $fait = $dashboard->getCell("D{$mr}")->getCalculatedValue();
    if ($aFaire > 0) {
        $totalMgrAttente += $aFaire;
        $mgrLines[] = "{$aFaire} pour M. {$mgrName}";
    }
}
echo "Total en attente : {$totalMgrAttente} évaluations ouvertes, dont " . implode(', ', array_slice($mgrLines, 0, 3)) . "...\n";
