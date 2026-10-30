<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Construit le socle offline MEDACAP : un fichier unique contenant toute la
 * banque de questions et tous les schemas, dedupliques.
 *
 * Le socle est envoye UNE fois puis reutilise. Il ne contient aucune donnee
 * nominative et aucune bonne reponse — la correction reste au serveur.
 *
 * Usage:
 *   php scripts/build_offline_socle.php
 *   php scripts/build_offline_socle.php --out=exports/socle --quality=90
 *   php scripts/build_offline_socle.php --stats-only
 *
 * @see scripts/includes/offline_socle_lib.php
 */
require __DIR__ . '/includes/offline_socle_lib.php';

function socleArg(array $argv, $name, $default = null)
{
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

$outDir = socleArg($argv, 'out', __DIR__ . '/../exports/socle');
$uri = socleArg($argv, 'mongo-uri', medacapMongoUri('mongodb://10.68.0.7:27017'));
$statsOnly = in_array('--stats-only', $argv, true);

echo "MEDACAP — Construction du socle offline\n";
echo "Mongo   : {$uri}\n";
echo "Images  : WebP q=" . OFFLINE_SOCLE_IMG_QUALITY . ", largeur max " . OFFLINE_SOCLE_IMG_WIDTH . "px\n\n";

if (!function_exists('imagewebp')) {
    fwrite(STDERR, "GD sans support WebP — impossible de construire le socle.\n");
    exit(1);
}

try {
    $db = new OfflineQcmMongo($uri);
} catch (Throwable $e) {
    fwrite(STDERR, 'Connexion Mongo impossible: ' . $e->getMessage() . "\n");
    exit(1);
}

$started = microtime(true);
echo "Lecture de la banque";

$bank = offlineSocleBuildBank($db, array(
    'onProgress' => function ($n) {
        echo '.';
    },
));

echo "\n\n";

$stats = $bank['stats'];
$json = json_encode($bank, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
$imagesBytes = 0;
foreach ($bank['images'] as $b64) {
    $imagesBytes += strlen($b64);
}

printf("Questions actives  : %d\n", $stats['questionCount']);
printf("Specialites        : %d\n", count($bank['specialties']));
printf("Schemas distincts  : %d\n", $stats['imageCount']);
printf("  poids images     : %.2f Mo (base64)\n", $imagesBytes / 1048576);
printf("  poids texte      : %.2f Mo\n", (strlen($json) - $imagesBytes) / 1048576);
printf("SOCLE (donnees)    : %.2f Mo\n", strlen($json) / 1048576);
printf("Duree              : %.1fs\n", microtime(true) - $started);

if (!empty($stats['missingImages'])) {
    printf("\nATTENTION — %d image(s) referencee(s) mais illisible(s) sur disque :\n", count($stats['missingImages']));
    foreach (array_slice($stats['missingImages'], 0, 20) as $name) {
        echo '  - ' . $name . "\n";
    }
    if (count($stats['missingImages']) > 20) {
        echo '  ... et ' . (count($stats['missingImages']) - 20) . " autres\n";
    }
}

if ($statsOnly) {
    echo "\n(--stats-only : rien ecrit)\n";
    exit(0);
}

if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$payloadPath = rtrim($outDir, '/\\') . '/socle.medacap.json';
file_put_contents($payloadPath, $json);

echo "\nEcrit : {$payloadPath}\n";
printf("        %.2f Mo\n", filesize($payloadPath) / 1048576);
echo "\nEtape suivante : integration dans le player multi-modules.\n";
