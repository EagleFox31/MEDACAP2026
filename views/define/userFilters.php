<?php

use MongoDB\BSON\ObjectId;
use MongoDB\Exception\Exception;
//
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
        //'test' => true, // Seuls les utilisateurs qui ont effectué un test
        'profile' => ['$in' => ['Technicien', 'Manager']] // Filtrer les techniciens et managers
    ];

    // Filtrer uniquement les managers qui ont "test: true"
    // Cela est nécessaire pour tous les managers peu importe le niveau
    $filter['$or'] = [
        ['profile' => 'Technicien'], // Inclure les techniciens (en fonction des autres filtres)
        [
            'profile' => 'Manager',
            'test' => true // Inclure uniquement les managers qui ont passé un test
        ]
    ];


    // Ajouter des filtres basés sur le profil utilisateur
    if ($profile === "Directeur Groupe" || $profile === "Super Admin") {
        // Directeur Groupe peut choisir de filtrer par n'importe quel pays, niveau, agence
        if ($country && ($country !== 'tous'||$country !== 'all')) {
            // Si un pays spécifique est sélectionné
            $filter['country'] = $country;
        }      
        if ($level) {
            $filter['level'] = $level;
        }
    } elseif ($profile === "Directeur Filiale") {
        // Directeur Filiale ne peut voir que son pays et peut filtrer par niveau
        if ($country) {
            $filter['country'] = $country;
        } else {
            // Supposer que $_SESSION contient le pays du Directeur Filiale
            $filter['country'] = $_SESSION['country'];
        }
        if ($level) {
            $filter['level'] = $level;
        }
    }
    elseif ($profile === "Manager") {
        // Gestion des filtres pour les utilisateurs avec le profil "Manager"
        if ($country) {
            $filter['country'] = $country;
        } else {
            // Supposer que $_SESSION contient le pays du Manager
            $filter['country'] = $_SESSION['country'];
        }
        if ($level) {
            $filter['level'] = $level;
        }
    }



    // Ajouter un filtre d'agence si spécifié
    if ($agency) {
        $filter['agency'] = $agency;
    }

    if ($managerId && $managerId !== 'all') {
        $filter['manager'] = new ObjectId($managerId);
    }

    // // Si un `managerId` est spécifié, appliquer l'agrégation avec `$lookup`
    // if ($managerId && $managerId !== 'all') {
    //     try {
    //         $pipeline = [
    //             [
    //                 '$match' => array_merge($filter, [
    //                     'manager' => new ObjectId($managerId)
    //                 ])
    //             ],
    //             [
    //                 '$lookup' => [
    //                     'from' => 'users', // Nom de la collection
    //                     'localField' => 'manager',
    //                     'foreignField' => '_id',
    //                     'as' => 'managerInfo'
    //                 ]
    //             ],
    //             [
    //                 '$project' => [
    //                     'firstName' => 1,
    //                     'lastName' => 1,
    //                     'profile' => 1,
    //                     'level' => 1,
    //                     'country' => 1,
    //                     'agency' => 1,
    //                     'managerInfo.firstName' => 1,
    //                     'managerInfo.lastName' => 1
    //                 ]
    //             ]
    //         ];


    //         // Exécuter l'agrégation et retourner les résultats
    //         $results = $academy->users->aggregate($pipeline)->toArray();

    //         return $results;

    //     } catch (Exception $e) {

    //         return [];
    //     }
    // }




        $results = $academy->users->find($filter)->toArray();
        return $results;

}
?>
