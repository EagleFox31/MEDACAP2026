<?php

use MongoDB\BSON\ObjectId;
use MongoDB\Exception\Exception;

/**
 * Filtre les utilisateurs en fonction du profil et des paramètres fournis.
 *
 * @param MongoDB\Database $academy La base de données MongoDB.
 * @param string $profile Le profil de l'utilisateur connecté.
 * @param string|null $country Filtre par pays.
 * @param string|null $level Filtre par niveau.
 * @param string|null $agency Filtre par agence.
 * @param string|null $managerId Filtre par manager (ID).
 * @return array Les utilisateurs filtrés.
 */
function filterUsersByProfile($academy, $profile, $country = null, $level = null, $agency = null, $managerId = null) {
    
    $filter = [
        'active' => true,
        'profile' => ['$in' => ['Technicien', 'Manager']] // Filtrer les techniciens et managers
    ];

    // Filtrer uniquement les managers qui ont "test: true"
    $filter['$or'] = [
        ['profile' => 'Technicien'], // Inclure les techniciens
        [
            'profile' => 'Manager',
            'test' => true // Inclure uniquement les managers qui ont passé un test
        ]
    ];

    // Ajouter des filtres basés sur le profil utilisateur
    if ($profile === "Directeur Groupe" || $profile === "Super Admin") {
        // Directeur Groupe ou Super Admin peut choisir de filtrer par n'importe quel pays
        if ($country && $country !== 'tous' && $country !== 'all') {
            $filter['country'] = $country;
        }
        if ($level) {
            $filter['level'] = $level;
        }
    } elseif ($profile === "Directeur Pièce et Service" || $profile === "Directeur des Opérations" || $profile === "Admin") {
        // Directeur Filiale ou Admin : limiter au pays de l'utilisateur
        $filter['country'] = $_SESSION['country'] ?? null; // Toujours utiliser le pays de la session
        if ($level) {
            $filter['level'] = $level;
        }
        // Si l'utilisateur est Admin et a un département non vide et non null, limiter au département
        if ($profile === "Admin" && !empty($_SESSION['department']) && $_SESSION['department'] !== null) {
            // Ajouter le filtre sur le département tout en conservant le filtre $or pour techniciens et managers
            $filter['department'] = $_SESSION['department'];
        }
    } elseif ($profile === "Manager") {
        // Manager : limiter au pays de l'utilisateur
        $filter['country'] = $_SESSION['country'] ?? null;
        if ($level) {
            $filter['level'] = $level;
        }
    }

    // Ajouter un filtre d'agence si spécifié
    if ($agency && $agency !== 'all') {
        $filter['agency'] = $agency;
    }

    // Ajouter un filtre de manager si spécifié
    if ($managerId && $managerId !== 'all') {
        $filter['manager'] = new ObjectId($managerId);
    }

    // Exécuter la requête et retourner les résultats
    $results = $academy->users->find($filter)->toArray();
    return $results;
}
?>