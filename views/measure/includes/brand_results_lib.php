<?php
/**
 * Résultats des tests par marque pour l'équipe d'un manager (page resultatsMarque).
 *
 * Les résultats ne portent pas de marque (questions communes) : la note « marque » est dérivée.
 *  - Marques d'un technicien : users.brandJunior / brandSenior / brandExpert (par niveau).
 *  - Spécialités couvertes par marque × niveau : collection nonSupportedGroupsByBrandLevel (supportedGroups).
 *  - Note technicien × marque = moyenne de ses % sur les spécialités couvertes par la marque au niveau affiché.
 * Résultats chargés en une requête (mêmes sources que tdLoadTechnicianDetail, le plus récent l'emporte).
 */

require_once __DIR__ . '/technician_detail_lib.php';

if (!function_exists('brLoadTeamTechnicians')) {

    /**
     * Techniciens de l'équipe du manager (même périmètre que bilanTestNotes pour un Manager).
     * @param MongoDB\Database $academy
     * @param MongoDB\BSON\ObjectId $managerId
     * @return array liste de docs utilisateur
     */
    function brLoadTeamTechnicians($academy, $managerId)
    {
        $techs = array();
        $cursor = $academy->users->find(
            array('manager' => $managerId, 'active' => true),
            array('projection' => array('firstName' => 1, 'lastName' => 1, 'profile' => 1, 'test' => 1, 'level' => 1,
                'brandJunior' => 1, 'brandSenior' => 1, 'brandExpert' => 1))
        );
        foreach ($cursor as $u) {
            $profile = isset($u['profile']) ? (string) $u['profile'] : '';
            if ($profile === 'Technicien' || ($profile === 'Manager' && !empty($u['test']))) {
                $techs[] = $u;
            }
        }
        return $techs;
    }

    /**
     * Notes par spécialité de chaque technicien : [userId => [spec => [level => [kind => pct]]]].
     * @param MongoDB\Database $academy
     * @param MongoDB\BSON\ObjectId[] $userIds
     * @return array
     */
    function brLoadCells($academy, $userIds)
    {
        $cells = array();
        if (empty($userIds)) {
            return $cells;
        }
        $hasTechSpec = array();   // userId => level => true (Déclaratif technicien par spécialité présent)
        $fallback = array();      // userId => level => specialtyScores du global typeR=Techniciens le plus récent

        $cursor = $academy->results->find(
            array(
                'user' => array('$in' => $userIds),
                'active' => true,
                'level' => array('$in' => tdLevels()),
                '$or' => array(
                    array('speciality' => array('$exists' => true)),
                    array('type' => 'Declaratif', 'typeR' => 'Techniciens', 'specialtyScores' => array('$exists' => true)),
                ),
            ),
            array('sort' => array('_id' => -1), 'projection' => array('user' => 1, 'speciality' => 1, 'level' => 1, 'type' => 1,
                'typeR' => 1, 'score' => 1, 'total' => 1, 'specialtyScores' => 1))
        );
        foreach ($cursor as $doc) {
            $uid = (string) $doc['user'];
            $level = (string) $doc['level'];
            $type = isset($doc['type']) ? (string) $doc['type'] : '';
            $typeR = isset($doc['typeR']) ? (string) $doc['typeR'] : '';

            if ($typeR === 'Techniciens') {
                if (!isset($fallback[$uid][$level]) && isset($doc['specialtyScores'])) {
                    $fallback[$uid][$level] = $doc['specialtyScores'];
                }
                continue;
            }
            if ($type === 'Factuel') {
                $kind = 'fac';
            } elseif ($type === 'Declaratif' && $typeR === 'Technicien') {
                $kind = 'tech';
            } elseif ($type === 'Declaratif' && $typeR === 'Manager') {
                $kind = 'mgr';
            } else {
                continue;
            }
            $spec = trim((string) $doc['speciality']);
            if ($spec === '' || isset($cells[$uid][$spec][$level][$kind])) {
                continue; // plus récent déjà retenu
            }
            $pct = tdPct(isset($doc['score']) ? $doc['score'] : 0, isset($doc['total']) ? $doc['total'] : 0);
            if ($pct === null) {
                continue;
            }
            $cells[$uid][$spec][$level][$kind] = $pct;
            if ($kind === 'tech') {
                $hasTechSpec[$uid][$level] = true;
            }
        }

        // Nouveau flux Déclaratif technicien : scores par spécialité embarqués dans le résultat global.
        $shortMap = tdDeclaShortMap();
        foreach ($fallback as $uid => $byLevel) {
            foreach ($byLevel as $level => $scores) {
                if (!empty($hasTechSpec[$uid][$level])) {
                    continue;
                }
                foreach ($scores as $short => $sc) {
                    if (!isset($shortMap[$short])) {
                        continue;
                    }
                    $pct = tdPct(isset($sc['correct']) ? $sc['correct'] : 0, isset($sc['total']) ? $sc['total'] : 0);
                    if ($pct !== null) {
                        $cells[$uid][$shortMap[$short]][$level]['tech'] = $pct;
                    }
                }
            }
        }
        return $cells;
    }

    /**
     * Spécialités couvertes : [marque => [niveau => [spec => true]]] (noms de marque trimés).
     * @param MongoDB\Database $academy
     * @return array
     */
    function brLoadSupportedGroups($academy)
    {
        $map = array();
        foreach ($academy->nonSupportedGroupsByBrandLevel->find(array()) as $doc) {
            $brand = isset($doc['brand']) ? trim((string) $doc['brand']) : '';
            if ($brand === '' || !isset($doc['levels'])) {
                continue;
            }
            foreach ($doc['levels'] as $lv) {
                $level = isset($lv['level']) ? (string) $lv['level'] : '';
                if ($level === '' || !isset($lv['supportedGroups'])) {
                    continue;
                }
                foreach ($lv['supportedGroups'] as $g) {
                    $g = trim((string) $g);
                    if ($g !== '') {
                        $map[$brand][$level][$g] = true;
                    }
                }
            }
        }
        return $map;
    }

    /** Moyenne arrondie, null si vide. */
    function brAvg($sum, $n)
    {
        return $n > 0 ? (int) round($sum / $n) : null;
    }

    /**
     * Notes technicien × marque de l'équipe d'un manager.
     * Note d'une marque = moyenne des notes du technicien sur les spécialités couvertes par la marque au niveau affiché.
     * @param MongoDB\Database $academy
     * @param MongoDB\BSON\ObjectId $managerId
     * @param string $level 'all' (chaque technicien à son niveau) ou Junior / Senior / Expert
     * @return array {levelOptions, level, brands, rows, techCount}
     *   rows : [['id', 'name', 'level', 'scores' => [marque => [kind => pct|null]]]] — marque absente = non pratiquée
     */
    function brLoadTeamTechBrandScores($academy, $managerId, $level)
    {
        $allLevels = tdLevels();
        $techs = brLoadTeamTechnicians($academy, $managerId);

        // Niveaux proposés : jusqu'au plus haut niveau de l'équipe.
        $maxRank = -1;
        foreach ($techs as $t) {
            $rank = array_search(isset($t['level']) ? (string) $t['level'] : '', $allLevels, true);
            if ($rank !== false && $rank > $maxRank) {
                $maxRank = $rank;
            }
        }
        $levelOptions = array_slice($allLevels, 0, $maxRank + 1);
        if ($level !== 'all' && !in_array($level, $levelOptions, true)) {
            $level = 'all';
        }

        $userIds = array();
        foreach ($techs as $t) {
            $userIds[] = $t['_id'];
        }
        $cells = brLoadCells($academy, $userIds);
        $supported = brLoadSupportedGroups($academy);

        $brandSet = array();
        $rows = array();
        foreach ($techs as $t) {
            $uid = (string) $t['_id'];
            $ownLevel = isset($t['level']) ? (string) $t['level'] : '';
            $lvl = $level === 'all' ? $ownLevel : $level;
            // Niveau filtré au-dessus du niveau du technicien : non évalué.
            if (!in_array($lvl, $allLevels, true) || array_search($ownLevel, $allLevels, true) < array_search($lvl, $allLevels, true)
                || !isset($t['brand' . $lvl])) {
                continue;
            }
            $scores = array();
            foreach ($t['brand' . $lvl] as $brand) {
                $brand = trim((string) $brand);
                if ($brand === '' || isset($scores[$brand])) {
                    continue;
                }
                $brandSet[$brand] = true;
                $acc = array('fac' => array(0, 0), 'tech' => array(0, 0), 'mgr' => array(0, 0));
                if (!empty($cells[$uid])) {
                    foreach ($cells[$uid] as $spec => $byLevel) {
                        if (!isset($byLevel[$lvl])) {
                            continue;
                        }
                        // Marque sans référentiel : toutes les spécialités passées comptent.
                        if (isset($supported[$brand]) && !isset($supported[$brand][$lvl][$spec])) {
                            continue;
                        }
                        foreach ($byLevel[$lvl] as $kind => $pct) {
                            $acc[$kind][0] += $pct;
                            $acc[$kind][1]++;
                        }
                    }
                }
                $scores[$brand] = array(
                    'fac' => brAvg($acc['fac'][0], $acc['fac'][1]),
                    'tech' => brAvg($acc['tech'][0], $acc['tech'][1]),
                    'mgr' => brAvg($acc['mgr'][0], $acc['mgr'][1]),
                );
            }
            if (empty($scores)) {
                continue; // BSONArray vide : empty() ne le détecte pas
            }
            $rows[] = array(
                'id' => $uid,
                'name' => trim((string) $t['firstName'] . ' ' . (string) $t['lastName']),
                'level' => $lvl,
                'scores' => $scores,
            );
        }

        $brands = array_keys($brandSet);
        usort($brands, function ($a, $b) {
            return strcmp(tdSortKey($a), tdSortKey($b));
        });
        usort($rows, function ($a, $b) {
            return strcmp(tdSortKey($a['name']), tdSortKey($b['name']));
        });

        return array('levelOptions' => $levelOptions, 'level' => $level, 'brands' => $brands, 'rows' => $rows, 'techCount' => count($techs));
    }
}
