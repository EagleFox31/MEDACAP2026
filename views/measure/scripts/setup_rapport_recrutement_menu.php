<?php
require_once __DIR__ . '/../../../core/medacap_config.php';
/**
 * Ajoute la fonctionnalité menu « Rapport recrutement » aux profils Admin, Super Admin et RH.
 * Usage : php views/measure/scripts/setup_rapport_recrutement_menu.php
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../includes/paper_candidate_report_lib.php';

$mongoUri = medacapMongoUri('mongodb://10.68.0.7:27017');
$db = (new MongoDB\Client($mongoUri))->academy;

$key = 'rapport_recrutement';
$existing = $db->functionalities->findOne(['key' => $key]);

$ref = $db->functionalities->findOne(['group' => 'Recrutement', 'modules' => 'measure']);
if (!$ref) {
    $ref = $db->functionalities->findOne(['modules' => 'measure'], ['sort' => ['group_order' => -1]]);
}
$groupOrder = $ref['group_order'] ?? 5;

$lastInGroup = $db->functionalities->findOne(
    ['group' => 'Recrutement', 'modules' => 'measure'],
    ['sort' => ['order' => -1]]
);
$nextOrder = isset($lastInGroup['order']) ? ((int) $lastInGroup['order'] + 1) : 0;

$doc = [
    'key' => $key,
    'name' => 'Rapport recrutement',
    'name_EN' => 'Recruitment report',
    'description' => 'Rapport candidats par épreuve recrutement PL — notes, temps, export Excel',
    'url' => './rapportRecrutement.php',
    'icon' => 'fas fa-chart-bar',
    'icon_type' => 'font_awesome',
    'group' => 'Recrutement',
    'group_EN' => 'Recruitment',
    'group_order' => $groupOrder,
    'order' => $nextOrder,
    'modules' => ['measure'],
    'active' => true,
    'updated' => date('Y-m-d H:i:s'),
];

if ($existing) {
    $db->functionalities->updateOne(['_id' => $existing['_id']], ['$set' => $doc]);
    $funcId = $existing['_id'];
    echo "Fonctionnalité mise à jour : {$funcId}\n";
} else {
    $doc['created'] = date('Y-m-d H:i:s');
    $result = $db->functionalities->insertOne($doc);
    $funcId = $result->getInsertedId();
    echo "Fonctionnalité créée : {$funcId}\n";
}

$profileNames = ['Super Admin', 'Admin', 'Ressource Humaine'];
foreach ($profileNames as $profileName) {
    $profile = $db->profiles->findOne(['name' => $profileName]);
    if (!$profile) {
        echo "Profil introuvable : {$profileName}\n";
        continue;
    }

    $funcs = $profile['functionalities'] ?? [];
    $ids = [];
    foreach ($funcs as $f) {
        $ids[] = $f instanceof MongoDB\BSON\ObjectId ? $f : new MongoDB\BSON\ObjectId((string) $f);
    }

    $already = false;
    foreach ($ids as $id) {
        if ((string) $id === (string) $funcId) {
            $already = true;
            break;
        }
    }

    if (!$already) {
        $ids[] = $funcId;
        $db->profiles->updateOne(
            ['_id' => $profile['_id']],
            ['$set' => ['functionalities' => $ids]]
        );
        echo "Ajouté au profil {$profileName}\n";
    } else {
        echo "Déjà présent sur {$profileName}\n";
    }
}

echo "Backfill paper_campaign_status (sans envoi mail)...\n";
foreach ($db->papers->find(['active' => true, 'label' => new MongoDB\BSON\Regex('Recrutement', 'i')]) as $paper) {
    $ctx = paperReportLoadPaperContext($db, $paper['_id']);
    if (!$ctx) {
        continue;
    }
    $candidates = paperReportBuildAllCandidates($db, $ctx);
    $stats = paperReportGetCompletionStats($candidates, (float) $ctx['threshold_pct']);
    paperReportUpsertCampaignStatus($db, $paper['_id'], $stats);
    echo '  · ' . ($ctx['paper_label'] ?? $paper['_id']) . " — {$stats['composed']}/{$stats['enrolled']}\n";
}

echo "OK\n";
