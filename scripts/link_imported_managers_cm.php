<?php
/**
 * Rattache les techniciens CM importés à leur manager (usernameManager legacy).
 * Usage: php scripts/link_imported_managers_cm.php [--dry-run] [--mongo=mongodb://localhost:27017]
 */
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$mongoUri = 'mongodb://localhost:27017';
foreach ($argv ?? [] as $a) {
    if (strpos($a, '--mongo=') === 0) {
        $mongoUri = substr($a, 8);
    }
}

/** username norme Excel → username legacy BD */
$managerLegacyMap = [
    'Clovis FORBANG' => 'cl4fo',
    'Sonkouat KUSSO' => 'ma66ku',
    'Gildas PASSO' => 'gi07pa',
    'Chancelier DJABON' => 'ch56dj',
    'Patrick BIYACK' => 'pa53bi',
    'Eric ASSONFACK' => 'er01as',
    'Venance TONGA' => 've72to',
    'Charly MOUDOUROU' => 'ch49mo',
    'Raoul DJEUKWI' => 'ra85dj',
    'Jules MOUSSA' => 'ju6mo',
];

$importFile = __DIR__ . '/../exports/import_techniciens_cm_2026.xlsx';
if (!is_file($importFile)) {
    fwrite(STDERR, "Fichier introuvable: $importFile\n");
    exit(1);
}

$rows = IOFactory::load($importFile)->getSheetByName('Import')->toArray();
unset($rows[0]);

$db = (new MongoDB\Client($mongoUri))->academy->users;
$subs = new MongoDB\BSON\Regex('CAMEROON', 'i');

echo ($dryRun ? "[DRY-RUN] " : '') . "Mongo: $mongoUri\n\n";

$ok = 0;
$skip = 0;

foreach ($rows as $i => $row) {
    $username = trim((string)($row[0] ?? ''));
    $mgrNorm = trim((string)($row[17] ?? ''));
    if ($username === '') continue;

    $legacyMgr = $managerLegacyMap[$mgrNorm] ?? $mgrNorm;
    $tech = $db->findOne(['username' => $username, 'profile' => 'Technicien', 'subsidiary' => $subs, 'active' => true]);
    if (!$tech) {
        echo "SKIP $username — technicien introuvable\n";
        $skip++;
        continue;
    }

    $mgr = $db->findOne(['username' => $legacyMgr, 'profile' => 'Manager', 'active' => true]);
    if (!$mgr) {
        echo "SKIP $username — manager $legacyMgr introuvable\n";
        $skip++;
        continue;
    }

    $techId = $tech['_id'];
    $mgrId = $mgr['_id'];
    $already = isset($tech['manager']) && (string)$tech['manager'] === (string)$mgrId;

    echo "$username → $legacyMgr ($mgrNorm)" . ($already ? ' [déjà OK]' : '') . "\n";

    if (!$dryRun && !$already) {
        $db->updateOne(['_id' => $techId], ['$set' => ['manager' => $mgrId, 'updated' => date('d-m-Y H:i:s')]]);
        $db->updateOne(['_id' => $mgrId], ['$addToSet' => ['users' => $techId]]);
    }
    $ok++;
}

echo "\nRattachés: $ok | Ignorés: $skip\n";
