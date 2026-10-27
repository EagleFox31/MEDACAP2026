<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Genere la « cle » d un technicien : identite + modules a composer, exprimes
 * en positions dans le socle. Aucun contenu de question ne circule.
 *
 * Usage:
 *   php scripts/make_offline_key.php --user="Toukam YONGA"
 *   php scripts/make_offline_key.php --agency=Bertoua --out=exports/socle/cles.txt
 *
 * Le socle doit avoir ete construit au prealable (build_offline_socle.php) :
 * les positions de questions n ont de sens que par rapport a lui.
 */
require __DIR__ . '/includes/offline_socle_lib.php';

use MongoDB\BSON\Regex;

function keyArg(array $argv, $name, $default = null)
{
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

$agency = keyArg($argv, 'agency');
$userFilter = keyArg($argv, 'user');
$soclePath = keyArg($argv, 'socle', __DIR__ . '/../exports/socle/socle.medacap.json');
$outPath = keyArg($argv, 'out');
$dryRun = in_array('--dry-run', $argv, true);
$uri = keyArg($argv, 'mongo-uri', medacapMongoUri('mongodb://10.68.0.7:27017'));

if (!$agency && !$userFilter) {
    fwrite(STDERR, "Preciser --agency= ou --user=\n");
    exit(1);
}
if (!is_file($soclePath)) {
    fwrite(STDERR, "Socle introuvable: {$soclePath}\nLancer d abord: php scripts/build_offline_socle.php\n");
    exit(1);
}

echo "MEDACAP — Generation des cles\n";
echo "Socle : {$soclePath}\n";

$bank = json_decode(file_get_contents($soclePath), true);
if (!is_array($bank) || ($bank['format'] ?? '') !== OFFLINE_SOCLE_FORMAT) {
    fwrite(STDERR, "Socle illisible ou format inattendu\n");
    exit(1);
}
$qIndex = offlineSocleQuestionIndex($bank);
printf("        %d questions indexees\n\n", count($qIndex));

try {
    $db = new OfflineQcmMongo($uri);
} catch (Throwable $e) {
    fwrite(STDERR, 'Connexion Mongo impossible: ' . $e->getMessage() . "\n");
    exit(1);
}

$filter = array('profile' => 'Technicien', 'active' => true);
if ($agency) {
    $filter['agency'] = new Regex('^' . preg_quote($agency, '/') . '$', 'i');
}
if ($userFilter) {
    $filter['username'] = new Regex(preg_quote($userFilter, '/'), 'i');
}

$users = iterator_to_array($db->getCollection('users')->find($filter, array(
    'sort' => array('lastName' => 1, 'firstName' => 1),
)));

if (empty($users)) {
    fwrite(STDERR, "Aucun technicien actif ne correspond\n");
    exit(1);
}

$lines = array();
$issued = 0;
$skipped = 0;

foreach ($users as $user) {
    $username = offlineQcmStr(isset($user['username']) ? $user['username'] : '');
    $userLevel = offlineQcmStr(isset($user['level']) ? $user['level'] : 'Junior');
    $modules = array();

    echo str_repeat('-', 64) . "\n";
    echo $username . ' — ' . offlineQcmStr(isset($user['agency']) ? $user['agency'] : '') . " (niveau max {$userLevel})\n";

    // Tout le chemin atteignable, pas seulement le niveau courant : hors ligne,
    // le technicien ne peut pas revenir chercher le niveau suivant.
    foreach (offlineQcmLevelsForUser($userLevel) as $level) {
        $test = $db->getCollection('tests')->findOne(array(
            'user' => $user['_id'],
            'type' => 'Factuel',
            'level' => $level,
            'active' => true,
        ));
        if (!$test) {
            echo "  [-] {$level} Factuel — aucun test actif\n";
            continue;
        }

        $done = $db->getCollection('results')->findOne(array(
            'user' => $user['_id'],
            'test' => $test['_id'],
            'type' => 'Factuel',
            'level' => $level,
            'active' => true,
        ));
        if ($done) {
            echo "  [-] {$level} Factuel — deja compose\n";
            continue;
        }

        $fetcher = new QuizDataFetcher($db);
        $userData = $fetcher->fetchUserData((string) $user['_id'], (string) $test['_id'], $level);
        if (!$userData) {
            echo "  [-] {$level} Factuel — donnees test incompletes\n";
            continue;
        }

        // Le tirage doit etre fige en base : le jeton de retour ne transporte
        // que des reponses, l exam persiste dit quelles questions ont ete posees.
        $gen = new QuizQuestionGenerator($db, $level, $userData);
        $drawn = $dryRun ? $gen->generateForExport() : $gen->generateAndPersist();
        if (empty($drawn)) {
            echo "  [-] {$level} Factuel — tirage vide\n";
            continue;
        }

        $positions = array();
        $orphans = 0;
        foreach ($drawn as $item) {
            $qid = (string) $item['question']['_id'];
            if (isset($qIndex[$qid])) {
                $positions[] = $qIndex[$qid];
            } else {
                $orphans++;
            }
        }
        if ($orphans > 0) {
            printf("  [!] %s Factuel — %d question(s) absente(s) du socle (socle perime ?)\n", $level, $orphans);
        }
        if (empty($positions)) {
            continue;
        }

        $timer = offlineQcmDefaultTimer($level);
        $modules[] = array(
            'type' => 'Factuel',
            'level' => $level,
            'testId' => (string) $test['_id'],
            'questions' => $positions,
            'seconds' => ($timer['hours'] * 3600) + ($timer['minutes'] * 60) + $timer['seconds'],
        );
        printf("  [+] %s Factuel — %d questions\n", $level, count($positions));
    }

    if (empty($modules)) {
        echo "  => aucune cle (rien a composer)\n";
        $skipped++;
        continue;
    }

    $key = offlineSocleBuildKey(array(
        'userId' => (string) $user['_id'],
        'username' => $username,
        'agency' => offlineQcmStr(isset($user['agency']) ? $user['agency'] : ''),
        'subsidiary' => offlineQcmStr(isset($user['subsidiary']) ? $user['subsidiary'] : ''),
    ), $modules);

    printf("  => cle de %d caracteres, %d module(s)\n", strlen($key), count($modules));
    $lines[] = '# ' . $username . ' — ' . count($modules) . ' module(s)';
    $lines[] = $key;
    $lines[] = '';
    $issued++;
}

echo "\n" . str_repeat('=', 64) . "\n";
printf("Techniciens: %d | Cles emises: %d | Sans objet: %d\n", count($users), $issued, $skipped);

if ($outPath && $issued > 0) {
    if (!is_dir(dirname($outPath))) {
        mkdir(dirname($outPath), 0755, true);
    }
    file_put_contents($outPath, implode("\n", $lines));
    echo "Ecrit : {$outPath}\n";
} elseif ($issued > 0) {
    echo "\n" . implode("\n", $lines) . "\n";
}

echo "\nNote : Declaratif absent tant que le moteur n est pas extrait de\n";
echo "views/measure/userQuizDeclaratif.php (cf. quiz_factuel_engine.php).\n";
