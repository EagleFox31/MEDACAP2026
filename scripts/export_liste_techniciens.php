<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Export Excel — Liste des techniciens MEDACAP ACTIFS (hors candidats, hors CFR, hors désactivés).
 * 
 * Colonnes strictement demandées :
 *   - Nom
 *   - Prénom
 *   - Poste (formalisé en CamelCase / TitleCase)
 *   - Filiale (CFAO MOBILITY <PAYS>)
 *   - Agence
 *   - BU (CamelCase : Equipment, Motors, Equipment & Motors)
 * 
 * Règles métier :
 *   - Profil 'Technicien' uniquement
 *   - Actifs uniquement (active = true, profils désactivés exclus)
 *   - Exclusion des candidats (candidat = true OU isCandidat = true)
 *   - Exclusion des comptes supprimés (champ 'deleted' non vide)
 *   - Exclusion des comptes CFR (CFAO Academy / équipe centrale)
 *   - CAMEROON MOTORS INDUSTRY / INDUSTRIES -> CFAO MOBILITY CAMEROON
 *   - Remplacement de MOTORS par MOBILITY UNIQUEMENT dans les filiales (pas dans les BU)
 *   - Poste formalisé en CamelCase / TitleCase
 *   - Pas de colonnes supplémentaires (pas de matricule, pas d'identifiant)
 * 
 * Usage :
 *   php scripts/export_liste_techniciens.php
 *   php scripts/export_liste_techniciens.php --mongo-uri=mongodb://10.68.0.7:27017
 */

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/mongo_compat.php';

use MongoDB\Client;
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

    // Exclusion CFR
    if (strtoupper($sub) === 'CFR') {
        return null;
    }

    // Renommage explicite CAMEROON MOTORS INDUSTRY / INDUSTRIES
    if (stripos($sub, 'CAMEROON MOTORS') !== false) {
        return 'CFAO MOBILITY CAMEROON';
    }

    // Cas filiale vide : déduction par pays ou agence Cameroun
    if ($sub === '') {
        $cntryLower = strtolower($cntry);
        $agLower = mb_strtolower($ag, 'UTF-8');
        $cameroonAgencies = array('yaoundé', 'yaounde', 'douala', 'bertoua', 'garoua', 'bafoussam', 'ngaoundere', 'ngaoundéré');
        if ($cntryLower === 'cameroun' || $cntryLower === 'cameroon' || in_array($agLower, $cameroonAgencies, true)) {
            return 'CFAO MOBILITY CAMEROON';
        }
        return '';
    }

    // Remplacement MOTORS par MOBILITY dans les filiales
    return str_ireplace('MOTORS', 'MOBILITY', $sub);
}

function cleanBU($buRaw)
{
    $bu = trim((string) $buRaw);
    if ($bu === '') {
        return '';
    }
    // Uniformisation CamelCase / TitleCase sans remplacer par Mobility
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

    // Normaliser apostrophes
    $role = str_replace(array('’', '`'), "'", $role);

    // Mots ou sigles à conserver en majuscules
    $acronyms = array(
        'PL' => 'PL',
        'SAV' => 'SAV',
        'GE' => 'GE',
        'EL' => 'EL',
        'GPE' => 'GPE',
        'VUI' => 'VUI',
        'SOSUCAM' => 'SOSUCAM',
    );

    // Mots de liaison en minuscules
    $lowercaseWords = array('de', 'du', 'des', 'en', 'et', 'au', 'aux', 'pour');

    $parts = preg_split('/([\s\-\/\(\)]+)/u', $role, -1, PREG_SPLIT_DELIM_CAPTURE);
    $result = array();
    $isFirstWord = true;

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        // Délimiteur
        if (preg_match('/^[\s\-\/\(\)]+$/u', $part)) {
            $result[] = $part;
            continue;
        }

        // Apostrophe d' / l'
        if (stripos($part, "d'") === 0 || stripos($part, "l'") === 0) {
            $prefix = mb_strtolower(mb_substr($part, 0, 2, 'UTF-8'), 'UTF-8');
            $rest = mb_substr($part, 2, null, 'UTF-8');
            $restUpper = strtoupper($rest);
            if (isset($acronyms[$restUpper])) {
                $restFormatted = $acronyms[$restUpper];
            } else {
                $restFormatted = mb_convert_case($rest, MB_CASE_TITLE, 'UTF-8');
            }
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

    // Harmonisations spécifiques et accents
    $replacements = array(
        "Chef D'equipe" => "Chef d'Équipe",
        "Chef D'équipe" => "Chef d'Équipe",
        "Chef d'equipe" => "Chef d'Équipe",
        "Chef D'atelier" => "Chef d'Atelier",
        "Chef d'ateilier" => "Chef d'Atelier",
        "Gestionnaire D'atelier" => "Gestionnaire d'Atelier",
        "Electricien" => "Électricien",
        "Mecanicien" => "Mécanicien",
        "Mecanicienne" => "Mécanicienne",
        "Electromecanicien" => "Électromécanicien",
        "Electromécanicien" => "Électromécanicien",
        "Controleur" => "Contrôleur",
        "Specialiste" => "Spécialiste",
        "Mecanique" => "Mécanique",
        "Itinerant" => "Itinérant",
        "Confirme" => "Confirmé",
    );
    foreach ($replacements as $from => $to) {
        $out = str_ireplace($from, $to, $out);
    }

    return $out;
}

$uri = argValue($argv, 'mongo-uri', medacapMongoUri('mongodb://10.68.0.7:27017'));
$dateStr = date('Y-m-d');
$outPath = __DIR__ . '/../exports/liste_techniciens_medacap_' . $dateStr . '.xlsx';
$outLatest = __DIR__ . '/../exports/liste_techniciens_medacap.xlsx';
$outActifs = __DIR__ . '/../exports/liste_techniciens_medacap_actifs.xlsx';

echo "MEDACAP — Export liste des techniciens actifs\n";
echo "Mongo : {$uri}\n";

try {
    $db = (new Client($uri))->academy;
    $usersCol = $db->users;
} catch (Throwable $e) {
    fwrite(STDERR, "Connexion Mongo impossible: " . $e->getMessage() . "\n");
    exit(1);
}

// Filtre strict : profil Technicien, ACTIF UNIQUEMENT, ni candidat, ni isCandidat, non supprimé, pas de loadtest
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

$rows = array();
$totalScanned = 0;
$totalExcludedCfr = 0;
$totalExcludedDemo = 0;

foreach ($cursor as $u) {
    $totalScanned++;
    $uname = (string) ($u['username'] ?? '');

    // Exclure comptes démo
    if (stripos($uname, 'demo_') === 0 || stripos($uname, 'test_demo') !== false) {
        $totalExcludedDemo++;
        continue;
    }

    $subRaw = (string) ($u['subsidiary'] ?? '');
    $agRaw = (string) ($u['agency'] ?? '');
    $cntryRaw = (string) ($u['country'] ?? '');

    $filiale = cleanSubsidiary($subRaw, $agRaw, $cntryRaw);
    if ($filiale === null) {
        // Compte CFR exclu
        $totalExcludedCfr++;
        continue;
    }

    $nom = trim((string) ($u['lastName'] ?? ''));
    $prenom = trim((string) ($u['firstName'] ?? ''));
    $poste = formatRoleCamelCase($u['role'] ?? '');
    $agence = trim($agRaw);
    $bu = cleanBU($u['department'] ?? '');

    $rows[] = array(
        'nom' => $nom,
        'prenom' => $prenom,
        'poste' => $poste,
        'filiale' => $filiale,
        'agence' => $agence,
        'bu' => $bu,
    );
}

// Tri : Filiale ASC, Agence ASC, Nom ASC, Prénom ASC
usort($rows, function ($a, $b) {
    $cmpSub = strcasecmp($a['filiale'], $b['filiale']);
    if ($cmpSub !== 0) {
        return $cmpSub;
    }
    $cmpAg = strcasecmp($a['agence'], $b['agence']);
    if ($cmpAg !== 0) {
        return $cmpAg;
    }
    $cmpNom = strcasecmp($a['nom'], $b['nom']);
    if ($cmpNom !== 0) {
        return $cmpNom;
    }
    return strcasecmp($a['prenom'], $b['prenom']);
});

$totalRows = count($rows);
echo "Techniciens actifs retenus : {$totalRows} (Exclus: {$totalExcludedCfr} CFR, {$totalExcludedDemo} démo)\n";

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Techniciens');

// Colonnes strictement demandées (pas de matricule, pas d'identifiant)
$headers = array(
    'A1' => 'Nom',
    'B1' => 'Prénom',
    'C1' => 'Poste',
    'D1' => 'Filiale',
    'E1' => 'Agence',
    'F1' => 'BU',
);

foreach ($headers as $cell => $title) {
    $sheet->setCellValue($cell, $title);
}

// Style de la ligne d'en-tête (CFAO Navy #24235C)
$sheet->getStyle('A1:F1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('24235C');
$sheet->getStyle('A1:F1')->getAlignment()
    ->setVertical(Alignment::VERTICAL_CENTER)
    ->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getRowDimension(1)->setRowHeight(26);

$rowNum = 2;
foreach ($rows as $item) {
    $sheet->setCellValue("A{$rowNum}", $item['nom']);
    $sheet->setCellValue("B{$rowNum}", $item['prenom']);
    $sheet->setCellValue("C{$rowNum}", $item['poste']);
    $sheet->setCellValue("D{$rowNum}", $item['filiale']);
    $sheet->setCellValue("E{$rowNum}", $item['agence']);
    $sheet->setCellValue("F{$rowNum}", $item['bu']);

    // Zèbre alterné (#F8F9FA)
    if ($rowNum % 2 === 0) {
        $sheet->getStyle("A{$rowNum}:F{$rowNum}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
    }

    $sheet->getRowDimension($rowNum)->setRowHeight(20);
    $rowNum++;
}

$lastRow = $rowNum - 1;

// Bordures fines
$borderStyle = array(
    'borders' => array(
        'allBorders' => array(
            'borderStyle' => Border::BORDER_THIN,
            'color' => array('rgb' => 'D5D8DC'),
        ),
    ),
);
$sheet->getStyle("A1:F{$lastRow}")->applyFromArray($borderStyle);

// Auto-filtre sur la ligne d'en-tête
$sheet->setAutoFilter("A1:F{$lastRow}");

// Gel de la ligne 1
$sheet->freezePane('A2');

// Ajustement automatique des largeurs de colonnes
foreach (range('A', 'F') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Sauvegarde du fichier Excel avec gestion du verrouillage Windows
if (!is_dir(dirname($outPath))) {
    mkdir(dirname($outPath), 0755, true);
}

$writer = new Xlsx($spreadsheet);
$savedPaths = array();

// Fichier dédié sans risque de lock
$writer->save($outActifs);
$savedPaths[] = $outActifs;

// Fichier propre
$outClean = __DIR__ . '/../exports/liste_techniciens_medacap_propre.xlsx';
try {
    @$writer->save($outClean);
    $savedPaths[] = $outClean;
} catch (Throwable $e) {}

// Tenter d'écraser le fichier du jour
try {
    @$writer->save($outPath);
    $savedPaths[] = $outPath;
} catch (Throwable $e) {}

// Tenter de copier vers le nom stable
try {
    @copy($outActifs, $outLatest);
    $savedPaths[] = $outLatest;
} catch (Throwable $e) {}

echo "Export terminé avec succès !\n";
foreach (array_unique($savedPaths) as $p) {
    echo "  -> {$p}\n";
}
