<?php
require_once __DIR__ . '/../core/medacap_config.php';
require __DIR__ . '/../vendor/autoload.php';

$client = new MongoDB\Client(medacapMongoUri('mongodb://10.68.0.7:27017'));
$db = $client->academy;

echo "=== Papers PL / 2026 ===\n";
foreach ($db->papers->find(['active' => true]) as $p) {
    $label = $p['label'] ?? '';
    if (stripos($label, '2026') !== false || stripos($label, 'Poids Lourds') !== false || stripos($label, 'PL') !== false) {
        echo (string)$p['_id'] . " | " . $label . " | q=" . count(iterator_to_array($p['questions'] ?? [])) . "\n";
    }
}
