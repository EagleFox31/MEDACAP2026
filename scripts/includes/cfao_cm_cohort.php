<?php
/**
 * Cohorte CFAO Cameroun 2026 — liste officielle (37 lignes, moins les sortis des effectifs).
 * PHP 8.4
 */
require_once __DIR__ . '/cfao_name_helpers.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function cfaoCmCohortSourcePath()
{
    $candidates = array(
        __DIR__ . '/../../Copie de Copie de Liste_techniciens_marques_CFAO (1) - Copie.xlsx',
        'C:/wamp64/www/MEDACAP/Copie de Copie de Liste_techniciens_marques_CFAO (1) - Copie.xlsx',
    );
    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

function cfaoCmNameKey($raw)
{
    $raw = trim(preg_replace('/\s+/', ' ', (string) $raw));
    $raw = str_replace(array('-', '—'), ' ', $raw);
    $parts = array_filter(explode(' ', mb_strtoupper($raw, 'UTF-8')));
    sort($parts);
    return implode(' ', $parts);
}

function cfaoCmManualUsernames()
{
    return array(
        cfaoCmNameKey('FORBANG CLOVIS TEKUM') => 'Clovis FORBANG',
        cfaoCmNameKey('KUSSO SONKOUAT MATHIAS') => 'Sonkouat KUSSO',
        cfaoCmNameKey('PASSO GILDAS ARMEL') => 'Gildas PASSO',
        cfaoCmNameKey('DZOGNOU WONEPO JULES') => 'Wonepo DZOGNOU',
        cfaoCmNameKey('MICHEL -') => 'MICHEL',
        cfaoCmNameKey('MICHEL') => 'MICHEL',
        cfaoCmNameKey('MESSI BEKOA Yvan') => 'Bekoa MESSI',
        cfaoCmNameKey('MESSI BEKOA') => 'Bekoa MESSI',
        cfaoCmNameKey('NYAMA Gabriel') => 'Gabriel NYAMA',
    );
}

/**
 * Personnes de la liste source qui ne sont plus dans les effectifs.
 */
function cfaoCmExcludedNames()
{
    return array(
        cfaoCmNameKey('GANYING KENGNE Johana Armandine') => true,
        cfaoCmNameKey('IBRAHIM Ntep') => true,
    );
}

/**
 * @return array<int, array{no:int, rawName:string, agency:string, role:string, siteKey:string}>
 */
function cfaoCmLoadCohort($sourcePath = null)
{
    if ($sourcePath === null) {
        $sourcePath = cfaoCmCohortSourcePath();
    }
    if (!$sourcePath || !is_file($sourcePath)) {
        throw new RuntimeException('Fichier liste CFAO introuvable');
    }

    $siteToAgency = array(
        'YAOUNDE' => 'Yaoundé',
        'DOUALA' => 'Douala',
        'NGAOUNDERE' => 'Ngaoundere',
        'BERTOUA' => 'Bertoua',
        'GAROUA' => 'Garoua',
        'BAFOUSSAM' => 'Bafoussam',
    );

    $ws = IOFactory::load($sourcePath)->getSheetByName('Techniciens à évaluer');
    if (!$ws) {
        throw new RuntimeException('Onglet Techniciens à évaluer introuvable');
    }

    $excluded = cfaoCmExcludedNames();
    $out = array();
    $no = 0;
    for ($r = 5; $r <= $ws->getHighestRow(); $r++) {
        $rawName = trim((string) $ws->getCellByColumnAndRow(2, $r)->getValue());
        if ($rawName === '' || stripos($rawName, 'Noms') !== false || stripos($rawName, 'Prénoms') !== false) {
            continue;
        }
        if (isset($excluded[cfaoCmNameKey($rawName)])) {
            continue;
        }
        $site =mb_strtoupper(trim((string) $ws->getCellByColumnAndRow(3, $r)->getValue()), 'UTF-8');
        $role = trim((string) $ws->getCellByColumnAndRow(4, $r)->getValue());
        $agency = isset($siteToAgency[$site]) ? $siteToAgency[$site] : $site;
        $no++;
        $out[] = array(
            'no' => $no,
            'rawName' => $rawName,
            'agency' => $agency,
            'role' => $role,
            'siteKey' => $site,
        );
    }
    return $out;
}

function cfaoCmResolveUser($usersCol, array $entry)
{
    $manual = cfaoCmManualUsernames();
    $key = cfaoCmNameKey($entry['rawName']);
    if (isset($manual[$key])) {
        $u = $usersCol->findOne(array(
            'username' => $manual[$key],
            'active' => true,
        ));
        if ($u) {
            return $u;
        }
    }

    $expected = usernameFromRawName($entry['rawName']);
    if ($expected !== '') {
        $u = $usersCol->findOne(array('username' => $expected, 'active' => true));
        if ($u) {
            return $u;
        }
    }

    $parts = array_filter(explode(' ', mb_strtoupper(trim($entry['rawName']), 'UTF-8')), function ($p) {
        return strlen($p) > 2;
    });

    $subs = new MongoDB\BSON\Regex('CAMEROON', 'i');
    $cursor = $usersCol->find(array('active' => true, 'subsidiary' => $subs));
    $best = null;
    $bestScore = 0;
    foreach ($cursor as $u) {
        $blob = mb_strtoupper(
            ($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? '') . ' ' . ($u['username'] ?? ''),
            'UTF-8'
        );
        $matched = 0;
        foreach ($parts as $p) {
            if (strpos($blob, $p) !== false) {
                $matched++;
            }
        }
        if ($matched > $bestScore && $matched >= max(1, count($parts) - 1)) {
            $bestScore = $matched;
            $best = $u;
        }
    }
    return $best;
}

function usernameFromRawName($raw)
{
    list($firstName, $lastName) = cfaoParseName($raw);
    return cfaoBuildUsername($firstName, $lastName);
}
