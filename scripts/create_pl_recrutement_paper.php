<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Crée une épreuve figée « Recrutement Poids Lourds » — mécanique / maintenance PL (hors électricité).
 *
 * Usage:
 *   php scripts/create_pl_recrutement_paper.php
 *   php scripts/create_pl_recrutement_paper.php --dry-run
 */
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client;

const CP_SPEC = 'Carrosserie & Peinture';
require_once __DIR__ . '/../views/measure/includes/fixed_paper_builder.php';

$dryRun = in_array('--dry-run', $argv, true);
$uri = medacapMongoUri('mongodb://10.68.0.7:27017');
$mongo = new Client($uri);
$db = $mongo->academy;
$vehicles = $db->vehicles;
$quizzes = $db->quizzes;
$questions = $db->questions;
$papers = $db->papers;

const LABEL = 'Recrutement Poids Lourds Aout 2026';
const BRANDS = [
    'FUSO',
    'HINO',
    'KING LONG',
    'MERCEDES TRUCK',
    'RENAULT TRUCK',
    'SINOTRUK',
];
const EXCLUDE_SPECIALITIES = [
    'Electricité Poids Lourds',
    'Electricité et Electronique',
    'Réseaux de Communication',
    'Assistance à la Conduite',
    'Carrosserie & Peinture',
];
const QCM_TOTAL = 100;
const DURATION_HOUR = 1;
const DURATION_MINUTE = 0;

const ORIENTATION_IDS = [
    '6944559e72d68a2edb25d835',
    '694455bf72d68a2edb25d836',
    '694455ca72d68a2edb25d837',
    '694455e172d68a2edb25d838',
    '694455f972d68a2edb25d839',
    '6944558e72d68a2edb25d834',
    '6944563d72d68a2edb25d83a',
];

function otherSpecialitiesAndStock(): array
{
    $pool = poolBrandQuizzes(BRANDS, 'Senior');
    $filtered = [];
    $specSet = [];
    $bySpec = [];
    foreach ($pool as $q) {
        $sp = (string) ($q['speciality'] ?? '');
        if ($sp === '' || in_array($sp, EXCLUDE_SPECIALITIES, true)) {
            continue;
        }
        $filtered[] = $q;
        $specSet[$sp] = true;
        $bySpec[$sp] = ($bySpec[$sp] ?? 0) + quizStockSize($q, 'Senior');
    }
    ksort($bySpec);
    return [array_keys($specSet), $bySpec, $filtered];
}

echo "Mongo: $uri\n";
echo "Label: " . LABEL . "\n";
echo "Cible: " . QCM_TOTAL . " QCM — 100 % Senior (hors électricité, sans CP)\n";
echo "Marques: " . implode(', ', BRANDS) . "\n";

[$specialities, $bySpec, $pool] = otherSpecialitiesAndStock();
$totalStock = array_sum($bySpec);
echo "Spécialités retenues: " . count($specialities) . "\n\nStock Senior mobilisable:\n";
foreach ($bySpec as $sp => $n) {
    echo "  $sp: $n\n";
}
echo "  TOTAL: $totalStock\n";

if ($dryRun) {
    echo "\n[DRY-RUN] Aucune insertion.\n";
    exit($totalStock >= QCM_TOTAL ? 0 : 1);
}

if ($totalStock < QCM_TOTAL) {
    fwrite(STDERR, "Stock insuffisant: $totalStock / " . QCM_TOTAL . "\n");
    exit(1);
}

$orientationOids = [];
foreach (ORIENTATION_IDS as $id) {
    $oid = toOid($id);
    if ($oid) {
        $orientationOids[] = $oid;
    }
}

$ratio = ['Junior' => 0, 'Senior' => 100, 'Expert' => 0];

try {
    $paperId = buildFixedPaper(
        LABEL,
        BRANDS,
        QCM_TOTAL,
        $ratio,
        $orientationOids,
        false,
        DURATION_HOUR,
        DURATION_MINUTE,
        $specialities
    );
} catch (\Throwable $e) {
    fwrite(STDERR, "Erreur: " . $e->getMessage() . "\n");
    exit(1);
}

$paper = $papers->findOne(['_id' => $paperId]);
$counts = $paper['counts']['by_level'] ?? [];

echo "\n=== Épreuve créée ===\n";
echo "ID: " . (string) $paperId . "\n";
echo "Questions: " . count($paper['questions'] ?? []) . "\n";
echo "Junior: " . (int) ($counts['Junior'] ?? 0);
echo " | Senior: " . (int) ($counts['Senior'] ?? 0);
echo " | Expert: " . (int) ($counts['Expert'] ?? 0) . "\n";
echo "Lien candidat: compose_fixed.php?paper=" . (string) $paperId . "&id=<USER_ID>\n";
