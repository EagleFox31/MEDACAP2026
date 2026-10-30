<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Export Excel « à envoyer » — suivi QCM techniciens Cameroun (RH + managers).
 *
 * Classeur construit de zéro, valeurs figées (aucune formule) : les chiffres sont
 * identiques dans Excel, l'aperçu Outlook et sur téléphone.
 *
 *   1. Synthèse            — avancement, charge par manager, relances, agences
 *   2. À faire managers    — 1 ligne = 1 évaluation manager restante
 *   3. Détail par personne — qui doit agir, prochaine action, statut des 9 QCM
 *   4. Légende
 *
 * Usage:
 *   php scripts/generate_clean_send_excel.php
 *   php scripts/generate_clean_send_excel.php --mongo-uri=mongodb://10.68.0.7:27017 --out=exports/suivi.xlsx
 */
define('MEDACAP_EXPORT_AS_LIB', true);
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/mongo_compat.php';
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

const SEND_DONE = 'Fait';
const SEND_PROGRESS = 'En cours';
const SEND_TECH_TODO = 'À faire — technicien';
const SEND_MGR_TODO = 'À évaluer — manager';
const SEND_LOCKED = 'Niveau verrouillé';
const SEND_OUT = '—';

const SEND_WHO_TECH = 'Technicien';
const SEND_WHO_MGR = 'Manager';
const SEND_WHO_BOTH = 'Technicien + manager';
const SEND_WHO_DONE = 'Tout terminé';

const SEND_COLOR_HEADER = '24235C';
const SEND_COLOR_DONE = 'D5F5E3';
const SEND_COLOR_PROGRESS = 'D6EAF8';
const SEND_COLOR_TECH = 'FCF3CF';
const SEND_COLOR_MGR = 'FAD7A0';
const SEND_COLOR_LOCKED = 'E5E8E8';
const SEND_COLOR_OUT = 'F8F9F9';
const SEND_COLOR_BAND = 'F4F6F7';

$uri = argValue($argv, 'mongo-uri', medacapMongoUri('mongodb://10.68.0.7:27017'));
$outPath = argValue($argv, 'out', null);
if ($outPath === null) {
    $outPath = __DIR__ . '/../exports/suivi_qcm_techniciens_cm_' . date('Y-m-d') . '-send.xlsx';
    if (file_exists($outPath)) {
        // Ne jamais écraser un fichier déjà envoyé (ou ouvert dans Excel).
        $outPath = __DIR__ . '/../exports/suivi_qcm_techniciens_cm_' . date('Y-m-d_His') . '-send.xlsx';
    }
}
$generatedAt = date('d/m/Y à H:i');

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

/**
 * Nom du manager au format de la liste CFAO (« NOM Prénom »).
 */
function sendManagerName($usersCol, array &$cache, $managerId)
{
    if (!$managerId) {
        return '(sans manager)';
    }
    $key = (string) $managerId;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $doc = $usersCol->findOne(array('_id' => $managerId), array(
        'projection' => array('username' => 1, 'firstName' => 1, 'lastName' => 1),
    ));
    $name = '(manager introuvable)';
    if ($doc) {
        $first = trim((string) ($doc['firstName'] ?? ''));
        $last = trim((string) ($doc['lastName'] ?? ''));
        $name = trim(mb_strtoupper($last, 'UTF-8') . ' ' . $first);
        if ($name === '') {
            $name = (string) ($doc['username'] ?? $name);
        }
    }
    $cache[$key] = $name;
    return $name;
}

/** Libellé « qui doit agir » d'une case théorique / pratique. */
function sendTechLabel(array $cell)
{
    switch ($cell['sort']) {
        case ST_DONE:
            return SEND_DONE;
        case ST_PROGRESS:
            return SEND_PROGRESS;
        case ST_TODO:
            return SEND_TECH_TODO;
        case ST_WAIT:
            return SEND_LOCKED;
        default:
            return SEND_OUT;
    }
}

/**
 * Libellé d'une case évaluation manager. Le manager évalue tous les niveaux,
 * même ceux encore verrouillés côté technicien.
 */
function sendManagerLabel(array $cell)
{
    switch ($cell['sort']) {
        case ST_DONE:
            return SEND_DONE;
        case ST_PROGRESS:
            return SEND_PROGRESS;
        case ST_TODO:
            return SEND_MGR_TODO;
        default:
            return SEND_OUT;
    }
}

/**
 * Transforme les statuts bruts d'une personne en vue « qui doit agir ».
 */
function sendBuildPerson(array $entry, $user, array $cells, array $levels, $managerName)
{
    $p = array(
        'name' => $entry['rawName'],
        'agency' => $entry['agency'] !== '' ? $entry['agency'] : '(sans agence)',
        'manager' => $managerName,
        'level_max' => $user ? (string) ($user['level'] ?? '') : '',
        'levels' => array(),
        'tech_done' => 0,
        'tech_app' => 0,
        'mgr_done' => 0,
        'mgr_app' => 0,
        'tech_todo' => array(),
        'mgr_todo' => array(),
        'na_reason' => '',
    );

    $first = reset($cells);
    if (!$user || $first['sort'] === ST_NA) {
        $p['na_reason'] = $first['label'];
        foreach ($levels as $level) {
            $p['levels'][$level] = array('t' => SEND_OUT, 'p' => SEND_OUT, 'm' => SEND_OUT);
        }
        return $p;
    }

    foreach ($levels as $level) {
        $t = $cells[$level . '_theorique'];
        $pr = $cells[$level . '_pratique'];
        $m = $cells[$level . '_manager'];
        $lv = array(
            't' => sendTechLabel($t),
            'p' => sendTechLabel($pr),
            'm' => sendManagerLabel($m),
        );
        foreach (array('t' => 'Théorique', 'p' => 'Pratique') as $k => $label) {
            if ($lv[$k] === SEND_OUT) {
                continue;
            }
            $p['tech_app']++;
            if ($lv[$k] === SEND_DONE) {
                $p['tech_done']++;
                continue;
            }
            if ($lv[$k] === SEND_TECH_TODO || $lv[$k] === SEND_PROGRESS) {
                $p['tech_todo'][] = $level . ' ' . mb_strtolower($label, 'UTF-8');
            }
        }
        if ($lv['m'] !== SEND_OUT) {
            $p['mgr_app']++;
            if ($lv['m'] === SEND_DONE) {
                $p['mgr_done']++;
            } elseif ($lv['m'] === SEND_MGR_TODO || $lv['m'] === SEND_PROGRESS) {
                // Évaluable dès que le niveau est ouvert, que le technicien ait composé ou non.
                $p['mgr_todo'][] = $level;
            }
        }
        $p['levels'][$level] = $lv;
    }
    return $p;
}

function sendWhoActs(array $p)
{
    if ($p['na_reason'] !== '') {
        return SEND_OUT;
    }
    $tech = !empty($p['tech_todo']);
    $mgr = !empty($p['mgr_todo']);
    if ($tech && $mgr) {
        return SEND_WHO_BOTH;
    }
    if ($tech) {
        return SEND_WHO_TECH;
    }
    if ($mgr) {
        return SEND_WHO_MGR;
    }
    if ($p['tech_app'] > 0 && $p['tech_done'] === $p['tech_app'] && $p['mgr_done'] === $p['mgr_app']) {
        return SEND_WHO_DONE;
    }
    return SEND_OUT;
}

function sendNextAction(array $p)
{
    if ($p['na_reason'] !== '') {
        return $p['na_reason'];
    }
    $parts = array();
    if (!empty($p['tech_todo'])) {
        $parts[] = 'Technicien : ' . implode(', ', $p['tech_todo']);
    }
    if (!empty($p['mgr_todo'])) {
        $parts[] = 'Manager : évaluer ' . implode(', ', $p['mgr_todo']);
    }
    return $parts !== array() ? implode(' · ', $parts) : '—';
}

function sendStatusColor($label)
{
    switch ($label) {
        case SEND_DONE:
        case SEND_WHO_DONE:
            return SEND_COLOR_DONE;
        case SEND_PROGRESS:
            return SEND_COLOR_PROGRESS;
        case SEND_TECH_TODO:
        case SEND_WHO_TECH:
            return SEND_COLOR_TECH;
        case SEND_MGR_TODO:
        case SEND_WHO_MGR:
        case SEND_WHO_BOTH:
            return SEND_COLOR_MGR;
        case SEND_LOCKED:
            return SEND_COLOR_LOCKED;
        default:
            return SEND_COLOR_OUT;
    }
}

function sendPct($done, $total)
{
    return $total > 0 ? $done / $total : 0;
}

function sendCol($i)
{
    return Coordinate::stringFromColumnIndex($i);
}

function sendFill($sheet, $range, $rgb)
{
    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
}

function sendText($sheet, $coord, $value)
{
    $sheet->setCellValueExplicit($coord, (string) $value, DataType::TYPE_STRING);
}

/** Écrit une ligne d'en-tête de tableau (fond foncé, texte blanc, retour à la ligne). */
function sendHeader($sheet, $row, array $headers, $firstCol = 1)
{
    foreach ($headers as $i => $h) {
        sendText($sheet, sendCol($firstCol + $i) . $row, $h);
    }
    $range = sendCol($firstCol) . $row . ':' . sendCol($firstCol + count($headers) - 1) . $row;
    $style = $sheet->getStyle($range);
    $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $style->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
    sendFill($sheet, $range, SEND_COLOR_HEADER);
    $sheet->getRowDimension($row)->setRowHeight(32);
}

function sendSectionTitle($sheet, $coord, $text)
{
    sendText($sheet, $coord, $text);
    $sheet->getStyle($coord)->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(SEND_COLOR_HEADER);
}

function sendBorders($sheet, $range)
{
    $sheet->getStyle($range)->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D5D8DC');
}

function sendPrintSetup($sheet)
{
    $sheet->getPageSetup()
        ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
        ->setPaperSize(PageSetup::PAPERSIZE_A4)
        ->setFitToWidth(1)
        ->setFitToHeight(0);
}

// ---------------------------------------------------------------------------
// Données
// ---------------------------------------------------------------------------

$db = (new Client($uri))->academy;
$usersCol = $db->users;
$testsCol = $db->tests;
$resultsCol = $db->results;
$allocCol = $db->allocations;
$examsCol = $db->exams;

try {
    $cohort = cfaoCmLoadCohort();
} catch (Throwable $e) {
    fwrite(STDERR, 'Liste CFAO: ' . $e->getMessage() . "\n");
    exit(1);
}
if (empty($cohort)) {
    fwrite(STDERR, "Cohorte CFAO vide.\n");
    exit(1);
}

$managerCache = array();
$people = array();
foreach ($cohort as $entry) {
    $user = cfaoCmResolveUser($usersCol, $entry);
    $cells = buildRowCells($user, $testsCol, $resultsCol, $allocCol, $examsCol, $usersCol, $testCols);
    $managerName = $user ? sendManagerName($usersCol, $managerCache, $user['manager'] ?? null) : '';
    $p = sendBuildPerson($entry, $user, $cells, $levels, $managerName);
    $p['who'] = sendWhoActs($p);
    $p['next'] = sendNextAction($p);
    $people[] = $p;
}

usort($people, function ($a, $b) {
    $c = strcmp($a['agency'], $b['agency']);
    return $c !== 0 ? $c : strcmp($a['name'], $b['name']);
});

$tot = array('tech_done' => 0, 'tech_app' => 0, 'mgr_done' => 0, 'mgr_app' => 0,
    'waiting_mgr' => 0, 'complete' => 0, 'none' => array());
$byManager = array();
$evalRows = array();

foreach ($people as $p) {
    $tot['tech_done'] += $p['tech_done'];
    $tot['tech_app'] += $p['tech_app'];
    $tot['mgr_done'] += $p['mgr_done'];
    $tot['mgr_app'] += $p['mgr_app'];
    $hasPath = $p['tech_app'] > 0;
    if ($hasPath && $p['who'] === SEND_WHO_MGR && $p['tech_done'] === $p['tech_app']) {
        $tot['waiting_mgr']++;
    }
    if ($p['who'] === SEND_WHO_DONE) {
        $tot['complete']++;
    }
    if ($hasPath && $p['tech_done'] + $p['mgr_done'] === 0) {
        $tot['none'][] = $p;
    }

    if (!$hasPath) {
        continue;
    }
    $mg = $p['manager'] !== '' ? $p['manager'] : '(sans manager)';
    if (!isset($byManager[$mg])) {
        $byManager[$mg] = array('people' => 0, 'todo' => 0, 'done' => 0, 'app' => 0);
    }
    $byManager[$mg]['people']++;
    $byManager[$mg]['done'] += $p['mgr_done'];
    $byManager[$mg]['app'] += $p['mgr_app'];
    foreach ($levels as $li => $level) {
        $lv = $p['levels'][$level];
        if ($lv['m'] !== SEND_MGR_TODO && $lv['m'] !== SEND_PROGRESS) {
            continue;
        }
        $byManager[$mg]['todo']++;
        $evalRows[] = array(
            'manager' => $mg,
            'name' => $p['name'],
            'agency' => $p['agency'],
            'level' => $level,
            'level_order' => $li,
            'status' => $lv['m'] === SEND_PROGRESS ? 'Évaluation en cours' : 'À évaluer',
        );
    }
}

uasort($byManager, function ($a, $b) {
    if ($a['todo'] !== $b['todo']) {
        return $b['todo'] - $a['todo'];
    }
    return $b['people'] - $a['people'];
});
usort($evalRows, function ($a, $b) {
    $c = strcmp($a['manager'], $b['manager']);
    if ($c !== 0) {
        return $c;
    }
    if ($a['level_order'] !== $b['level_order']) {
        return $a['level_order'] - $b['level_order'];
    }
    return strcmp($a['name'], $b['name']);
});

$nPeople = count($people);
$techPct = sendPct($tot['tech_done'], $tot['tech_app']);
$mgrPct = sendPct($tot['mgr_done'], $tot['mgr_app']);
$evalTodoTotal = count($evalRows);

// ---------------------------------------------------------------------------
// Onglet 1 — Synthèse
// ---------------------------------------------------------------------------

$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
$syn = $spreadsheet->getActiveSheet();
$syn->setTitle('Synthèse');
$syn->setShowGridlines(false);
// Une seule page lisible en 10 secondes : message, 4 chiffres, qui doit agir.
// Colonne A = marge ; cartes sur B:C, D:E, F:G, H:I.
$syn->getColumnDimension('A')->setWidth(2);
foreach (array('B', 'C', 'D', 'E', 'F', 'G', 'H', 'I') as $col) {
    $syn->getColumnDimension($col)->setWidth(15);
}

sendText($syn, 'B1', 'Suivi QCM techniciens — Cameroun');
$syn->getStyle('B1')->getFont()->setBold(true)->setSize(18)->getColor()->setRGB(SEND_COLOR_HEADER);
$syn->getRowDimension(1)->setRowHeight(30);
sendText($syn, 'B2', 'Situation au ' . $generatedAt . ' · ' . $nPeople . ' techniciens');
$syn->getStyle('B2')->getFont()->getColor()->setRGB('5D6D7E');

$message = 'Les techniciens ont fait ' . round($techPct * 100) . ' % de leurs tests. '
    . 'Le retard est chez les managers : ' . round($mgrPct * 100) . ' % des évaluations faites, '
    . $evalTodoTotal . ' restent à faire.';
sendText($syn, 'B4', $message);
$syn->mergeCells('B4:I4');
$syn->getStyle('B4')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER)
    ->setIndent(1);
$syn->getStyle('B4')->getFont()->setBold(true)->setSize(13);
sendFill($syn, 'B4:I4', 'FEF5E7');
$syn->getRowDimension(4)->setRowHeight(44);
$syn->getRowDimension(5)->setRowHeight(10);

$allDone = $tot['tech_done'] + $tot['mgr_done'];
$allApp = $tot['tech_app'] + $tot['mgr_app'];
$kpis = array(
    array(sendPct($allDone, $allApp), '0%', 'Tous les tests faits',
        $allDone . ' sur ' . $allApp . ' (techniciens + managers)', SEND_COLOR_BAND),
    array($techPct, '0%', 'Tests faits par les techniciens',
        $tot['tech_done'] . ' sur ' . $tot['tech_app'], SEND_COLOR_DONE),
    array($mgrPct, '0%', 'Évaluations faites par les managers',
        $tot['mgr_done'] . ' sur ' . $tot['mgr_app'], SEND_COLOR_MGR),
    array(count($tot['none']), '0', 'Techniciens à relancer',
        'aucun test fait', SEND_COLOR_TECH),
);
foreach ($kpis as $i => $k) {
    $c1 = sendCol(2 + $i * 2);
    $c2 = sendCol(3 + $i * 2);
    foreach (array(6, 7, 8) as $row) {
        $syn->mergeCells($c1 . $row . ':' . $c2 . $row);
    }
    $syn->setCellValue($c1 . '6', $k[0]);
    $syn->getStyle($c1 . '6')->getNumberFormat()->setFormatCode($k[1]);
    $syn->getStyle($c1 . '6')->getFont()->setBold(true)->setSize(26)->getColor()->setRGB(SEND_COLOR_HEADER);
    sendText($syn, $c1 . '7', $k[2]);
    $syn->getStyle($c1 . '7')->getFont()->setBold(true);
    sendText($syn, $c1 . '8', $k[3]);
    $syn->getStyle($c1 . '8')->getFont()->setSize(9)->getColor()->setRGB('5D6D7E');
    $box = $c1 . '6:' . $c2 . '8';
    sendFill($syn, $box, $k[4]);
    $syn->getStyle($box)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    $syn->getStyle($box)->getBorders()->getOutline()
        ->setBorderStyle(Border::BORDER_THICK)->getColor()->setRGB('FFFFFF');
}
$syn->getRowDimension(6)->setRowHeight(42);
$syn->getRowDimension(7)->setRowHeight(20);
$syn->getRowDimension(8)->setRowHeight(18);

// Qui doit agir : une ligne par manager, barre proportionnelle au reste à faire.
$r = 10;
sendSectionTitle($syn, 'B' . $r, 'Évaluations qui restent à faire, par manager');
$r++;
sendText($syn, 'B' . $r, 'Le nom des techniciens à évaluer est dans l\'onglet « À faire managers ».');
$syn->getStyle('B' . $r)->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('5D6D7E');
$r++;
$maxTodo = 1;
$upToDate = array();
foreach ($byManager as $mg => $st) {
    $maxTodo = max($maxTodo, $st['todo']);
    if ($st['todo'] === 0) {
        $upToDate[] = $mg;
    }
}
foreach ($byManager as $mg => $st) {
    if ($st['todo'] === 0) {
        continue;
    }
    sendText($syn, 'B' . $r, $mg);
    $syn->mergeCells('B' . $r . ':D' . $r);
    $syn->setCellValue('E' . $r, $st['todo']);
    $syn->getStyle('E' . $r)->getFont()->setBold(true);
    $syn->getStyle('E' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setIndent(1);
    sendText($syn, 'F' . $r, str_repeat('█', max(1, (int) round($st['todo'] / $maxTodo * 30))));
    $syn->mergeCells('F' . $r . ':I' . $r);
    $syn->getStyle('F' . $r)->getFont()->getColor()->setRGB('E67E22');
    $syn->getStyle('B' . $r . ':I' . $r)->getBorders()->getBottom()
        ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('D5D8DC');
    $r++;
}
if (!empty($upToDate)) {
    sendText($syn, 'B' . $r, 'À jour : ' . implode(', ', $upToDate));
    $syn->getStyle('B' . $r)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E8449'));
    $r++;
}
$r++;

// À relancer : une phrase, pas un tableau.
if (!empty($tot['none'])) {
    $names = array();
    foreach ($tot['none'] as $p) {
        $names[] = $p['name'] . ' (' . $p['manager'] . ')';
    }
    sendText($syn, 'B' . $r, 'À relancer, aucun test fait : ' . implode(' · ', $names));
    $syn->mergeCells('B' . $r . ':I' . $r);
    $syn->getStyle('B' . $r)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER)
        ->setIndent(1);
    sendFill($syn, 'B' . $r . ':I' . $r, SEND_COLOR_TECH);
    $syn->getRowDimension($r)->setRowHeight(34);
    $r += 2;
}

sendText($syn, 'B' . $r, 'Détail : onglets « À faire managers » (qui évaluer) et « Détail par personne » (filtre par agence). Codes couleur : « Légende ».');
$syn->getStyle('B' . $r)->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('5D6D7E');
sendPrintSetup($syn);
$syn->getPageSetup()->setFitToHeight(1);

// ---------------------------------------------------------------------------
// Onglet 2 — À faire managers
// ---------------------------------------------------------------------------

$todo = $spreadsheet->createSheet();
$todo->setTitle('À faire managers');
$todoHeaders = array('Manager', 'Technicien à évaluer', 'Agence', 'Niveau', 'Statut');
sendHeader($todo, 1, $todoHeaders);
foreach (array('A' => 32, 'B' => 36, 'C' => 14, 'D' => 10, 'E' => 20) as $col => $w) {
    $todo->getColumnDimension($col)->setWidth($w);
}
$r = 2;
$band = false;
$prevManager = null;
foreach ($evalRows as $e) {
    if ($e['manager'] !== $prevManager) {
        $band = !$band;
        $prevManager = $e['manager'];
    }
    sendText($todo, 'A' . $r, $e['manager']);
    sendText($todo, 'B' . $r, $e['name']);
    sendText($todo, 'C' . $r, $e['agency']);
    sendText($todo, 'D' . $r, $e['level']);
    sendText($todo, 'E' . $r, $e['status']);
    if ($band) {
        sendFill($todo, 'A' . $r . ':D' . $r, SEND_COLOR_BAND);
    }
    sendFill($todo, 'E' . $r, $e['status'] === 'À évaluer' ? SEND_COLOR_MGR : SEND_COLOR_PROGRESS);
    $r++;
}
if ($r === 2) {
    sendText($todo, 'A2', 'Aucune évaluation manager en attente.');
    $r = 3;
} else {
    $todo->setAutoFilter('A1:E' . ($r - 1));
    sendBorders($todo, 'A1:E' . ($r - 1));
}
$todo->freezePane('A2');
sendPrintSetup($todo);
$todo->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);

// ---------------------------------------------------------------------------
// Onglet 3 — Détail par personne
// ---------------------------------------------------------------------------

$det = $spreadsheet->createSheet();
$det->setTitle('Détail par personne');
$detHeaders = array('Nom', 'Agence', 'Manager', 'Niveau cible', 'Tests faits',
    'Évaluations manager', 'Qui doit agir', 'Prochaine action');
foreach ($levels as $level) {
    $detHeaders[] = $level . "\nThéorique";
    $detHeaders[] = $level . "\nPratique";
    $detHeaders[] = $level . "\nÉval. manager";
}
sendHeader($det, 1, $detHeaders);
$det->getRowDimension(1)->setRowHeight(34);
$widths = array(34, 12, 28, 11, 11, 12, 18, 46);
foreach ($widths as $i => $w) {
    $det->getColumnDimension(sendCol($i + 1))->setWidth($w);
}
$firstStatusCol = count($widths) + 1;
for ($i = 0; $i < 9; $i++) {
    $det->getColumnDimension(sendCol($firstStatusCol + $i))->setWidth(14);
}
$lastDetCol = sendCol(count($detHeaders));

$r = 2;
foreach ($people as $p) {
    sendText($det, 'A' . $r, $p['name']);
    if ($p['who'] === SEND_WHO_DONE) {
        // Tout terminé (technicien + manager) : nom en vert, sans colorer la ligne.
        $det->getStyle('A' . $r)->getFont()->setBold(true)->getColor()->setRGB('1E8449');
    }
    sendText($det, 'B' . $r, $p['agency']);
    sendText($det, 'C' . $r, $p['manager']);
    sendText($det, 'D' . $r, $p['level_max']);
    sendText($det, 'E' . $r, $p['tech_app'] > 0 ? $p['tech_done'] . ' / ' . $p['tech_app'] : '—');
    sendText($det, 'F' . $r, $p['mgr_app'] > 0 ? $p['mgr_done'] . ' / ' . $p['mgr_app'] : '—');
    sendText($det, 'G' . $r, $p['who']);
    sendFill($det, 'G' . $r, sendStatusColor($p['who']));
    sendText($det, 'H' . $r, $p['next']);
    $c = $firstStatusCol;
    foreach ($levels as $level) {
        foreach (array('t', 'p', 'm') as $k) {
            $label = $p['levels'][$level][$k];
            $coord = sendCol($c) . $r;
            sendText($det, $coord, $label);
            sendFill($det, $coord, sendStatusColor($label));
            if ($label === SEND_OUT) {
                $det->getStyle($coord)->getFont()->getColor()->setRGB('AAB7B8');
            }
            $c++;
        }
    }
    $r++;
}
$det->getStyle('D2:G' . ($r - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$det->getStyle(sendCol($firstStatusCol) . '2:' . $lastDetCol . ($r - 1))->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
$det->getStyle('H2:H' . ($r - 1))->getAlignment()->setWrapText(true);
$det->getStyle('A2:' . $lastDetCol . ($r - 1))->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$det->setAutoFilter('A1:' . $lastDetCol . ($r - 1));
sendBorders($det, 'A1:' . $lastDetCol . ($r - 1));
$det->freezePane('B2');
sendPrintSetup($det);
$det->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);

// ---------------------------------------------------------------------------
// Onglet 4 — Légende
// ---------------------------------------------------------------------------

$leg = $spreadsheet->createSheet();
$leg->setTitle('Légende');
$leg->setShowGridlines(false);
$leg->getColumnDimension('A')->setWidth(26);
$leg->getColumnDimension('B')->setWidth(100);
sendSectionTitle($leg, 'A1', 'Statuts (1 case = 1 QCM)');
$legendRows = array(
    array(SEND_DONE, 'Le QCM est terminé.'),
    array(SEND_PROGRESS, 'Le QCM est commencé mais pas encore finalisé.'),
    array(SEND_TECH_TODO, 'Le technicien peut et doit composer ce QCM.'),
    array(SEND_MGR_TODO, 'Le manager doit faire cette évaluation. Possible à tout moment, même si le technicien n\'a pas encore composé ou si son niveau est verrouillé.'),
    array(SEND_LOCKED, 'Niveau pas encore ouvert pour le technicien (le manager peut déjà l\'évaluer).'),
    array(SEND_OUT, 'Au-delà du niveau cible de la personne : ne la concerne pas.'),
);
$r = 3;
foreach ($legendRows as $lr) {
    sendText($leg, 'A' . $r, $lr[0]);
    sendFill($leg, 'A' . $r, sendStatusColor($lr[0]));
    sendText($leg, 'B' . $r, $lr[1]);
    $r++;
}
sendBorders($leg, 'A3:B' . ($r - 1));
$r++;
sendSectionTitle($leg, 'A' . $r, 'Règles');
$r++;
$rules = array(
    array('1 niveau = 3 QCM', 'Théorique (connaissances) + Pratique (auto-évaluation des tâches) + Évaluation manager (validation par le manager). Niveaux : Junior, Senior, Expert.'),
    array('Ouverture d\'un niveau', 'Un niveau s\'ouvre quand le technicien a terminé le théorique ET la pratique du niveau précédent. L\'évaluation manager ne bloque pas le passage au niveau suivant.'),
    array('Tests faits', 'Tests théoriques + pratiques faits / tests théoriques + pratiques prévus jusqu\'au niveau cible (niveaux verrouillés compris).'),
    array('Nom en vert', 'Le technicien a tout terminé : ses tests et l\'évaluation de son manager.'),
    array('Niveau cible', 'Niveau maximum autorisé pour la personne.'),
    array('Données', 'Valeurs figées le ' . $generatedAt . ' (aucune formule) : chiffres identiques dans Excel, l\'aperçu mail et sur téléphone.'),
);
foreach ($rules as $rl) {
    sendText($leg, 'A' . $r, $rl[0]);
    $leg->getStyle('A' . $r)->getFont()->setBold(true);
    sendText($leg, 'B' . $r, $rl[1]);
    $leg->getStyle('B' . $r)->getAlignment()->setWrapText(true);
    $r++;
}
$leg->getStyle('A1:B' . $r)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

$spreadsheet->setActiveSheetIndex(0);
$spreadsheet->getProperties()->setTitle('Suivi QCM techniciens — Cameroun')->setCreator('MEDACAP');

if (!is_dir(dirname($outPath))) {
    mkdir(dirname($outPath), 0755, true);
}
try {
    (new Xlsx($spreadsheet))->save($outPath);
} catch (Throwable $e) {
    fwrite(STDERR, 'Écriture impossible (' . get_class($e) . '): ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Export : {$outPath}\n";
echo 'Tests techniciens faits : ' . $tot['tech_done'] . '/' . $tot['tech_app'] . ' (' . round($techPct * 100) . " %)\n";
echo 'Évaluations manager : ' . $tot['mgr_done'] . '/' . $tot['mgr_app'] . ' (' . round($mgrPct * 100) . " %)\n";
echo 'Évaluations manager à faire : ' . $evalTodoTotal . "\n";
echo 'Attendent seulement leur manager : ' . $tot['waiting_mgr'] . ' | Sans aucun test fait : ' . count($tot['none'])
    . ' | Tout terminé : ' . $tot['complete'] . "\n";
