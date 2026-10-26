<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Corrige le compte "- MICHEL" (ligne Excel "MICHEL -" = prénom seul).
 * Usage: php scripts/fix_michel_incomplete_cm.php [--dry-run]
 */
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/includes/cfao_name_helpers.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$client = new MongoDB\Client(medacapMongoUri('mongodb://10.68.0.7:27017'));
$users = $client->academy->users;

$rawExcel = 'MICHEL -';
[$firstName, $lastName] = cfaoParseName($rawExcel);
$username = cfaoBuildUsername($firstName, $lastName);
$password = cfaoBuildPassword($firstName);

echo "Excel: [$rawExcel] => firstName=[$firstName] lastName=[$lastName]\n";
echo "Nouveau username: [$username]\n";
echo "Nouveau password: [$password]\n\n";

$user = $users->findOne(['username' => '- MICHEL', 'active' => true]);
if (!$user) {
    $user = $users->findOne(['lastName' => 'MICHEL', 'firstName' => '-', 'active' => true]);
}
if (!$user) {
    echo "Compte '- MICHEL' introuvable — peut-être déjà corrigé.\n";
    exit(0);
}

$conflict = $users->findOne([
    'username' => $username,
    'active' => true,
    '_id' => ['$ne' => $user['_id']],
]);
if ($conflict) {
    echo "ERREUR: username [$username] déjà pris par un autre compte.\n";
    exit(1);
}

$set = [
    'firstName' => ucfirst(cfaoFirstToken($firstName)),
    'lastName' => $lastName !== '' ? mb_strtoupper($lastName, 'UTF-8') : '',
    'username' => $username,
    'visiblePassword' => $password,
    'password' => sha1($password),
    'updated' => date('d-m-Y H:i:s'),
];

echo "Mise à jour _id=" . (string)$user['_id'] . "\n";
if ($dryRun) {
    echo "[DRY-RUN] OK\n";
    exit(0);
}

$users->updateOne(['_id' => $user['_id']], ['$set' => $set]);
echo "OK — compte corrigé.\n";
echo "Note: nom de famille toujours manquant dans l'Excel — à compléter par RH.\n";
