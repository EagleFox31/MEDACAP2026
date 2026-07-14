<?php
/**
 * Export Excel PL 2026 — synthèse + feuille/candidat avec macro-domaines HR.
 * Usage: php scripts/export_pl2026_macro_hr.php
 */
require __DIR__ . '/export_paper_scores.php';

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/** Macro-domaines demandés par le HR → groupes fonctionnels MEDACAP */
const MACRO_DOMAINS = [
    'Maintenance préventive ou périodique' => [
        'Arbre de Transmission', 'Boite de Transfert', 'Boite de Vitesse', 'Boite de Vitesse Mécanique',
        'Demi Arbre de Roue', 'Direction', 'Freinage', 'Hydraulique', 'Moteur Diesel', 'Moteur Thermique',
        'Pont', 'Reducteur', 'Suspension', 'Suspension à Lame', 'Transversale', 'Climatisation',
    ],
    'Pneumatique (un peu)' => [
        'Pneumatique', 'Freinage Pneumatique', 'Suspension Pneumatique',
    ],
    'Electricité' => [
        'Electricité et Electronique', 'Réseaux de Communication', 'Assistance à la Conduite',
    ],
];

function specToMacro(string $spec): string
{
    foreach (MACRO_DOMAINS as $macro => $specs) {
        if (in_array($spec, $specs, true)) {
            return $macro;
        }
    }
    return 'Maintenance préventive ou périodique';
}

function buildMacroStats(array $paperQuestions, array $normalized, string $level): array
{
    $macros = [];
    foreach (array_keys(MACRO_DOMAINS) as $m) {
        $macros[$m] = ['correct_maitrise' => 0, 'answered' => 0, 'total' => 0, 'specs' => []];
    }

    foreach ($paperQuestions as $pq) {
        if ($pq['level'] !== $level) {
            continue;
        }
        $macro = specToMacro($pq['speciality']);
        $idx = $pq['index'];
        $norm = $normalized[$idx] ?? ['answered' => false, 'correct' => false];

        $macros[$macro]['total']++;
        $spec = $pq['speciality'];
        if (!isset($macros[$macro]['specs'][$spec])) {
            $macros[$macro]['specs'][$spec] = ['correct_maitrise' => 0, 'answered' => 0, 'total' => 0];
        }
        $macros[$macro]['specs'][$spec]['total']++;

        if ($norm['answered']) {
            $macros[$macro]['answered']++;
            $macros[$macro]['specs'][$spec]['answered']++;
            if ($norm['correct']) {
                $macros[$macro]['correct_maitrise']++;
                $macros[$macro]['specs'][$spec]['correct_maitrise']++;
            }
        }
    }

    $out = [];
    foreach ($macros as $name => $d) {
        $out[$name] = buildLevelBlock(
            $d['correct_maitrise'],
            $d['correct_maitrise'],
            $d['answered'],
            $d['total']
        );
        $out[$name]['specs'] = $d['specs'];
    }
    return $out;
}

function fmtNote($v): string
{
    return $v === null ? '—' : number_format((float)$v, 2, ',', '');
}

function writeStatsRow($sheet, int $row, string $label, array $block, bool $bold = false): void
{
    $sheet->setCellValue("A{$row}", $label);
    $sheet->setCellValue("B{$row}", $block['answered'] . '/' . $block['total']);
    $sheet->setCellValue("C{$row}", ($block['pct_completion'] ?? '—') . '%');
    $sheet->setCellValue("D{$row}", fmtNote($block['note_maitrise'] ?? null));
    if ($bold) {
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
    }
}

$paperIdStr = '6a452db87ae27d2cc30d8592';
$mongoUri = 'mongodb://10.68.0.7:27017';
$client = new Client($mongoUri);
$db = $client->academy;
$paperId = new ObjectId($paperIdStr);
$paper = $db->papers->findOne(['_id' => $paperId]);
if (!$paper) {
    fwrite(STDERR, "Paper introuvable\n");
    exit(1);
}

$paperLabel = toStr($paper['label'] ?? 'Paper');
$recruitmentWeights = [
    'Junior' => (float)(toArr($paper['ratios'] ?? [])['Junior'] ?? 50),
    'Senior' => (float)(toArr($paper['ratios'] ?? [])['Senior'] ?? 50),
];

$qOids = [];
foreach (toArr($paper['questions'] ?? []) as $x) {
    $qOids[] = $x instanceof ObjectId ? $x : new ObjectId((string)$x);
}
$qmap = [];
foreach ($db->questions->find(['_id' => ['$in' => $qOids]]) as $qd) {
    $qmap[(string)$qd['_id']] = $qd;
}

$paperQuestions = [];
$specialtyTotals = [];
$levelTotals = ['Junior' => 0, 'Senior' => 0, 'Expert' => 0];
foreach ($qOids as $i => $oid) {
    $k = (string)$oid;
    if (!isset($qmap[$k])) continue;
    $qd = $qmap[$k];
    $spec = toStr($qd['speciality'] ?? '') ?: '(Sans spécialité)';
    $level = toStr($qd['level'] ?? 'Autre');
    if (!isset($levelTotals[$level])) $levelTotals[$level] = 0;
    $levelTotals[$level]++;
    $paperQuestions[] = [
        'index' => $i, 'num' => $i + 1, 'id' => $k,
        'level' => $level, 'speciality' => $spec,
        'groupe_fonctionnel' => $spec,
        'ref' => toStr($qd['ref'] ?? ''), 'label' => toStr($qd['label'] ?? ''),
        'answer' => toStr($qd['answer'] ?? ''),
    ];
    $specialtyTotals[$spec] = ($specialtyTotals[$spec] ?? 0) + 1;
}
$specialtyList = array_keys($specialtyTotals);
sort($specialtyList);

$candidates = [];
foreach ($db->users->find([
    'active' => true,
    '$or' => [['papers' => $paperId], ['role' => $paperLabel], ['roles' => $paperLabel]],
]) as $u) {
    $profile = strtoupper(trim(toStr($u['profile'] ?? '')));
    $country = strtoupper(trim(toStr($u['country'] ?? '')));
    if (!in_array($profile, ['CANDIDAT', 'CANDIDATE'], true)) continue;
    if (!in_array($country, ['CAMEROUN', 'CAMEROON'], true)) continue;
    if (!empty($u['loadtestTag'])) continue;
    if (stripos(toStr($u['username'] ?? ''), 'demo') !== false) continue;

    $c = buildCandidateData(
        $db, $u, $paperId, $paperQuestions, $specialtyList, $specialtyTotals,
        $levelTotals, $recruitmentWeights, 70.0
    );
    if (($c['global']['answered'] ?? 0) <= 0 && $c['status'] !== 'Terminé') continue;

    $global = $db->results->findOne([
        'user' => $u['_id'], 'paper' => $paperId, 'type' => 'Factuel', 'level' => 'Mixed',
        'userAnswers' => ['$exists' => true],
    ], ['sort' => ['created' => -1]]);
    $exam = $db->exams->findOne(['user' => $u['_id'], 'paper' => $paperId], ['sort' => ['updated' => -1]]);
    $normalized = normalizeAnswersByPaperIndex($paperQuestions, $global, $exam);

    $c['normalized'] = $normalized;
    $c['macro_junior'] = buildMacroStats($paperQuestions, $normalized, 'Junior');
    $c['macro_senior'] = buildMacroStats($paperQuestions, $normalized, 'Senior');
    $candidates[] = $c;
}
usort($candidates, fn($a, $b) => strcmp($a['name'], $b['name']));

$spreadsheet = new Spreadsheet();
$summary = $spreadsheet->getActiveSheet();
$summary->setTitle('Synthèse');
$summary->setCellValue('A1', 'Épreuve PL 2026 — Résultats candidats');
$summary->mergeCells('A1:N1');
$summary->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$summary->setCellValue('A2', $paperLabel);
$summary->setCellValue('A3', 'Généré le ' . date('d/m/Y H:i'));
$summary->setCellValue('A4', 'Note maîtrise /20 = bonnes réponses ÷ questions répondues');

$row = 6;
$headers = [
    'Candidat', 'Identifiant', 'Date composition', 'Statut',
    'Global répondues', 'Couverture %', 'Maîtrise /20',
    'J répondues', 'J couverture %', 'J maîtrise /20',
    'S répondues', 'S couverture %', 'S maîtrise /20',
    'Note recrutement /20',
];
foreach ($headers as $i => $h) {
    $summary->setCellValueByColumnAndRow($i + 1, $row, $h);
}
styleHeaderRow($summary, 'A' . $row . ':N' . $row);
$row++;

foreach ($candidates as $c) {
    $g = $c['global'];
    $j = $c['levels']['Junior'] ?? buildLevelBlock(0, 0, 0, 0);
    $s = $c['levels']['Senior'] ?? buildLevelBlock(0, 0, 0, 0);
    $vals = [
        $c['name'], $c['username'], $c['finished_at'], $c['status'],
        $g['answered'] . '/' . $g['total'], $g['pct_completion'] . '%', fmtNote($g['note_maitrise']),
        $j['answered'] . '/' . $j['total'], $j['pct_completion'] . '%', fmtNote($j['note_maitrise']),
        $s['answered'] . '/' . $s['total'], $s['pct_completion'] . '%', fmtNote($s['note_maitrise']),
        fmtNote($c['note_recrutement']),
    ];
    foreach ($vals as $i => $v) {
        $summary->setCellValueByColumnAndRow($i + 1, $row, $v);
    }
    $row++;
}
autoSizeColumns($summary, 1, count($headers));
$summary->freezePane('A7');

$usedTitles = ['Synthèse' => true];
foreach ($candidates as $c) {
    $title = sanitizeSheetTitle($c['name'] !== '' ? $c['name'] : $c['username']);
    $base = $title;
    $n = 1;
    while (isset($usedTitles[$title])) {
        $n++;
        $title = sanitizeSheetTitle($base . ' ' . $n);
    }
    $usedTitles[$title] = true;

    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle($title);
    $sheet->setCellValue('A1', $c['name']);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
    $sheet->setCellValue('A2', 'Identifiant : ' . $c['username']);
    $sheet->setCellValue('A3', 'Composition : ' . $c['finished_at'] . ' — ' . $c['status']);

    $r = 5;
    foreach (['Junior' => $c['macro_junior'], 'Senior' => $c['macro_senior']] as $levelLabel => $macros) {
        $lvBlock = $c['levels'][$levelLabel] ?? buildLevelBlock(0, 0, 0, 0);

        $sheet->setCellValue("A{$r}", strtoupper($levelLabel) . ' — ' . ($levelLabel === 'Junior' ? 'Maintenance' : 'Réparation'));
        $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(12);
        $r++;
        $sheet->fromArray(['Macro-domaine HR', 'Répondues', 'Couverture', 'Maîtrise /20'], null, "A{$r}");
        styleHeaderRow($sheet, "A{$r}:D{$r}");
        $r++;

        foreach (array_keys(MACRO_DOMAINS) as $macroName) {
            $block = $macros[$macroName] ?? buildLevelBlock(0, 0, 0, 0);
            writeStatsRow($sheet, $r, $macroName, $block);
            $r++;
        }
        writeStatsRow($sheet, $r, 'TOTAL ' . $levelLabel, $lvBlock, true);
        $r += 2;

        $sheet->setCellValue("A{$r}", 'Détail par spécialité — ' . $levelLabel);
        $sheet->getStyle("A{$r}")->getFont()->setBold(true);
        $r++;
        $sheet->fromArray(['Macro-domaine', 'Spécialité technique', 'Répondues', 'Couverture', 'Maîtrise /20'], null, "A{$r}");
        styleHeaderRow($sheet, "A{$r}:E{$r}");
        $r++;

        foreach (array_keys(MACRO_DOMAINS) as $macroName) {
            $specs = $macros[$macroName]['specs'] ?? [];
            ksort($specs);
            foreach ($specs as $specName => $sd) {
                if (($sd['total'] ?? 0) <= 0) continue;
                $block = buildLevelBlock($sd['correct_maitrise'], $sd['correct_maitrise'], $sd['answered'], $sd['total']);
                $sheet->setCellValue("A{$r}", $macroName);
                $sheet->setCellValue("B{$r}", $specName);
                $sheet->setCellValue("C{$r}", $block['answered'] . '/' . $block['total']);
                $sheet->setCellValue("D{$r}", ($block['pct_completion'] ?? '—') . '%');
                $sheet->setCellValue("E{$r}", fmtNote($block['note_maitrise']));
                $r++;
            }
        }
        $r += 2;
    }

    $g = $c['global'];
    $sheet->setCellValue("A{$r}", 'GLOBAL');
    $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(12);
    $r++;
    writeStatsRow($sheet, $r, 'Toutes spécialités', $g, true);
    autoSizeColumns($sheet, 1, 5);
}

$outDir = __DIR__ . '/out';
if (!is_dir($outDir)) mkdir($outDir, 0777, true);
$outPath = $outDir . '/PL_2026_resultats_macro_HR_' . date('Ymd_His') . '.xlsx';
(new Xlsx($spreadsheet))->save($outPath);

echo json_encode([
    'ok' => true,
    'file' => $outPath,
    'candidates' => count($candidates),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
