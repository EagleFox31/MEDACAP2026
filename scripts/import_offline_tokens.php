<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Importe les « jetons » rapportes par les techniciens.
 *
 * Un jeton fait ~100 caracteres : il ne transporte que des reponses. Les
 * questions posees sont relues depuis le tirage fige en base au moment de la
 * generation de la cle.
 *
 * Usage:
 *   php scripts/import_offline_tokens.php --file=retours.txt --dry-run
 *   php scripts/import_offline_tokens.php --file=retours.txt
 *   php scripts/import_offline_tokens.php --token=MJ1.AWY1H3C4...
 *
 * Le fichier accepte un jeton par ligne. Les lignes vides et celles commencant
 * par # sont ignorees — on peut donc coller directement un fil WhatsApp.
 */
require __DIR__ . '/includes/offline_socle_lib.php';

function tokArg(array $argv, $name, $default = null)
{
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

$file = tokArg($argv, 'file');
$single = tokArg($argv, 'token');
$dryRun = in_array('--dry-run', $argv, true);
$uri = tokArg($argv, 'mongo-uri', medacapMongoUri('mongodb://10.68.0.7:27017'));

if (!$file && !$single) {
    fwrite(STDERR, "Preciser --file=retours.txt ou --token=MJ1....\n");
    exit(1);
}

$raw = array();
if ($single) {
    $raw[] = $single;
}
if ($file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Fichier introuvable: {$file}\n");
        exit(1);
    }
    foreach (preg_split('/\R/', file_get_contents($file)) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        // Tolere le texte autour : on extrait le jeton ou qu il soit sur la ligne.
        if (preg_match('/MJ1\.[A-Za-z0-9_-]+/', $line, $m)) {
            $raw[] = $m[0];
        } else {
            $raw[] = $line;
        }
    }
}

echo "MEDACAP — Import des jetons offline\n";
echo 'Jetons lus : ' . count($raw) . "\n";
if ($dryRun) {
    echo "MODE dry-run : rien ne sera ecrit en base\n";
}
echo "\n";

try {
    $db = new OfflineQcmMongo($uri);
} catch (Throwable $e) {
    fwrite(STDERR, 'Connexion Mongo impossible: ' . $e->getMessage() . "\n");
    exit(1);
}

$ok = 0;
$failed = 0;
$receipts = array();
$seen = array();

foreach ($raw as $i => $token) {
    $n = $i + 1;
    $tok = offlineSocleParseToken($token);

    if ($tok === null) {
        printf("[%2d] ILLISIBLE — jeton tronque, abime, ou format inconnu\n", $n);
        $failed++;
        continue;
    }

    $sig = $tok['userId'] . '/' . $tok['testId'];
    if (isset($seen[$sig])) {
        printf("[%2d] DOUBLON — meme module deja traite ligne %d\n", $n, $seen[$sig]);
        $failed++;
        continue;
    }
    $seen[$sig] = $n;

    $answered = 0;
    foreach ($tok['answers'] as $a) {
        if ($a >= 1 && $a <= 4) {
            $answered++;
        }
    }

    if ($dryRun) {
        printf(
            "[%2d] OK (simule) — %s %s | %d/%d repondues | %s\n",
            $n,
            $tok['type'],
            $tok['level'],
            $answered,
            $tok['count'],
            gmdate('H:i:s', $tok['elapsedSeconds'])
        );
        $ok++;
        continue;
    }

    try {
        $res = offlineSocleImportToken($db, $tok, 'cli-token');
        printf(
            "[%2d] IMPORTE — %s | %s | score %d/%d | accuse %s\n",
            $n,
            $res['username'],
            $res['level'],
            $res['score'],
            $res['total'],
            $res['receipt']
        );
        $receipts[] = $res['username'] . ' : ' . $res['receipt'];
        $ok++;
    } catch (Throwable $e) {
        printf("[%2d] REFUSE — %s\n", $n, $e->getMessage());
        error_log('[import_offline_tokens] ' . $e->getMessage());
        $failed++;
    }
}

echo "\n" . str_repeat('=', 64) . "\n";
printf("Traites: %d | Refuses: %d\n", $ok, $failed);

if (!empty($receipts)) {
    echo "\nAccuses a renvoyer aux techniciens :\n";
    foreach ($receipts as $r) {
        echo '  ' . $r . "\n";
    }
    echo "\nLe technicien saisit son accuse dans le socle pour verrouiller le\n";
    echo "module et debloquer le niveau suivant hors ligne.\n";
}
