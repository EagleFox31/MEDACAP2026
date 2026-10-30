<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Importe un kit QCM offline finalise en base MEDACAP.
 *
 * Usage:
 *   php scripts/import_offline_qcm.php --file=exports/offline-qcm/bertoua/user_Junior_QCM.html
 *   php scripts/import_offline_qcm.php --file=... --dry-run
 */
require __DIR__ . '/includes/offline_qcm_lib.php';

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

$file = argValue($argv, 'file');
$dryRun = in_array('--dry-run', $argv, true);
$uri = argValue($argv, 'mongo-uri', medacapMongoUri('mongodb://10.68.0.7:27017'));

if (!$file || !is_file($file)) {
    fwrite(STDERR, "Usage: php scripts/import_offline_qcm.php --file=chemin/QCM.html\n");
    exit(1);
}

try {
    $pkg = offlineQcmLoadPackageFromFile($file);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

echo "MEDACAP — Import QCM offline\n";
echo "Fichier: {$file}\n";
echo "Technicien: " . ($pkg['meta']['username'] ?? '?') . ' | ' . ($pkg['meta']['level'] ?? '?') . "\n";
echo "Status: " . ($pkg['status'] ?? '?') . "\n\n";

try {
    offlineQcmValidatePackage($pkg);
} catch (Throwable $e) {
    fwrite(STDERR, "Validation: " . $e->getMessage() . "\n");
    exit(1);
}

$answered = 0;
if (!empty($pkg['answers']) && is_array($pkg['answers'])) {
    foreach ($pkg['answers'] as $v) {
        if ($v !== null && $v !== '') {
            $answered++;
        }
    }
    echo "Réponses Factuel : {$answered}/" . count($pkg['questions']) . "\n";
}

$answeredDecla = 0;
if (!empty($pkg['declaratifAnswers']) && is_array($pkg['declaratifAnswers'])) {
    foreach ($pkg['declaratifAnswers'] as $v) {
        if ($v !== null && $v !== '') {
            $answeredDecla++;
        }
    }
    echo "Réponses Déclaratif : {$answeredDecla}/" . count($pkg['declaratifQuestions']) . "\n";
}

if ($dryRun) {
    echo "dry-run OK — rien ecrit en base\n";
    exit(0);
}

try {
    $db = new OfflineQcmMongo($uri);
    $result = offlineQcmImportPackage($db, $pkg, 'cli-import');
} catch (Throwable $e) {
    fwrite(STDERR, "Import echoue: " . $e->getMessage() . "\n");
    error_log('[import_offline_qcm] ' . $e->getMessage());
    exit(1);
}

$pkg['status'] = 'imported';
$pkg['import']['importedAt'] = gmdate('c');
$pkg['import']['importedBy'] = 'cli-import';
if ($result['factuelScore'] !== null) {
    $pkg['import']['score'] = $result['factuelScore'] . '/' . $result['factuelTotal'];
}
if ($result['declaratifScore'] !== null) {
    $pkg['import']['declaratifScore'] = $result['declaratifScore'] . '/' . $result['declaratifTotal'];
}
offlineQcmWritePackageToFile($file, $pkg);

echo "\nImport reussi!\n";
echo "  " . $result['username'] . ' — ' . $result['level'] . "\n";
if ($result['factuelScore'] !== null) {
    echo '  Note Factuel : ' . $result['factuelScore'] . '/' . $result['factuelTotal'] . "\n";
}
if ($result['declaratifScore'] !== null) {
    echo '  Déclaratif : ' . $result['declaratifScore'] . '/' . $result['declaratifTotal'] . " tâches maîtrisées\n";
}
echo "  Fichier marque imported.\n";
