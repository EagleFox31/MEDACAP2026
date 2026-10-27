<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Exporte les kits QCM offline pour les techniciens d'une agence.
 *
 * Usage (sur serveur prod ou avec MEDACAP_MONGO_URI):
 *   php scripts/export_offline_qcm.php --agency=Bertoua
 *   php scripts/export_offline_qcm.php --agency=Bertoua --user="Michel BAYEMI"
 *   php scripts/export_offline_qcm.php --agency=Bertoua --level=Junior --dry-run
 *
 * Sortie: exports/offline-qcm/{agency}/
 */
require __DIR__ . '/includes/offline_qcm_lib.php';

use MongoDB\BSON\ObjectId;

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

$agency = argValue($argv, 'agency', 'Bertoua');
$userFilter = argValue($argv, 'user');
$levelFilter = argValue($argv, 'level');
$dryRun = in_array('--dry-run', $argv, true);
$force = in_array('--force', $argv, true);
$uri = argValue($argv, 'mongo-uri', medacapMongoUri('mongodb://10.68.0.7:27017'));

$outRoot = __DIR__ . '/../exports/offline-qcm/' . offlineQcmSlug($agency);

echo "MEDACAP — Export QCM offline\n";
echo "Agence: {$agency}\n";
echo "Mongo: {$uri}\n";
echo "Sortie: {$outRoot}\n";
echo "Mode: 1 fichier HTML par technicien/niveau (questions integrees)\n\n";

try {
    $db = new OfflineQcmMongo($uri);
} catch (Throwable $e) {
    fwrite(STDERR, "Connexion Mongo impossible: " . $e->getMessage() . "\n");
    exit(1);
}

$filter = array(
    'profile' => 'Technicien',
    'active' => true,
    'agency' => new MongoDB\BSON\Regex('^' . preg_quote($agency, '/') . '$', 'i'),
);
if ($userFilter) {
    $filter['username'] = new MongoDB\BSON\Regex(preg_quote($userFilter, '/'), 'i');
}

$users = iterator_to_array($db->getCollection('users')->find($filter, array(
    'sort' => array('lastName' => 1, 'firstName' => 1),
)));

if (empty($users)) {
    fwrite(STDERR, "Aucun technicien actif trouve pour l agence {$agency}\n");
    exit(1);
}

if (!$dryRun && !is_dir($outRoot)) {
    mkdir($outRoot, 0755, true);
}

$exported = 0;
$skipped = 0;

foreach ($users as $user) {
    $username = offlineQcmStr($user['username'] ?? '');
    $userLevel = offlineQcmStr($user['level'] ?? 'Junior');
    $levels = $levelFilter ? array($levelFilter) : offlineQcmLevelsForUser($userLevel);
    $userDir = $outRoot . '/' . offlineQcmSlug($username);

    echo str_repeat('-', 60) . "\n";
    echo $username . ' (' . offlineQcmStr($user['agency'] ?? '') . ', niveau max ' . $userLevel . ")\n";

    foreach ($levels as $level) {
        $allocFactuel = $db->getCollection('allocations')->findOne(array(
            'user' => $user['_id'],
            'type' => 'Factuel',
            'level' => $level,
        ));
        $allocDecla = $db->getCollection('allocations')->findOne(array(
            'user' => $user['_id'],
            'type' => 'Declaratif',
            'level' => $level,
        ));

        $hasFactuelAlloc = $allocFactuel && !empty($allocFactuel['activeTest']);
        $hasDeclaAlloc = $allocDecla && !empty($allocDecla['activeTest']);

        if (!$hasFactuelAlloc && !$hasDeclaAlloc) {
            echo "  [skip] {$level} — aucune allocation active (Factuel ou Déclaratif)\n";
            $skipped++;
            continue;
        }

        $testFactuel = null;
        $doneFactuel = false;
        if ($hasFactuelAlloc) {
            $testFactuel = $db->getCollection('tests')->findOne(array(
                'user' => $user['_id'],
                'type' => 'Factuel',
                'level' => $level,
                'active' => true,
            ));
            if ($testFactuel && !$force) {
                $done = $db->getCollection('results')->findOne(array(
                    'user' => $user['_id'],
                    'test' => $testFactuel['_id'],
                    'type' => 'Factuel',
                    'level' => $level,
                    'active' => true,
                ));
                if ($done) {
                    $doneFactuel = true;
                    echo "  [info] {$level} Factuel déjà composé (score {$done['score']}/{$done['total']})\n";
                }
            }
        }

        $testDecla = null;
        $doneDecla = false;
        if ($hasDeclaAlloc) {
            $testDecla = $db->getCollection('tests')->findOne(array(
                'user' => $user['_id'],
                'type' => 'Declaratif',
                'level' => $level,
                'active' => true,
            ));
            if ($testDecla && !$force) {
                $done = $db->getCollection('results')->findOne(array(
                    'user' => $user['_id'],
                    'test' => $testDecla['_id'],
                    'type' => 'Declaratif',
                    'typeR' => 'Techniciens',
                    'level' => $level,
                    'active' => true,
                ));
                if ($done) {
                    $doneDecla = true;
                    echo "  [info] {$level} Déclaratif déjà composé (score {$done['score']}/{$done['total']})\n";
                }
            }
        }

        $includeFactuel = ($testFactuel && (!$doneFactuel || $force));
        $includeDecla = ($testDecla && (!$doneDecla || $force));

        if (!$includeFactuel && !$includeDecla) {
            echo "  [skip] {$level} — épreuves déjà composées\n";
            $skipped++;
            continue;
        }

        $allFactuelQuestions = array();
        if ($includeFactuel) {
            $fetcher = new QuizDataFetcher($db);
            $userData = $fetcher->fetchUserData((string) $user['_id'], (string) $testFactuel['_id'], $level);
            if ($userData) {
                $gen = new QuizQuestionGenerator($db, $level, $userData);
                $allFactuelQuestions = $gen->generateForExport();
            }
        }

        $allDeclaQuestions = array();
        if ($includeDecla) {
            $allDeclaQuestions = offlineQcmFetchDeclaratifQuestions($db, $user, $testDecla, $level);
        }

        if (empty($allFactuelQuestions) && empty($allDeclaQuestions)) {
            echo "  [skip] {$level} — banques de questions vides\n";
            $skipped++;
            continue;
        }

        $options = array(
            'declaratifQuestions' => $allDeclaQuestions,
            'testDecla' => $testDecla,
            'exportedBy' => 'cli',
        );

        $pkg = offlineQcmBuildPackage($allFactuelQuestions, $user, $testFactuel, $level, $options);
        $htmlName = offlineQcmStandaloneFilename($username, $level);
        $htmlPath = $userDir . '/' . $htmlName;

        $descParts = array();
        if (!empty($pkg['questions'])) {
            $descParts[] = count($pkg['questions']) . ' QCM Factuel';
        }
        if (!empty($pkg['declaratifQuestions'])) {
            $descParts[] = count($pkg['declaratifQuestions']) . ' tâches Déclaratif';
        }
        echo '  [ok] ' . $level . ' — ' . implode(' + ', $descParts) . " -> {$htmlName}\n";

        if (!$dryRun) {
            if (!is_dir($userDir)) {
                mkdir($userDir, 0755, true);
            }
            file_put_contents($htmlPath, offlineQcmBuildStandaloneHtml($pkg));
            $exported++;
        }
    }
}

$readme = $outRoot . '/LISEZMOI.txt';
if (!$dryRun) {
    $txt = "MEDACAP — QCM offline — Agence {$agency}\n"
        . str_repeat('=', 50) . "\n\n"
        . "TECHNICIEN (brousse, sans reseau)\n"
        . "1. Ouvrir UN SEUL fichier : VotreNom_Junior_QCM.html (double-clic, Chrome)\n"
        . "2. Composer le QCM — aucune importation, les questions sont deja dans le fichier\n"
        . "3. Cliquer « Terminer l'epreuve » — le meme fichier HTML se telecharge avec vos reponses dedans\n"
        . "4. Renvoyer ce fichier HTML au RH (WhatsApp / mail / cle USB)\n\n"
        . "RH (sur serveur MEDACAP)\n"
        . "php scripts/import_offline_qcm.php --file=chemin/vers/Prenom_NOM_Junior_QCM.html\n";
    file_put_contents($readme, $txt);
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "Techniciens: " . count($users) . " | Kits exportes: {$exported} | Ignores: {$skipped}\n";
if ($dryRun) {
    echo "(dry-run — aucun fichier ecrit)\n";
}
echo "Termine.\n";
