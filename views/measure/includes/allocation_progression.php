<?php
/**
 * Déverrouillage automatique du niveau suivant (Senior / Expert)
 * quand le technicien a terminé Factuel + Déclaratif pour le niveau courant.
 */

use MongoDB\BSON\ObjectId;
use MongoDB\Collection;

require_once __DIR__ . '/../../../core/mongo_compat.php';

function allocationProgression_isTechnicianLevelComplete(Collection $allocations, ObjectId $userId, string $level): bool
{
    $factuel = $allocations->findOne([
        'user' => $userId,
        'level' => $level,
        'type' => 'Factuel',
    ]);
    $declaratif = $allocations->findOne([
        'user' => $userId,
        'level' => $level,
        'type' => 'Declaratif',
    ]);

    if (!$factuel || !$declaratif) {
        return false;
    }

    return !empty($factuel['active']) && !empty($declaratif['active']);
}

function allocationProgression_nextLevel(string $level): ?string
{
    $map = [
        'Junior' => 'Senior',
        'Senior' => 'Expert',
    ];

    return $map[$level] ?? null;
}

/**
 * Active activeTest sur le niveau suivant si le niveau courant est terminé côté technicien.
 *
 * @return string|null Niveau déverrouillé (Senior/Expert) ou null
 */
function allocationProgression_tryUnlockNextLevel(Collection $allocations, ObjectId $userId, string $level): ?string
{
    if (!allocationProgression_isTechnicianLevelComplete($allocations, $userId, $level)) {
        return null;
    }

    $nextLevel = allocationProgression_nextLevel($level);
    if ($nextLevel === null) {
        return null;
    }

    $hasNext = medacapCountDocuments($allocations, [
        'user' => $userId,
        'level' => $nextLevel,
    ]);

    if ($hasNext === 0) {
        return null;
    }

    $allocations->updateMany(
        [
            'user' => $userId,
            'level' => $nextLevel,
            'activeTest' => ['$ne' => true],
        ],
        ['$set' => ['activeTest' => true]]
    );

    return $nextLevel;
}
