<?php
/**
 * Recharge les quiz refs depuis vehicles pour les tests vides.
 * Usage: php scripts/repopulate_empty_tests_cm.php [--dry-run] [--user="Wonepo DZOGNOU"]
 */
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/includes/mongo_quiz_helpers.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$mongoUri = 'mongodb://10.68.0.7:27017';
$onlyUser = null;
foreach ($argv ?? [] as $a) {
    if (strpos($a, '--mongo=') === 0) {
        $mongoUri = substr($a, 8);
    }
    if (strpos($a, '--user=') === 0) {
        $onlyUser = substr($a, 7);
    }
}

$defaultUsers = ['Wonepo DZOGNOU', 'Clovis FORBANG'];
$targetUsers = $onlyUser ? [$onlyUser] : $defaultUsers;

$client = new MongoDB\Client($mongoUri);
$users = $client->academy->users;
$tests = $client->academy->tests;
$vehicles = $client->academy->vehicles;

echo ($dryRun ? "[DRY-RUN] " : '') . "Repopulation quiz refs\n\n";

foreach ($targetUsers as $username) {
    $user = $users->findOne(['username' => $username, 'active' => true]);
    if (!$user) {
        echo "SKIP $username — introuvable\n\n";
        continue;
    }

    echo "=== $username ===\n";
    foreach ($tests->find(['user' => $user['_id']]) as $test) {
        $before = mongoQuizCount($test);
        if ($before > 0) {
            echo "  {$test['level']} {$test['type']}: déjà $before refs — skip\n";
            continue;
        }

        if ($dryRun) {
            echo "  {$test['level']} {$test['type']}: rechargerait depuis " . implode(',', (array)($test['brand'] ?? [])) . "\n";
            continue;
        }

        $after = setTestQuizzesFromBrands($tests, $vehicles, $test);
        echo "  {$test['level']} {$test['type']}: 0 → $after refs\n";
    }
    echo "\n";
}

echo "Terminé.\n";
