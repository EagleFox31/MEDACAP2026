<?php
/**
 * Genere un HTML autonome de demo a partir de sample.medacap.json (test local).
 *
 *   php scripts/build_offline_sample_html.php
 */
require __DIR__ . '/includes/offline_qcm_lib.php';

$jsonPath = __DIR__ . '/../offline-qcm/player/sample.medacap.json';
$outPath = __DIR__ . '/../offline-qcm/player/sample_QCM.html';

if (!is_file($jsonPath)) {
    fwrite(STDERR, "Fichier introuvable: {$jsonPath}\n");
    exit(1);
}

$pkg = json_decode(file_get_contents($jsonPath), true);
if (!is_array($pkg)) {
    fwrite(STDERR, "JSON sample illisible\n");
    exit(1);
}

$pkg['status'] = 'pending';
$pkg['meta']['type'] = 'Factuel + Déclaratif';
$pkg['meta']['hasFactuel'] = true;
$pkg['meta']['hasDeclaratif'] = true;
$pkg['meta']['questionCount'] = count($pkg['questions']);
$pkg['meta']['declaratifQuestionCount'] = count($pkg['declaratifQuestions']);
$pkg['session'] = array(
    'step' => 'factuel',
    'startedAt' => null,
    'lastSavedAt' => null,
    'completedAt' => null,
    'timer' => array('hours' => 0, 'minutes' => 15, 'seconds' => 0),
    'timerFormatted' => '00:15:00',
    'remainingSeconds' => null,
    'declaratifStartedAt' => null,
    'declaratifCompletedAt' => null,
);

$ids = array();
foreach ($pkg['questions'] as $q) {
    $ids[] = (string) $q['id'];
}
foreach ($pkg['declaratifQuestions'] as $dq) {
    $ids[] = (string) $dq['id'];
}
$pkg['meta']['signature'] = offlineQcmSign($pkg['meta'], $ids);

file_put_contents($outPath, offlineQcmBuildStandaloneHtml($pkg));
echo "OK — {$outPath}\n";
echo "Double-clic sur ce fichier : le QCM 2-en-1 démarre sans import.\n";
