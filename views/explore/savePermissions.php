<?php
// savePermissions.php
session_start();
ob_start();

// Journalisation des erreurs
ini_set('log_errors', 1);

// Définir l'en-tête de réponse comme JSON
header('Content-Type: application/json');

// Fonction pour envoyer une réponse JSON avec le code HTTP approprié
function sendJsonResponse($data, $httpStatusCode = 200) {
    http_response_code($httpStatusCode);
    error_log("sendJsonResponse: Envoi de la réponse avec le statut $httpStatusCode - " . json_encode($data));
    ob_clean();
    echo json_encode($data);
    exit();
}

require_once "../../vendor/autoload.php";
require_once "groupFunctions.php"; // Inclure les fonctions liées au module

// Fonction pour valider un ObjectId MongoDB
if (!function_exists('isValidObjectId')) {
    function isValidObjectId($id) {
        return preg_match('/^[a-fA-F0-9]{24}$/', $id) === 1;
    }
}


try {
    error_log("savePermissions.php: Début de l'exécution");

    // Vérifier les permissions de l'utilisateur
    if (!isset($_SESSION["id"]) || $_SESSION["profile"] != "Super Admin") {
        sendJsonResponse(
            [
                'status' => 'error',
                'code' => 403,
                'message' => 'Accès non autorisé.'
            ],
            403
        );
    }

    // Récupérer les données JSON
    $inputData = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendJsonResponse(
            [
                'status' => 'error',
                'code' => 400,
                'message' => 'Données JSON invalides.',
                'error' => json_last_error_msg()
            ],
            400
        );
    }

    // Valider la présence des clés 'additions', 'deletions' et 'currentModule'
    if (
        !isset($inputData['additions']) || !is_array($inputData['additions']) ||
        !isset($inputData['deletions']) || !is_array($inputData['deletions']) ||
        !isset($inputData['currentModule']) || empty($inputData['currentModule'])
    ) {
        sendJsonResponse(
            [
                'status' => 'error',
                'code' => 400,
                'message' => 'Données invalides (additions, suppressions ou module actuel manquants).'
            ],
            400
        );
    }

    $additions = $inputData['additions'];
    $deletions = $inputData['deletions'];
    $currentModule = $inputData['currentModule']; // Récupération de currentModule depuis la requête

    // Connexion à MongoDB
    $conn = new MongoDB\Client("mongodb://localhost:27017");
    $academy = $conn->academy;
    $functionalitiesCollection = $academy->functionalities;
    $profilesCollection = $academy->profiles;

    // Préparer les IDs de fonctionnalités valides pour le currentModule
    // Récupérer toutes les fonctionnalités du currentModule
    $moduleFunctionalitiesCursor = $functionalitiesCollection->find([
        'modules' => $currentModule
    ]);

    $moduleFunctionalities = iterator_to_array($moduleFunctionalitiesCursor);
    $moduleFuncIds = [];
    foreach ($moduleFunctionalities as $func) {
        $moduleFuncIds[] = (string)$func['_id'];
    }

    // Traiter les ajouts
    foreach ($additions as $profileName => $funcIds) {
        // Filtrer les IDs pour n'inclure que ceux appartenant au currentModule
        $validFuncIds = array_filter($funcIds, function($id) use ($moduleFuncIds) {
            return in_array($id, $moduleFuncIds) && isValidObjectId($id);
        });

        if (empty($validFuncIds)) {
            // Aucun ID valide à ajouter pour ce profil
            continue;
        }

        // Convertir les IDs en ObjectId
        $objectIds = array_map(function($id) {
            return new MongoDB\BSON\ObjectId($id);
        }, $validFuncIds);

        // Trouver le profil correspondant
        $profile = $profilesCollection->findOne(['name' => $profileName]);

        if ($profile) {
            // Utiliser $addToSet avec $each pour éviter les doublons
            $profilesCollection->updateOne(
                ['_id' => $profile['_id']],
                ['$addToSet' => ['functionalities' => ['$each' => $objectIds]]]
            );
        }
    }

    // Traiter les suppressions
    foreach ($deletions as $profileName => $funcIds) {
        // Filtrer les IDs pour n'inclure que ceux appartenant au currentModule
        $validFuncIds = array_filter($funcIds, function($id) use ($moduleFuncIds) {
            return in_array($id, $moduleFuncIds) && isValidObjectId($id);
        });

        if (empty($validFuncIds)) {
            // Aucun ID valide à supprimer pour ce profil
            continue;
        }

        // Convertir les IDs en ObjectId
        $objectIds = array_map(function($id) {
            return new MongoDB\BSON\ObjectId($id);
        }, $validFuncIds);

        // Trouver le profil correspondant
        $profile = $profilesCollection->findOne(['name' => $profileName]);

        if ($profile) {
            // Utiliser $pull avec $in pour supprimer les fonctionnalités
            $profilesCollection->updateOne(
                ['_id' => $profile['_id']],
                ['$pull' => ['functionalities' => ['$in' => $objectIds]]]
            );
        }
    }

    // Répondre avec succès
    sendJsonResponse(
        [
            'status' => 'success',
            'code' => 200,
            'message' => 'Permissions mises à jour avec succès pour le module "' . htmlspecialchars($currentModule) . '".'
        ],
        200
    );

} catch (MongoDB\Driver\Exception\Exception $e) {
    // Erreur liée à MongoDB
    error_log("savePermissions.php: Erreur MongoDB - " . $e->getMessage());
    sendJsonResponse(
        [
            'status' => 'error',
            'code' => 500,
            'message' => 'Erreur de base de données.',
            'error' => $e->getMessage()
        ],
        500
    );
} catch (Exception $e) {
    // Erreur générale
    error_log("savePermissions.php: Erreur générale - " . $e->getMessage());
    sendJsonResponse(
        [
            'status' => 'error',
            'code' => 500,
            'message' => 'Une erreur interne est survenue.',
            'error' => $e->getMessage()
        ],
        500
    );
}
?>
