<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Export Excel « à envoyer » — résultats QCM techniciens Cameroun (RH + managers).
 *
 * Une ligne par technicien ayant composé au moins un test. Par niveau :
 *   - Théorique : note du QCM Factuel ;
 *   - Pratique  : note validée par le manager (résultat « Technicien - Manager ») ;
 *                 tant que le manager n'a pas évalué → « En attente évaluation manager » ;
 *   (colonne Global retirée le 05/11 : seules les notes théorique et pratique sont affichées)
 *
 * Valeurs figées (aucune formule).
 *
 * Usage:
 *   php scripts/generate_results_send_excel.php
 *   php scripts/generate_results_send_excel.php --mongo-uri=mongodb://10.68.0.7:27017 --out=exports/resultats.xlsx
 */
define('MEDACAP_EXPORT_AS_LIB', true);
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/includes/cfao_cm_cohort.php';
require_once __DIR__ . '/export_techniciens_progress_cm.php';

use MongoDB\Client;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

const RES_WAIT_MGR = 'En attente évaluation manager';
const RES_NOT_DONE = 'Pas composé';
const RES_OUT = '—';
const RES_COLS_PER_LEVEL = 2; // Théorique, Pratique

const RES_COLOR_HEADER = '24235C';
const RES_COLOR_GOOD = 'D5F5E3';   // ≥ 80 %
const RES_COLOR_MID = 'FCF3CF';    // 60 – 79 %
const RES_COLOR_LOW = 'FADBD8';    // < 60 %
const RES_COLOR_WAIT = 'FAD7A0';
const RES_COLOR_OUT = 'F8F9F9';

$uri = argValue($argv, 'mongo-uri', medacapMongoUri('mongodb://10.68.0.7:27017'));
$outPath = argValue($argv, 'out', null);
if ($outPath === null) {
    $outPath = __DIR__ . '/../exports/resultats_qcm_techniciens_cm_' . date('Y-m-d') . '-send.xlsx';
    if (file_exists($outPath)) {
        // Ne jamais écraser un fichier déjà envoyé (ou ouvert dans Excel).
        $outPath = __DIR__ . '/../exports/resultats_qcm_techniciens_cm_' . date('Y-m-d_His') . '-send.xlsx';
    }
}
$generatedAt = date('d/m/Y à H:i');
$levels = array('Junior', 'Senior', 'Expert');

/**
 * Résultat de synthèse d'un test (le plus récent), comme bilanTest.php.
 * Les documents par quiz n'ont pas de champ « test » : on les écarte.
 */
function resFindResult($resultsCol, $uid, $level, $type, $typeR)
{
    return $resultsCol->findOne(array(
        'user' => $uid,
        'level' => $level,
        'type' => $type,
        'typeR' => $typeR,
        'active' => true,
        'test' => array('$exists' => true),
    ), array('sort' => array('_id' => -1)));
}

/** Pourcentage arrondi au supérieur, comme calculatePercentage() de bilanTest.php. */
function resPct($result)
{
    if (!$result || !isset($result['score']) || empty($result['total'])) {
        return null;
    }
    return (int) ceil(($result['score'] * 100) / $result['total']);
}

function resManagerName($usersCol, array &$cache, $managerId)
{
    if (!$managerId) {
        return '(sans manager)';
    }
    $key = (string) $managerId;
    if (!isset($cache[$key])) {
        $doc = $usersCol->findOne(array('_id' => $managerId), array(
            'projection' => array('username' => 1, 'firstName' => 1, 'lastName' => 1),
        ));
        $name = '(manager introuvable)';
        if ($doc) {
            $name = trim(mb_strtoupper(trim((string) ($doc['lastName'] ?? '')), 'UTF-8') . ' '
                . trim((string) ($doc['firstName'] ?? '')));
            if ($name === '') {
                $name = (string) ($doc['username'] ?? '(manager introuvable)');
            }
        }
        $cache[$key] = $name;
    }
    return $cache[$key];
}

function resScoreColor($pct)
{
    if ($pct >= 80) {
        return RES_COLOR_GOOD;
    }
    return $pct >= 60 ? RES_COLOR_MID : RES_COLOR_LOW;
}

function resCol($i)
{
    return Coordinate::stringFromColumnIndex($i);
}

function resFill($sheet, $range, $rgb)
{
    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
}

function resText($sheet, $coord, $value)
{
    $sheet->setCellValueExplicit($coord, (string) $value, DataType::TYPE_STRING);
}

/** Écrit une case note : nombre coloré par seuil, ou libellé d'attente. */
function resWriteScore($sheet, $coord, $value, $bold = false)
{
    if (is_int($value)) {
        $sheet->setCellValue($coord, $value);
        $sheet->getStyle($coord)->getNumberFormat()->setFormatCode('0" %"');
        resFill($sheet, $coord, resScoreColor($value));
        if ($bold) {
            $sheet->getStyle($coord)->getFont()->setBold(true);
        }
        return;
    }
    resText($sheet, $coord, $value);
    if ($value === RES_WAIT_MGR) {
        resFill($sheet, $coord, RES_COLOR_WAIT);
        $sheet->getStyle($coord)->getFont()->setSize(9);
    } else {
        resFill($sheet, $coord, RES_COLOR_OUT);
        $sheet->getStyle($coord)->getFont()->setSize(9)->getColor()->setRGB('909497');
    }
}

// ---------------------------------------------------------------------------
// Données
// ---------------------------------------------------------------------------

$db = (new Client($uri))->academy;
$usersCol = $db->users;
$resultsCol = $db->results;

try {
    $cohort = cfaoCmLoadCohort();
} catch (Throwable $e) {
    fwrite(STDERR, 'Liste CFAO: ' . $e->getMessage() . "\n");
    exit(1);
}

$managerCache = array();
$people = array();
$notComposed = 0;
$stats = array('theo' => 0, 'prat' => 0, 'wait' => 0);
foreach ($cohort as $entry) {
    $user = cfaoCmResolveUser($usersCol, $entry);
    if (!$user || !hasTechnicianQcmPath($user)) {
        continue;
    }
    $target = array_search((string) ($user['level'] ?? ''), $levels, true);
    $target = $target === false ? count($levels) - 1 : $target;

    $p = array(
        'name' => $entry['rawName'],
        'agency' => $entry['agency'],
        'manager' => resManagerName($usersCol, $managerCache, $user['manager'] ?? null),
        'level_max' => (string) ($user['level'] ?? ''),
        'levels' => array(),
    );
    $composed = false;
    foreach ($levels as $i => $level) {
        if ($i > $target) {
            $p['levels'][$level] = array('t' => RES_OUT, 'p' => RES_OUT, 'g' => RES_OUT);
            continue;
        }
        $theo = resPct(resFindResult($resultsCol, $user['_id'], $level, 'Factuel', 'Technicien'));
        $techPrat = resFindResult($resultsCol, $user['_id'], $level, 'Declaratif', 'Techniciens');
        $prat = resPct(resFindResult($resultsCol, $user['_id'], $level, 'Declaratif', 'Technicien - Manager'));

        $lv = array('t' => RES_NOT_DONE, 'p' => RES_NOT_DONE, 'g' => RES_OUT);
        if ($theo !== null) {
            $lv['t'] = $theo;
            $composed = true;
            $stats['theo']++;
        }
        if ($prat !== null) {
            $lv['p'] = $prat;
            $composed = true;
            $stats['prat']++;
        } elseif ($techPrat) {
            $lv['p'] = RES_WAIT_MGR;
            $composed = true;
            $stats['wait']++;
        }
        $p['levels'][$level] = $lv;
    }
    if (!$composed) {
        $notComposed++;
        continue;
    }
    $people[] = $p;
}

usort($people, function ($a, $b) {
    $c = strcmp($a['agency'], $b['agency']);
    return $c !== 0 ? $c : strcmp($a['name'], $b['name']);
});

// ---------------------------------------------------------------------------
// Onglet 1 — Résultats
// ---------------------------------------------------------------------------

$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Résultats');
$sheet->setShowGridlines(false);

$lastCol = resCol(4 + count($levels) * RES_COLS_PER_LEVEL);

resText($sheet, 'A1', 'Résultats QCM techniciens — Cameroun');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB(RES_COLOR_HEADER);
resText($sheet, 'A2', 'Situation au ' . $generatedAt . ' · ' . count($people) . ' techniciens ont composé au moins un test'
    . ($notComposed > 0 ? ' (' . $notComposed . ' n\'ont encore rien composé : voir le fichier de suivi)' : '') . '.');
$sheet->getStyle('A2')->getFont()->getColor()->setRGB('5D6D7E');
resText($sheet, 'A3', 'Notes en %. Vert : 80 % et plus · Jaune : 60 à 79 % · Rouge : moins de 60 %.');
$sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('5D6D7E');

// En-tête sur deux lignes : niveau puis Théorique / Pratique (colonne Global retirée le 05/11).
$h1 = 5;
$h2 = 6;
foreach (array('Nom', 'Agence', 'Manager', 'Niveau cible') as $i => $label) {
    $c = resCol($i + 1);
    resText($sheet, $c . $h1, $label);
    $sheet->mergeCells($c . $h1 . ':' . $c . $h2);
}
foreach ($levels as $i => $level) {
    $c1 = resCol(5 + $i * RES_COLS_PER_LEVEL);
    $c3 = resCol(4 + ($i + 1) * RES_COLS_PER_LEVEL);
    resText($sheet, $c1 . $h1, $level);
    $sheet->mergeCells($c1 . $h1 . ':' . $c3 . $h1);
    foreach (array('Théorique', 'Pratique') as $j => $sub) {
        resText($sheet, resCol(5 + $i * RES_COLS_PER_LEVEL + $j) . $h2, $sub);
    }
}
$head = $sheet->getStyle('A' . $h1 . ':' . $lastCol . $h2);
$head->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$head->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)
    ->setWrapText(true);
resFill($sheet, 'A' . $h1 . ':' . $lastCol . $h2, RES_COLOR_HEADER);
$sheet->getRowDimension($h1)->setRowHeight(20);
$sheet->getRowDimension($h2)->setRowHeight(20);

$r = $h2 + 1;
foreach ($people as $p) {
    resText($sheet, 'A' . $r, $p['name']);
    resText($sheet, 'B' . $r, $p['agency']);
    resText($sheet, 'C' . $r, $p['manager']);
    resText($sheet, 'D' . $r, $p['level_max']);
    foreach ($levels as $i => $level) {
        $lv = $p['levels'][$level];
        resWriteScore($sheet, resCol(5 + $i * RES_COLS_PER_LEVEL) . $r, $lv['t']);
        resWriteScore($sheet, resCol(6 + $i * RES_COLS_PER_LEVEL) . $r, $lv['p']);
    }
    $sheet->getRowDimension($r)->setRowHeight(30);
    $r++;
}
$lastRow = $r - 1;
$body = $sheet->getStyle('E' . ($h2 + 1) . ':' . $lastCol . $lastRow);
$body->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
$sheet->getStyle('A' . ($h2 + 1) . ':' . $lastCol . $lastRow)->getAlignment()
    ->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A' . $h1 . ':' . $lastCol . $lastRow)->getBorders()->getAllBorders()
    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D5D8DC');
// Séparation nette entre niveaux.
foreach ($levels as $i => $level) {
    $sheet->getStyle(resCol(5 + $i * RES_COLS_PER_LEVEL) . $h1 . ':' . resCol(5 + $i * RES_COLS_PER_LEVEL) . $lastRow)->getBorders()->getLeft()
        ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(RES_COLOR_HEADER);
}

foreach (array('A' => 34, 'B' => 12, 'C' => 32, 'D' => 10) as $col => $w) {
    $sheet->getColumnDimension($col)->setWidth($w);
}
for ($i = 5; $i <= 4 + count($levels) * RES_COLS_PER_LEVEL; $i++) {
    $sheet->getColumnDimension(resCol($i))->setWidth(14);
}
$sheet->setAutoFilter('A' . $h2 . ':' . $lastCol . $lastRow);
$sheet->freezePane('B' . ($h2 + 1));
$sheet->getPageSetup()
    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
    ->setPaperSize(PageSetup::PAPERSIZE_A4)
    ->setFitToWidth(1)
    ->setFitToHeight(0);
$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($h1, $h2);

// ---------------------------------------------------------------------------
// Onglet 2 — Légende
// ---------------------------------------------------------------------------

$leg = $spreadsheet->createSheet();
$leg->setTitle('Légende');
$leg->setShowGridlines(false);
$leg->getColumnDimension('A')->setWidth(30);
$leg->getColumnDimension('B')->setWidth(100);
$rows = array(
    array('80 % et plus', 'Bon niveau.', RES_COLOR_GOOD),
    array('60 à 79 %', 'Niveau moyen.', RES_COLOR_MID),
    array('Moins de 60 %', 'Niveau insuffisant.', RES_COLOR_LOW),
    array(RES_WAIT_MGR, 'Le technicien a fait sa pratique ; la note sera connue quand son manager l\'aura évaluée.', RES_COLOR_WAIT),
    array(RES_NOT_DONE, 'Le technicien n\'a pas encore fait ce test.', RES_COLOR_OUT),
    array(RES_OUT, 'Niveau au-dessus du niveau cible du technicien.', RES_COLOR_OUT),
    array('Théorique', 'Note du QCM de connaissances.', null),
    array('Pratique', 'Note des tâches professionnelles, validée par le manager (accord technicien + manager).', null),
    array('Données', 'Valeurs figées au ' . $generatedAt . ', sans formule.', null),
);
resText($leg, 'A1', 'Légende');
$leg->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB(RES_COLOR_HEADER);
$r = 3;
foreach ($rows as $row) {
    resText($leg, 'A' . $r, $row[0]);
    resText($leg, 'B' . $r, $row[1]);
    if ($row[2] !== null) {
        resFill($leg, 'A' . $r, $row[2]);
    } else {
        $leg->getStyle('A' . $r)->getFont()->setBold(true);
    }
    $r++;
}
$leg->getStyle('A3:B' . ($r - 1))->getBorders()->getAllBorders()
    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D5D8DC');

$spreadsheet->setActiveSheetIndex(0);
$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save($outPath);

echo 'Export : ' . $outPath . "\n";
echo 'Techniciens avec au moins un test : ' . count($people) . ' | rien composé : ' . $notComposed . "\n";
echo 'Notes théoriques : ' . $stats['theo'] . ' | pratiques validées : ' . $stats['prat']
    . ' | pratiques en attente manager : ' . $stats['wait'] . "\n";
