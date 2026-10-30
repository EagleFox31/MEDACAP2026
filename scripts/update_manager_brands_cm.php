<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Met à jour brandJunior/Senior/Expert des managers CM depuis le Excel CFAO.
 * Usage: php scripts/update_manager_brands_cm.php [--dry-run]
 */
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

const SOURCE = __DIR__ . '/../Copie de Copie de Liste_techniciens_marques_CFAO (1) - Copie.xlsx';
if (!defined('MONGO_URI')) {
    define('MONGO_URI', medacapMongoUri('mongodb://10.68.0.7:27017'));
}

$dryRun = in_array('--dry-run', $argv ?? [], true);

$brandCols = [
    'FUSO' => 6, 'HINO' => 7, 'SINOTRUK' => 8, 'KINGLONG' => 9,
    'RENAULT TRUCKS' => 10, 'JCB' => 11, 'LOVOL' => 12, 'TOYOTA BT' => 13, 'TOYOTA FORKLIFT' => 14,
];
$brandDbMap = [
    'FUSO' => 'FUSO', 'HINO' => 'HINO', 'SINOTRUK' => 'SINOTRUK',
    'KINGLONG' => 'KING LONG', 'RENAULT TRUCKS' => 'RENAULT TRUCK',
    'JCB' => 'JCB', 'LOVOL' => 'LOVOL', 'TOYOTA BT' => 'TOYOTA BT', 'TOYOTA FORKLIFT' => 'TOYOTA FORKLIFT',
];

/** Excel name → username legacy en BD */
$managerExcelNames = [
    'cl4fo' => 'FORBANG CLOVIS TEKUM',
    'ma66ku' => 'KUSSO SONKOUAT MATHIAS',
    'gi07pa' => 'PASSO GILDAS ARMEL',
];

function normalizeLevel(?string $v): ?string {
    if (!$v) return null;
    if (stripos($v, 'Expert') !== false) return 'Expert';
    if (stripos($v, 'Senior') !== false) return 'Senior';
    if (stripos($v, 'Junior') !== false) return 'Junior';
    return null;
}

function parseBrandsFromRow($ws, int $row, array $brandCols, array $brandDbMap): array {
    $brandJunior = $brandSenior = $brandExpert = [];
    $levels = [];
    foreach ($brandCols as $excelBrand => $col) {
        $raw = trim((string)$ws->getCellByColumnAndRow($col, $row)->getCalculatedValue());
        if ($raw === '') continue;
        $lvl = normalizeLevel($raw);
        if (!$lvl) continue;
        $dbBrand = $brandDbMap[$excelBrand];
        $levels[] = $lvl;
        if ($lvl === 'Junior') $brandJunior[] = $dbBrand;
        if ($lvl === 'Senior') $brandSenior[] = $dbBrand;
        if ($lvl === 'Expert') $brandExpert[] = $dbBrand;
    }
    $rank = ['Junior' => 1, 'Senior' => 2, 'Expert' => 3];
    $maxLevel = 'Junior';
    foreach ($levels as $l) {
        if ($rank[$l] > $rank[$maxLevel]) $maxLevel = $l;
    }
    return compact('brandJunior', 'brandSenior', 'brandExpert', 'maxLevel');
}

function applyBrandCascade(array $junior, array $senior, array $expert): array {
    foreach ($expert as $b) {
        if (!in_array($b, $senior, true)) $senior[] = $b;
        if (!in_array($b, $junior, true)) $junior[] = $b;
    }
    foreach ($senior as $b) {
        if (!in_array($b, $junior, true)) $junior[] = $b;
    }
    return [$junior, $senior, $expert];
}

function findRowByName($ws, string $targetName): ?int {
    $target = mb_strtoupper(trim($targetName), 'UTF-8');
    for ($r = 5; $r <= $ws->getHighestRow(); $r++) {
        $raw = mb_strtoupper(trim((string)$ws->getCellByColumnAndRow(2, $r)->getCalculatedValue()), 'UTF-8');
        if ($raw === $target) return $r;
    }
    return null;
}

$ws = IOFactory::load(SOURCE)->getSheetByName('Techniciens à évaluer');
$db = (new MongoDB\Client(MONGO_URI))->academy->users;

echo ($dryRun ? "[DRY-RUN] " : '') . "Mise à jour marques managers CM\n\n";

foreach ($managerExcelNames as $legacyUsername => $excelName) {
    $row = findRowByName($ws, $excelName);
    if ($row === null) {
        echo "SKIP $legacyUsername — ligne Excel introuvable: $excelName\n";
        continue;
    }

    $parsed = parseBrandsFromRow($ws, $row, $brandCols, $brandDbMap);
    [$brandJunior, $brandSenior, $brandExpert] = applyBrandCascade(
        $parsed['brandJunior'],
        $parsed['brandSenior'],
        $parsed['brandExpert']
    );

    $user = $db->findOne([
        'username' => new MongoDB\BSON\Regex('^' . preg_quote($legacyUsername, '/') . '$', 'i'),
        'active' => true,
        'profile' => 'Manager',
    ]);

    if (!$user) {
        echo "SKIP $legacyUsername — manager introuvable en BD\n";
        continue;
    }

    $set = [
        'brandJunior' => array_values($brandJunior),
        'brandSenior' => array_values($brandSenior),
        'brandExpert' => array_values($brandExpert),
        'level' => $parsed['maxLevel'],
        'updated' => date('d-m-Y H:i:s'),
    ];

    echo "$legacyUsername ({$user['firstName']} {$user['lastName']})\n";
    echo "  level: {$parsed['maxLevel']}\n";
    echo "  J: " . implode(', ', $brandJunior) . "\n";
    echo "  S: " . implode(', ', $brandSenior) . "\n";
    echo "  E: " . implode(', ', $brandExpert) . "\n";

    if (!$dryRun) {
        $db->updateOne(['_id' => $user['_id']], ['$set' => $set]);
        echo "  => OK\n";
    }
    echo "\n";
}

echo "Terminé.\n";
