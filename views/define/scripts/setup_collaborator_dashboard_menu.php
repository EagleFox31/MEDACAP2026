<?php
require_once __DIR__ . '/../../../core/medacap_config.php';
/**
 * Ajoute la fonctionnalité menu « Plans de formation de mes collaborateurs » au profil DPS.
 * Usage : php views/define/scripts/setup_collaborator_dashboard_menu.php
 */
require_once __DIR__ . '/../../../vendor/autoload.php';

$mongoUri = medacapMongoUri('mongodb://10.68.0.7:27017');
$db = (new MongoDB\Client($mongoUri))->academy;

$key = 'collaborator_dashboard';
$existing = $db->functionalities->findOne(['key' => $key]);

$doc = [
    'key' => $key,
    'name' => 'Plans de formation de mes collaborateurs',
    'name_EN' => 'My collaborators training plans',
    'description' => 'Vue dashboard technicien avec filtres agence et collaborateur',
    'url' => './collaboratorDashboard',
    'icon' => 'fas fa-user-friends',
    'icon_type' => 'font_awesome',
    'group' => 'Plans de Formations',
    'group_order' => 3,
    'order' => 0,
    'modules' => ['define'],
    'active' => true,
    'created' => date('d-m-Y H:i:s'),
];

if ($existing) {
    $db->functionalities->updateOne(['_id' => $existing['_id']], ['$set' => $doc]);
    $funcId = $existing['_id'];
    echo "Fonctionnalité mise à jour : {$funcId}\n";
} else {
    $result = $db->functionalities->insertOne($doc);
    $funcId = $result->getInsertedId();
    echo "Fonctionnalité créée : {$funcId}\n";
}

$profileName = 'Directeur Pièce et Service';
$profile = $db->profiles->findOne(['name' => $profileName]);
if (!$profile) {
    fwrite(STDERR, "Profil introuvable : {$profileName}\n");
    exit(1);
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
    echo "Fonctionnalité ajoutée au profil {$profileName}\n";
} else {
    echo "Fonctionnalité déjà présente sur le profil {$profileName}\n";
}

echo "OK\n";
