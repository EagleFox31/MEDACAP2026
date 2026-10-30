<?php
require_once __DIR__ . '/../core/medacap_config.php';
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$ws = IOFactory::load(__DIR__ . '/../Copie de Copie de Liste_techniciens_marques_CFAO (1) - Copie.xlsx')
    ->getSheetByName('Techniciens à évaluer');

function parseName(string $raw): array {
    $raw = trim(preg_replace('/\s+/', ' ', $raw));
    if ($raw === '' || $raw === '-') return ['', ''];
    $parts = explode(' ', $raw);
    if (count($parts) === 1) return ['', mb_strtoupper($parts[0], 'UTF-8')];
    $lastName = array_shift($parts);
    $firstName = implode(' ', $parts);
    return [ucwords(mb_strtolower($firstName, 'UTF-8')), mb_strtoupper($lastName, 'UTF-8')];
}

echo "=== Lignes Excel contenant MICHEL ===\n";
for ($r = 5; $r <= $ws->getHighestRow(); $r++) {
    $raw = trim((string)$ws->getCellByColumnAndRow(2, $r)->getCalculatedValue());
    if (stripos($raw, 'MICHEL') === false) continue;
    [$fn, $ln] = parseName($raw);
    echo "Row $r: raw=[$raw] => firstName=[$fn] lastName=[$ln]\n";
}

$client = new MongoDB\Client(medacapMongoUri('mongodb://10.68.0.7:27017'));
$users = $client->academy->users;
echo "\n=== Users Cameroun avec MICHEL ===\n";
foreach ($users->find(['country' => 'Cameroun', 'active' => true, 'username' => new MongoDB\BSON\Regex('MICHEL', 'i')]) as $u) {
    echo "{$u['username']} | firstName={$u['firstName']} lastName={$u['lastName']} pwd={$u['visiblePassword']}\n";
}
