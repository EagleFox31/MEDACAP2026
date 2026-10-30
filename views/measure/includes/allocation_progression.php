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

/**
 * Tente de deverrouiller Senior puis Expert si les niveaux precedents sont termines.
 * A appeler a la soumission QCM et a l affichage de la liste (filet de securite).
 *
 * @return string[] Niveaux nouvellement deverrouilles (ex. ['Senior', 'Expert'])
 */
function allocationProgression_syncUnlockedLevels(Collection $allocations, ObjectId $userId)
{
    $unlocked = array();
    foreach (array('Junior', 'Senior') as $level) {
        $next = allocationProgression_tryUnlockNextLevel($allocations, $userId, $level);
        if ($next !== null) {
            $unlocked[] = $next;
        }
    }
    return $unlocked;
}
