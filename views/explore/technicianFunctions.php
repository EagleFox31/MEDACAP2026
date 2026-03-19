<?php
//technicianFunctions.php
require_once "../../vendor/autoload.php";
require_once "../userFilters.php";
/**
 * Récupérer tous les techniciens actifs.
 */
function getAlltechnicians($academy, $profile, $country = null, $level = null, $agency = null, $managerId = null) {
    // Appeler filterUsersByProfile avec tous les paramètres, y compris managerId
    file_put_contents('debug_logs.log', "Appel de getAlltechnician avec managerId: " . json_encode($managerId) . "\n", FILE_APPEND);
    file_put_contents('debug_logs.log', "Appel de getAlltechnician avec profile: " . json_encode($profile) . "\n", FILE_APPEND);
    file_put_contents('debug_logs.log', "Appel de getAlltechnician avec country: " . json_encode($country) . "\n", FILE_APPEND);
    file_put_contents('debug_logs.log', "Appel de getAlltechnician avec level: " . json_encode($level) . "\n", FILE_APPEND);
    file_put_contents('debug_logs.log', "Appel de getAlltechnician avec agency: " . json_encode($agency) . "\n", FILE_APPEND);
    return filterUsersByProfile($academy, $profile, $country, $level, $agency, $managerId);
}
?>
