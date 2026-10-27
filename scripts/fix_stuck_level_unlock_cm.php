<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Deverrouille Senior/Expert pour les techniciens dont le niveau precedent est termine
 * mais activeTest n a jamais ete pose sur le niveau suivant.
 *
 *   php scripts/fix_stuck_level_unlock_cm.php --dry-run
 *   php scripts/fix_stuck_level_unlock_cm.php
 */
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../views/measure/includes/allocation_progression.php';

$dryRun = in_array('--dry-run', $argv, true);
$uri = medacapMongoUri('mongodb://10.68.0.7:27017');
$client = new MongoDB\Client($uri, [], ['serverSelectionTimeoutMS' => 10000]);
$db = $client->academy;
$allocations = $db->allocations;

$users = $allocations->distinct('user');
$fixed = 0;

foreach ($users as $userId) {
    if (!($userId instanceof MongoDB\BSON\ObjectId)) {
        continue;
    }
    foreach (['Junior', 'Senior'] as $level) {
        if (!allocationProgression_isTechnicianLevelComplete($allocations, $userId, $level)) {
            continue;
        }
        $next = allocationProgression_nextLevel($level);
        if ($next === null) {
            continue;
        }
        $nextAllocs = iterator_to_array($allocations->find([
            'user' => $userId,
            'level' => $next,
        ]));
        if (empty($nextAllocs)) {
            continue;
        }
        $needsUnlock = false;
        foreach ($nextAllocs as $a) {
            if (empty($a['activeTest'])) {
                $needsUnlock = true;
                break;
            }
        }
        if (!$needsUnlock) {
            continue;
        }

        $u = $db->users->findOne(['_id' => $userId], ['projection' => ['username' => 1, 'agency' => 1]]);
        $name = $u['username'] ?? (string) $userId;
        $agency = $u['agency'] ?? '';
        echo "[fix] {$name} ({$agency}) — {$level} termine, deverrouille {$next}\n";
        if (!$dryRun) {
            allocationProgression_tryUnlockNextLevel($allocations, $userId, $level);
        }
        $fixed++;
    }
}

echo "\nTotal: {$fixed}" . ($dryRun ? ' (dry-run)' : '') . "\n";
