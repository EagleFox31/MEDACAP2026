<?php
/**
 * Correction du groupe de la fonctionnalité bilanTestNotes.
 * Passe de "Consulter" → "Bilan des Tests" pour qu'elle apparaisse
 * dans le menu Manager.
 *
 * Accès : http://<serveur>/medacap/views/measure/fix_bilan_notes_group.php
 * Supprimer ce fichier après exécution.
 */
session_start();
if (!isset($_SESSION["id"]) || !in_array($_SESSION["profile"], ["Super Admin", "Admin"])) {
    http_response_code(403);
    exit("Accès refusé.");
}

require_once "../../vendor/autoload.php";
$db    = (new MongoDB\Client("mongodb://localhost:27017"))->academy;
$funcs = $db->functionalities;

// Chercher le bon group_order pour "Bilan des Tests"
$ref = $funcs->findOne(['group' => 'Bilan des Tests', 'modules' => 'measure']);
$groupOrder = $ref['group_order'] ?? 2;
$lastOrder  = $funcs->findOne(
    ['group' => 'Bilan des Tests', 'modules' => 'measure'],
    ['sort' => ['order' => -1]]
);
$nextOrder = isset($lastOrder) ? ($lastOrder['order'] + 1) : 1;

$result = $funcs->updateOne(
    ['key' => 'bilan_notes', 'modules' => 'measure'],
    ['$set' => [
        'group'       => 'Bilan des Tests',
        'group_EN'    => 'Assessment Status',
        'group_order' => $groupOrder,
        'order'       => $nextOrder,
    ]]
);

if ($result->getMatchedCount() === 0) {
    echo "<p style='color:red'>✘ Fonctionnalité <strong>bilan_notes</strong> introuvable.</p>";
} elseif ($result->getModifiedCount() === 0) {
    echo "<p style='color:orange'>⚠ Déjà à jour.</p>";
} else {
    echo "<p style='color:green'>✔ Groupe mis à jour → <strong>Bilan des Tests</strong></p>";
}

echo "<hr><p><strong>Terminé.</strong> Tu peux supprimer ce fichier.</p>";
?>
