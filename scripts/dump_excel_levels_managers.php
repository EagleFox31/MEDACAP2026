<?php
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

const SOURCE = __DIR__ . '/../Copie de Copie de Liste_techniciens_marques_CFAO (1) - Copie.xlsx';

$names = [
    'FORBANG CLOVIS TEKUM',
    'KUSSO SONKOUAT MATHIAS',
    'PASSO GILDAS ARMEL',
    'DZOGNOU WONEPO JULES',
];

$brandCols = [
    'FUSO' => 6, 'HINO' => 7, 'SINOTRUK' => 8, 'KINGLONG' => 9,
    'RENAULT TRUCKS' => 10, 'JCB' => 11, 'LOVOL' => 12, 'TOYOTA BT' => 13, 'TOYOTA FORKLIFT' => 14,
];

$ws = IOFactory::load(SOURCE)->getSheetByName('Techniciens à évaluer');

foreach ($names as $target) {
    $row = null;
    $t = mb_strtoupper(trim($target), 'UTF-8');
    for ($r = 5; $r <= $ws->getHighestRow(); $r++) {
        $raw = mb_strtoupper(trim((string)$ws->getCellByColumnAndRow(2, $r)->getCalculatedValue()), 'UTF-8');
        if ($raw === $t) { $row = $r; break; }
    }
    if (!$row) { echo "$target: NOT FOUND\n\n"; continue; }

    $site = trim((string)$ws->getCellByColumnAndRow(3, $row)->getCalculatedValue());
    $rub = trim((string)$ws->getCellByColumnAndRow(4, $row)->getCalculatedValue());
    $role = trim((string)$ws->getCellByColumnAndRow(5, $row)->getCalculatedValue());

    echo "=== $target ===\n";
    echo "Site: $site | Rub: $rub | Rôle: $role\n";
    echo "Marques Excel (brut):\n";

    $levels = [];
    foreach ($brandCols as $brand => $col) {
        $v = trim((string)$ws->getCellByColumnAndRow($col, $row)->getCalculatedValue());
        if ($v === '') continue;
        echo "  - $brand: $v\n";
        if (stripos($v, 'Expert') !== false) $levels[] = 'Expert';
        elseif (stripos($v, 'Senior') !== false) $levels[] = 'Senior';
        elseif (stripos($v, 'Junior') !== false) $levels[] = 'Junior';
    }

    $rank = ['Junior' => 1, 'Senior' => 2, 'Expert' => 3];
    $max = 'Junior';
    foreach ($levels as $l) {
        if ($rank[$l] > $rank[$max]) $max = $l;
    }
    echo "Niveau max Excel: $max\n\n";
}
