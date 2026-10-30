<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Export Excel — Liste des techniciens Equipment MEDACAP ACTIFS avec résultats globaux par niveau.
 * 
 * Périmètre :
 *   - Profil 'Technicien' actif
 *   - Ayant au moins Equipment dans le département (Equipment, Equipment & Motors)
 *   - Exclusion des candidats, comptes CFR, comptes démo, supprimés
 * 
 * Colonnes :
 *   - Nom
 *   - Prénom
 *   - Poste (CamelCase)
 *   - Filiale (CFAO MOBILITY <PAYS>)
 *   - Agence
 *   - BU (Equipment ou Equipment & Motors)
 *   - Junior (MIN(Factuel %, Déclaratif %) sur marques Equipment)
 *   - Senior (idem, grisé si niveau < Senior)
 *   - Expert (idem, grisé si niveau < Expert)
 * 
 * Usage :
 *   php scripts/export_techniciens_equipment_scores.php
 *   php scripts/export_techniciens_equipment_scores.php --mongo-uri=mongodb://10.68.0.7:27017
 */

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/mongo_compat.php';

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

function argValue(array $argv, $name, $default = null)
{
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

function cleanSubsidiary($subRaw, $agencyRaw, $countryRaw)
{
    $sub = trim((string) $subRaw);
    $ag = trim((string) $agencyRaw);
    $cntry = trim((string) $countryRaw);

    if (strtoupper($sub) === 'CFR') {
        return null;
    }
    if (stripos($sub, 'CAMEROON MOTORS') !== false) {
        return 'CFAO MOBILITY CAMEROON';
    }
    if ($sub === '') {
        $cntryLower = strtolower($cntry);
        $agLower = mb_strtolower($ag, 'UTF-8');
        $cameroonAgencies = array('yaoundé', 'yaounde', 'douala', 'bertoua', 'garoua', 'bafoussam', 'ngaoundere', 'ngaoundéré');
        if ($cntryLower === 'cameroun' || $cntryLower === 'cameroon' || in_array($agLower, $cameroonAgencies, true)) {
            return 'CFAO MOBILITY CAMEROON';
        }
        return '';
    }
    return str_ireplace('MOTORS', 'MOBILITY', $sub);
}

function cleanBU($buRaw)
{
    $bu = trim((string) $buRaw);
    if ($bu === '') {
        return '';
    }
    if (stripos($bu, 'equipment') !== false && stripos($bu, 'motors') !== false) {
        return 'Equipment & Motors';
    }
    if (strcasecmp($bu, 'equipment') === 0) {
        return 'Equipment';
    }
    if (strcasecmp($bu, 'motors') === 0) {
        return 'Motors';
    }
    return ucwords(strtolower($bu));
}

function formatRoleCamelCase($roleRaw)
{
    $role = trim((string) $roleRaw);
    if ($role === '') {
        return '';
    }
    $role = str_replace(array('’', '`'), "'", $role);
    $acronyms = array(
        'PL' => 'PL', 'SAV' => 'SAV', 'GE' => 'GE', 'EL' => 'EL',
        'GPE' => 'GPE', 'VUI' => 'VUI', 'SOSUCAM' => 'SOSUCAM',
    );
    $lowercaseWords = array('de', 'du', 'des', 'en', 'et', 'au', 'aux', 'pour');
    $parts = preg_split('/([\s\-\/\(\)]+)/u', $role, -1, PREG_SPLIT_DELIM_CAPTURE);
    $result = array();
    $isFirstWord = true;

    foreach ($parts as $part) {
        if ($part === '') continue;
        if (preg_match('/^[\s\-\/\(\)]+$/u', $part)) {
            $result[] = $part;
            continue;
        }
        if (stripos($part, "d'") === 0 || stripos($part, "l'") === 0) {
            $prefix = mb_strtolower(mb_substr($part, 0, 2, 'UTF-8'), 'UTF-8');
            $rest = mb_substr($part, 2, null, 'UTF-8');
            $restUpper = strtoupper($rest);
            $restFormatted = isset($acronyms[$restUpper]) ? $acronyms[$restUpper] : mb_convert_case($rest, MB_CASE_TITLE, 'UTF-8');
            $result[] = $prefix . $restFormatted;
            $isFirstWord = false;
            continue;
        }
        $upper = strtoupper($part);
        if (isset($acronyms[$upper])) {
            $result[] = $acronyms[$upper];
            $isFirstWord = false;
            continue;
        }
        $lower = mb_strtolower($part, 'UTF-8');
        if (!$isFirstWord && in_array($lower, $lowercaseWords, true)) {
            $result[] = $lower;
            continue;
        }
        $formatted = mb_convert_case($part, MB_CASE_TITLE, 'UTF-8');
        $result[] = $formatted;
        $isFirstWord = false;
    }

    $out = implode('', $result);
    $replacements = array(
        "Chef D'equipe" => "Chef d'Équipe", "Chef D'équipe" => "Chef d'Équipe", "Chef d'equipe" => "Chef d'Équipe",
        "Chef D'atelier" => "Chef d'Atelier", "Chef d'ateilier" => "Chef d'Atelier", "Gestionnaire D'atelier" => "Gestionnaire d'Atelier",
        "Electricien" => "Électricien", "Mecanicien" => "Mécanicien", "Mecanicienne" => "Mécanicienne",
        "Electromecanicien" => "Électromécanicien", "Electromécanicien" => "Électromécanicien",
        "Controleur" => "Contrôleur", "Specialiste" => "Spécialiste", "Mecanique" => "Mécanique",
        "Itinerant" => "Itinérant", "Confirme" => "Confirmé",
    );
    foreach ($replacements as $from => $to) {
        $out = str_ireplace($from, $to, $out);
    }
    return $out;
}

// Spécialités associées à chaque marque Equipment
$brandSpecialitiesMap = array(
    'KING LONG' => array(
        'Arbre de Transmission', 'Assistance à la Conduite', 'Boite de Transfert', 'Boite de Vitesse',
        'Boite de Vitesse Mécanique', 'Climatisation', 'Demi Arbre de Roue', 'Direction',
        'Electricité et Electronique', 'Freinage', 'Freinage Pneumatique', 'Moteur Diesel',
        'Moteur Thermique', 'Pneumatique', 'Pont', 'Reducteur', 'Suspension', 'Suspension à Lame', 'Transversale'
    ),
    'FUSO' => array(
        'Arbre de Transmission', 'Assistance à la Conduite', 'Boite de Vitesse', 'Boite de Vitesse Mécanique',
        'Demi Arbre de Roue', 'Direction', 'Electricité et Electronique', 'Freinage',
        'Freinage Pneumatique', 'Moteur Diesel', 'Moteur Thermique', 'Pneumatique', 'Pont',
        'Reducteur', 'Suspension', 'Suspension à Lame', 'Transversale'
    ),
    'HINO' => array(
        'Arbre de Transmission', 'Assistance à la Conduite', 'Boite de Vitesse', 'Boite de Vitesse Mécanique',
        'Demi Arbre de Roue', 'Direction', 'Electricité et Electronique', 'Freinage',
        'Freinage Pneumatique', 'Moteur Diesel', 'Moteur Thermique', 'Pneumatique', 'Pont',
        'Reducteur', 'Suspension', 'Suspension à Lame', 'Transversale'
    ),
    'MERCEDES TRUCK' => array(
        'Arbre de Transmission', 'Assistance à la Conduite', 'Boite de Transfert', 'Boite de Vitesse',
        'Boite de Vitesse Mécanique', 'Climatisation', 'Demi Arbre de Roue', 'Direction',
        'Electricité et Electronique', 'Freinage', 'Freinage Pneumatique', 'Hydraulique',
        'Moteur Diesel', 'Moteur Thermique', 'Pneumatique', 'Pont', 'Reducteur', 'Suspension',
        'Suspension à Lame', 'Suspension Pneumatique', 'Transversale'
    ),
    'RENAULT TRUCK' => array(
        'Arbre de Transmission', 'Assistance à la Conduite', 'Boite de Transfert', 'Boite de Vitesse',
        'Boite de Vitesse Mécanique', 'Climatisation', 'Demi Arbre de Roue', 'Direction',
        'Electricité et Electronique', 'Freinage', 'Freinage Pneumatique', 'Hydraulique',
        'Moteur Diesel', 'Moteur Thermique', 'Pneumatique', 'Pont', 'Reducteur', 'Suspension',
        'Suspension à Lame', 'Suspension Pneumatique', 'Transversale'
    ),
    'SINOTRUK' => array(
        'Arbre de Transmission', 'Assistance à la Conduite', 'Boite de Transfert', 'Boite de Vitesse',
        'Boite de Vitesse Mécanique', 'Climatisation', 'Demi Arbre de Roue', 'Direction',
        'Electricité et Electronique', 'Freinage', 'Freinage Pneumatique', 'Hydraulique',
        'Moteur Diesel', 'Moteur Thermique', 'Pneumatique', 'Pont', 'Reducteur', 'Suspension',
        'Suspension à Lame', 'Transversale'
    ),
    'SINOTRUCK' => array(
        'Arbre de Transmission', 'Assistance à la Conduite', 'Boite de Transfert', 'Boite de Vitesse',
        'Boite de Vitesse Mécanique', 'Climatisation', 'Demi Arbre de Roue', 'Direction',
        'Electricité et Electronique', 'Freinage', 'Freinage Pneumatique', 'Hydraulique',
        'Moteur Diesel', 'Moteur Thermique', 'Pneumatique', 'Pont', 'Reducteur', 'Suspension',
        'Suspension à Lame', 'Transversale'
    ),
    'TOYOTA BT' => array(
        'Assistance à la Conduite', 'Boite de Vitesse', 'Boite de Vitesse Mécanique', 'Climatisation',
        'Direction', 'Electricité et Electronique', 'Freinage', 'Freinage Hydraulique',
        'Hydraulique', 'Moteur Electrique', 'Moteur Thermique', 'Pneumatique', 'Reducteur',
        'Suspension', 'Transversale'
    ),
    'Toyota BT' => array(
        'Assistance à la Conduite', 'Boite de Vitesse', 'Boite de Vitesse Mécanique', 'Climatisation',
        'Direction', 'Electricité et Electronique', 'Freinage', 'Freinage Hydraulique',
        'Hydraulique', 'Moteur Electrique', 'Moteur Thermique', 'Pneumatique', 'Reducteur',
        'Suspension', 'Transversale'
    ),
    'TOYOTA FORKLIFT' => array(
        'Assistance à la Conduite', 'Boite de Vitesse', 'Boite de Vitesse Mécanique', 'Boite de Vitesse Automatique',
        'Climatisation', 'Demi Arbre de Roue', 'Direction', 'Electricité et Electronique',
        'Freinage', 'Freinage Hydraulique', 'Hydraulique', 'Moteur Diesel', 'Moteur Electrique',
        'Moteur Essence', 'Moteur Thermique', 'Pneumatique', 'Pont', 'Reducteur', 'Suspension', 'Transversale'
    ),
    'Toyota Forklift' => array(
        'Assistance à la Conduite', 'Boite de Vitesse', 'Boite de Vitesse Mécanique', 'Boite de Vitesse Automatique',
        'Climatisation', 'Demi Arbre de Roue', 'Direction', 'Electricité et Electronique',
        'Freinage', 'Freinage Hydraulique', 'Hydraulique', 'Moteur Diesel', 'Moteur Electrique',
        'Moteur Essence', 'Moteur Thermique', 'Pneumatique', 'Pont', 'Reducteur', 'Suspension', 'Transversale'
    ),
    'TOYOTA FORFLIT' => array(
        'Assistance à la Conduite', 'Boite de Vitesse', 'Boite de Vitesse Mécanique', 'Boite de Vitesse Automatique',
        'Climatisation', 'Demi Arbre de Roue', 'Direction', 'Electricité et Electronique',
        'Freinage', 'Freinage Hydraulique', 'Hydraulique', 'Moteur Diesel', 'Moteur Electrique',
        'Moteur Essence', 'Moteur Thermique', 'Pneumatique', 'Pont', 'Reducteur', 'Suspension', 'Transversale'
    ),
    'JCB' => array(
        'Arbre de Transmission', 'Assistance à la Conduite', 'Boite de Transfert', 'Boite de Vitesse',
        'Boite de Vitesse Mécanique', 'Climatisation', 'Demi Arbre de Roue', 'Direction',
        'Electricité et Electronique', 'Freinage', 'Freinage Hydraulique', 'Hydraulique',
        'Moteur Diesel', 'Moteur Thermique', 'Pneumatique', 'Pont', 'Reducteur', 'Transversale'
    ),
    'LOVOL' => array(
        'Arbre de Transmission', 'Assistance à la Conduite', 'Boite de Transfert', 'Boite de Vitesse',
        'Boite de Vitesse Mécanique', 'Climatisation', 'Demi Arbre de Roue', 'Direction',
        'Electricité et Electronique', 'Freinage', 'Freinage Hydraulique', 'Hydraulique',
        'Moteur Diesel', 'Moteur Thermique', 'Pneumatique', 'Pont', 'Reducteur', 'Transversale'
    ),
);

$equipmentBrandNames = array_keys($brandSpecialitiesMap);

// Connexion MongoDB
$uri = argValue($argv, 'mongo-uri', medacapMongoUri('mongodb://10.68.0.7:27017'));
echo "MEDACAP — Export liste techniciens Equipment avec résultats MIN(Factuel, Déclaratif)\n";
echo "Mongo URI : {$uri}\n";

try {
    $db = (new Client($uri))->academy;
    $usersCol = $db->users;
    $resultsCol = $db->results;
} catch (Throwable $e) {
    fwrite(STDERR, "Connexion Mongo impossible: " . $e->getMessage() . "\n");
    exit(1);
}

// Filtre strict : profil Technicien actif, toutes BU confondues (Equipment, Motors, etc.)
$filter = array(
    'profile' => 'Technicien',
    'active' => true,
    '$and' => array(
        array(
            '$or' => array(
                array('candidat' => array('$exists' => false)),
                array('candidat' => false),
                array('candidat' => null),
                array('candidat' => ''),
                array('candidat' => 0),
            ),
        ),
        array(
            '$or' => array(
                array('isCandidat' => array('$exists' => false)),
                array('isCandidat' => false),
                array('isCandidat' => null),
                array('isCandidat' => ''),
                array('isCandidat' => 0),
            ),
        ),
        array(
            '$or' => array(
                array('deleted' => array('$exists' => false)),
                array('deleted' => null),
                array('deleted' => ''),
                array('deleted' => false),
            ),
        ),
        array('loadtestTag' => array('$exists' => false)),
    ),
);

$cursor = $usersCol->find($filter);

$techRows = array();
$levelRanks = array('Junior' => 1, 'Senior' => 2, 'Expert' => 3);

foreach ($cursor as $u) {
    $uname = (string) ($u['username'] ?? '');
    if (stripos($uname, 'demo_') === 0 || stripos($uname, 'test_demo') !== false) {
        continue;
    }

    $subRaw = (string) ($u['subsidiary'] ?? '');
    $agRaw = (string) ($u['agency'] ?? '');
    $cntryRaw = (string) ($u['country'] ?? '');

    $filiale = cleanSubsidiary($subRaw, $agRaw, $cntryRaw);
    if ($filiale === null) {
        continue;
    }

    $nom = trim((string) ($u['lastName'] ?? ''));
    $prenom = trim((string) ($u['firstName'] ?? ''));
    $poste = formatRoleCamelCase($u['role'] ?? '');
    $agence = trim($agRaw);
    $bu = cleanBU($u['department'] ?? '');

    // Niveau contractuel du technicien
    $rawLevel = trim((string) ($u['level'] ?? 'Junior'));
    $techLevel = isset($levelRanks[$rawLevel]) ? $rawLevel : 'Junior';
    $techRank = $levelRanks[$techLevel];

    $uid = $u['_id'];
    $isEquipmentDept = (stripos($bu, 'equipment') !== false);
    $isMotorsDept    = (stripos($bu, 'motors') !== false);
    $isMixedDept     = ($isEquipmentDept && $isMotorsDept);

    // Calcul pour chaque niveau (Junior, Senior, Expert)
    $levelScores = array();

    foreach (array('Junior', 'Senior', 'Expert') as $lvl) {
        $lvlRank = $levelRanks[$lvl];

        // Règle 1 : si le niveau est au-dessus du niveau du technicien -> GRISÉ
        if ($lvlRank > $techRank) {
            $levelScores[$lvl] = array(
                'status' => 'GREYED',
                'value' => '—',
                'score' => null,
            );
            continue;
        }

        // Règle 2 : niveau applicable -> calcul des notes
        $pctFac = null;
        $pctDecla = null;

        if (!$isMixedDept) {
            // Technicien purement Equipment OU purement Motors : note globale de son niveau
            $rf = $resultsCol->findOne(array(
                'user' => $uid,
                'level' => $lvl,
                'type' => 'Factuel',
                'typeR' => 'Technicien',
                'active' => true,
            ), array('sort' => array('numberTest' => -1, '_id' => -1)));
            $rtm = $resultsCol->findOne(array(
                'user' => $uid,
                'level' => $lvl,
                'type' => 'Declaratif',
                'typeR' => 'Technicien - Manager',
                'active' => true,
            ), array('sort' => array('numberTest' => -1, '_id' => -1)));

            if ($rf && !empty($rf['total'])) {
                $pctFac = round(($rf['score'] * 100) / $rf['total']);
            }
            if ($rtm && !empty($rtm['total'])) {
                $pctDecla = round(($rtm['score'] * 100) / $rtm['total']);
            }
        } else {
            // Technicien Equipment & Motors : filtrer sur les spécialités de ses marques Equipment
            $userBrandsLvl = array();
            $brandProp = 'brand' . $lvl;
            if (!empty($u[$brandProp])) {
                foreach ($u[$brandProp] as $b) {
                    $bTrim = trim((string) $b);
                    if ($bTrim !== '') $userBrandsLvl[] = $bTrim;
                }
            }
            // Retenir uniquement les marques Equipment du technicien
            $userEqBrands = array_intersect($userBrandsLvl, $equipmentBrandNames);
            if (empty($userEqBrands)) {
                // Fallback si vide à ce niveau : vérifier brandJunior
                if (!empty($u['brandJunior'])) {
                    foreach ($u['brandJunior'] as $b) {
                        $bTrim = trim((string) $b);
                        if ($bTrim !== '') $userBrandsLvl[] = $bTrim;
                    }
                    $userEqBrands = array_intersect($userBrandsLvl, $equipmentBrandNames);
                }
            }

            // Union des spécialités Equipment
            $targetSpecs = array();
            foreach ($userEqBrands as $b) {
                if (isset($brandSpecialitiesMap[$b])) {
                    $targetSpecs = array_merge($targetSpecs, $brandSpecialitiesMap[$b]);
                }
            }
            $targetSpecs = array_unique($targetSpecs);

            if (!empty($targetSpecs)) {
                // Factuel Equipment
                $facScore = 0; $facTotal = 0;
                $specResultsFac = $resultsCol->find(array(
                    'user' => $uid,
                    'level' => $lvl,
                    'type' => 'Factuel',
                    'speciality' => array('$in' => array_values($targetSpecs)),
                    'active' => true,
                ));
                foreach ($specResultsFac as $srf) {
                    $facScore += ($srf['score'] ?? 0);
                    $facTotal += ($srf['total'] ?? 0);
                }
                if ($facTotal > 0) {
                    $pctFac = round(($facScore * 100) / $facTotal);
                }

                // Déclaratif concordant Equipment
                $declaScore = 0; $declaTotal = 0;
                foreach ($targetSpecs as $spec) {
                    $rt = $resultsCol->findOne(array('user' => $uid, 'level' => $lvl, 'type' => 'Declaratif', 'typeR' => 'Technicien', 'speciality' => $spec, 'active' => true));
                    $rm = $resultsCol->findOne(array('user' => $uid, 'level' => $lvl, 'type' => 'Declaratif', 'typeR' => 'Manager', 'speciality' => $spec, 'active' => true));
                    if ($rt && $rm) {
                        $tAns = isset($rt['answers']) ? (is_array($rt['answers']) ? $rt['answers'] : iterator_to_array($rt['answers'])) : array();
                        $mAns = isset($rm['answers']) ? (is_array($rm['answers']) ? $rm['answers'] : iterator_to_array($rm['answers'])) : array();
                        $declaTotal += count($tAns);
                        foreach ($tAns as $idx => $ans) {
                            if ($ans === 'Oui' && isset($mAns[$idx]) && $mAns[$idx] === 'Oui') {
                                $declaScore++;
                            }
                        }
                    }
                }
                if ($declaTotal > 0) {
                    $pctDecla = round(($declaScore * 100) / $declaTotal);
                }
            }

            // Si pas de résultat sur spécialités Equipment pour ce profil mixte, fallback sur les tests globaux
            if ($pctFac === null || $pctDecla === null) {
                $rf = $resultsCol->findOne(array(
                    'user' => $uid,
                    'level' => $lvl,
                    'type' => 'Factuel',
                    'typeR' => 'Technicien',
                    'active' => true,
                ), array('sort' => array('numberTest' => -1, '_id' => -1)));
                $rtm = $resultsCol->findOne(array(
                    'user' => $uid,
                    'level' => $lvl,
                    'type' => 'Declaratif',
                    'typeR' => 'Technicien - Manager',
                    'active' => true,
                ), array('sort' => array('numberTest' => -1, '_id' => -1)));

                if ($rf && !empty($rf['total'])) {
                    $pctFac = round(($rf['score'] * 100) / $rf['total']);
                }
                if ($rtm && !empty($rtm['total'])) {
                    $pctDecla = round(($rtm['score'] * 100) / $rtm['total']);
                }
            }
        }

        // Si Factuel ET Déclaratif sont disponibles -> calcul du MIN
        if ($pctFac !== null && $pctDecla !== null) {
            $minVal = min($pctFac, $pctDecla);
            $levelScores[$lvl] = array(
                'status' => 'DONE',
                'value' => $minVal . '%',
                'score' => $minVal,
            );
        } else {
            // Test non composé ou évaluation manager non finalisée
            $levelScores[$lvl] = array(
                'status' => 'PENDING',
                'value' => '—',
                'score' => null,
            );
        }
    }

    $techRows[] = array(
        'nom' => $nom,
        'prenom' => $prenom,
        'poste' => $poste,
        'filiale' => $filiale,
        'agence' => $agence,
        'bu' => $bu,
        'level' => $techLevel,
        'scores' => $levelScores,
    );
}

// Tri : Filiale ASC, Agence ASC, Nom ASC, Prénom ASC
usort($techRows, function ($a, $b) {
    $cmpSub = strcasecmp($a['filiale'], $b['filiale']);
    if ($cmpSub !== 0) return $cmpSub;
    $cmpAg = strcasecmp($a['agence'], $b['agence']);
    if ($cmpAg !== 0) return $cmpAg;
    $cmpNom = strcasecmp($a['nom'], $b['nom']);
    if ($cmpNom !== 0) return $cmpNom;
    return strcasecmp($a['prenom'], $b['prenom']);
});

$totalCount = count($techRows);
echo "Total techniciens retenus : {$totalCount}\n";

function renderTechnicianWorksheet($sheet, $title, array $rows)
{
    $sheet->setTitle($title);

    // En-têtes des colonnes
    $headers = array(
        'A1' => 'Nom',
        'B1' => 'Prénom',
        'C1' => 'Poste',
        'D1' => 'Filiale',
        'E1' => 'Agence',
        'F1' => 'BU',
        'G1' => 'Niveau',
        'H1' => 'Junior',
        'I1' => 'Senior',
        'J1' => 'Expert',
    );

    foreach ($headers as $cell => $headerTitle) {
        $sheet->setCellValue($cell, $headerTitle);
    }

    // Style en-têtes de base A1:G1 (Bleu CFAO Navy #24235C)
    $sheet->getStyle('A1:G1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('24235C');
    $sheet->getStyle('A1:F1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle('G1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Style en-têtes niveaux H1:J1 (Bleu soutenu #1D4ED8)
    $sheet->getStyle('H1:J1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle('H1:J1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1D4ED8');
    $sheet->getStyle('H1:J1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->getRowDimension(1)->setRowHeight(28);

    $rowNum = 2;
    foreach ($rows as $item) {
        $sheet->setCellValue("A{$rowNum}", $item['nom']);
        $sheet->setCellValue("B{$rowNum}", $item['prenom']);
        $sheet->setCellValue("C{$rowNum}", $item['poste']);
        $sheet->setCellValue("D{$rowNum}", $item['filiale']);
        $sheet->setCellValue("E{$rowNum}", $item['agence']);
        $sheet->setCellValue("F{$rowNum}", $item['bu']);
        $sheet->setCellValue("G{$rowNum}", $item['level']);
        $sheet->getStyle("G{$rowNum}")->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Zèbre alterné A..G
        if ($rowNum % 2 === 0) {
            $sheet->getStyle("A{$rowNum}:G{$rowNum}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
        }

        // Colonnes H (Junior), I (Senior), J (Expert)
        $colMap = array('Junior' => 'H', 'Senior' => 'I', 'Expert' => 'J');
        foreach ($colMap as $lvl => $col) {
            $sc = $item['scores'][$lvl];
            $cellCoord = "{$col}{$rowNum}";
            $sheet->setCellValue($cellCoord, $sc['value']);

            // Alignement centré
            $sheet->getStyle($cellCoord)->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            if ($sc['status'] === 'GREYED') {
                // Fond grisé quand le niveau est au-dessus du niveau du technicien
                $sheet->getStyle($cellCoord)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
                $sheet->getStyle($cellCoord)->getFont()->getColor()->setRGB('94A3B8');
            } elseif ($sc['status'] === 'DONE') {
                // Score calculé : couleur adaptée
                $val = $sc['score'];
                $fontColor = '0F172A';
                if ($val >= 80) {
                    $fontColor = '15803D'; // Vert
                } elseif ($val >= 60) {
                    $fontColor = 'B45309'; // Ambre
                } else {
                    $fontColor = 'B91C1C'; // Rouge
                }
                $sheet->getStyle($cellCoord)->getFont()->setBold(true)->getColor()->setRGB($fontColor);
                if ($rowNum % 2 === 0) {
                    $sheet->getStyle($cellCoord)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
                }
            } else {
                // En attente : tiret gris
                $sheet->getStyle($cellCoord)->getFont()->getColor()->setRGB('94A3B8');
                if ($rowNum % 2 === 0) {
                    $sheet->getStyle($cellCoord)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
                }
            }
        }

        $sheet->getRowDimension($rowNum)->setRowHeight(21);
        $rowNum++;
    }

    $lastDataRow = max(2, $rowNum - 1);

    // Calcul des totaux par niveau et moyennes
    $totTech = count($rows);
    $jrCount = 0; $srCount = 0; $exCount = 0;
    $jrSum = 0; $jrScored = 0;
    $srSum = 0; $srScored = 0;
    $exSum = 0; $exScored = 0;

    foreach ($rows as $item) {
        $lvl = $item['level'];
        if ($lvl === 'Junior') $jrCount++;
        elseif ($lvl === 'Senior') $srCount++;
        elseif ($lvl === 'Expert') $exCount++;

        if (isset($item['scores']['Junior']) && $item['scores']['Junior']['status'] === 'DONE') {
            $jrScored++;
            $jrSum += $item['scores']['Junior']['score'];
        }
        if (isset($item['scores']['Senior']) && $item['scores']['Senior']['status'] === 'DONE') {
            $srScored++;
            $srSum += $item['scores']['Senior']['score'];
        }
        if (isset($item['scores']['Expert']) && $item['scores']['Expert']['status'] === 'DONE') {
            $exScored++;
            $exSum += $item['scores']['Expert']['score'];
        }
    }

    $avgJr = $jrScored > 0 ? round($jrSum / $jrScored) . '%' : '—';
    $avgSr = $srScored > 0 ? round($srSum / $srScored) . '%' : '—';
    $avgEx = $exScored > 0 ? round($exSum / $exScored) . '%' : '—';

    // Ligne 1 : Total techniciens par niveau
    $totRow = $rowNum;
    $sheet->setCellValue("A{$totRow}", 'Total techniciens');
    $sheet->setCellValue("G{$totRow}", $totTech);
    $sheet->setCellValue("H{$totRow}", $jrCount);
    $sheet->setCellValue("I{$totRow}", $srCount);
    $sheet->setCellValue("J{$totRow}", $exCount);

    $sheet->getStyle("A{$totRow}:J{$totRow}")->getFont()->setBold(true)->getColor()->setRGB('1E293B');
    $sheet->getStyle("A{$totRow}:J{$totRow}")->getFill()
        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
    $sheet->getStyle("A{$totRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle("G{$totRow}:J{$totRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension($totRow)->setRowHeight(24);

    // Ligne 2 : Moyenne des notes par niveau
    $avgRow = $rowNum + 1;
    $sheet->setCellValue("A{$avgRow}", 'Moyenne des notes');
    $sheet->setCellValue("G{$avgRow}", '—');
    $sheet->setCellValue("H{$avgRow}", $avgJr);
    $sheet->setCellValue("I{$avgRow}", $avgSr);
    $sheet->setCellValue("J{$avgRow}", $avgEx);

    $sheet->getStyle("A{$avgRow}:J{$avgRow}")->getFont()->setBold(true)->getColor()->setRGB('1E293B');
    $sheet->getStyle("A{$avgRow}:J{$avgRow}")->getFill()
        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
    $sheet->getStyle("A{$avgRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle("G{$avgRow}:J{$avgRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension($avgRow)->setRowHeight(24);

    $lastRow = $avgRow;

    // Bordures fines sur les données et totaux
    $borderStyle = array(
        'borders' => array(
            'allBorders' => array(
                'borderStyle' => Border::BORDER_THIN,
                'color' => array('rgb' => 'CBD5E1'),
            ),
        ),
    );
    $sheet->getStyle("A1:J{$lastRow}")->applyFromArray($borderStyle);

    // Bordure supérieure plus marquée pour le total
    $sheet->getStyle("A{$totRow}:J{$totRow}")->getBorders()->getTop()
        ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('94A3B8');
    // Bordure inférieure double pour la fin du tableau
    $sheet->getStyle("A{$avgRow}:J{$avgRow}")->getBorders()->getBottom()
        ->setBorderStyle(Border::BORDER_DOUBLE)->getColor()->setRGB('64748B');

    // Auto-filtre sur la ligne 1 uniquement pour la plage de données
    $sheet->setAutoFilter("A1:J{$lastDataRow}");

    // Gel des volets sous la ligne d'en-tête
    $sheet->freezePane('A2');

    // Ajustement automatique des largeurs de colonnes
    foreach (range('A', 'J') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

// Création du classeur Excel
$spreadsheet = new Spreadsheet();

// Feuille 1 : Vue globale
$globalSheet = $spreadsheet->getActiveSheet();
renderTechnicianWorksheet($globalSheet, 'Toutes les filiales', $techRows);

// Feuilles par filiale
$bySubsidiary = array();
foreach ($techRows as $r) {
    $subName = $r['filiale'];
    if (!isset($bySubsidiary[$subName])) {
        $bySubsidiary[$subName] = array();
    }
    $bySubsidiary[$subName][] = $r;
}
ksort($bySubsidiary);

foreach ($bySubsidiary as $subName => $subRows) {
    // Nettoyage et tronquage à 31 caractères max pour Excel
    $sheetTitle = mb_substr($subName, 0, 31, 'UTF-8');
    $subSheet = $spreadsheet->createSheet();
    renderTechnicianWorksheet($subSheet, $sheetTitle, $subRows);
}

// Activer la première feuille par défaut à l'ouverture
$spreadsheet->setActiveSheetIndex(0);

// Sauvegarde des fichiers
$dateStr = date('Y-m-d');
$outPathAll     = __DIR__ . '/../exports/liste_techniciens_par_filiale.xlsx';
$outPathEq      = __DIR__ . '/../exports/liste_techniciens_equipment_par_filiale.xlsx';
$outPathDated   = __DIR__ . '/../exports/liste_techniciens_toutes_filiales_' . $dateStr . '.xlsx';
$outPathMain    = __DIR__ . '/../exports/liste_techniciens_equipment_resultats.xlsx';

if (!is_dir(dirname($outPathAll))) {
    mkdir(dirname($outPathAll), 0755, true);
}

$writer = new Xlsx($spreadsheet);

$savedFiles = array();

// 1. Sauvegarde vers le fichier principal multi-filiales toutes BU
try {
    $writer->save($outPathAll);
    $savedFiles[] = $outPathAll;
    @copy($outPathAll, $outPathDated);
    $savedFiles[] = $outPathDated;
} catch (Throwable $e) {
    echo "Avertissement: Impossible d'écrire dans {$outPathAll}: " . $e->getMessage() . "\n";
}

// 2. Mise à jour de liste_techniciens_equipment_par_filiale.xlsx
try {
    $writer->save($outPathEq);
    $savedFiles[] = $outPathEq;
} catch (Throwable $e) {
    echo "Note: {$outPathEq} est actuellement ouvert dans Excel.\n";
}

// 3. Mise à jour du fichier legacy si non verrouillé
try {
    $writer->save($outPathMain);
    $savedFiles[] = $outPathMain;
} catch (Throwable $e) {
    echo "Note: {$outPathMain} est actuellement ouvert dans Excel (verrouillé par Windows).\n";
}

echo "Export terminé avec succès !\n";
echo "Feuilles créées :\n";
echo "  - Toutes les filiales (" . count($techRows) . " techniciens)\n";
foreach ($bySubsidiary as $subName => $subRows) {
    echo "  - {$subName} (" . count($subRows) . " techniciens)\n";
}
echo "Fichiers disponibles :\n";
foreach ($savedFiles as $f) {
    echo "  -> {$f}\n";
}


