<?php
$ref = $argv[1] ?? 'Fac2o25';
foreach (json_decode(file_get_contents(__DIR__ . '/../questions.json'), true) as $q) {
    if (strcasecmp((string)($q['ref'] ?? ''), $ref) === 0) {
        echo 'ref: ' . ($q['ref'] ?? '') . PHP_EOL;
        echo 'image: ' . (isset($q['image']) && $q['image'] !== null && $q['image'] !== '' ? $q['image'] : '(vide)') . PHP_EOL;
        echo 'label: ' . ($q['label'] ?? '') . PHP_EOL;
        exit(0);
    }
}
echo "Introuvable dans backup: $ref\n";
