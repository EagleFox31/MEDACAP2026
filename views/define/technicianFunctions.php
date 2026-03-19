<?php
//technicianFunctions.php
require_once "../../vendor/autoload.php";
include_once "../userFilters.php";

/**
 * Récupérer tous les techniciens actifs.
 */
function getAlltechnicians($academy, $profile, $country = null, $level = null, $agency = null, $managerId = null) {
    // Appeler filterUsersByProfile avec tous les paramètres, y compris managerId
    return filterUsersByProfile($academy, $profile, $country, $level, $agency, $managerId);
}
?>
