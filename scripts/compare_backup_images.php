<?php
/**
 * Compare questions.json backup vs bulk PL26-JR rename manifest.
 * Usage: php scripts/compare_backup_images.php
 */
$backupPath = __DIR__ . '/../questions.json';
$manifestPath = __DIR__ . '/out_pl26_junior_images_manifest.json';

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

$requestedRefs = ['Fac103', 'Fac240', 'Fac107', 'Fac210', 'Fac109', 'Fac127', 'Fac302'];

$raw = file_get_contents($backupPath);
$questions = json_decode($raw, true);
if (!is_array($questions)) {
    fwrite(STDERR, "Invalid JSON in questions.json\n");
    exit(1);
}

$byId = [];
$byRef = [];
foreach ($questions as $q) {
    $id = $q['_id']['$oid'] ?? $q['_id'] ?? null;
    if (!$id) continue;
    $id = (string)$id;
    $byId[$id] = $q;
    $ref = (string)($q['ref'] ?? '');
    if ($ref !== '') $byRef[$ref] = $q;
}

$manifest = json_decode(file_get_contents($manifestPath), true) ?: [];

$hadImage = [];
$noImage = [];
$overwroteReal = [];

foreach ($manifest as $row) {
    $id = $row['question_id'];
    $newName = $row['image'];
    $q = $byId[$id] ?? null;
    if (!$q) {
        $noImage[] = ['id' => $id, 'ref' => '?', 'backup_image' => 'ABSENT DU BACKUP', 'new' => $newName, 'label' => $row['label'] ?? ''];
        continue;
    }
    $ref = (string)($q['ref'] ?? '');
    $backupImg = $q['image'] ?? null;
    $hasBackup = !($backupImg === null || $backupImg === '' || (is_string($backupImg) && trim($backupImg) === ''));

    $entry = [
        'id' => $id,
        'ref' => $ref,
        'backup_image' => $hasBackup ? (string)$backupImg : null,
        'new' => $newName,
        'label' => (string)($q['label'] ?? $row['label'] ?? ''),
        'priority' => $row['priority'] ?? '',
        'is_keep9' => isset($keep9[$id]),
    ];

    if ($hasBackup) {
        $overwroteReal[] = $entry;
    } else {
        $noImage[] = $entry;
    }
}

// Junior PL stats from backup: all junior with/without image
$juniorWith = [];
$juniorWithout = [];
foreach ($questions as $q) {
    if (($q['level'] ?? '') !== 'Junior' || empty($q['active'])) continue;
    $id = (string)($q['_id']['$oid'] ?? $q['_id'] ?? '');
    $img = $q['image'] ?? null;
    $has = !($img === null || $img === '' || (is_string($img) && trim($img) === ''));
    $row = ['ref' => $q['ref'] ?? '', 'id' => $id, 'image' => $has ? (string)$img : null, 'label' => mb_substr((string)($q['label'] ?? ''), 0, 80)];
    if ($has) $juniorWith[] = $row;
    else $juniorWithout[] = $row;
}

echo "=== BACKUP questions.json ===\n";
echo "Total questions: " . count($questions) . "\n";
echo "Junior actives avec image: " . count($juniorWith) . "\n";
echo "Junior actives SANS image: " . count($juniorWithout) . "\n\n";

echo "=== BULK RENAME (manifest " . count($manifest) . " questions) ===\n";
echo "Dans backup SANS image (renommage abusif, pas d'ancien nom): " . count($noImage) . "\n";
echo "Dans backup AVEC image (vraie régression!): " . count($overwroteReal) . "\n\n";

echo "=== Refs signalées par l'utilisateur ===\n";
foreach ($requestedRefs as $ref) {
    $q = $byRef[$ref] ?? null;
    if (!$q) { echo "$ref: introuvable dans backup\n"; continue; }
    $id = (string)($q['_id']['$oid'] ?? $q['_id']);
    $img = $q['image'] ?? null;
    $has = !($img === null || $img === '' || (is_string($img) && trim($img) === ''));
    $inManifest = false;
    $newName = '';
    foreach ($manifest as $m) {
        if ($m['question_id'] === $id) { $inManifest = true; $newName = $m['image']; break; }
    }
    echo "$ref | $id\n";
    echo "  backup image: " . ($has ? $img : '(vide)') . "\n";
    echo "  devait avoir image? " . ($has ? 'OUI (déjà en backup)' : 'NON (texte seul)') . "\n";
    if ($inManifest) echo "  renommé en: $newName\n";
    echo "  " . mb_substr((string)($q['label'] ?? ''), 0, 90) . "\n\n";
}

echo "=== Les 9 priorité HAUTE — état dans backup ===\n";
foreach ($keep9 as $id => $expectedNew) {
    $q = $byId[$id] ?? null;
    if (!$q) { echo "$id: absent backup\n"; continue; }
    $img = $q['image'] ?? null;
    $has = !($img === null || $img === '' || (is_string($img) && trim($img) === ''));
    echo ($q['ref'] ?? '?') . " | $id\n";
    echo "  backup: " . ($has ? $img : '(vide)') . "\n";
    echo "  nouveau voulu: $expectedNew\n\n";
}

if ($overwroteReal) {
    echo "=== REGRESSION: avaient une image dans backup, écrasées par bulk ===\n";
    foreach ($overwroteReal as $e) {
        echo "{$e['ref']} | {$e['id']}\n";
        echo "  ANCIEN: {$e['backup_image']}\n";
        echo "  NOUVEAU (bulk): {$e['new']}\n";
        echo "  {$e['label']}\n\n";
    }
} else {
    echo "=== Aucune question du manifest n'avait d'image dans le backup ===\n";
    echo "(Le bulk n'a écrasé que des champs vides — le problème est d'avoir REMPLI des questions texte-seul)\n\n";
}

echo "=== Junior avec image dans backup (les vrais " . count($juniorWith) . ") ===\n";
foreach ($juniorWith as $r) {
    $inBulk = false;
    foreach ($manifest as $m) {
        if ($m['question_id'] === $r['id']) { $inBulk = true; break; }
    }
    $flag = $inBulk ? ' [DANS BULK?!]' : '';
    echo "{$r['ref']} | {$r['id']} | {$r['image']}$flag\n";
}

$outPath = __DIR__ . '/out_backup_image_restore.json';
$restore = [
    'summary' => [
        'backup_junior_with_image' => count($juniorWith),
        'backup_junior_without_image' => count($juniorWithout),
        'bulk_manifest_count' => count($manifest),
        'bulk_on_empty_backup' => count($noImage),
        'bulk_overwrote_real_image' => count($overwroteReal),
    ],
    'clear_image_field' => array_map(fn($e) => [
        'ref' => $e['ref'], 'id' => $e['id'], 'wrong_new' => $e['new'], 'backup_image' => $e['backup_image'],
    ], array_filter($noImage, fn($e) => !$e['is_keep9'])),
    'restore_old_names' => array_map(fn($e) => [
        'ref' => $e['ref'], 'id' => $e['id'], 'restore_to' => $e['backup_image'], 'wrong_new' => $e['new'],
    ], $overwroteReal),
    'keep9' => $keep9,
    'junior_with_image_in_backup' => $juniorWith,
];
file_put_contents($outPath, json_encode($restore, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\nRapport JSON: $outPath\n";
