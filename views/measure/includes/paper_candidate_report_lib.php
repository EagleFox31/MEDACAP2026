<?php
require_once __DIR__ . '/../../../core/medacap_config.php';
/**
 * Rapports candidats — épreuves figées (papers).
 * Partagé entre CLI (export Excel), console et page rapportRecrutement.php.
 */

require_once __DIR__ . '/paper_macro_specialities.php';

const PAPER_REPORT_THRESHOLD_PCT = 60.0;
const PAPER_REPORT_MIN_COMPLETION_PCT = 70.0;

function paperReportToArr($v): array
{
    if ($v instanceof MongoDB\Model\BSONArray || $v instanceof MongoDB\Model\BSONDocument) {
        return $v->getArrayCopy();
    }
    return is_array($v) ? $v : [];
}

function paperReportToStr($v): string
{
    if (is_string($v)) {
        return $v;
    }
    if (is_scalar($v)) {
        return (string) $v;
    }
    return '';
}

function paperReportNoteSur20(int $correct, int $total, int $decimals = 2): ?float
{
    if ($total <= 0) {
        return null;
    }
    return round(($correct / $total) * 20, $decimals);
}

function paperReportPct(int $part, int $total, int $decimals = 1): ?float
{
    if ($total <= 0) {
        return null;
    }
    return round(100 * $part / $total, $decimals);
}

function paperReportParseTimeToSeconds(?string $timeStr): ?int
{
    if ($timeStr === null || trim($timeStr) === '') {
        return null;
    }
    $parts = array_map('intval', explode(':', trim($timeStr)));
    if (count($parts) === 3) {
        return max(0, $parts[0] * 3600 + $parts[1] * 60 + $parts[2]);
    }
    if (count($parts) === 2) {
        return max(0, $parts[0] * 60 + $parts[1]);
    }
    return null;
}

function paperReportFormatSeconds(int $seconds): string
{
    $seconds = max(0, $seconds);
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

/** Durée totale autorisée du paper en secondes. */
function paperReportPaperDurationSeconds(array $paper): int
{
    $dur = paperReportToArr($paper['duration'] ?? []);
    $h = (int) ($dur['hour'] ?? $paper['hour'] ?? 0);
    $m = (int) ($dur['minute'] ?? $paper['minute'] ?? 0);
    $s = (int) ($dur['second'] ?? $paper['second'] ?? 0);
    return max(0, $h * 3600 + $m * 60 + $s);
}

/**
 * @return array{elapsed_seconds:?int, elapsed_label:string, remaining_seconds:?int, avg_speed_per_min:?float}
 */
function paperReportComputeElapsedAndSpeed(array $paper, $global, $exam, int $answered): array
{
    $totalSec = paperReportPaperDurationSeconds($paper);
    $remaining = null;

    if ($global) {
        $remaining = paperReportParseTimeToSeconds(paperReportToStr($global['time'] ?? ''));
    } elseif ($exam) {
        $remaining = max(0, (int) ($exam['hour'] ?? 0) * 3600
            + (int) ($exam['minute'] ?? 0) * 60
            + (int) ($exam['second'] ?? 0));
    }

    $elapsed = null;
    if ($totalSec > 0 && $remaining !== null) {
        $elapsed = max(0, min($totalSec, $totalSec - $remaining));
    }

    $avgSpeed = null;
    if ($elapsed !== null && $elapsed > 0 && $answered > 0) {
        $avgSpeed = round($answered / ($elapsed / 60), 1);
    }

    return [
        'elapsed_seconds' => $elapsed,
        'elapsed_label' => $elapsed !== null ? paperReportFormatSeconds($elapsed) : '',
        'remaining_seconds' => $remaining,
        'remaining_label' => $remaining !== null ? paperReportFormatSeconds($remaining) : '',
        'avg_speed_per_min' => $avgSpeed,
    ];
}

function paperReportNormalizeAnswersByPaperIndex(array $paperQuestions, $global, $exam): array
{
    $n = count($paperQuestions);
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        $out[$i] = ['answered' => false, 'correct' => false, 'user_answer' => ''];
    }

    if ($global && isset($global['answers'])) {
        $resultAnswers = array_values(paperReportToArr($global['answers']));
        $resultQuestions = array_values(paperReportToArr($global['questions'] ?? []));
        $userAnswers = array_values(paperReportToArr($global['userAnswers'] ?? []));
        $originalOrder = array_values(paperReportToArr($global['originalOrder'] ?? []));

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
            $status = $ri !== null ? paperReportToStr($resultAnswers[$ri] ?? '') : '';

            $userAnswer = '';
            if (isset($ooIndex[$qid]) && isset($userAnswers[$ooIndex[$qid]])) {
                $ua = paperReportToStr($userAnswers[$ooIndex[$qid]]);
                if ($ua !== '' && $ua !== 'null') {
                    $userAnswer = $ua;
                }
            }

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
        $examAnswers = array_values(paperReportToArr($exam['answers'] ?? []));
        $originalOrder = array_values(paperReportToArr($exam['originalOrder'] ?? []));
        if ($originalOrder === []) {
            $originalOrder = array_values(paperReportToArr($exam['questions'] ?? []));
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
            $ua = paperReportToStr($examAnswers[$ei] ?? '');
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

function paperReportBuildLevelBlock(int $correctCompletude, int $correctMaitrise, int $answered, int $total): array
{
    return [
        'correct' => $correctCompletude,
        'correct_maitrise' => $correctMaitrise,
        'answered' => $answered,
        'total' => $total,
        'note_maitrise' => paperReportNoteSur20($correctMaitrise, $answered),
        'note_completude' => paperReportNoteSur20($correctCompletude, $total),
        'pct_completion' => paperReportPct($answered, $total),
        'pct_maitrise' => paperReportPct($correctMaitrise, $answered),
        'pct_completude' => paperReportPct($correctCompletude, $total),
    ];
}

function paperReportWeightedRecruitmentNote(array $levelBlocks, array $weights, string $noteKey = 'note_maitrise'): ?float
{
    $sum = 0.0;
    $wSum = 0.0;
    foreach ($weights as $level => $weight) {
        if ($weight <= 0 || !isset($levelBlocks[$level])) {
            continue;
        }
        $block = $levelBlocks[$level];
        if (($block['total'] ?? 0) <= 0) {
            continue;
        }
        $note = isset($block[$noteKey]) ? $block[$noteKey] : null;
        if ($note === null) {
            continue;
        }
        $sum += $weight * (float) $note;
        $wSum += $weight;
    }
    if ($wSum <= 0) {
        return null;
    }
    return round($sum / $wSum, 2);
}

/**
 * Poids dynamique (0..1) accordé à la maîtrise dans la note recrutement.
 * Progressif : monte avec le volume répondu (réf. ~50 q.) et le taux de réussite (>50 %).
 */
function paperReportRecruitmentMasteryWeight(int $answered, int $total, int $correctMaitrise): float
{
    if ($answered <= 0 || $total <= 0) {
        return 0.0;
    }

    $accuracy = $correctMaitrise / $answered;
    $coverage = min(1.0, $answered / $total);

    $volume = min(1.0, $answered / 50.0);
    $quality = min(1.0, max(0.0, ($accuracy - 0.5) / 0.5));
    $coverageSoft = sqrt($coverage);

    $masteryTrust = $volume * (0.35 + (0.65 * $quality));
    $weight = $masteryTrust * (0.4 + (0.6 * $coverageSoft));

    return min(1.0, max(0.05, $weight));
}

/**
 * Note synthèse interne (legacy) = blend maîtrise / complétude selon volume et qualité des réponses.
 *
 * @return array{note:?float,poids_maitrise:?float,note_maitrise:?float,note_completude:?float}
 */
function paperReportComputeRecruitmentNote(
    ?float $noteMaitrise,
    ?float $noteCompletude,
    int $answered,
    int $total,
    int $correctMaitrise
): array {
    if ($noteMaitrise === null) {
        return array(
            'note' => null,
            'poids_maitrise' => null,
            'note_maitrise' => null,
            'note_completude' => $noteCompletude,
        );
    }
    if ($noteCompletude === null) {
        $noteCompletude = $noteMaitrise;
    }

    $w = paperReportRecruitmentMasteryWeight($answered, $total, $correctMaitrise);
    $note = round(($noteMaitrise * $w) + ($noteCompletude * (1.0 - $w)), 2);

    return array(
        'note' => $note,
        'poids_maitrise' => round($w, 3),
        'note_maitrise' => $noteMaitrise,
        'note_completude' => $noteCompletude,
    );
}

function paperReportIsDemoUser($u): bool
{
    $u = paperReportToArr($u);
    if (!empty($u['loadtestTag'])) {
        return true;
    }
    $uname = strtolower(paperReportToStr($u['username'] ?? ''));
    if (strpos($uname, 'demo') !== false || preg_match('/\btest\b/i', $uname)) {
        return true;
    }
    $fname = strtolower(paperReportToStr($u['firstName'] ?? ''));
    $lname = strtolower(paperReportToStr($u['lastName'] ?? ''));
    if (strpos($fname, 'demo') !== false || preg_match('/\btest\b/i', $fname) || strpos($lname, 'demo') !== false || preg_match('/\btest\b/i', $lname)) {
        return true;
    }
    return false;
}

/** @return array<string, array> userId => user doc */
function paperReportGetEnrolledCandidates($db, MongoDB\BSON\ObjectId $paperId, string $paperLabel): array
{
    $expected = [];
    foreach ($db->users->find([
        'active' => true,
        '$or' => [
            ['papers' => $paperId],
            ['role' => $paperLabel],
            ['roles' => $paperLabel],
        ],
    ]) as $u) {
        $profile = strtoupper(trim(paperReportToStr($u['profile'] ?? $u['typeR'] ?? '')));
        $country = strtoupper(trim(paperReportToStr($u['country'] ?? '')));
        if (!in_array($profile, ['CANDIDAT', 'CANDIDATE'], true)) {
            continue;
        }
        if (!in_array($country, ['CAMEROUN', 'CAMEROON'], true)) {
            continue;
        }
        if (paperReportIsDemoUser($u)) {
            continue;
        }
        $expected[(string) $u['_id']] = $u;
    }
    return $expected;
}

function paperReportCandidateHasComposed(array $candidateReport): bool
{
    $answered = (int) ($candidateReport['global']['answered'] ?? 0);
    if ($answered > 0) {
        return true;
    }
    return ($candidateReport['status'] ?? '') === 'Terminé';
}

/**
 * Charge le contexte d'une épreuve (questions, niveaux, spécialités).
 * @return array|null
 */
function paperReportLoadPaperContext($db, MongoDB\BSON\ObjectId $paperId): ?array
{
    $paper = $db->papers->findOne(['_id' => $paperId]);
    if (!$paper) {
        return null;
    }

    $paperLabel = paperReportToStr($paper['label'] ?? 'Paper');
    $paperRatios = paperReportToArr($paper['ratios'] ?? []);
    $recruitmentWeights = [
        'Junior' => (float) ($paperRatios['Junior'] ?? 50),
        'Senior' => (float) ($paperRatios['Senior'] ?? 50),
    ];

    $qOids = [];
    foreach (paperReportToArr($paper['questions'] ?? []) as $x) {
        $qOids[] = $x instanceof MongoDB\BSON\ObjectId ? $x : new MongoDB\BSON\ObjectId((string) $x);
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
        $spec = paperReportToStr($qd['speciality'] ?? '');
        if ($spec === '') {
            $spec = '(Sans spécialité)';
        }
        $level = paperReportToStr($qd['level'] ?? '');
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
            'ref' => paperReportToStr($qd['ref'] ?? ''),
            'label' => paperReportToStr($qd['label'] ?? ''),
            'level' => $level,
            'speciality' => $spec,
            'groupe_fonctionnel' => $spec,
            'answer' => paperReportToStr($qd['answer'] ?? ''),
        ];
        $specialtyTotals[$spec] = ($specialtyTotals[$spec] ?? 0) + 1;
    }
    ksort($specialtyTotals);
    $specialtyList = array_keys($specialtyTotals);
    $macroMap = paperBuildMacroMap($specialtyList);

    return [
        'paper' => $paper,
        'paper_id' => $paperId,
        'paper_label' => $paperLabel,
        'recruitment_weights' => $recruitmentWeights,
        'min_completion_pct' => PAPER_REPORT_MIN_COMPLETION_PCT,
        'threshold_pct' => PAPER_REPORT_THRESHOLD_PCT,
        'paper_questions' => $paperQuestions,
        'specialty_totals' => $specialtyTotals,
        'specialty_list' => $specialtyList,
        'specialty_level_totals' => $specialtyLevelTotals,
        'level_totals' => $levelTotals,
        'macro_map' => $macroMap,
        'q_total' => count($paperQuestions),
    ];
}

function paperReportBuildCandidateData(
    $db,
    $u,
    MongoDB\BSON\ObjectId $paperId,
    array $paper,
    array $paperQuestions,
    array $specialtyList,
    array $specialtyTotals,
    array $levelTotals,
    array $recruitmentWeights,
    float $minCompletionPct,
    array $macroMap
): array {
    $userOid = $u['_id'];
    $name = trim(paperReportToStr($u['firstName'] ?? '') . ' ' . paperReportToStr($u['lastName'] ?? ''));
    $username = paperReportToStr($u['username'] ?? '');
    $subsidiary = paperReportToStr($u['subsidiary'] ?? '');

    $alloc = $db->allocations->findOne(['user' => $userOid, 'paper' => $paperId]);
    $allocStatus = paperReportToStr($alloc['status'] ?? '');

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

    $status = 'Non démarré';
    $finishedAt = '';
    $timeSpent = '';

    if ($global) {
        $status = 'Terminé';
        $finishedAt = paperReportToStr($global['created'] ?? '');
        $timeSpent = paperReportToStr($global['time'] ?? '');
    } elseif ($allocStatus === 'done' || ($exam && empty($exam['active']))) {
        $status = 'Terminé';
        $finishedAt = paperReportToStr($alloc['finishedAt'] ?? $exam['updated'] ?? $alloc['updated'] ?? $exam['created'] ?? '');
        if ($exam) {
            $timeSpent = sprintf(
                '%02d:%02d:%02d',
                (int) ($exam['hour'] ?? 0),
                (int) ($exam['minute'] ?? 0),
                (int) ($exam['second'] ?? 0)
            );
        }
    } elseif ($exam) {
        $status = $allocStatus === 'awaiting_orientation' ? 'Orientation en cours' : 'En cours';
        $finishedAt = paperReportToStr($exam['updated'] ?? $exam['created'] ?? '');
        $timeSpent = sprintf(
            '%02d:%02d:%02d',
            (int) ($exam['hour'] ?? 0),
            (int) ($exam['minute'] ?? 0),
            (int) ($exam['second'] ?? 0)
        );
    } elseif ($alloc) {
        $status = $allocStatus === 'done' ? 'Terminé' : 'En cours';
        $finishedAt = paperReportToStr($alloc['finishedAt'] ?? $alloc['updated'] ?? $alloc['created'] ?? '');
    }

    $normalized = paperReportNormalizeAnswersByPaperIndex($paperQuestions, $global, $exam);

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
            'speciality' => $spec,
            'label' => $pq['label'],
            'reponse_candidat' => $answered ? $ua : '',
            'bonne_reponse' => $pq['answer'],
            'resultat' => !$answered ? 'Non répondu' : ($isCorrect ? 'Correct' : 'Incorrect'),
        ];
    }

    $globalTotal = count($paperQuestions);
    $levelBlocks = [];
    foreach ($levelCounts as $lv => $lc) {
        $levelBlocks[$lv] = paperReportBuildLevelBlock($lc['correct'], $lc['correct_maitrise'], $lc['answered'], $lc['total']);
    }

    $globalBlock = paperReportBuildLevelBlock($globalCorrect, $globalCorrectMaitrise, $globalAnswered, $globalTotal);

    $specialtyNotes = [];
    foreach ($specialtyList as $spec) {
        $sc = $specialtyScores[$spec];
        $specialtyNotes[$spec] = [
            'correct' => $sc['correct'],
            'correct_maitrise' => $sc['correct_maitrise'],
            'total' => $sc['total'],
            'answered' => $sc['answered'],
            'note_maitrise' => paperReportNoteSur20($sc['correct_maitrise'], $sc['answered']),
            'note_completude' => paperReportNoteSur20($sc['correct'], $sc['total']),
            'pct_completion' => paperReportPct($sc['answered'], $sc['total']),
        ];
    }

    $macroNotes = paperRollupByMacroSpeciality($specialtyNotes, $macroMap);

    $noteRecrutementMaitrise = paperReportWeightedRecruitmentNote($levelBlocks, $recruitmentWeights, 'note_maitrise');
    $noteRecrutementCompletude = paperReportWeightedRecruitmentNote($levelBlocks, $recruitmentWeights, 'note_completude');
    $recrutementBlend = paperReportComputeRecruitmentNote(
        $noteRecrutementMaitrise,
        $noteRecrutementCompletude,
        $globalAnswered,
        $globalTotal,
        $globalCorrectMaitrise
    );
    $noteRecrutement = $recrutementBlend['note'];
    $noteRecrutementPoidsMaitrise = $recrutementBlend['poids_maitrise'];
    $juniorOk = ($levelBlocks['Junior']['pct_completion'] ?? 0) >= $minCompletionPct;
    $seniorOk = ($levelBlocks['Senior']['pct_completion'] ?? 0) >= $minCompletionPct;
    $recrutementValide = $juniorOk && $seniorOk && $noteRecrutement !== null;

    $orientationDone = false;
    $rOri = $db->results->findOne(
        ['user' => $userOid, 'paper' => $paperId, 'type' => 'Orientation'],
        ['sort' => ['created' => -1]]
    );
    if ($rOri) {
        foreach (paperReportToArr($rOri['answers'] ?? []) as $row) {
            $row = paperReportToArr($row);
            $sel = paperReportToArr($row['selected'] ?? []);
            if (count(array_filter($sel, function ($v) {
                return is_string($v) && trim($v) !== '';
            })) > 0) {
                $orientationDone = true;
                break;
            }
        }
    }

    $timing = paperReportComputeElapsedAndSpeed($paper, $global, $exam, $globalAnswered);

    return [
        'user_id' => (string) $userOid,
        'name' => $name,
        'username' => $username,
        'subsidiary' => $subsidiary,
        'status' => $status,
        'finished_at' => $finishedAt,
        'time_spent' => $timeSpent,
        'time_elapsed' => $timing['elapsed_label'],
        'time_remaining' => $timing['remaining_label'],
        'elapsed_seconds' => $timing['elapsed_seconds'],
        'avg_speed_per_min' => $timing['avg_speed_per_min'],
        'global' => $globalBlock,
        'levels' => $levelBlocks,
        'note_recrutement' => $noteRecrutement,
        'note_recrutement_maitrise' => $noteRecrutementMaitrise,
        'note_recrutement_completude' => $noteRecrutementCompletude,
        'note_recrutement_poids_maitrise' => $noteRecrutementPoidsMaitrise,
        'recrutement_valide' => $recrutementValide,
        'specialty_notes' => $specialtyNotes,
        'macro_notes' => $macroNotes,
        'questions' => $questionRows,
        'orientation_done' => $orientationDone,
        'has_composed' => false,
    ];
}

/** @return list<array> */
function paperReportBuildAllCandidates($db, array $ctx): array
{
    $paperId = $ctx['paper_id'];
    $enrolled = paperReportGetEnrolledCandidates($db, $paperId, $ctx['paper_label']);
    $candidates = [];

    foreach ($enrolled as $u) {
        $c = paperReportBuildCandidateData(
            $db,
            $u,
            $paperId,
            paperReportToArr($ctx['paper']),
            $ctx['paper_questions'],
            $ctx['specialty_list'],
            $ctx['specialty_totals'],
            $ctx['level_totals'],
            $ctx['recruitment_weights'],
            (float) $ctx['min_completion_pct'],
            $ctx['macro_map']
        );
        $c['has_composed'] = paperReportCandidateHasComposed($c);
        $candidates[] = $c;
    }

    usort($candidates, function ($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    return $candidates;
}

/**
 * @return array{enrolled:int, composed:int, completion_pct:float, threshold_pct:float, threshold_reached:bool}
 */
function paperReportGetCompletionStats(array $candidates, float $thresholdPct = PAPER_REPORT_THRESHOLD_PCT): array
{
    $enrolled = count($candidates);
    $composed = 0;
    foreach ($candidates as $c) {
        if (!empty($c['has_composed'])) {
            $composed++;
        }
    }
    $pct = $enrolled > 0 ? round(100 * $composed / $enrolled, 1) : 0.0;

    return [
        'enrolled' => $enrolled,
        'composed' => $composed,
        'completion_pct' => $pct,
        'threshold_pct' => $thresholdPct,
        'threshold_reached' => $enrolled > 0 && ($composed / $enrolled) >= ($thresholdPct / 100),
    ];
}

function paperReportGetCampaignStatus($db, MongoDB\BSON\ObjectId $paperId): ?array
{
    $doc = $db->paper_campaign_status->findOne(['paper' => $paperId]);
    return $doc ? paperReportToArr($doc) : null;
}

function paperReportUpsertCampaignStatus($db, MongoDB\BSON\ObjectId $paperId, array $stats, array $extra = []): void
{
    $set = array_merge([
        'paper' => $paperId,
        'enrolled' => (int) $stats['enrolled'],
        'composed' => (int) $stats['composed'],
        'completionPct' => (float) $stats['completion_pct'],
        'thresholdPct' => (float) $stats['threshold_pct'],
        'updated' => date('Y-m-d H:i:s'),
    ], $extra);

    $db->paper_campaign_status->updateOne(
        ['paper' => $paperId],
        ['$set' => $set, '$setOnInsert' => ['created' => date('Y-m-d H:i:s')]],
        ['upsert' => true]
    );
}

/** Alias CLI legacy */
function buildCandidateData(
    $db,
    $u,
    MongoDB\BSON\ObjectId $paperId,
    array $paperQuestions,
    array $specialtyList,
    array $specialtyTotals,
    array $levelTotals,
    array $recruitmentWeights,
    float $minCompletionPct
): array {
    $paper = $db->papers->findOne(['_id' => $paperId]);
    $macroMap = paperBuildMacroMap($specialtyList);
    $c = paperReportBuildCandidateData(
        $db,
        $u,
        $paperId,
        paperReportToArr($paper),
        $paperQuestions,
        $specialtyList,
        $specialtyTotals,
        $levelTotals,
        $recruitmentWeights,
        $minCompletionPct,
        $macroMap
    );
    $c['has_composed'] = paperReportCandidateHasComposed($c);
    return $c;
}

function toArr($v): array
{
    return paperReportToArr($v);
}

function toStr($v): string
{
    return paperReportToStr($v);
}

function noteSur20(int $correct, int $total, int $decimals = 2): ?float
{
    return paperReportNoteSur20($correct, $total, $decimals);
}

function pct(int $part, int $total, int $decimals = 1): ?float
{
    return paperReportPct($part, $total, $decimals);
}

function normalizeAnswersByPaperIndex(array $paperQuestions, $global, $exam): array
{
    return paperReportNormalizeAnswersByPaperIndex($paperQuestions, $global, $exam);
}

function buildLevelBlock(int $correctCompletude, int $correctMaitrise, int $answered, int $total): array
{
    return paperReportBuildLevelBlock($correctCompletude, $correctMaitrise, $answered, $total);
}

function weightedRecruitmentNote(array $levelBlocks, array $weights): ?float
{
    return paperReportWeightedRecruitmentNote($levelBlocks, $weights);
}

/**
 * Recalcule les stats de campagne et envoie l'alerte mail 60% une seule fois par épreuve.
 */
function paperReportMaybeSendThresholdAlert($db, MongoDB\BSON\ObjectId $paperId, ?MongoDB\Client $mongoClient = null): bool
{
    static $checked = [];
    $key = (string) $paperId;
    if (isset($checked[$key])) {
        return false;
    }
    $checked[$key] = true;

    $campaign = paperReportGetCampaignStatus($db, $paperId);
    $prevReached = false;
    if ($campaign) {
        $prevEnrolled = max(0, (int) ($campaign['enrolled'] ?? 0));
        $prevComposed = max(0, (int) ($campaign['composed'] ?? 0));
        $prevThreshold = (float) ($campaign['thresholdPct'] ?? PAPER_REPORT_THRESHOLD_PCT);
        $prevReached = $prevEnrolled > 0 && ($prevComposed / $prevEnrolled) >= ($prevThreshold / 100);
    }

    if (!empty($campaign['autoAlertSentAt'])) {
        return false;
    }

    $ctx = paperReportLoadPaperContext($db, $paperId);
    if (!$ctx) {
        return false;
    }

    $candidates = paperReportBuildAllCandidates($db, $ctx);
    $stats = paperReportGetCompletionStats($candidates, (float) $ctx['threshold_pct']);
    paperReportUpsertCampaignStatus($db, $paperId, $stats);

    if (empty($stats['threshold_reached'])) {
        return false;
    }

    // Alerte uniquement au franchissement du seuil (pas rétroactif).
    if ($prevReached) {
        return false;
    }

    if ($mongoClient === null) {
        $uri = medacapMongoUri();
        $mongoClient = new MongoDB\Client($uri);
    }

    require_once __DIR__ . '/../../sendMail.php';

    $sent = sendPaperCompletionThresholdMail(
        $mongoClient,
        $ctx['paper_label'],
        (string) $paperId,
        $stats
    );

    if ($sent) {
        paperReportUpsertCampaignStatus($db, $paperId, $stats, [
            'autoAlertSentAt' => date('Y-m-d H:i:s'),
        ]);
    }

    return $sent;
}

/**
 * @return array{ok:bool, error?:string, sent_at?:string, stats?:array}
 */
function paperReportSendManualRhReport($db, MongoDB\BSON\ObjectId $paperId, ?MongoDB\Client $mongoClient = null, bool $force = false): array
{
    $campaign = paperReportGetCampaignStatus($db, $paperId);
    if (!$force && !empty($campaign['manualReportSentAt'])) {
        return [
            'ok' => false,
            'error' => 'already_sent',
            'sent_at' => paperReportToStr($campaign['manualReportSentAt']),
        ];
    }

    $ctx = paperReportLoadPaperContext($db, $paperId);
    if (!$ctx) {
        return ['ok' => false, 'error' => 'paper_not_found'];
    }

    $candidates = paperReportBuildAllCandidates($db, $ctx);
    $stats = paperReportGetCompletionStats($candidates, (float) $ctx['threshold_pct']);
    paperReportUpsertCampaignStatus($db, $paperId, $stats);

    require_once __DIR__ . '/paper_report_excel_export.php';
    require_once __DIR__ . '/../../sendMail.php';

    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'medacap_paper_reports';
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0777, true);
    }

    try {
        $export = paperReportExportExcel($db, $paperId, $tmpDir);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'export_failed', 'message' => $e->getMessage()];
    }

    if ($mongoClient === null) {
        $uri = medacapMongoUri();
        $mongoClient = new MongoDB\Client($uri);
    }

    $sent = sendPaperManualReportToRh(
        $mongoClient,
        $ctx['paper_label'],
        (string) $paperId,
        $stats,
        $export['path']
    );

    if (is_file($export['path'])) {
        @unlink($export['path']);
    }

    if (!$sent) {
        return ['ok' => false, 'error' => 'mail_failed', 'stats' => $stats];
    }

    paperReportUpsertCampaignStatus($db, $paperId, $stats, [
        'manualReportSentAt' => date('Y-m-d H:i:s'),
    ]);

    return ['ok' => true, 'stats' => $stats];
}

/** Papers recrutement actifs (label contient « Recrutement »). */
function paperReportListRecruitmentPapers($db): array
{
    $papers = [];
    foreach ($db->papers->find(
        ['active' => true, 'label' => new MongoDB\BSON\Regex('Recrutement', 'i')],
        ['sort' => ['created' => -1], 'projection' => ['label' => 1, 'created' => 1]]
    ) as $p) {
        $papers[] = [
            'id' => (string) $p['_id'],
            'label' => paperReportToStr($p['label'] ?? ''),
            'created' => paperReportToStr($p['created'] ?? ''),
        ];
    }
    return $papers;
}
