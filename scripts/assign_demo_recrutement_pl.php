<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * Assigne le dernier paper PL multi-marques à un ou plusieurs comptes démo.
 *
 * Usage:
 *   php scripts/assign_demo_recrutement_pl.php
 *   php scripts/assign_demo_recrutement_pl.php "Test DEMO"
 *   php scripts/assign_demo_recrutement_pl.php --all
 */
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\Client;

const DEFAULT_DEMO_USERNAME = 'demo recrutement';
const DEMO_PASSWORD = '0000';
const FIXED_SUBSIDIARY = 'CFAO MOBILITY CAMEROON';
const FIXED_DEPARTMENT = 'Equipment & Motors';
const FIXED_COUNTRY = 'Cameroun';

$mongoUri = medacapMongoUri('mongodb://10.68.0.7:27017');
$paperIdArg = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--mongo-uri=') === 0) {
        $mongoUri = trim(substr($arg, 12));
    }
    if (strpos($arg, '--paper-id=') === 0) {
        $paperIdArg = trim(substr($arg, 11));
    }
}

$db = (new Client($mongoUri))->academy;
$users = $db->users;
$papers = $db->papers;

if ($paperIdArg && preg_match('/^[a-f0-9]{24}$/i', $paperIdArg)) {
    $paper = $papers->findOne(['_id' => new ObjectId($paperIdArg), 'active' => true]);
} else {
    $paper = $papers->findOne(
        [
            'active' => true,
            '$and' => [
                ['label' => new Regex('Poids Lourd| PL |Garoua', 'i')],
                ['label' => ['$not' => new Regex('Renault Truck', 'i')]],
            ],
        ],
        ['sort' => ['created' => -1]]
    );
}

if (!$paper) {
    fwrite(STDERR, "Aucun paper Poids Lourds multi-marques trouvé.\n");
    exit(1);
}

$campRole = (string)$paper['label'];
$paperId = (string)$paper['_id'];

$assignAll = in_array('--all', $argv, true);
$requested = array_values(array_filter(array_slice($argv, 1), function ($a) {
    return $a !== '--all' && strpos($a, '--paper-id=') !== 0 && strpos($a, '--mongo-uri=') !== 0;
}));

if ($assignAll) {
    $targets = [];
    foreach ($users->find(['username' => new Regex('demo', 'i'), 'active' => true], ['projection' => ['username' => 1]]) as $u) {
        $targets[] = (string)$u['username'];
    }
} elseif (!empty($requested)) {
    $targets = $requested;
} else {
    $targets = [DEFAULT_DEMO_USERNAME];
}

$template = $users->findOne(['username' => 'demo', 'active' => true]) ?: [];
$hr = $template['hr'] ?? null;

foreach ($targets as $username) {
    $username = trim($username);
    if ($username === '') {
        continue;
    }

    $existing = $users->findOne(['username' => $username]);

    if ($existing) {
        $users->updateOne(
            ['_id' => $existing['_id']],
            [
                '$set' => [
                    'role' => $campRole,
                    'papers' => [$paper['_id']],
                    'status' => 'New',
                    'active' => true,
                ],
                '$addToSet' => ['roles' => $campRole],
            ]
        );
        echo "[OK] « $username » mis à jour → $campRole\n";
        continue;
    }

    if ($username !== DEFAULT_DEMO_USERNAME) {
        echo "[SKIP] « $username » introuvable.\n";
        continue;
    }

    $doc = [
        'matricule' => '999999',
        'username' => DEFAULT_DEMO_USERNAME,
        'firstName' => 'DEMO',
        'lastName' => 'Recrutement',
        'phone' => '',
        'profile' => 'Candidat',
        'country' => FIXED_COUNTRY,
        'subsidiary' => FIXED_SUBSIDIARY,
        'department' => FIXED_DEPARTMENT,
        'role' => $campRole,
        'roles' => [$campRole],
        'password' => sha1(DEMO_PASSWORD),
        'visiblePassword' => DEMO_PASSWORD,
        'active' => true,
        'status' => 'New',
        'created' => date('d-m-Y H:i:s'),
    ];
    if ($hr instanceof ObjectId) {
        $doc['hr'] = $hr;
    }

    $users->insertOne($doc);
    echo "[OK] « " . DEFAULT_DEMO_USERNAME . " » créé → $campRole\n";
}

echo "Paper : $campRole ($paperId)\n";
