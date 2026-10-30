<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Cron — vérifie le seuil 60% composé par épreuve recrutement et envoie l'alerte mail si besoin.
 *
 * Usage (toutes les 15–30 min) :
 *   php cronjobs/check_paper_completion_threshold.php
 */
ini_set('memory_limit', '256M');
date_default_timezone_set('Africa/Douala');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../views/measure/includes/paper_candidate_report_lib.php';
require_once __DIR__ . '/../views/sendMail.php';

use MongoDB\Client;
use MongoDB\BSON\Regex;

$mongoUri = medacapMongoUri('mongodb://10.68.0.7:27017');
$client = new Client($mongoUri);
$db = $client->academy;

$checked = 0;
$sent = 0;

foreach ($db->papers->find(['active' => true, 'label' => new Regex('Recrutement', 'i')]) as $paper) {
    $paperId = $paper['_id'];
    $checked++;
    if (paperReportMaybeSendThresholdAlert($db, $paperId, $client)) {
        $sent++;
        echo '[' . date('Y-m-d H:i:s') . '] Alerte envoyée : ' . ($paper['label'] ?? $paperId) . "\n";
    }
}

echo "Terminé — épreuves vérifiées : {$checked}, alertes envoyées : {$sent}\n";
