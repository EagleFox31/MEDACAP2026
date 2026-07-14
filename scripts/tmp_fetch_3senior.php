<?php
$questions = json_decode(file_get_contents(__DIR__ . '/../questions.json'), true);
$refs = ['Fac2o85', 'Fac2o204', 'Fac2o19'];
foreach ($questions as $q) {
    if (!in_array($q['ref'] ?? '', $refs, true)) continue;
    echo ($q['ref'] ?? '') . ' | ' . ($q['_id']['$oid'] ?? '') . "\n";
    echo 'label: ' . ($q['label'] ?? '') . "\n";
    echo 'spec: ' . ($q['speciality'] ?? '') . "\n";
    foreach (['proposal1', 'proposal2', 'proposal3', 'proposal4'] as $p) {
        echo '  - ' . ($q[$p] ?? '') . "\n";
    }
    echo 'answer: ' . ($q['answer'] ?? '') . "\n";
    echo 'image: ' . json_encode($q['image'] ?? null) . "\n\n";
}
