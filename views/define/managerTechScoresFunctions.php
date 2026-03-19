<?php
// managerTechScoresFunctions.php

require_once "../../vendor/autoload.php";
require_once __DIR__ . '/scoreFunctions.php'; // Chemin correct
use MongoDB\Client;
use MongoDB\BSON\ObjectId;

/**
 * Fonction pour récupérer les managers purs et leurs techniciens avec scores et marques
 *
 * @param MongoDB\Database $academy
 * @return array
 */
function getPureManagersAndScores($academy)
{
    $usersCollection = $academy->users;

    // Récupérer tous les managers actifs
    $allManagers = $usersCollection->find([
        'profile' => 'Manager',
        'active'  => true
    ])->toArray();

    // Filtrage : exclure ceux qui ont un Manager sous eux
    $pureManagers = [];
    foreach ($allManagers as $m) {
        $mId = (string)$m['_id'];

        // Vérifier s'il existe un sub-manager sous ce manager
        $sub = $usersCollection->findOne([
            'profile' => 'Manager',
            'active'  => true,
            'manager' => new ObjectId($mId)
        ]);

        if ($sub === null) {
            $pureManagers[] = $m; // Pas de manager en dessous => pur
        }
    }

    // Instancier votre ScoreCalculator
    $scoreCalc = new ScoreCalculator($academy);

    $levels       = ['Junior','Senior','Expert'];
    $specialities = $scoreCalc->getAllSpecialities();

    $results = [];

    foreach ($pureManagers as $manager) {
        $managerId   = (string)$manager['_id'];
        $managerName = ($manager['firstName'] ?? '') . ' ' . ($manager['lastName'] ?? '');

        // Récupérer ses Techniciens
        $technicians = $usersCollection->find([
            'profile' => 'Technicien',
            'active'  => true,
            'manager' => new ObjectId($managerId)
        ])->toArray();

        // Construire la map pour calcul du déclaratif
        $technicianManagerMap = [];
        foreach ($technicians as $tech) {
            $technicianManagerMap[(string)$tech['_id']] = $managerId;
        }

        $debug = [];
        // Récupérer scores Factuel + Declaratif
        $allScores = $scoreCalc->getAllScoresForTechnicians($academy, $technicianManagerMap, $levels, $specialities, $debug);

        // Préparer la structure
        $managerInfo = [
            'managerId'   => $managerId,
            'managerName' => $managerName,
            'technicians' => []
        ];

        // Construire la liste "technicians"
        foreach ($technicians as $tech) {
            $tId   = (string)$tech['_id'];
            $tName = ($tech['firstName'] ?? '') . ' ' . ($tech['lastName'] ?? '');

            // Récupérer les marques et les convertir en tableaux PHP
            $brandJunior = isset($tech['brandJunior']) ? $tech['brandJunior'] : [];
            $brandSenior = isset($tech['brandSenior']) ? $tech['brandSenior'] : [];
            $brandExpert = isset($tech['brandExpert']) ? $tech['brandExpert'] : [];

            // S'assurer que les marques sont des tableaux PHP
            $brandJunior = convertBSONArrayToPHPArray($brandJunior);
            $brandSenior = convertBSONArrayToPHPArray($brandSenior);
            $brandExpert = convertBSONArrayToPHPArray($brandExpert);

            // Récupérer les scores par niveau et spécialité
            $scoresByLevel = [];
            if (isset($allScores[$tId])) {
                foreach ($allScores[$tId] as $level => $specData) {
                    $tempSpecs = [];
                    foreach ($specData as $spec => $scoreTypes) {
                        $fact = $scoreTypes['Factuel']    ?? null;
                        $decl = $scoreTypes['Declaratif'] ?? null;
                        $tempSpecs[] = [
                            'speciality'      => $spec,
                            'factuelScore'    => $fact,
                            'declaratifScore' => $decl
                        ];
                    }
                    $scoresByLevel[] = [
                        'level'        => $level,
                        'specialities' => $tempSpecs
                    ];
                }
            }

            $managerInfo['technicians'][] = [
                'technicianId'   => $tId,
                'technicianName' => $tName,
                'brandJunior'    => $brandJunior,
                'brandSenior'    => $brandSenior,
                'brandExpert'    => $brandExpert,
                'scores'         => $scoresByLevel
            ];
        }

        $results[] = $managerInfo;
    }

    // Retourner les résultats
    return $results;
}

/**
 * Fonction pour convertir BSONArray ou autres types en tableaux PHP
 *
 * @param mixed $input
 * @return array
 */
function convertBSONArrayToPHPArray($input)
{
    if ($input instanceof \MongoDB\Model\BSONArray) {
        return $input->getArrayCopy();
    } elseif (is_object($input)) {
        // Si c'est un autre type d'objet, essayez de le convertir
        return (array)$input;
    } elseif (!is_array($input)) {
        return [$input];
    }
    return $input;
}
?>
