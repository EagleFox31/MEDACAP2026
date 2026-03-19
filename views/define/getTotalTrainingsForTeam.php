<?php
/**
 * Compte le nombre de formations par technicien et totalise le nombre pour l'équipe.
 *
 * @param MongoDB\Collection $trainingsColl     La collection des formations.
 * @param array              $technicianIdsObj  Tableau d'ObjectId des techniciens.
 * @return array|false                        Retourne un tableau avec le total ou false en cas d'erreur.
 */
function getTotalTrainingsForTeam($trainingsColl, array $technicianIdsObj)
{
    if (empty($technicianIdsObj)) {
        return [
            'totalTrainings' => 0,
            'trainingsPerTechnician' => []
        ];
    }

    // Pipeline d'agrégation
    $pipeline = [
        [
            '$match' => [
                'active' => true,
                'users'  => ['$in' => $technicianIdsObj],
                'brand'  => ['$ne' => '']
            ]
        ],
        [
            '$unwind' => '$users'
        ],
        [
            '$match' => [
                'users' => ['$in' => $technicianIdsObj]
            ]
        ],
        [
            '$group' => [
                '_id'   => '$users',
                'count' => ['$sum' => 1]
            ]
        ]
    ];

    try {
        $results = $trainingsColl->aggregate($pipeline);
        $trainingsPerTechnician = [];
        $totalTrainings = 0;

        foreach ($results as $doc) {
            $techId = (string)$doc->_id;
            $count = (int)$doc->count;
            $trainingsPerTechnician[$techId] = $count;
            $totalTrainings += $count;
        }

        return [
            'totalTrainings' => $totalTrainings,
            'trainingsPerTechnician' => $trainingsPerTechnician
        ];
    } catch (MongoDB\Exception\Exception $e) {
        error_log("Erreur lors de l'agrégation des formations par technicien : " . $e->getMessage());
        return false;
    }
}
?>
    