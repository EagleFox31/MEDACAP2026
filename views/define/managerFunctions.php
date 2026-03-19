<?php
// managerFunctions.php

require_once "../../vendor/autoload.php";
use MongoDB\Client;

/**
 * Récupère tous les techniciens sous un Manager spécifique.
 *
 * @param MongoDB\Database $academy
 * @param string $managerId
 * @return array
 */
function getTechniciansByManager($academy, $managerId) {
    try {
        $techniciansCursor = $academy->users->find([
            'profile' => 'Technicien',
            'manager' => new MongoDB\BSON\ObjectId($managerId),
            'active' => true
        ]);

        $technicians = iterator_to_array($techniciansCursor);
        return $technicians;
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération des techniciens : " . $e->getMessage());
        return [];
    }
}
?>
