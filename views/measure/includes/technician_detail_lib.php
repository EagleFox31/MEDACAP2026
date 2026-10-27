<?php
/**
 * Détail des notes d'un technicien par spécialité et par niveau (page detailTechnicien).
 *
 * Sources (collection `results`) :
 *  - Connaissances (Factuel)        : un doc par spécialité, type=Factuel, champ `speciality`.
 *  - Tâches pro technicien          : un doc par spécialité, type=Declaratif, typeR=Technicien (historique)
 *                                     sinon `specialtyScores` du résultat global typeR=Techniciens (nouveau flux).
 *  - Tâches pro manager             : un doc par spécialité, type=Declaratif, typeR=Manager.
 *  - Totaux                         : résultats globaux typeR Technicien / Techniciens / Managers (comme bilanTestNotes).
 * En cas de plusieurs passages, le plus récent (_id) l'emporte.
 */

if (!function_exists('tdLevels')) {

    /** @return string[] */
    function tdLevels()
    {
        return array('Junior', 'Senior', 'Expert');
    }

    /** Seuil sous lequel une note est un point à travailler (aligné sur l'orange/rouge de bilanTestNotes). */
    function tdWeakThreshold()
    {
        return 70;
    }

    /**
     * Codes courts de `specialtyScores` (userQuizDeclaratif QuizConstants) → libellé de spécialité.
     * @return array
     */
    function tdDeclaShortMap()
    {
        return array(
            'assistanceConduite' => 'Assistance à la Conduite',
            'arbre' => 'Arbre de Transmission',
            'transfert' => 'Boite de Transfert',
            'boite_vitesse' => 'Boite de Vitesse',
            'boite_vitesse_auto' => 'Boite de Vitesse Automatique',
            'boite_vitesse_meca' => 'Boite de Vitesse Mécanique',
            'boite_vitesse_VC' => 'Boite de Vitesse à Variation Continue',
            'clim' => 'Climatisation',
            'demi' => 'Demi Arbre de Roue',
            'direction' => 'Direction',
            'electricite' => 'Electricité et Electronique',
            'freinage' => 'Freinage',
            'freinage_elec' => 'Freinage Electromagnétique',
            'freinage_hyd' => 'Freinage Hydraulique',
            'frein' => 'Freinage Pneumatique',
            'hydraulique' => 'Hydraulique',
            'moteur_diesel' => 'Moteur Diesel',
            'moteur_elec' => 'Moteur Electrique',
            'moteur_essence' => 'Moteur Essence',
            'moteur' => 'Moteur Thermique',
            'multiplexage' => 'Réseaux de Communication',
            'pont' => 'Pont',
            'pneumatique' => 'Pneumatique',
            'reducteur' => 'Reducteur',
            'suspension' => 'Suspension',
            'suspension_lame' => 'Suspension à Lame',
            'suspension_ressort' => 'Suspension Ressort',
            'suspension_pneumatique' => 'Suspension Pneumatique',
            'transversale' => 'Transversale',
        );
    }

    /**
     * Même arrondi que bilanTestNotes::calcPct().
     * @return int|null
     */
    function tdPct($score, $total)
    {
        $total = (float) $total;
        if ($total <= 0) {
            return null;
        }
        return (int) ceil(((float) $score * 100) / $total);
    }

    /** Clé de tri insensible aux accents. */
    function tdSortKey($label)
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
        return strtolower($ascii !== false ? $ascii : $label);
    }

    /**
     * Le profil connecté peut-il consulter ce technicien ? Même périmètre que bilanTestNotes.
     * @param array $session
     * @param mixed $user doc Mongo (BSONDocument)
     * @return bool
     */
    function tdCanView($session, $user)
    {
        $profile = isset($session['profile']) ? $session['profile'] : '';
        if (in_array($profile, array('Super Admin', 'Directeur Groupe'), true)) {
            return true;
        }
        if ($profile === 'Manager') {
            return isset($user['manager'], $session['id']) && (string) $user['manager'] === (string) $session['id'];
        }
        $sameSubsidiary = isset($user['subsidiary'], $session['subsidiary'])
            && (string) $user['subsidiary'] === (string) $session['subsidiary'];
        if ($profile === 'Directeur Général') {
            return $sameSubsidiary;
        }
        if (in_array($profile, array('Admin', 'Ressource Humaine', 'Directeur Pièce et Service', 'Directeur des Opérations'), true)) {
            if (!$sameSubsidiary) {
                return false;
            }
            $dept = isset($session['department']) ? (string) $session['department'] : '';
            if ($dept !== 'Equipment & Motors') {
                return isset($user['department']) && (string) $user['department'] === $dept;
            }
            return true;
        }
        return false;
    }

    /**
     * @param MongoDB\Database $academy
     * @param MongoDB\BSON\ObjectId $userId
     * @param string $userLevel niveau du technicien (Junior/Senior/Expert)
     * @return array {levels, specs, global, weak}
     *   specs  : [spécialité => [niveau => ['fac'=>int|null,'tech'=>int|null,'mgr'=>int|null]]]
     *   global : [niveau => ['fac'=>..,'tech'=>..,'mgr'=>..]]
     *   weak   : liste [spec, level, kind, pct] des 3 notes les plus basses sous le seuil
     */
    function tdLoadTechnicianDetail($academy, $userId, $userLevel)
    {
        $results = $academy->results;
        $allLevels = tdLevels();
        $empty = array('fac' => null, 'tech' => null, 'mgr' => null);

        $cells = array();   // spec => level => kind => pct
        $hasLevel = array();

        $cursor = $results->find(
            array('user' => $userId, 'active' => true, 'speciality' => array('$exists' => true), 'level' => array('$in' => $allLevels)),
            array('sort' => array('_id' => -1), 'projection' => array('speciality' => 1, 'level' => 1, 'type' => 1, 'typeR' => 1, 'score' => 1, 'total' => 1))
        );
        foreach ($cursor as $doc) {
            $type = isset($doc['type']) ? (string) $doc['type'] : '';
            $typeR = isset($doc['typeR']) ? (string) $doc['typeR'] : '';
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
            $level = (string) $doc['level'];
            if ($spec === '' || isset($cells[$spec][$level][$kind])) {
                continue; // plus récent déjà retenu
            }
            $pct = tdPct(isset($doc['score']) ? $doc['score'] : 0, isset($doc['total']) ? $doc['total'] : 0);
            if ($pct === null) {
                continue;
            }
            $cells[$spec][$level][$kind] = $pct;
            $hasLevel[$level][$kind] = true;
        }

        // Nouveau flux Déclaratif technicien : scores par spécialité embarqués dans le résultat global.
        $shortMap = tdDeclaShortMap();
        foreach ($allLevels as $level) {
            if (!empty($hasLevel[$level]['tech'])) {
                continue;
            }
            $global = $results->findOne(
                array('user' => $userId, 'level' => $level, 'type' => 'Declaratif', 'typeR' => 'Techniciens', 'active' => true, 'specialtyScores' => array('$exists' => true)),
                array('sort' => array('_id' => -1))
            );
            if (!$global) {
                continue;
            }
            foreach ($global['specialtyScores'] as $short => $sc) {
                if (!isset($shortMap[$short])) {
                    continue;
                }
                $pct = tdPct(isset($sc['correct']) ? $sc['correct'] : 0, isset($sc['total']) ? $sc['total'] : 0);
                if ($pct === null) {
                    continue;
                }
                $cells[$shortMap[$short]][$level]['tech'] = $pct;
                $hasLevel[$level]['tech'] = true;
            }
        }

        // Totaux par niveau (mêmes sources que bilanTestNotes)
        $globalTypes = array('fac' => array('Factuel', 'Technicien'), 'tech' => array('Declaratif', 'Techniciens'), 'mgr' => array('Declaratif', 'Managers'));
        $global = array();
        foreach ($allLevels as $level) {
            $global[$level] = $empty;
            foreach ($globalTypes as $kind => $t) {
                $doc = $results->findOne(
                    array('user' => $userId, 'level' => $level, 'type' => $t[0], 'typeR' => $t[1], 'active' => true),
                    array('sort' => array('_id' => -1), 'projection' => array('score' => 1, 'total' => 1))
                );
                if ($doc) {
                    $global[$level][$kind] = tdPct(isset($doc['score']) ? $doc['score'] : 0, isset($doc['total']) ? $doc['total'] : 0);
                    if ($global[$level][$kind] !== null) {
                        $hasLevel[$level][$kind] = true;
                    }
                }
            }
        }

        // Niveaux affichés : jusqu'au niveau du technicien, plus tout niveau ayant des notes.
        $userRank = array_search($userLevel, $allLevels, true);
        if ($userRank === false) {
            $userRank = 0;
        }
        $levels = array();
        foreach ($allLevels as $rank => $level) {
            if ($rank <= $userRank || !empty($hasLevel[$level])) {
                $levels[] = $level;
            }
        }

        // Tableau spécialité × niveau, trié alphabétiquement
        $specs = array();
        foreach ($cells as $spec => $byLevel) {
            foreach ($levels as $level) {
                $specs[$spec][$level] = isset($byLevel[$level]) ? array_merge($empty, $byLevel[$level]) : $empty;
            }
        }
        uksort($specs, function ($a, $b) {
            return strcmp(tdSortKey($a), tdSortKey($b));
        });

        // 3 notes les plus basses sous le seuil
        $weak = array();
        $levelRank = array_flip($allLevels);
        foreach ($specs as $spec => $byLevel) {
            foreach ($byLevel as $level => $kinds) {
                foreach ($kinds as $kind => $pct) {
                    if ($pct !== null && $pct < tdWeakThreshold()) {
                        $weak[] = array('spec' => $spec, 'level' => $level, 'kind' => $kind, 'pct' => $pct);
                    }
                }
            }
        }
        usort($weak, function ($a, $b) use ($levelRank) {
            if ($a['pct'] !== $b['pct']) {
                return $a['pct'] - $b['pct'];
            }
            return $levelRank[$a['level']] - $levelRank[$b['level']];
        });
        $weak = array_slice($weak, 0, 3);

        return array('levels' => $levels, 'specs' => $specs, 'global' => $global, 'weak' => $weak);
    }
}
