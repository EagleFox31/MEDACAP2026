<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Export Excel — résultats paper recrutement (CLI).
 *
 * Usage:
 *   php scripts/export_paper_scores.php --paper-id=6a82dbf45b3b2bb665098ec2
 *   php scripts/export_paper_scores.php --paper-ids=6a82dbf45b3b2bb665098ec2,6a82dafa726d3580f703ecc2
 *   Options : --db=dump1105 (copie restaurée d'un dump) --out-dir=/var/www/html/output
 */

if (defined('MEDACAP_PAPER_SCORES_LOADED')) {
    return;
}
define('MEDACAP_PAPER_SCORES_LOADED', true);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../views/measure/includes/paper_report_excel_export.php';

use MongoDB\Client;
use MongoDB\BSON\ObjectId;

function argValue(array $argv, string $name, ?string $default = null): ?string
{
    $prefix = "--{$name}=";
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

// Alias legacy pour scripts qui incluaient ce fichier
function sanitizeSheetTitle(string $title): string
{
    return paperReportExcelSanitizeSheetTitle($title);
}

function styleHeaderRow($sheet, string $range): void
{
    paperReportExcelStyleHeaderRow($sheet, $range);
}

function autoSizeColumns($sheet, int $fromCol, int $toCol): void
{
    paperReportExcelAutoSizeColumns($sheet, $fromCol, $toCol);
}

if (php_sapi_name() !== 'cli' || realpath($argv[0] ?? '') !== realpath(__FILE__)) {
    return;
}

$paperIdStr = (string) argValue($argv, 'paper-id', '');
$paperIdsStr = (string) argValue($argv, 'paper-ids', '');
$mongoUri = (string) argValue($argv, 'mongo-uri', medacapMongoUri('mongodb://10.68.0.7:27017'));
$bundleLabel = argValue($argv, 'label');
$dbName = (string) argValue($argv, 'db', 'academy');
$outDir = argValue($argv, 'out-dir');

$client = new Client($mongoUri);
$db = $client->selectDatabase($dbName);

try {
    if ($paperIdsStr !== '') {
        $ids = array();
        foreach (explode(',', $paperIdsStr) as $part) {
            $part = trim($part);
            if (preg_match('/^[0-9a-f]{24}$/i', $part)) {
                $ids[] = new ObjectId($part);
            }
        }
        if ($ids === array()) {
            throw new RuntimeException('No valid --paper-ids');
        }
        $result = paperReportExportMultiExcel($db, $ids, $outDir, $bundleLabel);
        echo json_encode(array(
            'ok' => true,
            'mode' => 'multi',
            'papers' => $result['papers'],
            'total_candidates' => $result['total_candidates'],
            'file' => $result['path'],
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit(0);
    }

    if ($paperIdStr === '') {
        $paperIdStr = '6a452db87ae27d2cc30d8592';
    }
    if (!preg_match('/^[0-9a-f]{24}$/i', $paperIdStr)) {
        fwrite(STDERR, "ERROR: invalid --paper-id\n");
        exit(1);
    }

    $result = paperReportExportExcel($db, new ObjectId($paperIdStr), $outDir);
} catch (RuntimeException $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

$ctx = $result['ctx'];
echo json_encode(array(
    'ok' => true,
    'mode' => 'single',
    'paper' => $ctx['paper_label'],
    'candidates' => count($result['candidates']),
    'file' => $result['path'],
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
