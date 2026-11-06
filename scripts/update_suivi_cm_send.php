<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Met à jour le classeur de suivi pour CFAO Cameroun.
 * Prend le modèle `suivi_qcm_techniciens_cm_2026-08-27_151656-send.xlsx`,
 * réactualise les statuts des 37 techniciens depuis Mongo prod,
 * et recalcule les indicateurs de la feuille Synthese agences (feuille Tableau de bord retirée).
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
// Graphique « AvancementAgence » du tableau de bord : sans includeCharts, il est perdu mais son lien reste
// dans sheet1.xml.rels, et Excel signale un classeur à réparer.
$reader = IOFactory::createReader('Xlsx');
$reader->setIncludeCharts(true);
$spreadsheet = $reader->load($template);

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

// Feuille « Tableau de bord » retirée (05/11) : aucune autre feuille n'y fait référence
$spreadsheet->removeSheetByIndex($spreadsheet->getIndex($spreadsheet->getSheetByName('Tableau de bord')));
$spreadsheet->setActiveSheetIndexByName('Suivi QCM');

// Sauvegarde du fichier
$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
// Valeurs calculées à l'écriture : une pièce jointe s'ouvre en mode protégé, où Excel ne recalcule pas (sinon tout à 0)
$writer->setPreCalculateFormulas(true);
$writer->setIncludeCharts(true);
$writer->save($outPath);
copy($outPath, $outFixed);

echo "Mise à jour enregistrée :\n";
echo "  -> {$outPath}\n";
echo "  -> {$outFixed}\n\n";

// Synthèse texte, à partir de la feuille « Synthese agences »
$syntheseAg = IOFactory::load($outPath)->getSheetByName('Synthese agences');
echo "=== SYNTHÈSE ACTUALISÉE AU {$todayHuman} ===\n";
for ($ar = 2; $ar <= 7; $ar++) {
    $ag = $syntheseAg->getCell("A{$ar}")->getValue();
    $pctAg = round($syntheseAg->getCell("H{$ar}")->getCalculatedValue() * 100, 1);
    echo "  - {$ag} : {$pctAg} % d'avancement\n";
}
