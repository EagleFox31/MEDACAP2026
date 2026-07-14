<?php
foreach (json_decode(file_get_contents(__DIR__ . '/../questions.json'), true) as $q) {
    if (($q['ref'] ?? '') === 'Fac117') {
        echo 'backup image: ' . ($q['image'] ?? 'null') . PHP_EOL;
        echo 'label: ' . ($q['label'] ?? '') . PHP_EOL;
        break;
    }
}
