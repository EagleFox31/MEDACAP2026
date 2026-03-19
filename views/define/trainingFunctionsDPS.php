<?php
// trainingFunctions.php

use MongoDB\BSON\ObjectId;
use MongoDB\Exception\Exception;

/**
 * Counts the number of recommended trainings for a technician.
 *
 * @param MongoDB\Collection $trainingsColl The MongoDB trainings collection.
 * @param ObjectId $technicianObjId The ObjectId of the technician.
 * @param array $levels The levels to include in the count.
 * @return int The count of recommended trainings.
 */
function countRecommendedTrainings($trainingsColl, $technicianObjId, array $levels) {
    $query = [
        'active'   => true,
        'level'    => ['$in' => $levels],
        'users'    => $technicianObjId,
        'brand'    => ['$ne' => '']  // Exclude empty brands
    ];
    try {
        return $trainingsColl->count($query);
    } catch (Exception $e) {
        // Log the error
        file_put_contents('training_functions_error.log', $e->getMessage(), FILE_APPEND);
        return 0;
    }
}

/**
 * Counts the number of completed trainings for a technician.
 *
 * @param MongoDB\Collection $trainingsColl The MongoDB trainings collection.
 * @param ObjectId $technicianObjId The ObjectId of the technician.
 * @param array $levels The levels to include in the count.
 * @return int The count of completed trainings.
 */
function countCompletedTrainings($trainingsColl, $technicianObjId, array $levels) {
    $query = [
        'active'   => true,
        'level'    => ['$in' => $levels],
        'endDate'  => ['$exists' => true, '$ne' => null],
        'users'    => $technicianObjId,
        'brand'    => ['$ne' => '']  // Exclude empty brands
    ];
    try {
        return $trainingsColl->count($query);
    } catch (Exception $e) {
        // Log the error
        file_put_contents('training_functions_error.log', $e->getMessage(), FILE_APPEND);
        return 0;
    }
}

/**
 * Retrieves the number of trainings per brand for a technician.
 *
 * @param MongoDB\Collection $trainingsColl The MongoDB trainings collection.
 * @param ObjectId $technicianObjId The ObjectId of the technician.
 * @param array $levels The levels to include in the count.
 * @return array An array of trainings grouped by brand.
 */
function getTrainingsByBrand($trainingsColl, $technicianObjId, array $levels) {
    $pipeline = [
        [
            '$match' => [
                'active' => true,
                'level'  => ['$in' => $levels],
                'users'  => $technicianObjId,
                'brand'  => ['$ne' => '']  // Exclude empty brands
            ]
        ],
        [
            '$group' => [
                '_id'   => '$brand',
                'count' => ['$sum' => 1]
            ]
        ],
    ];

    try {
        $results = $trainingsColl->aggregate($pipeline);
        $trainingsByBrand = [];
        foreach ($results as $doc) {
            $trainingsByBrand[] = [
                'brand' => $doc->_id,
                'count' => $doc->count
            ];
        }
        return $trainingsByBrand;
    } catch (Exception $e) {
        // Log the error
        file_put_contents('training_functions_error.log', $e->getMessage(), FILE_APPEND);
        return [];
    }
}

/**
 * Retrieves the total training hours per brand for a technician.
 *
 * @param MongoDB\Collection $trainingsColl The MongoDB trainings collection.
 * @param ObjectId $technicianObjId The ObjectId of the technician.
 * @param array $levels The levels to include in the calculation.
 * @return array An associative array with brands as keys and total hours as values.
 */
function getTrainingHoursByBrand($trainingsColl, $technicianObjId, array $levels) {
    $pipeline = [
        [
            '$match' => [
                'active'       => true,
                'level'        => ['$in' => $levels],
                'users'        => $technicianObjId,
                'brand'        => ['$ne' => ''],
                'duree_jours'  => ['$exists' => true, '$ne' => null]
            ]
        ],
        [
            '$group' => [
                '_id'             => '$brand',
                'totalDureeJours' => ['$sum' => '$duree_jours']
            ]
        ]
    ];

    try {
        $results = $trainingsColl->aggregate($pipeline);
        $brandHoursMap = [];
        foreach ($results as $doc) {
            $totalHours = $doc->totalDureeJours * 8; // 1 day = 8 hours
            $brandHoursMap[(string)$doc->_id] = $totalHours;
        }
        return $brandHoursMap;
    } catch (Exception $e) {
        // Log the error
        file_put_contents('training_functions_error.log', $e->getMessage(), FILE_APPEND);
        return [];
    }
}
?>
