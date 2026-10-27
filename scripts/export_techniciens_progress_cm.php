<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Export Excel — suivi QCM techniciens Cameroun (RH).
 *
 * Libellés métier : Théorique / Pratique / Évaluation manager
 * Statuts : Terminé | En cours | À composer | En attente…
 *
 * Usage:
 *   php scripts/export_techniciens_progress_cm.php
 *   php scripts/export_techniciens_progress_cm.php --mongo-uri=mongodb://10.68.0.7:27017
 */
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/mongo_compat.php';
require_once __DIR__ . '/includes/cfao_cm_cohort.php';

use MongoDB\Client;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

function argValue(array $argv, $name, $default = null)
{
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

const ST_DONE = 4;
const ST_PROGRESS = 3;
const ST_TODO = 2;
const ST_WAIT = 1;
const ST_NONE = 0;

const ST_NA = -1;

if (defined('MEDACAP_EXPORT_AS_LIB')) {
    return;
}

$uri = argValue($argv, 'mongo-uri', medacapMongoUri('mongodb://10.68.0.7:27017'));
$outPath = __DIR__ . '/../exports/suivi_qcm_techniciens_cm_' . date('Y-m-d_His') . '.xlsx';

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

$personCache = array();

function displayPersonName($doc)
{
    if (!$doc) {
        return '';
    }
    if ($doc instanceof MongoDB\Model\BSONDocument) {
        $doc = json_decode(json_encode($doc), true);
    }
    $first = trim((string) ($doc['firstName'] ?? ''));
    $last = trim((string) ($doc['lastName'] ?? ''));
    $name = trim($first . ' ' . $last);
    if ($name !== '') {
        return $name;
    }
    return trim((string) ($doc['username'] ?? ''));
}

function getPersonName($usersCol, &$cache, $personId)
{
    if (!$personId) {
        return '';
    }
    $key = (string) $personId;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $doc = $usersCol->findOne(array('_id' => $personId), array(
        'projection' => array('username' => 1, 'firstName' => 1, 'lastName' => 1),
    ));
    $cache[$key] = displayPersonName($doc);
    return $cache[$key];
}

function findResult($resultsCol, $uid, $testId, $level, $type, $typeR = null)
{
    $q = array(
        'user' => $uid,
        'test' => $testId,
        'level' => $level,
        'type' => $type,
        'active' => true,
    );
    if ($typeR !== null) {
        $q['typeR'] = $typeR;
    }
    return $resultsCol->findOne($q);
}

function findActiveExam($examsCol, $uid, $testId, $managerId = null)
{
    $q = array(
        'user' => $uid,
        'test' => $testId,
        'active' => true,
    );
    if ($managerId) {
        $q['manager'] = $managerId;
    }
    return $examsCol->findOne($q, array('sort' => array('_id' => -1)));
}

function statusTheorique($testsCol, $resultsCol, $allocCol, $examsCol, $uid, $level)
{
    $type = 'Factuel';
    $test = $testsCol->findOne(array(
        'user' => $uid, 'level' => $level, 'type' => $type, 'active' => true,
    ));
    if (!$test) {
        return cell('—', ST_NONE);
    }

    $alloc = $allocCol->findOne(array('user' => $uid, 'level' => $level, 'type' => $type));
    if (!$alloc || empty($alloc['activeTest'])) {
        return cell('En attente niveau inférieur', ST_WAIT);
    }

    if (findResult($resultsCol, $uid, $test['_id'], $level, $type)) {
        return cell('Terminé', ST_DONE);
    }
    if (findActiveExam($examsCol, $uid, $test['_id'])) {
        return cell('En cours', ST_PROGRESS);
    }
    return cell('À composer', ST_TODO);
}

function statusPratique($testsCol, $resultsCol, $allocCol, $examsCol, $uid, $level)
{
    $type = 'Declaratif';
    $test = $testsCol->findOne(array(
        'user' => $uid, 'level' => $level, 'type' => $type, 'active' => true,
    ));
    if (!$test) {
        return cell('—', ST_NONE);
    }

    $alloc = $allocCol->findOne(array('user' => $uid, 'level' => $level, 'type' => $type));
    if (!$alloc || empty($alloc['activeTest'])) {
        return cell('En attente niveau inférieur', ST_WAIT);
    }

    if (findResult($resultsCol, $uid, $test['_id'], $level, $type, 'Techniciens')
        || findResult($resultsCol, $uid, $test['_id'], $level, $type, 'Technicien - Manager')) {
        return cell('Terminé', ST_DONE);
    }
    if (findActiveExam($examsCol, $uid, $test['_id'])) {
        return cell('En cours', ST_PROGRESS);
    }
    return cell('À composer', ST_TODO);
}

function statusManager($testsCol, $resultsCol, $allocCol, $examsCol, $usersCol, $uid, $level, $userDoc)
{
    $type = 'Declaratif';
    $test = $testsCol->findOne(array(
        'user' => $uid, 'level' => $level, 'type' => $type, 'active' => true,
    ));
    if (!$test) {
        return cell('—', ST_NONE);
    }

    $alloc = $allocCol->findOne(array('user' => $uid, 'level' => $level, 'type' => $type));
    if (!$alloc) {
        return cell('—', ST_NONE);
    }

    if (findResult($resultsCol, $uid, $test['_id'], $level, $type, 'Technicien - Manager')) {
        return cell('Terminé', ST_DONE);
    }

    if (!empty($alloc['activeManager'])) {
        return cell('Terminé', ST_DONE);
    }

    $managerId = isset($userDoc['manager']) ? $userDoc['manager'] : null;
    if ($managerId && findActiveExam($examsCol, $uid, $test['_id'], $managerId)) {
        return cell('En cours', ST_PROGRESS);
    }

    return cell('À évaluer', ST_TODO);
}

function cell($label, $sort)
{
    return array('label' => $label, 'sort' => $sort);
}

function buildCell($modality, $testsCol, $resultsCol, $allocCol, $examsCol, $usersCol, $uid, $level, $userDoc)
{
    if ($modality === 'theorique') {
        return statusTheorique($testsCol, $resultsCol, $allocCol, $examsCol, $uid, $level);
    }
    if ($modality === 'pratique') {
        return statusPratique($testsCol, $resultsCol, $allocCol, $examsCol, $uid, $level);
    }
    return statusManager($testsCol, $resultsCol, $allocCol, $examsCol, $usersCol, $uid, $level, $userDoc);
}

function progressSummary(array $cells)
{
    $done = 0;
    $progress = 0;
    $todo = 0;
    $wait = 0;
    foreach ($cells as $c) {
        if ($c['sort'] === ST_DONE) {
            $done++;
        } elseif ($c['sort'] === ST_PROGRESS) {
            $progress++;
        } elseif ($c['sort'] === ST_TODO) {
            $todo++;
        } elseif ($c['sort'] === ST_WAIT) {
            $wait++;
        }
    }
    $parts = array();
    if ($done > 0) {
        $parts[] = $done . ' terminé' . ($done > 1 ? 's' : '');
    }
    if ($progress > 0) {
        $parts[] = $progress . ' en cours';
    }
    if ($todo > 0) {
        $parts[] = $todo . ' à composer';
    }
    if ($wait > 0) {
        $parts[] = $wait . ' en attente';
    }
    return $parts !== array() ? implode(' · ', $parts) : '—';
}

function currentStep(array $cells, array $testColsMeta)
{
    foreach ($testColsMeta as $meta) {
        $c = $cells[$meta['key']];
        if ($c['sort'] === ST_PROGRESS || $c['sort'] === ST_TODO) {
            return $meta['header'];
        }
    }
    foreach ($testColsMeta as $meta) {
        $c = $cells[$meta['key']];
        if ($c['sort'] === ST_WAIT && strpos($c['label'], 'niveau inférieur') === false) {
            return $meta['header'] . ' (' . $c['label'] . ')';
        }
    }
    $allDone = true;
    foreach ($cells as $c) {
        if ($c['sort'] !== ST_DONE && $c['sort'] !== ST_NONE && $c['label'] !== '—') {
            if ($c['sort'] !== ST_WAIT || strpos($c['label'], 'niveau inférieur') !== false) {
                // still locked levels ahead
            }
        }
        if ($c['sort'] === ST_TODO || $c['sort'] === ST_PROGRESS) {
            $allDone = false;
        }
    }
    foreach ($testColsMeta as $meta) {
        $c = $cells[$meta['key']];
        if ($c['sort'] === ST_WAIT && strpos($c['label'], 'niveau inférieur') !== false) {
            return $meta['header'] . ' (en attente niveau inférieur)';
        }
    }
    return 'Parcours terminé (niveaux débloqués composés)';
}

function nonTechnicianStatus($profile)
{
    if ($profile === 'Manager') {
        return cell('Profil Manager — hors parcours technicien', ST_NA);
    }
    if ($profile === 'Candidat') {
        return cell('Profil Candidat — compte à vérifier', ST_NA);
    }
    return cell('Hors parcours technicien', ST_NA);
}

function missingUserStatus()
{
    return cell('Non trouvé en base MEDACAP', ST_NA);
}

function isTechnicianProfile($profile)
{
    return strtolower((string) $profile) === 'technicien';
}

/** Technicien ou Manager avec test=true (Manager-Technicien : compose son QCM). */
function hasTechnicianQcmPath($user)
{
    if (!$user) {
        return false;
    }
    $profile = isset($user['profile']) ? (string) $user['profile'] : '';
    if (isTechnicianProfile($profile)) {
        return true;
    }
    return $profile === 'Manager' && !empty($user['test']);
}

function isManagerEvaluatorOnly($user)
{
    if (!$user) {
        return false;
    }
    return isset($user['profile']) && $user['profile'] === 'Manager' && empty($user['test']);
}

function managerEvalStats($resultsCol, $allocCol, $usersCol, $managerId)
{
    $manager = $usersCol->findOne(array('_id' => $managerId));
    if (!$manager || empty($manager['users'])) {
        return array('done' => 0, 'pending' => 0, 'total' => 0);
    }
    $collabs = $manager['users'];
    if ($collabs instanceof Traversable) {
        $collabs = iterator_to_array($collabs);
    }
    $done = 0;
    $pending = 0;
    $total = 0;
    foreach ($collabs as $cid) {
        foreach ($allocCol->find(array('user' => $cid, 'type' => 'Declaratif')) as $alloc) {
            if (empty($alloc['test'])) {
                continue;
            }
            $testId = $alloc['test'];
            $level = isset($alloc['level']) ? $alloc['level'] : '';
            $total++;
            $mgrDone = $resultsCol->findOne(array(
                'user' => $cid,
                'manager' => $managerId,
                'test' => $testId,
                'level' => $level,
                'type' => 'Declaratif',
                'typeR' => 'Technicien - Manager',
                'active' => true,
            ));
            if ($mgrDone || !empty($alloc['activeManager'])) {
                $done++;
            } else {
                $pending++;
            }
        }
    }
    return array('done' => $done, 'pending' => $pending, 'total' => $total);
}

function displayProfileLabel($user)
{
    if (!$user) {
        return 'Absent';
    }
    $profile = isset($user['profile']) ? (string) $user['profile'] : '';
    if ($profile === 'Manager' && !empty($user['test'])) {
        return 'Manager (compose + évalue)';
    }
    if ($profile === 'Manager') {
        return 'Manager (évalue seulement)';
    }
    return $profile;
}

function buildRowCells($user, $testsCol, $resultsCol, $allocCol, $examsCol, $usersCol, $testCols)
{
    $cells = array();
    if (!$user) {
        foreach ($testCols as $meta) {
            $cells[$meta['key']] = missingUserStatus();
        }
        return $cells;
    }
    $profile = isset($user['profile']) ? $user['profile'] : '';
    if (!hasTechnicianQcmPath($user)) {
        $na = nonTechnicianStatus($profile);
        foreach ($testCols as $meta) {
            $cells[$meta['key']] = $na;
        }
        return $cells;
    }
    $uid = $user['_id'];
    foreach ($testCols as $meta) {
        $cells[$meta['key']] = buildCell(
            $meta['modality'],
            $testsCol,
            $resultsCol,
            $allocCol,
            $examsCol,
            $usersCol,
            $uid,
            $meta['level'],
            $user
        );
    }
    return $cells;
}

function profileSummary($user, array $cells, $resultsCol = null, $allocCol = null, $usersCol = null)
{
    if (!$user) {
        return 'Non trouvé en base';
    }
    if (!hasTechnicianQcmPath($user)) {
        $profile = isset($user['profile']) ? (string) $user['profile'] : '';
        if ($profile === 'Manager' && $resultsCol && $allocCol && $usersCol) {
            $ev = managerEvalStats($resultsCol, $allocCol, $usersCol, $user['_id']);
            if ($ev['total'] > 0) {
                return 'Évaluations équipe : ' . $ev['done'] . '/' . $ev['total'] . ' faites';
            }
        }
        return displayProfileLabel($user);
    }
    $summary = progressSummary($cells);
    if (isset($user['profile']) && $user['profile'] === 'Manager' && $resultsCol && $allocCol && $usersCol) {
        $ev = managerEvalStats($resultsCol, $allocCol, $usersCol, $user['_id']);
        if ($ev['total'] > 0) {
            $summary .= ' · Évaluations équipe : ' . $ev['done'] . '/' . $ev['total'];
        }
    }
    return $summary;
}

function profileStep($user, array $cells, array $testColsMeta)
{
    if (!$user) {
        return 'Compte MEDACAP manquant';
    }
    if (!hasTechnicianQcmPath($user)) {
        if (isManagerEvaluatorOnly($user)) {
            return 'Évaluer les pratiques de ses techniciens';
        }
        return 'Pas de QCM technicien (' . displayProfileLabel($user) . ')';
    }
    return currentStep($cells, $testColsMeta);
}

function cellColor($sort, $label)
{
    if ($sort === ST_NA) {
        return 'EBDEF0';
    }
    if ($sort === ST_DONE) {
        return 'D5F5E3';
    }
    if ($sort === ST_PROGRESS) {
        return 'D6EAF8';
    }
    if ($sort === ST_TODO) {
        return 'FCF3CF';
    }
    if ($label === '—') {
        return 'F2F3F4';
    }
    return 'E5E8E8';
}

$rows = array();
foreach ($cohort as $entry) {
    $user = cfaoCmResolveUser($usersCol, $entry);
    $cells = buildRowCells($user, $testsCol, $resultsCol, $allocCol, $examsCol, $usersCol, $testCols);

    $rows[] = array(
        'no' => $entry['no'],
        'list_name' => $entry['rawName'],
        'role' => $entry['role'],
        'agency' => $entry['agency'],
        'name' => $user ? displayPersonName($user) : '',
        'profile' => $user ? displayProfileLabel($user) : 'Absent',
        'matricule' => $user ? (string) ($user['matricule'] ?? '') : '',
        'level_max' => $user ? (string) ($user['level'] ?? '') : '',
        'manager' => $user ? getPersonName($usersCol, $personCache, $user['manager'] ?? null) : '',
        'recrutment' => $user ? (string) ($user['recrutmentDate'] ?? ($user['created'] ?? '')) : '',
        'cells' => $cells,
        'progress' => profileSummary($user, $cells, $resultsCol, $allocCol, $usersCol),
        'step' => profileStep($user, $cells, $testCols),
    );
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Suivi QCM');

$headers = array(
    'N°', 'Nom (liste CFAO)', 'Agence', 'Fonction', 'Profil MEDACAP',
    'Nom en base', 'Matricule', 'Niveau max autorisé', 'Manager (N+2)',
    'Date inscription', 'Situation globale', 'Prochaine étape',
);
foreach ($testCols as $meta) {
    $headers[] = $meta['header'];
}

foreach ($headers as $i => $h) {
    $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
}
$lastCol = count($headers);
$sheet->getStyleByColumnAndRow(1, 1, $lastCol, 1)->getFont()->setBold(true);
$sheet->getStyleByColumnAndRow(1, 1, $lastCol, 1)->getFill()
    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('24235C');
$sheet->getStyleByColumnAndRow(1, 1, $lastCol, 1)->getFont()->getColor()->setRGB('FFFFFF');

$r = 2;
foreach ($rows as $row) {
    $sheet->setCellValueByColumnAndRow(1, $r, $row['no']);
    $sheet->setCellValueByColumnAndRow(2, $r, $row['list_name']);
    $sheet->setCellValueByColumnAndRow(3, $r, $row['agency']);
    $sheet->setCellValueByColumnAndRow(4, $r, $row['role']);
    $sheet->setCellValueByColumnAndRow(5, $r, $row['profile']);
    $sheet->setCellValueByColumnAndRow(6, $r, $row['name']);
    $sheet->setCellValueByColumnAndRow(7, $r, $row['matricule']);
    $sheet->setCellValueByColumnAndRow(8, $r, $row['level_max']);
    $sheet->setCellValueByColumnAndRow(9, $r, $row['manager']);
    $sheet->setCellValueByColumnAndRow(10, $r, $row['recrutment']);
    $sheet->setCellValueByColumnAndRow(11, $r, $row['progress']);
    $sheet->setCellValueByColumnAndRow(12, $r, $row['step']);

    $c = 13;
    foreach ($testCols as $meta) {
        $cell = $row['cells'][$meta['key']];
        $sheet->setCellValueByColumnAndRow($c, $r, $cell['label']);
        $rgb = cellColor($cell['sort'], $cell['label']);
        $sheet->getStyleByColumnAndRow($c, $r)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
        $c++;
    }
    $r++;
}

foreach (range(1, $lastCol) as $col) {
    $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
}
$sheet->freezePane('A2');

$legend = $spreadsheet->createSheet();
$legend->setTitle('Legende');
$legend->setCellValue('A1', 'Légende des statuts');
$legend->setCellValue('A3', 'Terminé');
$legend->setCellValue('B3', 'L\'épreuve a été composée jusqu\'au bout.');
$legend->setCellValue('A4', 'En cours');
$legend->setCellValue('B4', 'Composition commencée, pas encore finalisée.');
$legend->setCellValue('A5', 'À composer');
$legend->setCellValue('B5', 'Épreuve débloquée, le technicien (ou le manager) peut la passer.');
$legend->setCellValue('A6', 'En attente niveau inférieur');
$legend->setCellValue('B6', 'Le théorique et la pratique du niveau précédent ne sont pas terminés (l\'évaluation manager ne bloque pas).');
$legend->setCellValue('A7', 'En attente pratique');
$legend->setCellValue('B7', 'Pratique technicien non terminée (colonne Pratique uniquement).');
$legend->setCellValue('A8', 'À évaluer');
$legend->setCellValue('B8', 'Colonne Évaluation manager : le N+2 peut évaluer sans attendre la pratique du technicien.');
$legend->setCellValue('A9', 'Violet clair');
$legend->setCellValue('B9', 'Profil sans QCM personnel (Candidat, absent, ou manager qui n\'évalue que son équipe).');
$legend->setCellValue('A10', 'Manager (compose + évalue)');
$legend->setCellValue('B10', 'Comme un technicien : colonnes théorique / pratique / évaluation manager + synthèse évaluations équipe.');
$legend->setCellValue('A11', 'Colonnes');
$legend->setCellValue('B11', 'Théorique = QCM connaissances · Pratique = auto-évaluation tâches · Évaluation manager = validation N+2');
$legend->setCellValue('A12', 'Périmètre');
$legend->setCellValue('B12', count($rows) . ' personnes — liste CFAO Cameroun 2026');
$legend->setCellValue('A13', 'Généré le');
$legend->setCellValue('B13', date('d/m/Y H:i'));
$legend->getStyle('A9')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EBDEF0');
$legend->getStyle('A3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D5F5E3');
$legend->getStyle('A4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D6EAF8');
$legend->getStyle('A5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCF3CF');
$legend->getStyle('A6:A8')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E8E8');

$byAgency = $spreadsheet->createSheet();
$byAgency->setTitle('Synthese agences');
$byAgency->setCellValue('A1', 'Agence');
$byAgency->setCellValue('B1', 'Personnes (liste CFAO)');
$byAgency->setCellValue('C1', 'Techniciens');
$byAgency->setCellValue('D1', 'Au moins 1 épreuve terminée');
$byAgency->setCellValue('E1', 'Rien commencé (techn.)');
$byAgency->setCellValue('F1', 'Junior théorique terminé');
$byAgency->getStyle('A1:F1')->getFont()->setBold(true);

$agencyStats = array();
foreach ($rows as $row) {
    $ag = $row['agency'] !== '' ? $row['agency'] : '(sans agence)';
    if (!isset($agencyStats[$ag])) {
        $agencyStats[$ag] = array('total' => 0, 'tech' => 0, 'started' => 0, 'none' => 0, 'junior_t' => 0);
    }
    $agencyStats[$ag]['total']++;
    $onQcmPath = ($row['profile'] === 'Technicien' || $row['profile'] === 'Manager (compose + évalue)');
    if (!$onQcmPath) {
        continue;
    }
    $agencyStats[$ag]['tech']++;
    $any = false;
    foreach ($row['cells'] as $c) {
        if ($c['sort'] === ST_DONE) {
            $any = true;
            break;
        }
    }
    if ($any) {
        $agencyStats[$ag]['started']++;
    } else {
        $agencyStats[$ag]['none']++;
    }
    if (($row['cells']['Junior_theorique']['sort'] ?? 0) === ST_DONE) {
        $agencyStats[$ag]['junior_t']++;
    }
}
ksort($agencyStats);
$ar = 2;
foreach ($agencyStats as $ag => $st) {
    $byAgency->setCellValue("A{$ar}", $ag);
    $byAgency->setCellValue("B{$ar}", $st['total']);
    $byAgency->setCellValue("C{$ar}", $st['tech']);
    $byAgency->setCellValue("D{$ar}", $st['started']);
    $byAgency->setCellValue("E{$ar}", $st['none']);
    $byAgency->setCellValue("F{$ar}", $st['junior_t']);
    $ar++;
}

if (!is_dir(dirname($outPath))) {
    mkdir(dirname($outPath), 0755, true);
}
$writer = new Xlsx($spreadsheet);
$writer->save($outPath);

echo "Export OK: {$outPath}\n";
echo 'Personnes liste CFAO: ' . count($rows) . "\n";
$techCount = 0;
$managerCompose = 0;
$managerEvalOnly = 0;
foreach ($rows as $row) {
    if ($row['profile'] === 'Technicien') {
        $techCount++;
    } elseif ($row['profile'] === 'Manager (compose + évalue)') {
        $managerCompose++;
    } elseif ($row['profile'] === 'Manager (évalue seulement)') {
        $managerEvalOnly++;
    }
}
echo "Techniciens: {$techCount} | Managers (compose+évalue): {$managerCompose} | Managers (évalue seul.): {$managerEvalOnly}\n";
