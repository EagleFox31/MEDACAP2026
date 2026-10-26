<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Restaure les champs image Junior PL après le bulk rename abusif.
 * - Vide image sur les 67 questions texte-seul (PL26-JR-{id24}.png)
 * - Conserve / applique les 9 noms PL26-JR-*-{slug}.png
 * - Restaure depuis questions.json si une question avait une vraie image
 *
 * Usage: php scripts/restore_pl_junior_images.php [--dry-run]
 */
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\Client;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$uri = medacapMongoUri();
$db = (new Client($uri))->academy;

$keep9 = [
    '66351f73b87b0000790039c1' => 'PL26-JR-66351f73b87b0000790039c1-pneu-88.png',
    '66351f73b87b0000790039c2' => 'PL26-JR-66351f73b87b0000790039c2-pneu-14.png',
    '66351f71b87b000079003907' => 'PL26-JR-66351f71b87b000079003907-batterie-1200A.png',
    '66351f71b87b00007900393a' => 'PL26-JR-66351f71b87b00007900393a-frein-accumulateurs.png',
    '66351f71b87b00007900393c' => 'PL26-JR-66351f71b87b00007900393c-frein-valve-4voies.png',
    '66351f71b87b00007900393b' => 'PL26-JR-66351f71b87b00007900393b-frein-compresseur.png',
    '66351f70b87b0000790038c3' => 'PL26-JR-66351f70b87b0000790038c3-embrayage-commande.png',
    '66351f70b87b0000790038c4' => 'PL26-JR-66351f70b87b0000790038c4-embrayage-amiante.png',
    '66351f70b87b0000790038d9' => 'PL26-JR-66351f70b87b0000790038d9-demi-arbre-joint.png',
];

$restoreFile = __DIR__ . '/out_backup_image_restore.json';
if (!is_file($restoreFile)) {
    fwrite(STDERR, "Manquant: $restoreFile — lancez compare_backup_images.php\n");
    exit(1);
}
$restore = json_decode(file_get_contents($restoreFile), true);
$toClear = array_values($restore['clear_image_field'] ?? []);

$cleared = 0;
$kept = 0;
$restored = 0;
$skipped = 0;

echo $dryRun ? "=== DRY RUN ===\n" : "=== RESTAURATION ===\n";

// 1) Vider les faux PL26-JR-{id24}.png
foreach ($toClear as $row) {
    $id = $row['id'];
    $oid = new ObjectId($id);
    $q = $db->questions->findOne(['_id' => $oid], ['projection' => ['ref' => 1, 'image' => 1]]);
    if (!$q) {
        echo "[SKIP] {$row['ref']} $id — introuvable\n";
        $skipped++;
        continue;
    }
    $current = trim((string)($q['image'] ?? ''));
    if ($current === '') {
        $skipped++;
        continue;
    }
    if (!preg_match('/^PL26-JR-[a-f0-9]{24}\.png$/i', $current)) {
        echo "[SKIP] {$row['ref']} — image actuelle non bulk: $current\n";
        $skipped++;
        continue;
    }
    echo "[CLEAR] {$row['ref']} | $id | $current → null\n";
    if (!$dryRun) {
        $db->questions->updateOne(
            ['_id' => $oid],
            ['$set' => ['image' => null, 'updated' => date('d-m-Y H:i:s')]]
        );
    }
    $cleared++;
}

// 2) Appliquer les 9 noms slug
foreach ($keep9 as $id => $filename) {
    $oid = new ObjectId($id);
    $q = $db->questions->findOne(['_id' => $oid], ['projection' => ['ref' => 1, 'image' => 1]]);
    if (!$q) {
        echo "[ERR] keep9 $id introuvable\n";
        continue;
    }
    $current = trim((string)($q['image'] ?? ''));
    if ($current === $filename) {
        echo "[OK] {$q['ref']} — déjà $filename\n";
        $kept++;
        continue;
    }
    echo "[SET9] {$q['ref']} | $id | $current → $filename\n";
    if (!$dryRun) {
        $db->questions->updateOne(
            ['_id' => $oid],
            ['$set' => ['image' => $filename, 'updated' => date('d-m-Y H:i:s')]]
        );
    }
    $kept++;
}

// 3) Restaurer d'éventuelles régressions (backup avait image, bulk l'a écrasé)
foreach ($restore['restore_old_names'] ?? [] as $row) {
    $oid = new ObjectId($row['id']);
    echo "[RESTORE] {$row['ref']} → {$row['restore_to']}\n";
    if (!$dryRun) {
        $db->questions->updateOne(
            ['_id' => $oid],
            ['$set' => ['image' => $row['restore_to'], 'updated' => date('d-m-Y H:i:s')]]
        );
    }
    $restored++;
}

echo "\n=== Résumé ===\n";
echo "Champs vidés (faux PL26-JR-id): $cleared\n";
echo "9 priorité HAUTE: $kept\n";
echo "Restaurés depuis backup: $restored\n";
echo "Ignorés: $skipped\n";

if ($dryRun) {
    echo "\nRelancez sans --dry-run pour appliquer.\n";
}
