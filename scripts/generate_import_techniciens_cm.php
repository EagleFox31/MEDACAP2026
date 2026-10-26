<?php
/**
 * Transforme le Excel CFAO marques → format importeUser.php
 */
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/includes/cfao_name_helpers.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

const SOURCE = 'C:/wamp64/www/MEDACAP/Copie de Copie de Liste_techniciens_marques_CFAO (1) - Copie.xlsx';
const OUTPUT = 'C:/wamp64/www/MEDACAP/exports/import_techniciens_cm_2026.xlsx';

$brandCols = [
    'FUSO' => 6, 'HINO' => 7, 'SINOTRUK' => 8, 'KINGLONG' => 9,
    'RENAULT TRUCKS' => 10, 'JCB' => 11, 'LOVOL' => 12, 'TOYOTA BT' => 13, 'TOYOTA FORKLIFT' => 14,
];
$brandDbMap = [
    'FUSO' => 'FUSO', 'HINO' => 'HINO', 'SINOTRUK' => 'SINOTRUK',
    'KINGLONG' => 'KING LONG', 'RENAULT TRUCKS' => 'RENAULT TRUCK',
    'JCB' => 'JCB', 'LOVOL' => 'LOVOL', 'TOYOTA BT' => 'TOYOTA BT', 'TOYOTA FORKLIFT' => 'TOYOTA FORKLIFT',
];
$siteToAgency = [
    'YAOUNDE' => 'Yaoundé', 'DOUALA' => 'Douala', 'NGAOUNDERE' => 'Ngaoundere',
    'BERTOUA' => 'Bertoua', 'GAROUA' => 'Garoua', 'BAFOUSSAM' => 'Bafoussam',
];

function normalizeLevel(?string $v): ?string {
    if (!$v) return null;
    if (stripos($v, 'Expert') !== false) return 'Expert';
    if (stripos($v, 'Senior') !== false) return 'Senior';
    if (stripos($v, 'Junior') !== false) return 'Junior';
    return null;
}

function parseName(string $raw): array { return cfaoParseName($raw); }
function buildPassword(string $firstName): string { return cfaoBuildPassword($firstName); }
function firstToken(string $s): string { return cfaoFirstToken($s); }
function buildUsername(string $firstName, string $lastName): string { return cfaoBuildUsername($firstName, $lastName); }

function nameKey(string $raw): string {
    $parts = array_filter(explode(' ', mb_strtoupper(trim($raw), 'UTF-8')));
    sort($parts);
    return implode(' ', $parts);
}

function usernameFromRawName(string $raw): string {
    [$firstName, $lastName] = parseName($raw);
    return buildUsername($firstName, $lastName);
}

/**
 * Anciens usernames cryptiques (cl4fo…) → norme « 1er prénom + 1er nom ».
 */
$managerRawNames = [
    'cl4fo' => 'FORBANG CLOVIS TEKUM',
    'ma66ku' => 'KUSSO SONKOUAT MATHIAS',
    'gi07pa' => 'PASSO GILDAS ARMEL',
    'ch56dj' => 'DJABON CHANCELIER BERTRAND',
    'pa53bi' => 'BIYACK PATRICK',
    'er01as' => 'ASSONFACK ERIC',
    've72to' => 'TONGA VENANCE',
    'ch49mo' => 'MOUDOUROU BOULOU CHARLY MARTIN',
    'ra85dj' => 'DJEUKWI RAOUL',
    'ju6mo' => 'MOUSSA JULES',
    'la51di' => 'DIALLO LAMARANA',
    'he37nz' => 'NZIE HELENE',
];
$managerNormUsernames = [];
foreach ($managerRawNames as $legacy => $raw) {
    $managerNormUsernames[$legacy] = usernameFromRawName($raw);
}

function normManagerUsername(string $legacyCode, array $map): string {
    return $map[$legacyCode] ?? $legacyCode;
}

/** Marques par défaut si aucune case cochée (Senior partout sauf LOVOL en Junior). */
function defaultBrandsForRub(string $rub): array {
    $rubU = mb_strtoupper(trim($rub), 'UTF-8');
    if ($rubU === 'ENGINS' || preg_match('/^ENGIN/', $rubU)) {
        return [
            'brandJunior' => ['LOVOL'],
            'brandSenior' => ['JCB', 'TOYOTA BT', 'TOYOTA FORKLIFT'],
            'brandExpert' => [],
            'maxLevel' => 'Senior',
            'note' => 'Engins — Senior (LOVOL en Junior)',
        ];
    }
    return [
        'brandJunior' => [],
        'brandSenior' => ['FUSO', 'HINO', 'SINOTRUK', 'KING LONG', 'RENAULT TRUCK'],
        'brandExpert' => [],
        'maxLevel' => 'Senior',
        'note' => 'PL — toutes marques PL en Senior',
    ];
}

/** Managers déjà en base — ne pas importer comme techniciens */
$excludeNameKeys = [
    nameKey('FORBANG CLOVIS TEKUM'),
    nameKey('KUSSO SONKOUAT MATHIAS'),
    nameKey('PASSO GILDAS ARMEL'),
];

/** Chef d'équipe à créer comme Manager, pas technicien */
$excludeChefEquipe = [
    nameKey('DZOGNOU WONEPO JULES'),
];

function assignManager(string $site, string $rub, string $fonction): array {
    $siteU = mb_strtoupper(trim($site), 'UTF-8');
    $f = mb_strtoupper($fonction, 'UTF-8');
    $rubU = mb_strtoupper(trim($rub), 'UTF-8');

    if ($siteU === 'DOUALA') {
        if ($rubU === 'ENGINS' || preg_match('/ENGIN|GPE|ITINERANT/i', $fonction)) {
            return ['ma66ku', 'N+2 KUSSO (engins/organe)'];
        }
        if (preg_match('/SUPPORT|HOTLINE|GARANTIE/i', $f)) {
            return ['gi07pa', 'N+2 PASSO (support — déjà manager, techs sous gi07pa)'];
        }
        if (preg_match('/CARROSS/i', $f)) {
            return ['ch56dj', 'N+2 DJABON (carrosserie)'];
        }
        if (preg_match('/CONTROLE/i', $f)) {
            return ['pa53bi', 'N+2 BIYACK (contrôle technique)'];
        }
        return ['cl4fo', 'N+2 FORBANG (PL mécanique — défaut)'];
    }
    if ($siteU === 'YAOUNDE') {
        if ($rubU === 'ENGINS') {
            return ['ve72to', 'N+2 TONGA (engins)'];
        }
        return ['er01as', 'N+2 ASSONFACK (PL)'];
    }
    if ($siteU === 'NGAOUNDERE') {
        return ['ch49mo', 'N+2 MOUDOUROU (N+1 DIALLO)'];
    }
    if ($siteU === 'BERTOUA') {
        return ['ra85dj', 'N+2 DJEUKWI'];
    }
    if ($siteU === 'GAROUA') {
        return ['ju6mo', 'N+2 MOUSSA'];
    }
    if ($siteU === 'BAFOUSSAM') {
        return ['er01as', '⚠ Bafoussam sans manager — proposé ASSONFACK Yaoundé'];
    }
    return ['', 'Site non mappé'];
}

$ws = IOFactory::load(SOURCE)->getSheetByName('Techniciens à évaluer');
$importRows = [];
$excluded = [];
$warnings = [];

for ($r = 5; $r <= $ws->getHighestRow(); $r++) {
    $num = trim((string)$ws->getCellByColumnAndRow(1, $r)->getCalculatedValue());
    $rawName = trim((string)$ws->getCellByColumnAndRow(2, $r)->getCalculatedValue());
    if ($rawName === '' || !is_numeric($num)) continue;

    $site = mb_strtoupper(trim((string)$ws->getCellByColumnAndRow(3, $r)->getCalculatedValue()), 'UTF-8');
    $fonction = trim((string)$ws->getCellByColumnAndRow(4, $r)->getCalculatedValue());
    $rub = trim((string)$ws->getCellByColumnAndRow(5, $r)->getCalculatedValue());
    $nk = nameKey($rawName);

    if (in_array($nk, $excludeNameKeys, true)) {
        $excluded[] = [$num, $rawName, 'Déjà manager N+2 en base'];
        continue;
    }
    if (in_array($nk, $excludeChefEquipe, true)) {
        $excluded[] = [$num, $rawName, 'Chef d\'équipe — créer comme Manager sous la51di (DIALLO)'];
        continue;
    }

    [$firstName, $lastName] = parseName($rawName);
    if ($firstName === '' && $lastName !== '') {
        $warnings[] = [$num, $rawName, 'Nom seul (NOM sans prénom) — vérifier'];
    }
    if ($firstName !== '' && $lastName === '') {
        $warnings[] = [$num, $rawName, 'Prénom seul (nom de famille manquant) — compléter avec RH'];
    }

    $brandJunior = $brandSenior = $brandExpert = [];
    $levels = [];
    foreach ($brandCols as $excelBrand => $col) {
        $raw = trim((string)$ws->getCellByColumnAndRow($col, $r)->getCalculatedValue());
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
    if (empty($levels)) {
        $defaults = defaultBrandsForRub($rub);
        $brandJunior = $defaults['brandJunior'];
        $brandSenior = $defaults['brandSenior'];
        $brandExpert = $defaults['brandExpert'];
        $maxLevel = $defaults['maxLevel'];
        $warnings[] = [$num, $rawName, 'Marques vides — appliqué : ' . $defaults['note']];
    }

    [$mgrLegacy, $mgrNote] = assignManager($site, $rub, $fonction);
    $mgrUsername = $mgrLegacy; // legacy BD — importeUser.php matche username exact
    $agency = $siteToAgency[$site] ?? $site;
    $username = buildUsername($firstName, $lastName);
    $matricule = ''; // Matricule RH obligatoire — le fichier source n'en contient pas
    $visiblePwd = buildPassword($firstName ?: 'User');

    $importRows[] = [
        'num_source' => $num,
        'username' => $username,
        'matricule' => $matricule,
        'firstName' => $firstName,
        'lastName' => $lastName,
        'email' => '',
        'phone' => '',
        'gender' => 'Masculin',
        'birthdate' => '01/01/1990',
        'level' => $maxLevel,
        'country' => 'Cameroun',
        'profile' => 'Technicien',
        'certificate' => '',
        'subsidiary' => 'CFAO MOBILITY CAMEROON',
        'agency' => $agency,
        'department' => 'Equipment',
        'role' => $fonction,
        'recrutmentDate' => date('d/m/Y'),
        'usernameManager' => $mgrUsername,
        'visiblePassword' => $visiblePwd,
        'brandJunior' => implode(', ', $brandJunior),
        'brandSenior' => implode(', ', $brandSenior),
        'brandExpert' => implode(', ', $brandExpert),
        'specialitySenior' => '',
        'specialityExpert' => '',
        'mgr_note' => $mgrNote,
        'rub' => $rub,
        'site' => $site,
    ];
}

$headers = [
    'username', 'matricule', 'firstName', 'lastName', 'email', 'phone', 'gender', 'birthdate',
    'level', 'country', 'profile', 'certificate', 'subsidiary', 'agency', 'department', 'role',
    'recrutmentDate', 'usernameManager', 'visiblePassword',
    'brandJunior', 'brandSenior', 'brandExpert', '', 'specialitySenior', 'specialityExpert',
    'mgr_note', 'num_source',
];

$out = new Spreadsheet();

// Sheet import
$sh = $out->getActiveSheet();
$sh->setTitle('Import');
$sh->fromArray($headers, null, 'A1');

$rowIdx = 2;
foreach ($importRows as $row) {
    $line = [
        $row['username'], $row['matricule'], $row['firstName'], $row['lastName'],
        $row['email'], $row['phone'], $row['gender'], $row['birthdate'],
        $row['level'], $row['country'], $row['profile'], $row['certificate'],
        $row['subsidiary'], $row['agency'], $row['department'], $row['role'],
        $row['recrutmentDate'], $row['usernameManager'], $row['visiblePassword'],
        $row['brandJunior'], $row['brandSenior'], $row['brandExpert'], '',
        $row['specialitySenior'], $row['specialityExpert'],
        $row['mgr_note'], $row['num_source'],
    ];
    $sh->fromArray($line, null, 'A' . $rowIdx++);
}

// Sheet exclusions
$ex = $out->createSheet();
$ex->setTitle('Exclus');
$ex->fromArray(['N° source', 'Nom', 'Raison'], null, 'A1');
$i = 2;
foreach ($excluded as $e) {
    $ex->fromArray($e, null, 'A' . $i++);
}

// Sheet warnings
$wa = $out->createSheet();
$wa->setTitle('Avertissements');
$wa->fromArray(['N° source', 'Nom', 'Alerte'], null, 'A1');
$i = 2;
foreach ($warnings as $w) {
    $wa->fromArray($w, null, 'A' . $i++);
}

// Sheet légende
$leg = $out->createSheet();
$leg->setTitle('Legende');
$legend = [
    ['Règle', 'Détail'],
    ['level', 'Niveau MAX parmi toutes les marques'],
    ['brandJunior/Senior/Expert', 'Marques par niveau (sans cascade — importeUser.php propage Expert→Senior→Junior)'],
    ['username', 'Norme : 1er prénom + 1er nom (ex. Clovis FORBANG) — PAS cl4fo / vi21ib'],
    ['usernameManager', 'Username legacy en BD (cl4fo…) OU migrer managers avant import norme'],
    ['Douala PL', 'Clovis FORBANG'],
    ['Douala Engins', 'Sonkouat KUSSO'],
    ['Douala Support', 'Gildas PASSO'],
    ['Douala Carrossier', 'Chancelier DJABON'],
    ['Douala Contrôleur', 'Patrick BIYACK'],
    ['Yaoundé PL', 'Eric ASSONFACK'],
    ['Yaoundé Engins', 'Venance TONGA'],
    ['Ngaoundéré', 'Charly MOUDOUROU'],
    ['Bafoussam', 'Eric ASSONFACK proposé — à valider RH'],
    ['Mot de passe', 'Academy.{Prenom}@' . date('Y') . ' — compléter emails avant import'],
    ['Matricule', 'Vide = à compléter par RH (matricule paie réel, ex. 34462) — ne pas inventer'],
];
$leg->fromArray($legend, null, 'A1');

// Hiérarchie
$hi = $out->createSheet();
$hi->setTitle('Hierarchie_managers');
$hi->fromArray(['N+1', 'Username norme', 'Ancien username', 'Agence'], null, 'A1');
$hier = [
    ['Lamarana DIALLO', $managerNormUsernames['cl4fo'], 'cl4fo', 'Douala'],
    ['Lamarana DIALLO', $managerNormUsernames['ma66ku'], 'ma66ku', 'Douala'],
    ['Lamarana DIALLO', $managerNormUsernames['gi07pa'], 'gi07pa', 'Douala'],
    ['Lamarana DIALLO', $managerNormUsernames['ch49mo'], 'ch49mo', 'Ngaoundere'],
    ['—', $managerNormUsernames['pa53bi'], 'pa53bi', 'Douala'],
    ['—', $managerNormUsernames['ch56dj'], 'ch56dj', 'Douala'],
    ['Helene NZIE', $managerNormUsernames['er01as'], 'er01as', 'Yaoundé'],
    ['—', $managerNormUsernames['ve72to'], 've72to', 'Yaoundé'],
    ['—', $managerNormUsernames['ra85dj'], 'ra85dj', 'Bertoua'],
    ['—', $managerNormUsernames['ju6mo'], 'ju6mo', 'Garoua'],
];
$hi->fromArray($hier, null, 'A2');

// Migration managers (à exécuter en BD avant import techniciens)
$mig = $out->createSheet();
$mig->setTitle('Managers_migration');
$mig->fromArray(['Ancien username', 'Nouveau username (norme)', 'Matricule RH', 'Action'], null, 'A1');
$migRows = [];
foreach ($managerNormUsernames as $old => $new) {
    if ($old === 'he37nz') continue; // N+1, pas N+2 opérationnel
    $migRows[] = [$old, $new, '', 'Renommer username en BD puis import techniciens'];
}
$mig->fromArray($migRows, null, 'A2');

if (!is_dir(dirname(OUTPUT))) {
    mkdir(dirname(OUTPUT), 0777, true);
}
(new Xlsx($out))->save(OUTPUT);

echo "OK: " . OUTPUT . "\n";
echo "Source Excel: 37 techniciens\n";
echo "Lignes import: " . count($importRows) . " (37 − 4 exclus managers/chef)\n";
echo "Exclus: " . count($excluded) . "\n";
echo "Avertissements: " . count($warnings) . "\n";
