<?php

/**
 * Applique une pénalité si le score est >= 80% mais que le technicien a des groupes fonctionnels
 * (pour la marque/niveau) en dessous de 80%.
 * 
 * @param  MongoDB\Database $academy       La DB ou un objet te permettant de requêter Mongo
 * @param  string           $technicianId  L'ID du technicien (en string)
 * @param  string           $brand         La marque
 * @param  string           $level         Le niveau (Junior / Senior / Expert)
 * @param  float            $currentScore  Le score moyen actuel (averageTotal) avant pénalité
 * @return float            Le score ajusté (pénalisé si besoin)
 */
function appliquerPenaliteSiNecessaire($academy, $technicianId, $brand, $level, $currentScore)
{
    // 1) Si le score actuel est déjà < 80, on ne fait rien (pas de pénalité).
    if ($currentScore < 80) {
        return $currentScore;
    }

    // 2) Récupérer la liste des groupes "supported" vs "nonSupported" pour cette marque + niveau
    //    depuis la collection "nonSupportedGroupsByBrandLevel".
    $collection = $academy->nonSupportedGroupsByBrandLevel;
    $doc = $collection->findOne(['brand' => $brand]);

    if (!$doc || empty($doc['levels'])) {
        // On n'a pas d'info => on ne pénalise pas
        return $currentScore;
    }

    // Trouver le bloc correspondant au level
    $blocNiveau = null;
    foreach ($doc['levels'] as $lvlInfo) {
        if (!empty($lvlInfo['level']) && $lvlInfo['level'] === $level) {
            $blocNiveau = $lvlInfo;
            break;
        }
    }

    if (!$blocNiveau) {
        // Pas d'info sur ce niveau => pas de pénalité
        return $currentScore;
    }

    // 3) On récupère la liste "supportedGroups" (ou "groups") pour ce brand + level
    //    (à adapter selon ta structure : c'est parfois "groups" = nonSupportés, 
    //     et "supportedGroups" = OK. Fais attention à bien inverser si besoin.)
    $supportedGroups = $blocNiveau['supportedGroups'] ?? [];
    // $nonSupportedGroups = $blocNiveau['groups'] ?? []; // si tu en as besoin

    // 4) Aller chercher la note de chaque groupe dans la collection "technicianScores" 
    //    (ou "technicianBrandScores", selon comment tu stockes les notes de groupe/spécialité).
    $techScoresColl = $academy->technicianScores;  // ou technicianBrandScores
    $techDoc = $techScoresColl->findOne(['userId' => new MongoDB\BSON\ObjectId($technicianId)]);

    if (!$techDoc) {
        // Technicien inconnu => pas de pénalité
        return $currentScore;
    }

    // Dans ta collection "technicianScores", tu as une structure du genre:
    // levels -> Junior -> specialities -> { "Boite de Vitesse" => [factuel, declaratif, moyenne], etc. }
    // On va parcourir "supportedGroups" et vérifier leur moyenne.
    
    // 5) Compter combien de groupes "supported" sont en dessous de 80%
    $countUnder80 = 0;

    if (!empty($techDoc['levels'][$level]['specialities'])) {
        $specialitiesArr = $techDoc['levels'][$level]['specialities'];
        
        // Parcourir chaque groupe supporté
        foreach ($supportedGroups as $groupName) {
            // S'assurer qu'on a bien ce groupe dans le doc
            if (isset($specialitiesArr[$groupName]['moyenne'])) {
                $moy = (float)$specialitiesArr[$groupName]['moyenne'];
                if ($moy < 80) {
                    $countUnder80++;
                }
            }
            // S'il n'existe pas, on peut décider de considérer que c'est 0 (ou 100 ?),
            // c'est à toi de voir comment tu veux gérer l'absence de note pour un groupe.
        }
    }

    // 6) Appliquer la pénalité : par exemple, retirer 1% par groupe <80
    //    (ou n'importe quelle autre formule)
    $finalScore = $currentScore - $countUnder80;

    // Optionnel: éviter de descendre en-dessous de 0
    if ($finalScore < 0) {
        $finalScore = 0;
    }

    return $finalScore;
}