<?php
/**
 * Export Excel — résultats paper avec notes maîtrise / complétude par niveau (Junior, Senior, Expert).
 *
 * Usage:
 *   php scripts/export_paper_scores.php --paper-id=6a324e65516f0000880007ac
 */

if (defined('MEDACAP_PAPER_SCORES_LOADED')) {
    return;
}
define('MEDACAP_PAPER_SCORES_LOADED', true);

require __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

function argValue(array $argv, string $name, ?string $default = null): ?string
{
    $prefix = "--{$name}=";
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

function toArr($v): array
{
    if ($v instanceof MongoDB\Model\BSONArray || $v instanceof MongoDB\Model\BSONDocument) {
        return $v->getArrayCopy();
    }
    return is_array($v) ? $v : [];
}

function toStr($v): string
{
    if (is_string($v)) {
        return $v;
    }
    if (is_scalar($v)) {
        return (string) $v;
    }
    return '';
}

function noteSur20(int $correct, int $total, int $decimals = 2): ?float
{
    if ($total <= 0) {
        return null;
    }
    return round(($correct / $total) * 20, $decimals);
}

function pct(int $part, int $total, int $decimals = 1): ?float
{
    if ($total <= 0) {
        return null;
    }
    return round(100 * $part / $total, $decimals);
}

function sanitizeSheetTitle(string $title): string
{
    $title = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $title) ?? $title;
    $title = trim($title);
    if ($title === '') {
        $title = 'Feuille';
    }
    return mb_substr($title, 0, 31);
}

function styleHeaderRow($sheet, string $range): void
{
    $sheet->getStyle($range)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '005BA9'],
        ],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
}

function autoSizeColumns($sheet, int $fromCol, int $toCol): void
{
    for ($c = $fromCol; $c <= $toCol; $c++) {
        $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
    }
}

function normalizeAnswersByPaperIndex(array $paperQuestions, $global, $exam): array
{
    $n = count($paperQuestions);
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        $out[$i] = ['answered' => false, 'correct' => false, 'user_answer' => ''];
    }

    if ($global && isset($global['answers'])) {
        $resultAnswers = array_values(toArr($global['answers']));
        $resultQuestions = array_values(toArr($global['questions'] ?? []));
        $userAnswers = array_values(toArr($global['userAnswers'] ?? []));
        $originalOrder = array_values(toArr($global['originalOrder'] ?? []));

        $resultQIndex = [];
        foreach ($resultQuestions as $ri => $oid) {
            $resultQIndex[(string) $oid] = $ri;
        }
        $ooIndex = [];
        foreach ($originalOrder as $ei => $oid) {
            $ooIndex[(string) $oid] = $ei;
        }

        for ($i = 0; $i < $n; $i++) {
            $qid = $paperQuestions[$i]['id'];
            $ri = $resultQIndex[$qid] ?? null;
            $status = $ri !== null ? toStr($resultAnswers[$ri] ?? '') : '';

            $userAnswer = '';
            if (isset($ooIndex[$qid]) && isset($userAnswers[$ooIndex[$qid]])) {
                $ua = toStr($userAnswers[$ooIndex[$qid]]);
                if ($ua !== '' && $ua !== 'null') {
                    $userAnswer = $ua;
                }
            }

            // answers[] utilise "Non maitrisé" pour les questions non traitées ;
            // seul userAnswers (via originalOrder) indique une vraie réponse.
            $answered = $userAnswer !== '';
            $correct = ($status === 'Maitrisé');
            $out[$i] = [
                'answered' => $answered,
                'correct' => $correct,
                'user_answer' => $userAnswer,
            ];
        }
        return $out;
    }

    if ($exam) {
        $examAnswers = array_values(toArr($exam['answers'] ?? []));
        $originalOrder = array_values(toArr($exam['originalOrder'] ?? []));
        if (empty($originalOrder)) {
            $originalOrder = array_values(toArr($exam['questions'] ?? []));
        }
        $paperIdxByQid = [];
        foreach ($paperQuestions as $pq) {
            $paperIdxByQid[$pq['id']] = $pq['index'];
        }

        foreach ($originalOrder as $ei => $oid) {
            $qid = (string) $oid;
            if (!isset($paperIdxByQid[$qid])) {
                continue;
            }
            $pi = $paperIdxByQid[$qid];
            $ua = toStr($examAnswers[$ei] ?? '');
            $answered = ($ua !== '' && $ua !== 'null');
            $out[$pi] = [
                'answered' => $answered,
                'correct' => $answered && ($ua === $paperQuestions[$pi]['answer']),
                'user_answer' => $answered ? $ua : '',
            ];
        }
    }

    return $out;
}

function buildLevelBlock(int $correctCompletude, int $correctMaitrise, int $answered, int $total): array
{
    return [
        'correct' => $correctCompletude,
        'correct_maitrise' => $correctMaitrise,
        'answered' => $answered,
        'total' => $total,
        'note_maitrise' => noteSur20($correctMaitrise, $answered),
        'note_completude' => noteSur20($correctCompletude, $total),
        'pct_completion' => pct($answered, $total),
        'pct_maitrise' => pct($correctMaitrise, $answered),
        'pct_completude' => pct($correctCompletude, $total),
    ];
}

function weightedRecruitmentNote(array $levelBlocks, array $weights): ?float
{
    $sum = 0.0;
    $wSum = 0.0;
    foreach ($weights as $level => $weight) {
        if ($weight <= 0 || !isset($levelBlocks[$level])) {
            continue;
        }
        $note = $levelBlocks[$level]['note_maitrise'] ?? null;
        if ($note === null) {
            continue;
        }
        $sum += $weight * $note;
        $wSum += $weight;
    }
    if ($wSum <= 0) {
        return null;
    }
    return round($sum / $wSum, 2);
}

if (php_sapi_name() !== 'cli' || realpath($argv[0] ?? '') !== realpath(__FILE__)) {
    return;
}

$paperIdStr = (string) argValue($argv, 'paper-id', '6a452db87ae27d2cc30d8592');
$mongoUri = (string) argValue($argv, 'mongo-uri', 'mongodb://10.68.0.7:27017');

if (!preg_match('/^[0-9a-f]{24}$/i', $paperIdStr)) {
    fwrite(STDERR, "ERROR: invalid --paper-id\n");
    exit(1);
}

$client = new Client($mongoUri);
$db = $client->academy;
$paperId = new ObjectId($paperIdStr);
$paper = $db->papers->findOne(['_id' => $paperId]);

if (!$paper) {
    fwrite(STDERR, "ERROR: paper not found\n");
    exit(1);
}

$paperLabel = toStr($paper['label'] ?? 'Paper');
$paperRatios = toArr($paper['ratios'] ?? []);
$recruitmentWeights = [
    'Junior' => (float) ($paperRatios['Junior'] ?? 50),
    'Senior' => (float) ($paperRatios['Senior'] ?? 50),
];
$minCompletionPct = 70.0;

$qOids = [];
foreach (toArr($paper['questions'] ?? []) as $x) {
    $qOids[] = $x instanceof ObjectId ? $x : new ObjectId((string) $x);
}
$qTotal = count($qOids);

$gfNames = [];
foreach ($db->groupes_fonctionnels->find(['isActive' => true]) as $gf) {
    $name = toStr($gf['name'] ?? '');
    if ($name !== '') {
        $gfNames[$name] = true;
    }
}

$qmap = [];
if ($qOids) {
    foreach ($db->questions->find(['_id' => ['$in' => $qOids]]) as $qd) {
        $qmap[(string) $qd['_id']] = $qd;
    }
}

$paperQuestions = [];
$specialtyTotals = [];
$levelTotals = ['Junior' => 0, 'Senior' => 0, 'Expert' => 0];
$specialtyLevelTotals = [];

foreach ($qOids as $i => $oid) {
    $k = (string) $oid;
    if (!isset($qmap[$k])) {
        continue;
    }
    $qd = $qmap[$k];
    $spec = toStr($qd['speciality'] ?? '');
    if ($spec === '') {
        $spec = '(Sans spécialité)';
    }
    $level = toStr($qd['level'] ?? '');
    if (!isset($levelTotals[$level])) {
        $level = 'Autre';
        $levelTotals[$level] = 0;
    }
    $levelTotals[$level]++;

    if (!isset($specialtyLevelTotals[$spec])) {
        $specialtyLevelTotals[$spec] = ['Junior' => 0, 'Senior' => 0, 'Expert' => 0, 'Autre' => 0];
    }
    if (!isset($specialtyLevelTotals[$spec][$level])) {
        $specialtyLevelTotals[$spec][$level] = 0;
    }
    $specialtyLevelTotals[$spec][$level]++;

    $paperQuestions[] = [
        'index' => $i,
        'num' => $i + 1,
        'id' => $k,
        'ref' => toStr($qd['ref'] ?? ''),
        'label' => toStr($qd['label'] ?? ''),
        'level' => $level,
        'speciality' => $spec,
        'groupe_fonctionnel' => $spec,
        'answer' => toStr($qd['answer'] ?? ''),
    ];
    $specialtyTotals[$spec] = ($specialtyTotals[$spec] ?? 0) + 1;
}
ksort($specialtyTotals);
$specialtyList = array_keys($specialtyTotals);

$expected = [];
foreach ($db->users->find([
    'active' => true,
    '$or' => [
        ['papers' => $paperId],
        ['role' => $paperLabel],
        ['roles' => $paperLabel],
    ],
]) as $u) {
    $profile = strtoupper(trim(toStr($u['profile'] ?? $u['typeR'] ?? '')));
    $country = strtoupper(trim(toStr($u['country'] ?? '')));
    if (!in_array($profile, ['CANDIDAT', 'CANDIDATE'], true)) {
        continue;
    }
    if (!in_array($country, ['CAMEROUN', 'CAMEROON'], true)) {
        continue;
    }
    if (!empty($u['loadtestTag'])) {
        continue;
    }
    $expected[(string) $u['_id']] = $u;
}

function buildCandidateData(
    $db,
    $u,
    ObjectId $paperId,
    array $paperQuestions,
    array $specialtyList,
    array $specialtyTotals,
    array $levelTotals,
    array $recruitmentWeights,
    float $minCompletionPct
): array {
    $userOid = $u['_id'];
    $name = trim(toStr($u['firstName'] ?? '') . ' ' . toStr($u['lastName'] ?? ''));
    $username = toStr($u['username'] ?? '');
    $subsidiary = toStr($u['subsidiary'] ?? '');

    $alloc = $db->allocations->findOne(['user' => $userOid, 'paper' => $paperId]);
    $allocStatus = toStr($alloc['status'] ?? '');

    $global = $db->results->findOne(
        [
            'user' => $userOid,
            'paper' => $paperId,
            'type' => 'Factuel',
            'level' => 'Mixed',
            'userAnswers' => ['$exists' => true],
        ],
        ['sort' => ['created' => -1]]
    );

    $exam = $db->exams->findOne(['user' => $userOid, 'paper' => $paperId, 'active' => true]);
    if (!$exam) {
        $exam = $db->exams->findOne(['user' => $userOid, 'paper' => $paperId], ['sort' => ['updated' => -1]]);
    }

    $userAnswers = [];
    $status = 'Non démarré';
    $finishedAt = '';
    $timeSpent = '';

    if ($global) {
        $userAnswers = array_values(toArr($global['userAnswers'] ?? []));
        $status = 'Terminé';
        $finishedAt = toStr($global['created'] ?? '');
        $timeSpent = toStr($global['time'] ?? '');
    } elseif ($exam) {
        $userAnswers = array_values(toArr($exam['answers'] ?? []));
        $status = $allocStatus === 'awaiting_orientation' ? 'Orientation en cours' : 'En cours';
        $finishedAt = toStr($exam['updated'] ?? $exam['created'] ?? '');
        $timeSpent = sprintf(
            '%02d:%02d:%02d',
            (int) ($exam['hour'] ?? 0),
            (int) ($exam['minute'] ?? 0),
            (int) ($exam['second'] ?? 0)
        );
    } elseif ($alloc) {
        $status = $allocStatus === 'done' ? 'Terminé' : 'En cours';
        $finishedAt = toStr($alloc['finishedAt'] ?? $alloc['updated'] ?? $alloc['created'] ?? '');
    }

    $normalized = normalizeAnswersByPaperIndex($paperQuestions, $global, $exam);

    $levelCounts = [];
    foreach (array_keys($levelTotals) as $lv) {
        $levelCounts[$lv] = ['correct' => 0, 'correct_maitrise' => 0, 'answered' => 0, 'total' => (int) $levelTotals[$lv]];
    }

    $specialtyScores = [];
    foreach ($specialtyList as $spec) {
        $specialtyScores[$spec] = ['correct' => 0, 'correct_maitrise' => 0, 'answered' => 0, 'total' => (int) $specialtyTotals[$spec]];
    }

    $globalCorrect = 0;
    $globalCorrectMaitrise = 0;
    $globalAnswered = 0;
    $questionRows = [];

    foreach ($paperQuestions as $pq) {
        $idx = $pq['index'];
        $norm = $normalized[$idx] ?? ['answered' => false, 'correct' => false, 'user_answer' => ''];
        $answered = $norm['answered'];
        $isCorrect = $norm['correct'];
        $ua = $norm['user_answer'];
        $spec = $pq['speciality'];
        $level = $pq['level'];

        if ($isCorrect) {
            $globalCorrect++;
        }
        if ($answered) {
            $globalAnswered++;
            if ($isCorrect) {
                $globalCorrectMaitrise++;
            }
            $specialtyScores[$spec]['answered']++;
            if ($isCorrect) {
                $specialtyScores[$spec]['correct_maitrise']++;
            }
            if (isset($levelCounts[$level])) {
                $levelCounts[$level]['answered']++;
                if ($isCorrect) {
                    $levelCounts[$level]['correct_maitrise']++;
                }
            }
        }
        if ($isCorrect) {
            $specialtyScores[$spec]['correct']++;
            if (isset($levelCounts[$level])) {
                $levelCounts[$level]['correct']++;
            }
        }

        $questionRows[] = [
            'num' => $pq['num'],
            'ref' => $pq['ref'],
            'niveau' => $level,
            'groupe_fonctionnel' => $pq['groupe_fonctionnel'],
            'label' => $pq['label'],
            'reponse_candidat' => $answered ? $ua : '',
            'bonne_reponse' => $pq['answer'],
            'resultat' => !$answered ? 'Non répondu' : ($isCorrect ? 'Correct' : 'Incorrect'),
        ];
    }

    $globalTotal = count($paperQuestions);
    $levelBlocks = [];
    foreach ($levelCounts as $lv => $lc) {
        $levelBlocks[$lv] = buildLevelBlock($lc['correct'], $lc['correct_maitrise'], $lc['answered'], $lc['total']);
    }

    $globalBlock = buildLevelBlock($globalCorrect, $globalCorrectMaitrise, $globalAnswered, $globalTotal);

    $specialtyNotes = [];
    foreach ($specialtyList as $spec) {
        $sc = $specialtyScores[$spec];
        $specialtyNotes[$spec] = [
            'correct' => $sc['correct'],
            'total' => $sc['total'],
            'answered' => $sc['answered'],
            'note_maitrise' => noteSur20($sc['correct_maitrise'], $sc['answered']),
            'note_completude' => noteSur20($sc['correct'], $sc['total']),
            'pct_completion' => pct($sc['answered'], $sc['total']),
        ];
    }

    $noteRecrutement = weightedRecruitmentNote($levelBlocks, $recruitmentWeights);
    $juniorOk = ($levelBlocks['Junior']['pct_completion'] ?? 0) >= $minCompletionPct;
    $seniorOk = ($levelBlocks['Senior']['pct_completion'] ?? 0) >= $minCompletionPct;
    $recrutementValide = $juniorOk && $seniorOk && $noteRecrutement !== null;

    $orientationDone = false;
    $rOri = $db->results->findOne(
        ['user' => $userOid, 'paper' => $paperId, 'type' => 'Orientation'],
        ['sort' => ['created' => -1]]
    );
    if ($rOri) {
        foreach (toArr($rOri['answers'] ?? []) as $row) {
            $row = toArr($row);
            $sel = toArr($row['selected'] ?? []);
            if (count(array_filter($sel, fn($v) => is_string($v) && trim($v) !== '')) > 0) {
                $orientationDone = true;
                break;
            }
        }
    }

    return [
        'name' => $name,
        'username' => $username,
        'subsidiary' => $subsidiary,
        'status' => $status,
        'finished_at' => $finishedAt,
        'time_spent' => $timeSpent,
        'global' => $globalBlock,
        'levels' => $levelBlocks,
        'note_recrutement' => $noteRecrutement,
        'recrutement_valide' => $recrutementValide,
        'specialty_notes' => $specialtyNotes,
        'questions' => $questionRows,
        'orientation_done' => $orientationDone,
    ];
}

$candidates = [];
foreach ($expected as $uid => $u) {
    $candidates[] = buildCandidateData(
        $db,
        $u,
        $paperId,
        $paperQuestions,
        $specialtyList,
        $specialtyTotals,
        $levelTotals,
        $recruitmentWeights,
        $minCompletionPct
    );
}
usort($candidates, fn($a, $b) => strcmp($a['name'], $b['name']));

$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()->setCreator('MEDACAP')->setTitle($paperLabel);

// --- Résumé ---
$summary = $spreadsheet->getActiveSheet();
$summary->setTitle('Résumé');
$summary->setCellValue('A1', 'Rapport — ' . $paperLabel);
$summary->mergeCells('A1:P1');
$summary->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$summary->setCellValue('A2', 'Généré le');
$summary->setCellValue('B2', date('d/m/Y H:i:s'));
$summary->setCellValue('A3', 'Questions');
$summary->setCellValue('B3', sprintf(
    'Total %d — Junior %d — Senior %d — Expert %d',
    $qTotal,
    $levelTotals['Junior'] ?? 0,
    $levelTotals['Senior'] ?? 0,
    $levelTotals['Expert'] ?? 0
));
$summary->setCellValue('A4', 'Prorata recrutement');
$summary->setCellValue('B4', sprintf(
    'Junior %s%% + Senior %s%% (maîtrise) — seuil complétion %s%%/niveau',
    $recruitmentWeights['Junior'],
    $recruitmentWeights['Senior'],
    $minCompletionPct
));

$row = 6;
$headers = [
    'Candidat', 'Identifiant', 'Filiale', 'Statut', 'Orientation',
    'Répondues', 'Bonnes',
    'Global maîtrise /20', 'Global complétude /20', 'Global % complétion',
    'Junior rép.', 'Junior maîtrise /20', 'Junior complétude /20', 'Junior % complétion',
    'Senior rép.', 'Senior maîtrise /20', 'Senior complétude /20', 'Senior % complétion',
    'Note recrutement /20', 'Recrutement valide',
];
foreach ($headers as $i => $h) {
    $summary->setCellValueByColumnAndRow($i + 1, $row, $h);
}
$lastCol = count($headers);
styleHeaderRow($summary, 'A' . $row . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol) . $row);

$row++;
foreach ($candidates as $c) {
    $g = $c['global'];
    $j = $c['levels']['Junior'] ?? buildLevelBlock(0, 0, 0, 0);
    $s = $c['levels']['Senior'] ?? buildLevelBlock(0, 0, 0, 0);
    $values = [
        $c['name'], $c['username'], $c['subsidiary'], $c['status'], $c['orientation_done'] ? 'Oui' : 'Non',
        $g['answered'] . '/' . $g['total'], $g['correct_maitrise'] ?? $g['correct'],
        $g['note_maitrise'] ?? '—', $g['note_completude'] ?? '—', ($g['pct_completion'] ?? '—') . '%',
        $j['answered'] . '/' . $j['total'], $j['note_maitrise'] ?? '—', $j['note_completude'] ?? '—', ($j['pct_completion'] ?? '—') . '%',
        $s['answered'] . '/' . $s['total'], $s['note_maitrise'] ?? '—', $s['note_completude'] ?? '—', ($s['pct_completion'] ?? '—') . '%',
        $c['note_recrutement'] ?? '—', $c['recrutement_valide'] ? 'Oui' : 'Non',
    ];
    foreach ($values as $i => $v) {
        $summary->setCellValueByColumnAndRow($i + 1, $row, $v);
    }
    $row++;
}
autoSizeColumns($summary, 1, $lastCol);
$summary->freezePane('A7');

// --- Répartition paper ---
$dist = $spreadsheet->createSheet();
$dist->setTitle('Répartition paper');
$dist->fromArray(['Niveau', 'Nb questions', 'Part %'], null, 'A1');
styleHeaderRow($dist, 'A1:C1');
$r = 2;
foreach ($levelTotals as $lv => $cnt) {
    if ($cnt <= 0) {
        continue;
    }
    $dist->fromArray([$lv, $cnt, pct($cnt, $qTotal) . '%'], null, "A{$r}");
    $r++;
}
autoSizeColumns($dist, 1, 3);

// --- Groupes fonctionnels ---
$gfSheet = $spreadsheet->createSheet();
$gfSheet->setTitle('Groupes fonctionnels');
$gfSheet->fromArray(['Groupe fonctionnel', 'Total', 'Junior', 'Senior', 'Expert'], null, 'A1');
styleHeaderRow($gfSheet, 'A1:E1');
$r = 2;
foreach ($specialtyList as $spec) {
    $sl = $specialtyLevelTotals[$spec] ?? [];
    $gfSheet->fromArray([
        $spec,
        $specialtyTotals[$spec],
        $sl['Junior'] ?? 0,
        $sl['Senior'] ?? 0,
        $sl['Expert'] ?? 0,
    ], null, "A{$r}");
    $r++;
}
autoSizeColumns($gfSheet, 1, 5);

// --- Feuilles candidats ---
$usedTitles = ['Résumé' => true, 'Répartition paper' => true, 'Groupes fonctionnels' => true];
foreach ($candidates as $c) {
    $baseTitle = sanitizeSheetTitle($c['name'] !== '' ? $c['name'] : $c['username']);
    $title = $baseTitle;
    $suffix = 1;
    while (isset($usedTitles[$title])) {
        $suffix++;
        $title = sanitizeSheetTitle($baseTitle . ' ' . $suffix);
    }
    $usedTitles[$title] = true;

    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle($title);
    $sheet->setCellValue('A1', $c['name']);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
    $sheet->setCellValue('A2', 'Identifiant : ' . $c['username']);
    $sheet->setCellValue('A3', 'Filiale : ' . $c['subsidiary'] . ' — Statut : ' . $c['status']);

    $g = $c['global'];
    $sheet->setCellValue('A5', 'GLOBAL — maîtrise : ' . ($g['note_maitrise'] ?? '—') . '/20 | complétude : ' . ($g['note_completude'] ?? '—') . '/20 | réponses : ' . $g['answered'] . '/' . $g['total']);
    $sheet->setCellValue('A6', 'Note recrutement (50% J maîtrise + 50% S maîtrise) : ' . ($c['note_recrutement'] ?? '—') . '/20 — Valide : ' . ($c['recrutement_valide'] ? 'Oui' : 'Non'));

    $start = 8;
    $sheet->setCellValue("A{$start}", 'Notes par niveau');
    $sheet->getStyle("A{$start}")->getFont()->setBold(true);
    $start++;
    $sheet->fromArray(
        ['Niveau', 'Bonnes', 'Répondues', 'Total', 'Maîtrise /20', 'Complétude /20', '% complétion', '% bonnes/répondues'],
        null,
        "A{$start}"
    );
    styleHeaderRow($sheet, "A{$start}:H{$start}");
    $r = $start + 1;
    foreach (['Junior', 'Senior', 'Expert', 'Autre'] as $lv) {
        if (!isset($c['levels'][$lv]) || ($c['levels'][$lv]['total'] ?? 0) <= 0) {
            continue;
        }
        $lb = $c['levels'][$lv];
        $sheet->fromArray([
            $lv, $lb['correct'], $lb['answered'], $lb['total'],
            $lb['note_maitrise'] ?? '—', $lb['note_completude'] ?? '—',
            ($lb['pct_completion'] ?? '—') . '%', ($lb['pct_maitrise'] ?? '—') . '%',
        ], null, "A{$r}");
        $r++;
    }
    $lb = $c['global'];
    $sheet->fromArray([
        'GLOBAL', $lb['correct'], $lb['answered'], $lb['total'],
        $lb['note_maitrise'] ?? '—', $lb['note_completude'] ?? '—',
        ($lb['pct_completion'] ?? '—') . '%', ($lb['pct_maitrise'] ?? '—') . '%',
    ], null, "A{$r}");
    $r += 2;

    $sheet->setCellValue("A{$r}", 'Notes par groupe fonctionnel');
    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
    $r++;
    $sheet->fromArray(['Groupe', 'Bonnes', 'Répondues', 'Total', 'Maîtrise /20', 'Complétude /20', '% complétion'], null, "A{$r}");
    styleHeaderRow($sheet, "A{$r}:G{$r}");
    $r++;
    foreach ($specialtyList as $spec) {
        $sn = $c['specialty_notes'][$spec];
        $sheet->fromArray([
            $spec, $sn['correct'], $sn['answered'], $sn['total'],
            $sn['note_maitrise'] ?? '—', $sn['note_completude'] ?? '—',
            ($sn['pct_completion'] ?? '—') . '%',
        ], null, "A{$r}");
        $r++;
    }
    $r += 2;

    $sheet->setCellValue("A{$r}", 'Détail par question');
    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
    $r++;
    $sheet->fromArray(['N°', 'Réf.', 'Niveau', 'Groupe fonctionnel', 'Énoncé', 'Réponse candidat', 'Bonne réponse', 'Résultat'], null, "A{$r}");
    styleHeaderRow($sheet, "A{$r}:H{$r}");
    $r++;
    foreach ($c['questions'] as $qr) {
        $sheet->fromArray([
            $qr['num'], $qr['ref'], $qr['niveau'], $qr['groupe_fonctionnel'], $qr['label'],
            $qr['reponse_candidat'], $qr['bonne_reponse'], $qr['resultat'],
        ], null, "A{$r}");
        if ($qr['resultat'] === 'Correct') {
            $sheet->getStyle("H{$r}")->getFont()->getColor()->setRGB('059669');
        } elseif ($qr['resultat'] === 'Incorrect') {
            $sheet->getStyle("H{$r}")->getFont()->getColor()->setRGB('DC2626');
        }
        $r++;
    }
    autoSizeColumns($sheet, 1, 8);
}

$outDir = __DIR__ . '/out';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}
$safeLabel = preg_replace('/[^A-Za-z0-9_-]+/', '_', $paperLabel) ?: 'paper';
$outPath = $outDir . '/' . $safeLabel . '_v2_' . date('Ymd_His') . '.xlsx';

$writer = new Xlsx($spreadsheet);
$writer->save($outPath);

echo json_encode([
    'ok' => true,
    'paper' => $paperLabel,
    'level_totals' => $levelTotals,
    'recruitment_weights' => $recruitmentWeights,
    'min_completion_pct' => $minCompletionPct,
    'candidates' => count($candidates),
    'file' => $outPath,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
