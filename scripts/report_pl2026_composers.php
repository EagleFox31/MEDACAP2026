<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Rapport console — candidats ayant composé une épreuve figée.
 *
 * Usage:
 *   php scripts/report_pl2026_composers.php --paper-id=6a82dbf45b3b2bb665098ec2
 *   php scripts/report_pl2026_composers.php --paper-id=6a82dafa726d3580f703ecc2
 */
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../views/measure/includes/paper_candidate_report_lib.php';

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

if (php_sapi_name() === 'cli' && realpath($argv[0] ?? '') === realpath(__FILE__)) {
    $paperIdStr = (string) argValue($argv, 'paper-id', '6a82dbf45b3b2bb665098ec2');
    $mongoUri = (string) argValue($argv, 'mongo-uri', medacapMongoUri('mongodb://10.68.0.7:27017'));

    if (!preg_match('/^[0-9a-f]{24}$/i', $paperIdStr)) {
        fwrite(STDERR, "ERROR: invalid paper-id\n");
        exit(1);
    }

    $client = new Client($mongoUri);
    $db = $client->academy;
    $paperId = new ObjectId($paperIdStr);
    $ctx = paperReportLoadPaperContext($db, $paperId);
    if (!$ctx) {
        fwrite(STDERR, "Paper not found\n");
        exit(1);
    }

    $candidates = paperReportBuildAllCandidates($db, $ctx);
    $stats = paperReportGetCompletionStats($candidates, (float) $ctx['threshold_pct']);
    $composers = array_values(array_filter($candidates, fn($c) => !empty($c['has_composed'])));

    $paperLabel = $ctx['paper_label'];
    $levelTotals = $ctx['level_totals'];

    echo "Épreuve : {$paperLabel}\n";
    echo "Paper ID : {$paperIdStr}\n";
    echo "Banque : Junior " . ($levelTotals['Junior'] ?? 0)
        . " | Senior " . ($levelTotals['Senior'] ?? 0)
        . " | Expert " . ($levelTotals['Expert'] ?? 0) . " questions\n";
    echo "Inscrits : {$stats['enrolled']} | Composés : {$stats['composed']}"
        . " | Complétion : {$stats['completion_pct']}%"
        . " | Seuil {$stats['threshold_pct']}% : " . ($stats['threshold_reached'] ? 'ATTEINT' : 'non') . "\n\n";

    $fmt = function ($v) {
        return $v === null ? '—' : (is_float($v) ? number_format($v, 2, ',', '') : (string) $v);
    };

    foreach ($composers as $c) {
        echo str_repeat('=', 72) . "\n";
        echo $c['name'] . ' (' . $c['username'] . ")\n";
        echo 'Statut : ' . $c['status'] . ' | Filiale : ' . $c['subsidiary'] . "\n";
        if ($c['finished_at'] !== '') {
            echo 'Fin : ' . $c['finished_at'] . "\n";
        }
        echo 'Temps écoulé : ' . ($c['time_elapsed'] ?: '—')
            . ' | Chrono restant : ' . ($c['time_remaining'] ?: '—')
            . ' | Vitesse : ' . ($c['avg_speed_per_min'] ?? '—') . " q/min\n";

        $g = $c['global'];
        echo "\nGLOBAL : " . $g['answered'] . '/' . $g['total'] . ' répondues'
            . ' | couverture ' . ($g['pct_completion'] ?? 0) . '%'
            . ' | note maîtrise ' . $fmt($g['note_maitrise']) . '/20'
            . ' | note complétude ' . $fmt($g['note_completude']) . "/20\n";

        echo 'Maîtrise globale : ' . $fmt($g['note_maitrise']) . '/20'
            . ' | Complétude globale : ' . $fmt($g['note_completude']) . '/20'
            . ' | Orientation : ' . ($c['orientation_done'] ? 'Oui' : 'Non') . "\n\n";

        if (!empty($c['macro_notes'])) {
            echo "MACRO-SPÉCIALITÉS\n";
            printf("%-28s %-12s %-14s %-18s\n", 'Macro', 'Répondues', 'Couverture', 'Maîtrise /20');
            foreach ($c['macro_notes'] as $macro => $mn) {
                printf(
                    "%-28s %-12s %-14s %-18s\n",
                    $macro,
                    $mn['answered'] . '/' . $mn['total'],
                    ($mn['pct_completion'] ?? 0) . '%',
                    $fmt($mn['note_maitrise'])
                );
            }
            echo "\n";
        }

        echo "SPÉCIALITÉS\n";
        printf("%-32s %-12s %-14s %-18s\n", 'Spécialité', 'Répondues', 'Couverture', 'Maîtrise /20');
        foreach ($c['specialty_notes'] as $spec => $sn) {
            printf(
                "%-32s %-12s %-14s %-18s\n",
                mb_substr($spec, 0, 32),
                $sn['answered'] . '/' . $sn['total'],
                ($sn['pct_completion'] ?? 0) . '%',
                $fmt($sn['note_maitrise'])
            );
        }
        echo "\n";
    }

    if (!$composers) {
        echo "Aucun candidat n'a composé pour le moment.\n";
    }
}
