<?php
require_once __DIR__ . '/../core/medacap_config.php';
/**
 * 4 exclus CFAO : migrer identifiants (3 existants) + créer DZOGNOU Manager testable.
 * Usage: php scripts/setup_exclus_managers_cm.php [--dry-run] [--mongo=mongodb://10.68.0.7:27017]
 */
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/includes/mongo_quiz_helpers.php';
require __DIR__ . '/includes/cfao_name_helpers.php';

use MongoDB\BSON\ObjectId;
use PhpOffice\PhpSpreadsheet\IOFactory;

const SOURCE = __DIR__ . '/../Copie de Copie de Liste_techniciens_marques_CFAO (1) - Copie.xlsx';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$mongoUri = medacapMongoUri('mongodb://10.68.0.7:27017');
foreach ($argv ?? [] as $a) {
    if (strpos($a, '--mongo=') === 0) $mongoUri = substr($a, 8);
}

$brandCols = [
    'FUSO' => 6, 'HINO' => 7, 'SINOTRUK' => 8, 'KINGLONG' => 9,
    'RENAULT TRUCKS' => 10, 'JCB' => 11, 'LOVOL' => 12, 'TOYOTA BT' => 13, 'TOYOTA FORKLIFT' => 14,
];
$brandDbMap = [
    'FUSO' => 'FUSO', 'HINO' => 'HINO', 'SINOTRUK' => 'SINOTRUK',
    'KINGLONG' => 'KING LONG', 'RENAULT TRUCKS' => 'RENAULT TRUCK',
    'JCB' => 'JCB', 'LOVOL' => 'LOVOL', 'TOYOTA BT' => 'TOYOTA BT', 'TOYOTA FORKLIFT' => 'TOYOTA FORKLIFT',
];

function normalizeLevel(?string $v): ?string {
    if (!$v) return null;
    if (stripos($v, 'Expert') !== false) return 'Expert';
    if (stripos($v, 'Senior') !== false) return 'Senior';
    if (stripos($v, 'Junior') !== false) return 'Junior';
    return null;
}

function parseName(string $raw): array { return cfaoParseName($raw); }
function firstToken(string $s): string { return cfaoFirstToken($s); }
function buildUsername(string $firstName, string $lastName): string { return cfaoBuildUsername($firstName, $lastName); }
function buildPassword(string $firstName): string { return cfaoBuildPassword($firstName); }

function applyBrandCascade(array $junior, array $senior, array $expert): array {
    foreach ($expert as $b) {
        if (!in_array($b, $senior, true)) $senior[] = $b;
        if (!in_array($b, $junior, true)) $junior[] = $b;
    }
    foreach ($senior as $b) {
        if (!in_array($b, $junior, true)) $junior[] = $b;
    }
    return [array_values($junior), array_values($senior), array_values($expert)];
}

function parseBrandsFromRow($ws, int $row, array $brandCols, array $brandDbMap): array {
    $brandJunior = $brandSenior = $brandExpert = [];
    $levels = [];
    foreach ($brandCols as $excelBrand => $col) {
        $raw = trim((string)$ws->getCellByColumnAndRow($col, $row)->getCalculatedValue());
        if ($raw === '') continue;
        $lvl = normalizeLevel($raw);
        if (!$lvl) continue;
        $dbBrand = $brandDbMap[$excelBrand];
        $levels[] = $lvl;
        if ($lvl === 'Junior') $brandJunior[] = $dbBrand;
        if ($lvl === 'Senior') $brandSenior[] = $dbBrand;
        if ($lvl === 'Expert') $brandExpert[] = $dbBrand;
    }
    $rank = ['Junior' => 1, 'Senior' => 2, 'Expert' => 3];
    $maxLevel = 'Junior';
    foreach ($levels as $l) {
        if ($rank[$l] > $rank[$maxLevel]) $maxLevel = $l;
    }
    [$brandJunior, $brandSenior, $brandExpert] = applyBrandCascade($brandJunior, $brandSenior, $brandExpert);
    return compact('brandJunior', 'brandSenior', 'brandExpert', 'maxLevel');
}

function findExcelRow($ws, string $excelName): ?int {
    $target = mb_strtoupper(trim($excelName), 'UTF-8');
    for ($r = 5; $r <= $ws->getHighestRow(); $r++) {
        $raw = mb_strtoupper(trim((string)$ws->getCellByColumnAndRow(2, $r)->getCalculatedValue()), 'UTF-8');
        if ($raw === $target) return $r;
    }
    return null;
}

/** TestPlanBuilder minimal (createUser.php) */
class TestPlanBuilder {
    const TYPE_FACTUEL = 'Factuel';
    const TYPE_DECLARATIF = 'Declaratif';

    public static $tests;
    public static $vehicles;
    public static $allocations;
    public static $specialities;

    private static function createTest(ObjectId $userId, array $brandList, string $type, string $level): ObjectId {
        return self::$tests->insertOne([
            'quizzes' => [], 'user' => $userId, 'brand' => $brandList,
            'type' => $type, 'level' => $level, 'total' => 0, 'active' => true,
            'created' => date('d-m-Y H:i:s'),
        ])->getInsertedId();
    }

    private static function addFromVehicles(ObjectId $testId, string $brand, string $type, string $level): void {
        $vehicle = self::$vehicles->findOne(['brand' => $brand, 'type' => $type, 'level' => $level, 'active' => true]);
        $refs = mongoQuizRefs($vehicle);
        if ($refs) {
            self::$tests->updateOne(['_id' => $testId], ['$addToSet' => ['quizzes' => ['$each' => $refs]]]);
        }
    }

    private static function finalize(ObjectId $testId): void {
        $t = self::$tests->findOne(['_id' => $testId]);
        $total = mongoQuizCount($t);
        self::$tests->updateOne(['_id' => $testId], ['$set' => ['total' => $total]]);
    }

    private static function allocate(ObjectId $userId, ObjectId $testId, string $type, string $level, bool $activeTest, bool $activeManager = false): void {
        self::$allocations->insertOne([
            'user' => $userId, 'test' => $testId, 'type' => $type, 'level' => $level,
            'activeTest' => $activeTest, 'activeManager' => $activeManager,
            'active' => false, 'created' => date('d-m-Y H:i:s'),
        ]);
    }

    public static function buildForExpert(ObjectId $userId, array $brandJunior, array $brandSenior, array $brandExpert): void {
        $tJuF = self::createTest($userId, $brandJunior, self::TYPE_FACTUEL, 'Junior');
        $tJuD = self::createTest($userId, $brandJunior, self::TYPE_DECLARATIF, 'Junior');
        foreach ($brandJunior as $b) {
            self::addFromVehicles($tJuF, $b, self::TYPE_FACTUEL, 'Junior');
            self::addFromVehicles($tJuD, $b, self::TYPE_DECLARATIF, 'Junior');
        }
        self::finalize($tJuF); self::finalize($tJuD);
        self::allocate($userId, $tJuF, self::TYPE_FACTUEL, 'Junior', true);
        self::allocate($userId, $tJuD, self::TYPE_DECLARATIF, 'Junior', true, false);

        $tSeF = self::createTest($userId, $brandSenior, self::TYPE_FACTUEL, 'Senior');
        $tSeD = self::createTest($userId, $brandSenior, self::TYPE_DECLARATIF, 'Senior');
        foreach ($brandSenior as $b) {
            self::addFromVehicles($tSeF, $b, self::TYPE_FACTUEL, 'Senior');
            self::addFromVehicles($tSeD, $b, self::TYPE_DECLARATIF, 'Senior');
        }
        self::finalize($tSeF); self::finalize($tSeD);
        self::allocate($userId, $tSeF, self::TYPE_FACTUEL, 'Senior', false);
        self::allocate($userId, $tSeD, self::TYPE_DECLARATIF, 'Senior', false, false);

        $tExF = self::createTest($userId, $brandExpert, self::TYPE_FACTUEL, 'Expert');
        $tExD = self::createTest($userId, $brandExpert, self::TYPE_DECLARATIF, 'Expert');
        foreach ($brandExpert as $b) {
            self::addFromVehicles($tExF, $b, self::TYPE_FACTUEL, 'Expert');
            self::addFromVehicles($tExD, $b, self::TYPE_DECLARATIF, 'Expert');
        }
        self::finalize($tExF); self::finalize($tExD);
        self::allocate($userId, $tExF, self::TYPE_FACTUEL, 'Expert', false);
        self::allocate($userId, $tExD, self::TYPE_DECLARATIF, 'Expert', false, false);
    }

    public static function buildForSenior(ObjectId $userId, array $brandJunior, array $brandSenior): void {
        $tJuF = self::createTest($userId, $brandJunior, self::TYPE_FACTUEL, 'Junior');
        $tJuD = self::createTest($userId, $brandJunior, self::TYPE_DECLARATIF, 'Junior');
        foreach ($brandJunior as $b) {
            self::addFromVehicles($tJuF, $b, self::TYPE_FACTUEL, 'Junior');
            self::addFromVehicles($tJuD, $b, self::TYPE_DECLARATIF, 'Junior');
        }
        self::finalize($tJuF); self::finalize($tJuD);
        self::allocate($userId, $tJuF, self::TYPE_FACTUEL, 'Junior', true);
        self::allocate($userId, $tJuD, self::TYPE_DECLARATIF, 'Junior', true, false);

        $tSeF = self::createTest($userId, $brandSenior, self::TYPE_FACTUEL, 'Senior');
        $tSeD = self::createTest($userId, $brandSenior, self::TYPE_DECLARATIF, 'Senior');
        foreach ($brandSenior as $b) {
            self::addFromVehicles($tSeF, $b, self::TYPE_FACTUEL, 'Senior');
            self::addFromVehicles($tSeD, $b, self::TYPE_DECLARATIF, 'Senior');
        }
        self::finalize($tSeF); self::finalize($tSeD);
        self::allocate($userId, $tSeF, self::TYPE_FACTUEL, 'Senior', false);
        self::allocate($userId, $tSeD, self::TYPE_DECLARATIF, 'Senior', false, false);
    }
}

$people = [
    ['legacy' => 'cl4fo', 'excel' => 'FORBANG CLOVIS TEKUM', 'create' => false],
    ['legacy' => 'ma66ku', 'excel' => 'KUSSO SONKOUAT MATHIAS', 'create' => false],
    ['legacy' => 'gi07pa', 'excel' => 'PASSO GILDAS ARMEL', 'create' => false],
    ['legacy' => null, 'excel' => 'DZOGNOU WONEPO JULES', 'create' => true, 'managerLegacy' => 'la51di'],
];

$client = new MongoDB\Client($mongoUri);
$academy = $client->academy;
$users = $academy->users;
TestPlanBuilder::$tests = $academy->tests;
TestPlanBuilder::$vehicles = $academy->vehicles;
TestPlanBuilder::$allocations = $academy->allocations;
TestPlanBuilder::$specialities = $academy->specialities;

$ws = IOFactory::load(SOURCE)->getSheetByName('Techniciens à évaluer');
$credentials = [];

echo ($dryRun ? "[DRY-RUN] " : '') . "Mongo: $mongoUri\n\n";

foreach ($people as $p) {
    $row = findExcelRow($ws, $p['excel']);
    if ($row === null) {
        echo "ERREUR: ligne Excel introuvable pour {$p['excel']}\n";
        continue;
    }

    [$firstName, $lastName] = parseName($p['excel']);
    $brands = parseBrandsFromRow($ws, $row, $brandCols, $brandDbMap);
    $username = buildUsername($firstName, $lastName);
    $password = buildPassword($firstName);
    $role = trim((string)$ws->getCellByColumnAndRow(4, $row)->getCalculatedValue());

    if (!$p['create']) {
        $user = $users->findOne(['username' => $p['legacy'], 'profile' => 'Manager', 'active' => true]);
        if (!$user) {
            echo "ERREUR: {$p['legacy']} introuvable\n";
            continue;
        }
        $conflict = $users->findOne(['username' => $username, 'active' => true, '_id' => ['$ne' => $user['_id']]]);
        if ($conflict) {
            echo "ERREUR: username $username déjà pris\n";
            continue;
        }

        $set = [
            'username' => $username,
            'firstName' => ucfirst(firstToken($firstName)),
            'lastName' => mb_strtoupper($lastName, 'UTF-8'),
            'level' => $brands['maxLevel'],
            'brandJunior' => $brands['brandJunior'],
            'brandSenior' => $brands['brandSenior'],
            'brandExpert' => $brands['brandExpert'],
            'visiblePassword' => $password,
            'password' => sha1($password),
            'updated' => date('d-m-Y H:i:s'),
        ];

        echo "UPDATE {$p['legacy']} → $username | level={$brands['maxLevel']}\n";
        if (!$dryRun) {
            $users->updateOne(['_id' => $user['_id']], ['$set' => $set]);
        }
        $credentials[] = [
            'name' => trim($firstName . ' ' . $lastName),
            'username' => $username,
            'password' => $password,
            'profile' => 'Manager (existant)',
            'level' => $brands['maxLevel'],
        ];
        continue;
    }

    // Création DZOGNOU
    $existing = $users->findOne(['username' => $username, 'active' => true]);
    if ($existing) {
        echo "ERREUR: $username existe déjà\n";
        continue;
    }

    $n1 = $users->findOne(['username' => $p['managerLegacy'], 'profile' => 'Manager', 'active' => true]);
    if (!$n1) {
        echo "ERREUR: N+1 {$p['managerLegacy']} introuvable\n";
        continue;
    }

    $doc = [
        'users' => [],
        'username' => $username,
        'matricule' => 926037,
        'firstName' => ucfirst(firstToken($firstName)),
        'lastName' => mb_strtoupper($lastName, 'UTF-8'),
        'email' => '',
        'phone' => '',
        'gender' => 'Masculin',
        'level' => $brands['maxLevel'],
        'country' => 'Cameroun',
        'profile' => 'Manager',
        'birthdate' => '01-01-1990',
        'recrutmentDate' => date('d-m-Y'),
        'certificate' => '',
        'subsidiary' => 'CFAO MOBILITY CAMEROON',
        'agency' => 'Douala',
        'department' => 'Equipment',
        'brandJunior' => $brands['brandJunior'],
        'brandSenior' => $brands['brandSenior'],
        'brandExpert' => $brands['brandExpert'],
        'specialitySenior' => [],
        'specialityExpert' => [],
        'role' => ucfirst($role),
        'password' => sha1($password),
        'visiblePassword' => $password,
        'manager' => $n1['_id'],
        'test' => true,
        'active' => true,
        'created' => date('d-m-Y H:i:s'),
    ];

    echo "CREATE $username | level={$brands['maxLevel']} | N+1={$p['managerLegacy']}\n";

    if (!$dryRun) {
        $insert = $users->insertOne($doc);
        $userId = $insert->getInsertedId();
        $users->updateOne(['_id' => $n1['_id']], ['$addToSet' => ['users' => $userId]]);

        if ($brands['maxLevel'] === 'Expert') {
            TestPlanBuilder::buildForExpert($userId, $brands['brandJunior'], $brands['brandSenior'], $brands['brandExpert']);
        } elseif ($brands['maxLevel'] === 'Senior') {
            TestPlanBuilder::buildForSenior($userId, $brands['brandJunior'], $brands['brandSenior']);
        }
    }

    $credentials[] = [
        'name' => trim($firstName . ' ' . $lastName),
        'username' => $username,
        'password' => $password,
        'profile' => 'Manager (créé — chef d\'équipe)',
        'level' => $brands['maxLevel'],
    ];
}

echo "\n=== IDENTIFIANTS (nouveau format) ===\n";
foreach ($credentials as $c) {
    echo "{$c['name']}\n";
    echo "  Profil  : {$c['profile']} | Niveau : {$c['level']}\n";
    echo "  Identifiant : {$c['username']}\n";
    echo "  Mot de passe : {$c['password']}\n\n";
}

echo "Terminé.\n";
