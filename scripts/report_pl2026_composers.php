<?php
/**
 * Rapport console — épreuve PL 2026, candidats ayant composé.
 * Usage: php scripts/report_pl2026_composers.php [--paper-id=6a452db87ae27d2cc30d8592]
 */
require __DIR__ . '/export_paper_scores.php';

if (php_sapi_name() === 'cli' && realpath($argv[0] ?? '') === realpath(__FILE__)) {
$paperIdStr = (string) argValue($argv, 'paper-id', '6a452db87ae27d2cc30d8592');
$mongoUri = (string) argValue($argv, 'mongo-uri', 'mongodb://10.68.0.7:27017');

if (!preg_match('/^[0-9a-f]{24}$/i', $paperIdStr)) {
    fwrite(STDERR, "ERROR: invalid paper-id\n");
    exit(1);
}

$client = new MongoDB\Client($mongoUri);
$db = $client->academy;
$paperId = new MongoDB\BSON\ObjectId($paperIdStr);
$paper = $db->papers->findOne(['_id' => $paperId]);
if (!$paper) {
    fwrite(STDERR, "Paper not found\n");
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
$specialtyList = [];

foreach ($qOids as $i => $oid) {
    $k = (string) $oid;
    if (!isset($qmap[$k])) continue;
    $qd = $qmap[$k];
    $spec = toStr($qd['speciality'] ?? '') ?: '(Sans spécialité)';
    $level = toStr($qd['level'] ?? 'Autre');
    if (!isset($levelTotals[$level])) $levelTotals[$level] = 0;
    $levelTotals[$level]++;
    $paperQuestions[] = [
        'index' => $i, 'num' => $i + 1, 'id' => $k,
        'ref' => toStr($qd['ref'] ?? ''), 'label' => toStr($qd['label'] ?? ''),
        'level' => $level, 'speciality' => $spec, 'groupe_fonctionnel' => $spec,
        'answer' => toStr($qd['answer'] ?? ''),
    ];
    $specialtyTotals[$spec] = ($specialtyTotals[$spec] ?? 0) + 1;
}
$specialtyList = array_keys($specialtyTotals);
sort($specialtyList);

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
    if (!in_array($profile, ['CANDIDAT', 'CANDIDATE'], true)) continue;
    if (!in_array($country, ['CAMEROUN', 'CAMEROON'], true)) continue;
    if (!empty($u['loadtestTag'])) continue;
    $uname = strtolower(toStr($u['username'] ?? ''));
    if (strpos($uname, 'demo') !== false) continue;
    $expected[(string) $u['_id']] = $u;
}

$composers = [];
foreach ($expected as $u) {
    $c = buildCandidateData($db, $u, $paperId, $paperQuestions, $specialtyList, $specialtyTotals, $levelTotals, $recruitmentWeights, $minCompletionPct);
    $answered = (int) ($c['global']['answered'] ?? 0);
    if ($answered <= 0 && $c['status'] !== 'Terminé') {
        continue;
    }
    $composers[] = $c;
}
usort($composers, fn($a, $b) => strcmp($a['name'], $b['name']));

echo "Épreuve : $paperLabel\n";
echo "Paper ID : $paperIdStr\n";
echo "Banque : Junior {$levelTotals['Junior']} | Senior {$levelTotals['Senior']} | Expert {$levelTotals['Expert']} questions\n";
echo "Candidats inscrits : " . count($expected) . " | Ayant composé : " . count($composers) . "\n\n";

$fmt = function ($v) {
    return $v === null ? '—' : (is_float($v) ? number_format($v, 2, ',', '') : (string) $v);
};

foreach ($composers as $c) {
    echo str_repeat('=', 72) . "\n";
    echo $c['name'] . " (" . $c['username'] . ")\n";
    echo "Statut : " . $c['status'] . " | Filiale : " . $c['subsidiary'] . "\n";
    if ($c['finished_at'] !== '') echo "Fin : " . $c['finished_at'] . "\n";
    if ($c['time_spent'] !== '') echo "Temps : " . $c['time_spent'] . "\n";

    $g = $c['global'];
    echo "\nGLOBAL : " . $g['answered'] . "/" . $g['total'] . " répondues"
        . " | couverture " . ($g['pct_completion'] ?? 0) . "%"
        . " | note maîtrise " . $fmt($g['note_maitrise']) . "/20"
        . " | note complétude " . $fmt($g['note_completude']) . "/20\n";

    echo "Note recrutement (J+S maîtrise) : " . $fmt($c['note_recrutement']) . "/20"
        . " | Valide : " . ($c['recrutement_valide'] ? 'Oui' : 'Non') . "\n\n";

    printf("%-8s %-12s %-14s %-18s %-18s\n", 'Niveau', 'Répondues', 'Couverture', 'Note maîtrise/20', 'Note complétude/20');
    foreach (['Junior', 'Senior', 'Expert'] as $lv) {
        if (!isset($c['levels'][$lv]) || ($c['levels'][$lv]['total'] ?? 0) <= 0) continue;
        $lb = $c['levels'][$lv];
        printf(
            "%-8s %-12s %-14s %-18s %-18s\n",
            $lv,
            $lb['answered'] . '/' . $lb['total'],
            ($lb['pct_completion'] ?? 0) . '%',
            $fmt($lb['note_maitrise']),
            $fmt($lb['note_completude'])
        );
    }
    echo "\n";
}

if (!$composers) {
    echo "Aucun candidat n'a composé pour le moment.\n";
}
}
