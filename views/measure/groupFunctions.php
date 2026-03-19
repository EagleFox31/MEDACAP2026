<?php
// groupFunctions.php
require_once "../../vendor/autoload.php";

use MongoDB\Client;

// Fonction pour obtenir le client MongoDB
function getMongoClient() {
    return new Client("mongodb://localhost:27017");
}

// Fonction pour obtenir la collection des fonctionnalités
function getFunctionalitiesCollection() {
    $client = getMongoClient();
    $academy = $client->academy;
    return $academy->functionalities;
}

// Fonction pour valider un ObjectId MongoDB
function isValidObjectId($id) {
    return preg_match('/^[a-fA-F0-9]{24}$/', $id) === 1;
}

// Récupère la liste des groupes distincts triés par group_order pour le module actuel
function getOrderedGroups($currentModule) {
    $functionalities = getFunctionalitiesCollection();

    // On récupère la liste distincte des groupes pour le module actuel
    $groups = $functionalities->distinct('group', [
        'modules' => $currentModule,
        'group' => ['$ne' => null],
        'group' => ['$ne' => '']
    ]);

    // Initialiser le tableau des groupes ordonnés
    $orderedGroups = [];

    foreach ($groups as $g) {
        if ($g === null || $g === '' || $g === 'connect_app') {
            // Ignorer certains groupes si nécessaire
            continue;
        }

        // Obtenir une fonctionnalité du groupe pour récupérer le group_order
        $oneFunc = $functionalities->findOne(['group' => $g, 'modules' => $currentModule]);
        $groupOrder = isset($oneFunc['group_order']) ? $oneFunc['group_order'] : 9999;

        $orderedGroups[] = [
            'name' => $g,
            'group_order' => $groupOrder
        ];
    }

    // Trier les groupes par group_order
    usort($orderedGroups, function($a, $b) {
        return $a['group_order'] <=> $b['group_order'];
    });

    return $orderedGroups;
}

// Récupérer les fonctionnalités d'un groupe pour le module actuel
function getFunctionalitiesByGroup($groupName, $currentModule) {
    $functionalities = getFunctionalitiesCollection();
    $cursor = $functionalities->find(
        [
            'group' => $groupName,
            'modules' => $currentModule,
            'active' => true // Filtrer les fonctionnalités actives
        ],
        [
            'sort' => ['order' => 1]
        ]
    );
    return iterator_to_array($cursor);
}

// Récupérer les fonctionnalités sans groupe pour le module actuel
function getFunctionalitiesWithoutGroup($currentModule, $active = true) {
    $functionalities = getFunctionalitiesCollection();
    $cursor = $functionalities->find(
        [
            '$or' => [
                ['group' => ''],
                ['group' => null]
            ],
            'modules' => $currentModule,
            'active' => $active // Filtrer par statut actif/inactif
        ]
    );
    return iterator_to_array($cursor);
}

// Créer un groupe : on va simplement donner un nom au groupe et l'ajouter via group_order
function createGroup($name, $currentModule) {
    $functionalities = getFunctionalitiesCollection();
    // Trouver le max group_order existant pour le module actuel
    $maxOrder = 0;
    $groups = getOrderedGroups($currentModule);
    if (count($groups) > 0) {
        $maxOrder = $groups[count($groups)-1]['group_order'];
    }
    $newGroupOrder = $maxOrder + 1;

    // Créer une fonctionnalité minimale avec ce groupe pour que le groupe existe réellement
    $document = [
        'name' => 'Groupe: ' . $name,
        'description' => 'Groupe créé automatiquement',
        'group' => $name,
        'group_order' => $newGroupOrder,
        'order' => 1,
        'modules' => $currentModule,
        'active' => true,
        'created_at' => time() // Utiliser un timestamp simple
    ];
    
    $result = $functionalities->insertOne($document);

    // Retourner le nouveau groupe avec son id
    return [
        'name' => $name,
        'group_order' => $newGroupOrder
    ];
}

// Renommer un groupe pour le module actuel
function renameGroup($oldName, $newName, $currentModule) {
    $functionalities = getFunctionalitiesCollection();
    // Récupérer une fonctionnalité du groupe existant pour obtenir le group_order
    $oneFunc = $functionalities->findOne(['group' => $oldName, 'modules' => $currentModule]);
    if ($oneFunc) {
        $groupOrder = $oneFunc['group_order'];
        // Mettre à jour toutes les fonctionnalités ayant group=$oldName pour le module actuel
        $functionalities->updateMany(
            ['group' => $oldName, 'modules' => $currentModule],
            ['$set' => ['group' => $newName, 'group_order' => $groupOrder]]
        );
    } else {
        // Si aucune fonctionnalité n'est actuellement associée, renvoyer une erreur ou ignorer
        throw new Exception("Le groupe '$oldName' n'existe pas pour le module '$currentModule'.");
    }
}

// Supprimer un groupe pour le module actuel
function deleteGroup($groupName, $currentModule) {
    $functionalities = getFunctionalitiesCollection();
    // Retirer le groupe des fonctionnalités (les passer en groupe vide)
    $functionalities->updateMany(
        ['group' => $groupName, 'modules' => $currentModule],
        ['$set' => ['group' => '', 'group_order' => 9999]]
    );
}

// Réordonner les groupes : $groupNamesInOrder est un tableau de noms de groupes dans le nouvel ordre pour le module actuel
function reorderGroups($groupNamesInOrder, $currentModule) {
    $functionalities = getFunctionalitiesCollection();
    $order = 1;
    foreach ($groupNamesInOrder as $gName) {
        // Mettre à jour toutes les fonctionnalités du groupe $gName pour le module actuel
        $functionalities->updateMany(
            ['group' => $gName, 'modules' => $currentModule],
            ['$set' => ['group_order' => $order]]
        );
        $order++;
    }
}

// Réordonner les fonctionnalités dans un groupe pour le module actuel
function reorderFunctionalities($groupName, $functionalityIdsInOrder, $currentModule) {
    $functionalities = getFunctionalitiesCollection();
    $order = 1;
    foreach ($functionalityIdsInOrder as $funcId) {
        if (!isValidObjectId($funcId)) {
            continue; // Ignorer les IDs invalides
        }
        try {
            $objectId = new MongoDB\BSON\ObjectId($funcId);
            $functionalities->updateOne(
                ['_id' => $objectId, 'modules' => $currentModule],
                ['$set' => ['order' => $order, 'group' => $groupName]]
            );
        } catch (Exception $e) {
            error_log("Error updating functionality: " . $e->getMessage());
            continue;
        }
        $order++;
    }
}

// Assigner des fonctionnalités à un groupe pour le module actuel
function assignFunctionalitiesToGroup($groupName, $functionalityIds, $currentModule) {
    $functionalities = getFunctionalitiesCollection();

    // Récupérer le group_order actuel, s'il existe
    $existingFunc = $functionalities->findOne(['group' => $groupName, 'modules' => $currentModule]);
    if ($existingFunc) {
        $groupOrder = $existingFunc['group_order'];
        // Trouver le max order du groupe
        $lastFunc = $functionalities->findOne(
            ['group' => $groupName, 'modules' => $currentModule],
            ['sort' => ['order' => -1]]
        );
        $maxOrder = $lastFunc ? $lastFunc['order'] : 0;
    } else {
        // Le groupe est nouveau, lui assigner un group_order à la fin
        $currentGroups = getOrderedGroups($currentModule);
        $maxG = 0;
        if (count($currentGroups) > 0) {
            $maxG = $currentGroups[count($currentGroups)-1]['group_order'];
        }
        $groupOrder = $maxG + 1;
        $maxOrder = 0;
    }

    foreach ($functionalityIds as $funcId) {
        if (!isValidObjectId($funcId)) {
            continue; // Ignorer les IDs invalides
        }
        $maxOrder++;
        try {
            $objectId = new MongoDB\BSON\ObjectId($funcId);
            $functionalities->updateOne(
                ['_id' => $objectId, 'modules' => $currentModule],
                ['$set' => ['group' => $groupName, 'order' => $maxOrder, 'group_order' => $groupOrder]]
            );
        } catch (Exception $e) {
            error_log("Error assigning functionality: " . $e->getMessage());
            continue;
        }
    }
}

// Retirer une fonctionnalité d'un groupe pour le module actuel
function removeFromGroup($funcId, $currentModule) {
    $functionalities = getFunctionalitiesCollection();
    if (!isValidObjectId($funcId)) {
        throw new Exception("Invalid functionality ID.");
    }
    try {
        $objectId = new MongoDB\BSON\ObjectId($funcId);
        // Remettre le group à '', order et group_order à 9999 ou une valeur par défaut
        $functionalities->updateOne(
            ['_id' => $objectId, 'modules' => $currentModule],
            ['$set' => ['group' => '', 'group_order' => 9999, 'order' => 9999]]
        );
    } catch (Exception $e) {
        error_log("Error removing from group: " . $e->getMessage());
        throw $e;
    }
}

// Obtenir une fonctionnalité par ID pour le module actuel
function getFunctionalityById($id, $currentModule) {
    if (!isValidObjectId($id)) {
        return null;
    }
    $functionalities = getFunctionalitiesCollection();
    try {
        $objectId = new MongoDB\BSON\ObjectId($id);
        return $functionalities->findOne(['_id' => $objectId, 'modules' => $currentModule]);
    } catch (Exception $e) {
        error_log("Error getting functionality: " . $e->getMessage());
        return null;
    }
}

// Mettre à jour une fonctionnalité par ID pour le module actuel
function updateFunctionality($id, $data, $currentModule) {
    if (!isValidObjectId($id)) {
        return false;
    }
    $functionalities = getFunctionalitiesCollection();
    try {
        $objectId = new MongoDB\BSON\ObjectId($id);
        // S'assurer que la fonctionnalité appartient au module actuel
        $updateResult = $functionalities->updateOne(
            ['_id' => $objectId, 'modules' => $currentModule],
            ['$set' => $data]
        );
        return ($updateResult->getModifiedCount() > 0);
    } catch (Exception $e) {
        error_log("Error updating functionality: " . $e->getMessage());
        return false;
    }
}
?>
