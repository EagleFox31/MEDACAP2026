<?php
declare(strict_types=1);

/** Bridge portail React ↔ Mesure — config locale optionnelle. */
$portalBridgeKey = 'demo-cfao-portal';
$mongoDb = 'academy';
$portalApiEnv = 'dev';
$mongoUriSource = 'default';
$local = [];

$configPath = __DIR__ . '/config.local.php';
if (is_file($configPath)) {
    /** @var array<string, mixed> $local */
    $local = require $configPath;
    $portalBridgeKey = $local['portalBridgeKey'] ?? $portalBridgeKey;
    $mongoDb = $local['mongoDb'] ?? $mongoDb;
}

require_once __DIR__ . '/mongo-resolver.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

$mongoResolved = portalApiResolveMongoUri($local);
$mongoUri = $mongoResolved['uri'];
$portalApiEnv = $mongoResolved['env'];
$mongoUriSource = $mongoResolved['source'];

use MongoDB\Client;
use MongoDB\BSON\ObjectId;

function portalApiCors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:4173',
        'http://127.0.0.1:4173',
    ];
    if ($origin && preg_match('#^https?://10\.68\.\d+\.\d+(:\d+)?$#', $origin)) {
        $allowed[] = $origin;
    }
    if ($origin && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    } else {
        header('Access-Control-Allow-Origin: *');
    }
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Portal-Key');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function portalApiJson($payload, int $status = 200): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function portalApiDb(string $mongoUri, string $mongoDb): Client
{
    return new Client($mongoUri, [
        'serverSelectionTimeoutMS' => 60000,
        'connectTimeoutMS' => 15000,
    ]);
}

function portalApiResolveUserId($users, ?string $userId, ?string $username, ?string $email): ?ObjectId
{
    if ($userId && preg_match('/^[a-f0-9]{24}$/i', $userId)) {
        return new ObjectId($userId);
    }
    if ($username) {
        $doc = $users->findOne(['username' => $username, 'active' => true]);
        if ($doc && isset($doc['_id'])) {
            return $doc['_id'];
        }
    }
    if ($email) {
        $doc = $users->findOne(['email' => $email, 'active' => true]);
        if ($doc && isset($doc['_id'])) {
            return $doc['_id'];
        }
    }
    return null;
}

function portalApiPercentScore(?array $result): ?int
{
    if (!$result || !isset($result['score'], $result['total'])) {
        return null;
    }
    $total = (int) $result['total'];
    if ($total <= 0) {
        return null;
    }
    return (int) ceil(((float) $result['score'] * 100) / $total);
}

function portalApiTierFromLevel(string $level): string
{
    return strtolower($level);
}

function portalApiModalityFromType(string $type): string
{
    return $type === 'Factuel' ? 'theory' : 'practical';
}

function portalApiCertLevelFromTier(string $tier): string
{
    return strtoupper($tier);
}

function portalApiBaseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $measureApi = '/views/measure/api';
    $pos = strpos($script, $measureApi);
    if ($pos !== false) {
        $base = substr($script, 0, $pos);
        return $scheme . '://' . $host . rtrim($base, '/');
    }
    return $scheme . '://' . $host . '/medacap';
}

/** Marques Mongo (brandJunior/Senior/Expert) → slugs portail React. */
function portalApiExtractUserBrands(array $userDoc): array
{
    $raw = [];
    foreach (['brandJunior', 'brandSenior', 'brandExpert'] as $field) {
        $val = $userDoc[$field] ?? [];
        if ($val instanceof \MongoDB\Model\BSONArray) {
            $val = $val->getArrayCopy();
        }
        foreach ((array) $val as $brand) {
            $brand = trim((string) $brand);
            if ($brand !== '') {
                $raw[] = $brand;
            }
        }
    }
    return array_values(array_unique($raw, SORT_STRING));
}

function portalApiBrandToSlug(string $brand): string
{
    static $map = [
        'RENAULT TRUCK' => 'renault-trucks',
        'KING LONG' => 'king-long',
        'TOYOTA FORKLIFT' => 'toyota-forklift',
        'TOYOTA BT' => 'bt',
        'TOYOTA MATERIAL' => 'toyota-forklift',
        'HINO' => 'hino',
        'SINOTRUK' => 'sinotruk',
        'FUSO' => 'fuso',
        'JCB' => 'jcb',
        'LOVOL' => 'lovol',
        'SHACMAN' => 'shacman',
        'TOYOTA' => 'toyota',
    ];
    $upper = strtoupper(trim($brand));
    if (isset($map[$upper])) {
        return $map[$upper];
    }
    return strtolower(preg_replace('/\s+/', '-', trim($brand)));
}

function portalApiUserProfilePayload(array $userDoc, string $userIdHex): array
{
    $mongoBrands = portalApiExtractUserBrands($userDoc);
    $brandSlugs = array_values(array_unique(array_map('portalApiBrandToSlug', $mongoBrands), SORT_STRING));

    return [
        'firstName' => $userDoc['firstName'] ?? null,
        'lastName' => $userDoc['lastName'] ?? null,
        'subsidiary' => $userDoc['subsidiary'] ?? null,
        'agency' => $userDoc['agency'] ?? null,
        'country' => $userDoc['country'] ?? null,
        'department' => $userDoc['department'] ?? null,
        'level' => $userDoc['level'] ?? null,
        'username' => $userDoc['username'] ?? null,
        'brands' => $brandSlugs,
        'medacapUserId' => $userIdHex,
    ];
}

function portalApiBrandFieldHasBrands(array $doc, string $field): bool
{
    $val = $doc[$field] ?? [];
    if ($val instanceof \MongoDB\Model\BSONArray) {
        $val = $val->getArrayCopy();
    }
    foreach ((array) $val as $brand) {
        if (trim((string) $brand) !== '') {
            return true;
        }
    }
    return false;
}

function portalApiTechnicianDisplayName(array $doc): string
{
    $first = trim((string) ($doc['firstName'] ?? ''));
    $last = trim((string) ($doc['lastName'] ?? ''));
    $full = trim($first . ' ' . $last);
    if ($full !== '') {
        return $full;
    }
    $username = trim((string) ($doc['username'] ?? ''));
    return $username !== '' ? $username : 'Technicien';
}

function portalApiTechnicianPrimaryTier(array $doc): string
{
    if (portalApiBrandFieldHasBrands($doc, 'brandExpert')) {
        return 'expert';
    }
    if (portalApiBrandFieldHasBrands($doc, 'brandSenior')) {
        return 'senior';
    }
    return 'junior';
}

function portalApiLaunchUrl(
    string $bridgeKey,
    string $userIdHex,
    string $testIdHex,
    string $level,
    string $type
): string {
    $params = http_build_query([
        'key' => $bridgeKey,
        'userId' => $userIdHex,
        'test' => $testIdHex,
        'level' => $level,
        'type' => $type,
    ]);
    return portalApiBaseUrl() . '/views/measure/api/portal-launch.php?' . $params;
}

/** @return list<string> */
function portalApiBrandsForTier(array $doc, string $tier): array
{
    $tierNorm = strtolower($tier);
    if ($tierNorm === 'expert') {
        $field = 'brandExpert';
    } elseif ($tierNorm === 'senior') {
        $field = 'brandSenior';
    } else {
        $field = 'brandJunior';
    }
    $val = $doc[$field] ?? [];
    if ($val instanceof \MongoDB\Model\BSONArray) {
        $val = $val->getArrayCopy();
    }
    $brands = [];
    foreach ((array) $val as $brand) {
        $brand = trim((string) $brand);
        if ($brand !== '') {
            $brands[] = $brand;
        }
    }
    return $brands;
}

/** @return list<string> */
function portalApiBrandSlugsForTier(array $doc, string $tier): array
{
    $brands = portalApiBrandsForTier($doc, $tier);
    if (!$brands) {
        $brands = portalApiExtractUserBrands($doc);
    }
    if (!$brands) {
        return [];
    }
    return array_values(array_unique(array_map('portalApiBrandToSlug', $brands), SORT_STRING));
}

function portalApiPrimaryBrandSlugForTier(array $doc, string $tier): ?string
{
    $slugs = portalApiBrandSlugsForTier($doc, $tier);
    return $slugs[0] ?? null;
}

/**
 * Tests compétence Mesure pour un utilisateur (même logique que evaluations.php).
 *
 * @return list<array<string, mixed>>
 */
function portalApiUserCompetencyEvaluations(
    \MongoDB\BSON\ObjectId $userOid,
    array $userDoc,
    $allocations,
    $exams,
    $results,
    string $portalBridgeKey,
    ?string $collaboratorName = null,
    ?string $agency = null
): array {
    $userIdHex = (string) $userOid;
    $levels = ['Junior', 'Senior', 'Expert'];
    $types = ['Factuel', 'Declaratif'];
    $evaluations = [];

    foreach ($types as $type) {
        foreach ($levels as $level) {
            $allocate = $allocations->findOne([
                'user' => $userOid,
                'type' => $type,
                'level' => $level,
            ]);
            if (!$allocate || !isset($allocate['test'])) {
                $tier = portalApiTierFromLevel($level);
                $modality = portalApiModalityFromType($type);
                $evalId = 'medacap-' . $userIdHex . '-' . $tier . '-' . ($modality === 'theory' ? 't' : 'p');
                $brandSlugs = portalApiBrandSlugsForTier($userDoc, $tier);
                $evaluations[] = [
                    'id' => $evalId,
                    'kind' => 'competency',
                    'competencyTier' => $tier,
                    'modality' => $modality,
                    'certLevel' => strtoupper($tier),
                    'status' => 'todo',
                    'date' => '—',
                    'score' => null,
                    'passMark' => 60,
                    'questions' => null,
                    'questionsAnswered' => null,
                    'attempts' => 0,
                    'feedback' => null,
                    'brand' => $brandSlugs[0] ?? null,
                    'brands' => $brandSlugs,
                    'medacapCreated' => null,
                    'medacap' => null,
                ];
                continue;
            }

            $testId = $allocate['test'];
            $testIdHex = (string) $testId;
            $isAvailable = isset($allocate['activeTest']) && $allocate['activeTest'] === true;

            $activeExam = $exams->findOne(
                [
                    'user' => $userOid,
                    'test' => $testId,
                    'active' => true,
                ],
                ['sort' => ['_id' => -1]]
            );

            $completedExam = $exams->findOne(
                [
                    'user' => $userOid,
                    'test' => $testId,
                    'active' => false,
                ],
                ['sort' => ['_id' => -1]]
            );

            $typeR = $type === 'Declaratif' ? 'Technicien - Manager' : 'Technicien';
            $latestResult = $results->findOne(
                [
                    'user' => $userOid,
                    'test' => $testId,
                    'level' => $level,
                    'type' => $type,
                    'typeR' => $typeR,
                ],
                ['sort' => ['_id' => -1]]
            );

            $technicienDeclResult = null;
            if ($type === 'Declaratif') {
                $technicienDeclResult = $results->findOne(
                    [
                        'user' => $userOid,
                        'test' => $testId,
                        'level' => $level,
                        'type' => $type,
                        'typeR' => 'Techniciens',
                    ],
                    ['sort' => ['_id' => -1]]
                );
            }

            $score = portalApiPercentScore($latestResult ? $latestResult->getArrayCopy() : null);
            $tier = portalApiTierFromLevel($level);
            $modality = portalApiModalityFromType($type);
            $evalId = 'medacap-' . $userIdHex . '-' . $tier . '-' . ($modality === 'theory' ? 't' : 'p');
            $brandSlugs = portalApiBrandSlugsForTier($userDoc, $tier);
            $brandSlug = $brandSlugs[0] ?? null;

            $status = 'todo';
            $questions = null;
            $questionsAnswered = null;
            $date = '—';
            $createdRaw = null;
            $managerPending = isset($allocate['activeManager']) && $allocate['activeManager'] === true;

            if ($type === 'Declaratif') {
                if (!$latestResult) {
                    $score = null;
                }

                if ($latestResult && ($completedExam || ($score !== null && !$activeExam))) {
                    $status = 'done';
                    $createdRaw = isset($latestResult['created']) ? (string) $latestResult['created'] : null;
                    $date = $createdRaw ?? '—';
                } elseif (
                    $activeExam
                    || $managerPending
                    || ($technicienDeclResult && !$latestResult)
                ) {
                    $status = 'progress';
                    $score = null;
                    if ($activeExam) {
                        $questionsRaw = $activeExam['questions'] ?? [];
                        if ($questionsRaw instanceof \MongoDB\Model\BSONArray) {
                            $questionsRaw = $questionsRaw->getArrayCopy();
                        }
                        $questions = is_array($questionsRaw) ? count($questionsRaw) : null;
                        $answeredRaw = $activeExam['answers'] ?? [];
                        if ($answeredRaw instanceof \MongoDB\Model\BSONArray) {
                            $answeredRaw = $answeredRaw->getArrayCopy();
                        }
                        if (is_array($answeredRaw)) {
                            $questionsAnswered = count(array_filter($answeredRaw, static fn($v) => $v !== null && $v !== ''));
                        }
                    }
                } elseif (!$isAvailable) {
                    continue;
                }
            } elseif ($completedExam || ($score !== null && !$activeExam)) {
                $status = 'done';
                $createdRaw = isset($latestResult['created']) ? (string) $latestResult['created'] : null;
                $date = $createdRaw ?? '—';
            } elseif ($activeExam) {
                $status = 'progress';
                $questionsRaw = $activeExam['questions'] ?? [];
                if ($questionsRaw instanceof \MongoDB\Model\BSONArray) {
                    $questionsRaw = $questionsRaw->getArrayCopy();
                }
                $questions = is_array($questionsRaw) ? count($questionsRaw) : null;
                $answeredRaw = $activeExam['answers'] ?? [];
                if ($answeredRaw instanceof \MongoDB\Model\BSONArray) {
                    $answeredRaw = $answeredRaw->getArrayCopy();
                }
                if (is_array($answeredRaw)) {
                    $questionsAnswered = count(array_filter($answeredRaw, static fn($v) => $v !== null && $v !== ''));
                }
            } elseif (!$isAvailable) {
                continue;
            }

            $declarativeStage = null;
            if ($type === 'Declaratif' && $status !== 'done') {
                if ($managerPending || ($technicienDeclResult && !$latestResult)) {
                    $declarativeStage = 'awaiting_manager';
                } elseif ($activeExam) {
                    $declarativeStage = 'technician_in_progress';
                } else {
                    $declarativeStage = 'awaiting_technician';
                }
            }

            $row = [
                'id' => $evalId,
                'kind' => 'competency',
                'competencyTier' => $tier,
                'modality' => $modality,
                'certLevel' => portalApiCertLevelFromTier($tier),
                'status' => $status,
                'date' => $date,
                'score' => $score,
                'passMark' => 60,
                'questions' => $questions,
                'questionsAnswered' => $questionsAnswered,
                'attempts' => isset($latestResult['numberTest']) ? (int) $latestResult['numberTest'] : 0,
                'feedback' => null,
                'brand' => $brandSlug,
                'brands' => $brandSlugs,
                'medacapCreated' => $createdRaw,
                'medacap' => [
                    'userId' => $userIdHex,
                    'testId' => $testIdHex,
                    'allocationId' => isset($allocate['_id']) ? (string) $allocate['_id'] : null,
                    'level' => $level,
                    'type' => $type,
                    'launchUrl' => portalApiLaunchUrl(
                        $portalBridgeKey,
                        $userIdHex,
                        $testIdHex,
                        $level,
                        $type
                    ),
                ],
            ];

            if ($collaboratorName) {
                $row['collaboratorName'] = $collaboratorName;
            }
            if ($agency) {
                $row['agency'] = $agency;
            }
            if ($declarativeStage !== null) {
                $row['declarativeStage'] = $declarativeStage;
            }

            $evaluations[] = $row;
        }
    }

    return $evaluations;
}

function portalApiParseMedacapDate(?string $raw): ?\DateTimeImmutable
{
    if (!$raw || trim($raw) === '' || $raw === '—') {
        return null;
    }
    $raw = trim($raw);
    $formats = ['d-m-y H:i:s', 'd-m-Y H:i:s', 'Y-m-d H:i:s', \DateTimeInterface::ATOM];
    foreach ($formats as $fmt) {
        $dt = \DateTimeImmutable::createFromFormat($fmt, $raw);
        if ($dt instanceof \DateTimeImmutable) {
            return $dt;
        }
    }
    $ts = strtotime($raw);
    if ($ts !== false) {
        return (new \DateTimeImmutable())->setTimestamp($ts);
    }
    return null;
}

/** @return array{time: string, dayGroup: string, at: string} */
function portalApiActivityTimeMeta(?string $createdRaw): array
{
    $dt = portalApiParseMedacapDate($createdRaw);
    if (!$dt) {
        return ['time' => '—', 'dayGroup' => 'week', 'at' => ''];
    }

    $now = new \DateTimeImmutable('today');
    $day = $dt->setTime(0, 0, 0);
    $diffDays = (int) $now->diff($day)->format('%r%a');

    if ($diffDays === 0) {
        return [
            'time' => $dt->format('H:i'),
            'dayGroup' => 'today',
            'at' => $dt->format(\DateTimeInterface::ATOM),
        ];
    }
    if ($diffDays === -1) {
        return [
            'time' => 'Hier',
            'dayGroup' => 'yesterday',
            'at' => $dt->format(\DateTimeInterface::ATOM),
        ];
    }

    return [
        'time' => $dt->format('d/m'),
        'dayGroup' => 'week',
        'at' => $dt->format(\DateTimeInterface::ATOM),
    ];
}

/** @param mixed $value @return list<string> */
function portalApiGfStringList($value): array
{
    if ($value instanceof \MongoDB\Model\BSONArray) {
        $value = $value->getArrayCopy();
    }
    if (!is_array($value)) {
        return [];
    }
    return array_values(array_filter(array_map(
        static fn ($v) => trim((string) $v),
        $value
    ), static fn ($v) => $v !== ''));
}

function portalApiNormalizeSpeciality(string $value): string
{
    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
}

function portalApiSpecialityMatches(string $resultSpec, string $supportedSpec): bool
{
    $a = mb_strtoupper(portalApiNormalizeSpeciality($resultSpec), 'UTF-8');
    $b = mb_strtoupper(portalApiNormalizeSpeciality($supportedSpec), 'UTF-8');
    if ($a === $b) {
        return true;
    }
    $lenB = strlen($b);
    $lenA = strlen($a);
    if ($lenB > 0 && strncmp($a, $b, $lenB) === 0) {
        return true;
    }
    if ($lenA > 0 && strncmp($b, $a, $lenA) === 0) {
        return true;
    }
    return false;
}

/** @return array<string, array<string, list<string>>> brand → level → GF[] */
function portalApiLoadSupportedGroupsCache($db): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    $refJunior = [];

    foreach ($db->nonSupportedGroupsByBrandLevel->find([]) as $doc) {
        $arr = $doc instanceof \MongoDB\Model\BSONDocument ? $doc->getArrayCopy() : (array) $doc;
        $brand = trim((string) ($arr['brand'] ?? ''));
        if ($brand === '') {
            $refJunior = portalApiGfStringList($arr['supportedGroups'] ?? []);
            continue;
        }
        if (!isset($cache[$brand])) {
            $cache[$brand] = [];
        }
        foreach ($arr['levels'] ?? [] as $lvl) {
            $lvlArr = $lvl instanceof \MongoDB\Model\BSONDocument ? $lvl->getArrayCopy() : (array) $lvl;
            $level = (string) ($lvlArr['level'] ?? '');
            if ($level === '') {
                continue;
            }
            $supported = portalApiGfStringList($lvlArr['supportedGroups'] ?? []);
            if ($supported) {
                $cache[$brand][$level] = $supported;
            }
        }
    }

    if ($refJunior) {
        $configPath = dirname(__DIR__, 2) . '/define/configGF.php';
        $cfg = is_file($configPath) ? require $configPath : [];
        $excludedByBrand = is_array($cfg) ? ($cfg['nonSupportedGroupsByBrand'] ?? []) : [];
        foreach ($excludedByBrand as $brand => $excluded) {
            if (!isset($cache[$brand]['Junior'])) {
                $excludedList = portalApiGfStringList($excluded);
                $cache[$brand]['Junior'] = array_values(array_filter(
                    $refJunior,
                    static fn (string $gf) => !in_array($gf, $excludedList, true)
                ));
            }
        }
    }

    return $cache;
}

/** @return list<string> */
function portalApiSupportedGroupsForBrandLevel(array $cache, string $brandMongo, string $level): array
{
    return $cache[$brandMongo][$level] ?? [];
}

/**
 * Indexe les résultats GF d'un technicien pour un palier.
 *
 * @return array<string, array<string, array<string, mixed>>>
 */
function portalApiBuildUserGfResultIndex($results, ObjectId $userOid, string $level): array
{
    $index = [];
    $cursor = $results->find(
        [
            'user' => $userOid,
            'level' => $level,
            'speciality' => ['$exists' => true],
        ],
        ['sort' => ['_id' => -1]]
    );

    foreach ($cursor as $doc) {
        $row = $doc instanceof \MongoDB\Model\BSONDocument ? $doc->getArrayCopy() : (array) $doc;
        $spec = portalApiNormalizeSpeciality((string) ($row['speciality'] ?? ''));
        if ($spec === '') {
            continue;
        }
        $type = (string) ($row['type'] ?? '');
        $typeR = (string) ($row['typeR'] ?? '');
        $slot = $type . '|' . $typeR;
        if (!isset($index[$spec])) {
            $index[$spec] = [];
        }
        if (!isset($index[$spec][$slot])) {
            $index[$spec][$slot] = $row;
        }
    }

    return $index;
}

function portalApiFindGfResult(array $index, string $gf, string $type, string $typeR): ?array
{
    foreach ($index as $spec => $bySlot) {
        if (!portalApiSpecialityMatches($spec, $gf)) {
            continue;
        }
        $slot = $type . '|' . $typeR;
        return $bySlot[$slot] ?? null;
    }
    return null;
}

function portalApiFindGfFactuelResult(array $index, string $gf): ?array
{
    foreach (['Technicien', ''] as $typeR) {
        $row = portalApiFindGfResult($index, $gf, 'Factuel', $typeR);
        if ($row) {
            return $row;
        }
    }
    return null;
}

function portalApiFindGfDeclaratifTechnicienResult(array $index, string $gf): ?array
{
    return portalApiFindGfResult($index, $gf, 'Declaratif', 'Technicien')
        ?? portalApiFindGfResult($index, $gf, 'Declaratif', 'Techniciens');
}

function portalApiFindGfDeclaratifManagerResult(array $index, string $gf): ?array
{
    return portalApiFindGfResult($index, $gf, 'Declaratif', 'Manager');
}

/** @param mixed $value @return array<string, mixed>|list<mixed> */
function portalApiDeepArray($value): array
{
    if ($value instanceof \MongoDB\Model\BSONDocument || $value instanceof \MongoDB\Model\BSONArray) {
        $value = $value->getArrayCopy();
    }
    if (!is_array($value)) {
        return [];
    }
    foreach ($value as $key => $item) {
        if ($item instanceof \MongoDB\Model\BSONDocument || $item instanceof \MongoDB\Model\BSONArray) {
            $value[$key] = portalApiDeepArray($item);
        }
    }
    return $value;
}

/** @return array<string, array<string, array<string, mixed>>>|null level → brand → scores */
function portalApiLoadTechnicianBrandScoresDoc($db, ObjectId $userOid): ?array
{
    $doc = $db->technicianBrandScores->findOne(['userId' => $userOid]);
    if (!$doc) {
        return null;
    }
    $arr = $doc instanceof \MongoDB\Model\BSONDocument ? $doc->getArrayCopy() : (array) $doc;
    if (!isset($arr['scores'])) {
        return null;
    }
    $scores = portalApiDeepArray($arr['scores']);
    return $scores ?: null;
}

/** @return array{theory: ?int, practical: ?int, score: ?int, gfEvaluated: int, gfTotal: int, inProgress: bool, declarativeStage: ?string}|null */
function portalApiBrandTierScoresFromPrecomputed(?array $scoresDoc, string $brandMongo, string $level): ?array
{
    if (!$scoresDoc || !isset($scoresDoc[$level][$brandMongo])) {
        return null;
    }
    $row = $scoresDoc[$level][$brandMongo];
    if (!is_array($row)) {
        return null;
    }

    $theory = isset($row['averageFactuel']) ? (int) round((float) $row['averageFactuel']) : null;
    $practical = isset($row['averageDeclaratif']) ? (int) round((float) $row['averageDeclaratif']) : null;
    $score = null;
    if (isset($row['averageTotalWithPenalty'])) {
        $score = (int) round((float) $row['averageTotalWithPenalty']);
    } elseif (isset($row['averageTotal'])) {
        $score = (int) round((float) $row['averageTotal']);
    } else {
        $score = portalApiDpsPersonModalityAverage($theory, $practical);
    }

    $declarativeStage = null;
    $inProgress = false;
    if ($theory !== null && $practical === null) {
        $inProgress = true;
        $declarativeStage = 'awaiting_technician';
    } elseif ($theory === null && $practical === null) {
        return null;
    }

    return [
        'theory' => $theory,
        'practical' => $practical,
        'score' => $score,
        'gfEvaluated' => 0,
        'gfTotal' => 0,
        'inProgress' => $inProgress && $score === null,
        'declarativeStage' => $declarativeStage,
    ];
}

/**
 * Score marque × palier = moyenne des moyennes GF (Factuel + concordance Déclaratif).
 *
 * @return array{
 *   theory: ?int,
 *   practical: ?int,
 *   score: ?int,
 *   gfEvaluated: int,
 *   gfTotal: int,
 *   inProgress: bool,
 *   declarativeStage: ?string
 * }
 */
function portalApiBrandGfTierBreakdown(
    array $gfIndex,
    array $supportedGroups
): array {
    $factuelScores = [];
    $practicalScores = [];
    $gfAvgs = [];
    $awaitingManager = false;
    $awaitingTechnician = false;
    $hasPartial = false;

    foreach ($supportedGroups as $gf) {
        $factuel = portalApiFindGfFactuelResult($gfIndex, $gf);
        $technicienDecl = portalApiFindGfDeclaratifTechnicienResult($gfIndex, $gf);
        $managerDecl = portalApiFindGfDeclaratifManagerResult($gfIndex, $gf);

        $factuelPct = portalApiPercentScore($factuel);
        $practicalPct = null;
        if ($technicienDecl && $managerDecl) {
            $techPct = portalApiPercentScore($technicienDecl);
            $mgrPct = portalApiPercentScore($managerDecl);
            if ($techPct !== null && $mgrPct !== null) {
                $practicalPct = (int) round(($techPct + $mgrPct) / 2);
            }
        }

        if ($factuelPct !== null) {
            $factuelScores[] = $factuelPct;
        }
        if ($practicalPct !== null) {
            $practicalScores[] = $practicalPct;
        }

        $gfParts = array_values(array_filter(
            [$factuelPct, $practicalPct],
            static fn ($v) => $v !== null
        ));
        if ($gfParts) {
            $gfAvgs[] = (int) round(array_sum($gfParts) / count($gfParts));
        }

        if ($factuelPct !== null && $practicalPct === null) {
            $hasPartial = true;
            if ($technicienDecl && !$managerDecl) {
                $awaitingManager = true;
            } else {
                $awaitingTechnician = true;
            }
        }
    }

    $theory = $factuelScores
        ? (int) round(array_sum($factuelScores) / count($factuelScores))
        : null;
    $practical = $practicalScores
        ? (int) round(array_sum($practicalScores) / count($practicalScores))
        : null;
    $score = portalApiDpsPersonModalityAverage($theory, $practical);

    $declarativeStage = null;
    if ($awaitingManager) {
        $declarativeStage = 'awaiting_manager';
    } elseif ($awaitingTechnician) {
        $declarativeStage = 'technician_in_progress';
    } elseif ($theory !== null && $practical === null && !$hasPartial) {
        $declarativeStage = 'awaiting_technician';
    }

    return [
        'theory' => $theory,
        'practical' => $practical,
        'score' => $score,
        'gfEvaluated' => count($gfAvgs),
        'gfTotal' => count($supportedGroups),
        'inProgress' => $hasPartial && $score === null,
        'declarativeStage' => $declarativeStage,
    ];
}

/**
 * Évaluations compétence agrégées par marque (GF / speciality).
 *
 * @return list<array<string, mixed>>
 */
function portalApiUserBrandGfEvaluations(
    $db,
    $results,
    ObjectId $userOid,
    array $userDoc,
    array $supportedGroupsCache,
    ?string $collaboratorName = null,
    ?string $agency = null
): array {
    $userIdHex = (string) $userOid;
    $evaluations = [];
    $levels = ['Junior', 'Senior', 'Expert'];
    $precomputed = portalApiLoadTechnicianBrandScoresDoc($db, $userOid);

    foreach ($levels as $level) {
        $tier = portalApiTierFromLevel($level);
        $brands = portalApiBrandsForTier($userDoc, $tier);
        if (!$brands) {
            continue;
        }

        $gfIndex = portalApiBuildUserGfResultIndex($results, $userOid, $level);

        foreach ($brands as $brandMongo) {
            $brandSlug = portalApiBrandToSlug($brandMongo);
            $breakdown = portalApiBrandTierScoresFromPrecomputed($precomputed, $brandMongo, $level);

            if ($breakdown === null) {
                $supported = portalApiSupportedGroupsForBrandLevel($supportedGroupsCache, $brandMongo, $level);
                if (!$supported) {
                    continue;
                }
                $breakdown = portalApiBrandGfTierBreakdown($gfIndex, $supported);
            }

            if (
                $breakdown['theory'] === null
                && $breakdown['practical'] === null
                && !$breakdown['inProgress']
                && !$breakdown['declarativeStage']
            ) {
                continue;
            }

            $base = [
                'kind' => 'competency',
                'competencyTier' => $tier,
                'certLevel' => portalApiCertLevelFromTier($tier),
                'passMark' => 60,
                'brand' => $brandSlug,
                'brands' => [$brandSlug],
                'scoreSource' => $precomputed ? 'technicianBrandScores' : 'gf',
                'gfEvaluated' => $breakdown['gfEvaluated'],
                'gfTotal' => $breakdown['gfTotal'],
                'medacap' => ['userId' => $userIdHex, 'level' => $level, 'brandMongo' => $brandMongo],
            ];
            if ($collaboratorName) {
                $base['collaboratorName'] = $collaboratorName;
            }
            if ($agency) {
                $base['agency'] = $agency;
            }

            if ($breakdown['theory'] !== null) {
                $evaluations[] = array_merge($base, [
                    'id' => 'medacap-gf-' . $userIdHex . '-' . $brandSlug . '-' . $tier . '-t',
                    'modality' => 'theory',
                    'status' => 'done',
                    'date' => '—',
                    'score' => $breakdown['theory'],
                ]);
            }

            if ($breakdown['practical'] !== null) {
                $evaluations[] = array_merge($base, [
                    'id' => 'medacap-gf-' . $userIdHex . '-' . $brandSlug . '-' . $tier . '-p',
                    'modality' => 'practical',
                    'status' => 'done',
                    'date' => '—',
                    'score' => $breakdown['practical'],
                ]);
            } elseif ($breakdown['inProgress'] || $breakdown['declarativeStage']) {
                $row = array_merge($base, [
                    'id' => 'medacap-gf-' . $userIdHex . '-' . $brandSlug . '-' . $tier . '-p',
                    'modality' => 'practical',
                    'status' => 'progress',
                    'date' => '—',
                    'score' => null,
                ]);
                if ($breakdown['declarativeStage']) {
                    $row['declarativeStage'] = $breakdown['declarativeStage'];
                }
                $evaluations[] = $row;
            }

            if ($breakdown['theory'] === null && $breakdown['inProgress']) {
                $evaluations[] = array_merge($base, [
                    'id' => 'medacap-gf-' . $userIdHex . '-' . $brandSlug . '-' . $tier . '-t',
                    'modality' => 'theory',
                    'status' => 'progress',
                    'date' => '—',
                    'score' => null,
                ]);
            }
        }
    }

    return $evaluations;
}

/**
 * Agrège évaluations filiale → brandStats, collaboratorEvaluations, recentLogs.
 *
 * @param list<array<string, mixed>> $technicianDocs
 * @return array{collaboratorEvaluations: list<array>, brandStats: list<array>, recentLogs: list<array>}
 */
function portalApiDpsFilialeInsights(
    array $technicianDocs,
    $db,
    $allocations,
    $exams,
    $results,
    string $portalBridgeKey
): array {
    $supportedGroupsCache = portalApiLoadSupportedGroupsCache($db);
    $collaboratorEvaluations = [];
    $recentCandidates = [];

    foreach ($technicianDocs as $tech) {
        $userOid = $tech['_id'];
        $userArr = $tech['doc'];
        $name = portalApiTechnicianDisplayName($userArr);
        $agency = trim((string) ($userArr['agency'] ?? ''));
        $agency = ($agency !== '' && $agency !== '—') ? $agency : null;

        $gfRows = portalApiUserBrandGfEvaluations(
            $db,
            $results,
            $userOid,
            $userArr,
            $supportedGroupsCache,
            $name,
            $agency
        );
        foreach ($gfRows as $row) {
            $collaboratorEvaluations[] = $row;
        }

        $legacyRows = portalApiUserCompetencyEvaluations(
            $userOid,
            $userArr,
            $allocations,
            $exams,
            $results,
            $portalBridgeKey,
            $name,
            $agency
        );

        foreach ($legacyRows as $row) {
            if ($row['status'] === 'done' && !empty($row['medacapCreated'])) {
                $recentCandidates[] = $row;
            }
        }
    }

    usort($recentCandidates, static function (array $a, array $b): int {
        $da = portalApiParseMedacapDate($a['medacapCreated'] ?? null);
        $db = portalApiParseMedacapDate($b['medacapCreated'] ?? null);
        $ta = $da ? $da->getTimestamp() : 0;
        $tb = $db ? $db->getTimestamp() : 0;
        return $tb <=> $ta;
    });

    $recentLogs = [];
    foreach (array_slice($recentCandidates, 0, 20) as $row) {
        $timeMeta = portalApiActivityTimeMeta($row['medacapCreated'] ?? null);
        $modality = $row['modality'] === 'practical' ? 'pratique' : 'théorique';
        $name = $row['collaboratorName'] ?? 'Technicien';
        $agency = $row['agency'] ?? '';
        $agencySuffix = $agency ? " — {$agency}" : '';

        $recentLogs[] = [
            'type' => 'test',
            'brand' => $row['brand'] ?? null,
            'score' => $row['score'] ?? null,
            'summary' => "Test {$modality} {$row['competencyTier']} — {$name}{$agencySuffix}",
            'time' => $timeMeta['time'],
            'dayGroup' => $timeMeta['dayGroup'],
            'at' => $timeMeta['at'] ?: null,
        ];
    }

    $brandStats = portalApiDpsAggregateBrandStats($collaboratorEvaluations, $technicianDocs);

    return [
        'collaboratorEvaluations' => $collaboratorEvaluations,
        'brandStats' => $brandStats,
        'recentLogs' => $recentLogs,
    ];
}

/**
 * Moyenne théorie + pratique pour un technicien (modalités terminées).
 */
function portalApiDpsPersonModalityAverage(?int $theory, ?int $practical): ?int
{
    if ($theory === null || $practical === null) {
        return null;
    }

    return min($theory, $practical);
}

/**
 * Moyenne marque = moyenne des moyennes technicien.
 *
 * @param list<int> $personScores
 */
function portalApiDpsBrandAverageFromPersonScores(array $personScores): ?int
{
    if (!$personScores) {
        return null;
    }
    return (int) round(array_sum($personScores) / count($personScores));
}

/**
 * @param array<string, array<string, array<string, mixed>>> $collaborators
 * @param list<string> $tiersToScan
 * @return array{evaluated: int, avgScore: ?int}
 */
function portalApiDpsBrandScoresFromCollaborators(array $collaborators, array $tiersToScan): array
{
    $personScores = [];
    $evaluated = [];

    foreach ($collaborators as $name => $tiers) {
        $personCounted = false;
        foreach ($tiersToScan as $tier) {
            $row = $tiers[$tier] ?? [];
            $theory = isset($row['theory']) ? (int) $row['theory'] : null;
            $practical = isset($row['practical']) ? (int) $row['practical'] : null;
            $personAvg = portalApiDpsPersonModalityAverage($theory, $practical);
            if ($personAvg !== null) {
                $personScores[] = $personAvg;
                $personCounted = true;
            }
            if (!empty($row['inProgress'])) {
                $personCounted = true;
            }
        }
        if ($personCounted) {
            $evaluated[$name] = true;
        }
    }

    return [
        'evaluated' => count($evaluated),
        'avgScore' => portalApiDpsBrandAverageFromPersonScores($personScores),
    ];
}

/**
 * @param list<array<string, mixed>> $evaluations
 * @param list<array{_id: ObjectId, doc: array}> $technicianDocs
 * @return list<array<string, mixed>>
 */
function portalApiDpsAggregateBrandStats(array $evaluations, array $technicianDocs): array
{
    $techCountBySlug = [];
    foreach ($technicianDocs as $tech) {
        $slugs = array_values(array_unique(array_map(
            'portalApiBrandToSlug',
            portalApiExtractUserBrands($tech['doc'])
        ), SORT_STRING));
        foreach ($slugs as $slug) {
            $techCountBySlug[$slug] = ($techCountBySlug[$slug] ?? 0) + 1;
        }
    }

    $userDocById = [];
    foreach ($technicianDocs as $tech) {
        $userDocById[(string) $tech['_id']] = $tech['doc'];
    }

    /** @var array<string, array<string, array<string, array<string, mixed>>>> $buckets */
    $buckets = [];
    foreach ($evaluations as $ev) {
        $tier = $ev['competencyTier'] ?? 'junior';
        $brandSlugs = [];
        if (!empty($ev['brands']) && is_array($ev['brands'])) {
            $brandSlugs = $ev['brands'];
        } elseif (!empty($ev['medacap']['userId'])) {
            $doc = $userDocById[$ev['medacap']['userId']] ?? null;
            if ($doc) {
                $brandSlugs = portalApiBrandSlugsForTier($doc, $tier);
            }
        }
        if (!$brandSlugs && !empty($ev['brand'])) {
            $brandSlugs = [$ev['brand']];
        }
        if (!$brandSlugs) {
            continue;
        }

        $name = $ev['collaboratorName'] ?? ($ev['medacap']['userId'] ?? null);
        if (!$name) {
            continue;
        }

        $modality = ($ev['modality'] ?? '') === 'practical' ? 'practical' : 'theory';

        foreach ($brandSlugs as $brand) {
            if (!isset($buckets[$brand])) {
                $buckets[$brand] = [];
            }
            if (!isset($buckets[$brand][$name])) {
                $buckets[$brand][$name] = [
                    'junior' => [],
                    'senior' => [],
                    'expert' => [],
                ];
            }
            if (!isset($buckets[$brand][$name][$tier])) {
                $buckets[$brand][$name][$tier] = [];
            }

            if ($ev['status'] === 'done' && $ev['score'] !== null) {
                $buckets[$brand][$name][$tier][$modality] = (int) $ev['score'];
            } elseif ($ev['status'] === 'progress') {
                $buckets[$brand][$name][$tier]['inProgress'] = true;
            }
        }
    }

    $stats = [];
    foreach ($techCountBySlug as $slug => $technicians) {
        $collaborators = $buckets[$slug] ?? [];
        $allTiers = ['junior', 'senior', 'expert'];
        $overall = portalApiDpsBrandScoresFromCollaborators($collaborators, $allTiers);

        $tiers = [];
        foreach ($allTiers as $tier) {
            $tiers[$tier] = portalApiDpsBrandScoresFromCollaborators($collaborators, [$tier]);
        }

        $stats[] = [
            'brand' => $slug,
            'technicians' => $technicians,
            'avgScore' => $overall['avgScore'],
            'evaluated' => $overall['evaluated'],
            'tiers' => $tiers,
        ];
    }

    usort($stats, static function (array $a, array $b): int {
        return ($b['technicians'] ?? 0) <=> ($a['technicians'] ?? 0)
            ?: strcmp($a['brand'], $b['brand']);
    });

    return $stats;
}

/** @return list<string> */
function portalApiResolveSubsidiaries(string $subsidiary): array
{
    $aliasesFile = dirname(__DIR__, 2) . '/define/components/subsidiaryAliases.php';
    $aliases = is_file($aliasesFile) ? require $aliasesFile : [];
    if (!is_array($aliases)) {
        $aliases = [];
    }

    foreach ($aliases as $key => $list) {
        $group = array_values(array_unique(array_merge([(string) $key], array_map('strval', (array) $list))));
        if (in_array($subsidiary, $group, true)) {
            return $group;
        }
    }

    return [$subsidiary];
}

/** @return string|array<string> */
function portalApiTechnicianDepartmentFilter(string $department)
{
    $normalized = strtoupper(trim($department));
    if ($normalized === 'EQUIPMENT') {
        return 'Equipment';
    }
    // strpos() plutot que str_contains() : Apache tourne en PHP 7.4, ou str_contains()
    // n'existe pas (PHP 8.0+). Tout departement != 'Equipment' atteignait cette ligne
    // et renvoyait une 500 — le portail DPS ne le voyait pas car 'Equipment' sort avant.
    if (strpos($normalized, 'EQUIPMENT') !== false && strpos($normalized, 'MOTOR') !== false) {
        return ['Equipment', 'Motors'];
    }
    if ($normalized !== '') {
        return $department;
    }
    return 'Equipment';
}

function portalApiAgencyIdFromName(string $name): string
{
    $slug = mb_strtolower(trim($name), 'UTF-8');
    $slug = str_replace(
        ['à', 'á', 'â', 'ã', 'ä', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ú', 'û', 'ü', 'ç', 'ñ'],
        ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c', 'n'],
        $slug
    );
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
    return trim($slug, '-') ?: 'agency';
}

function portalApiAuthorizePortalRequest(): void
{
    global $portalBridgeKey;
    $providedKey = $_GET['key'] ?? ($_SERVER['HTTP_X_PORTAL_KEY'] ?? '');
    if ($providedKey !== $portalBridgeKey) {
        portalApiJson(['error' => 'Unauthorized'], 401);
    }
}

/** Comptes démo / test — exclus du périmètre DPS filiale. */
function portalApiIsExcludedDemoTechnician(array $doc): bool
{
    if (!empty($doc['demoTechnicien'])) {
        return true;
    }
    $username = trim((string) ($doc['username'] ?? ''));
    return stripos($username, 'DEMO Technicien') === 0;
}

/**
 * Contexte filiale DPS (utilisateur connecté → techniciens du périmètre).
 *
 * @return array{
 *   userId: string,
 *   subsidiary: string,
 *   department: string,
 *   technicianDepartment: string|array<string>,
 *   technicianDocs: list<array{_id: ObjectId, doc: array}>,
 *   technicians: list<array<string, mixed>>,
 *   brands: list<array<string, mixed>>,
 *   agencies: list<array<string, mixed>>,
 *   totalTechnicians: int
 * }
 */
function portalApiResolveDpsFilialeContext($db, ?string $userIdParam, ?string $username, ?string $email): array
{
    $users = $db->users;
    $userOid = portalApiResolveUserId($users, $userIdParam, $username, $email);
    if (!$userOid) {
        portalApiJson(['error' => 'User not found'], 404);
    }

    $userDoc = $users->findOne(['_id' => $userOid, 'active' => true]);
    if (!$userDoc) {
        portalApiJson(['error' => 'User inactive'], 404);
    }

    $userArr = $userDoc instanceof \MongoDB\Model\BSONDocument ? $userDoc->getArrayCopy() : (array) $userDoc;
    $subsidiary = trim((string) ($userArr['subsidiary'] ?? ''));
    if ($subsidiary === '') {
        portalApiJson(['error' => 'No subsidiary on user'], 400);
    }

    $department = trim((string) ($userArr['department'] ?? 'Equipment'));
    $techDepartment = portalApiTechnicianDepartmentFilter($department);
    $subsidiaries = portalApiResolveSubsidiaries($subsidiary);

    $cursor = $users->find(
        [
            'profile' => 'Technicien',
            'active' => true,
            'department' => $techDepartment,
            'subsidiary' => ['$in' => $subsidiaries],
            'demoTechnicien' => ['$ne' => true],
        ],
        [
            'projection' => [
                'username' => 1,
                'firstName' => 1,
                'lastName' => 1,
                'agency' => 1,
                'brandJunior' => 1,
                'brandSenior' => 1,
                'brandExpert' => 1,
            ],
        ]
    );

    $brandTechIds = [];
    $agencyCounts = [];
    $technicians = [];
    $technicianDocs = [];
    $total = 0;

    foreach ($cursor as $tech) {
        $techArr = $tech instanceof \MongoDB\Model\BSONDocument ? $tech->getArrayCopy() : (array) $tech;
        if (portalApiIsExcludedDemoTechnician($techArr)) {
            continue;
        }
        $total++;
        $techOid = $techArr['_id'] ?? null;
        if ($techOid instanceof \MongoDB\BSON\ObjectId) {
            $technicianDocs[] = ['_id' => $techOid, 'doc' => $techArr];
        }
        $usernameTech = (string) ($techArr['username'] ?? $techArr['_id'] ?? $total);
        $agency = trim((string) ($techArr['agency'] ?? '—'));
        if ($agency !== '') {
            $agencyCounts[$agency] = ($agencyCounts[$agency] ?? 0) + 1;
        }

        $brandSlugs = array_values(array_unique(array_map(
            'portalApiBrandToSlug',
            portalApiExtractUserBrands($techArr)
        ), SORT_STRING));

        $technicians[] = [
            'id' => (string) ($techArr['_id'] ?? ''),
            'name' => portalApiTechnicianDisplayName($techArr),
            'agency' => ($agency !== '' && $agency !== '—') ? $agency : null,
            'primaryTier' => portalApiTechnicianPrimaryTier($techArr),
            'brands' => $brandSlugs,
        ];

        foreach (portalApiExtractUserBrands($techArr) as $brandName) {
            $brandTechIds[$brandName][$usernameTech] = true;
        }
    }

    usort($technicians, static function (array $a, array $b): int {
        return strcasecmp($a['name'], $b['name']);
    });

    $brands = [];
    foreach ($brandTechIds as $brandName => $techMap) {
        $count = count($techMap);
        if ($count < 1) {
            continue;
        }
        $brands[] = [
            'name' => $brandName,
            'slug' => portalApiBrandToSlug($brandName),
            'technicians' => $count,
        ];
    }

    usort($brands, static function (array $a, array $b): int {
        return $b['technicians'] <=> $a['technicians']
            ?: strcasecmp($a['name'], $b['name']);
    });

    $agencies = [];
    ksort($agencyCounts, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($agencyCounts as $name => $count) {
        $agencies[] = [
            'id' => portalApiAgencyIdFromName($name),
            'name' => $name,
            'technicians' => $count,
        ];
    }

    return [
        'userId' => (string) $userOid,
        'subsidiary' => $subsidiary,
        'department' => $department,
        'technicianDepartment' => $techDepartment,
        'technicianDocs' => $technicianDocs,
        'technicians' => $technicians,
        'brands' => $brands,
        'agencies' => $agencies,
        'totalTechnicians' => $total,
    ];
}

function portalApiAnnualStatusPriority(string $status): int
{
    static $map = [
        'fait' => 6,
        'en_cours' => 5,
        'inscrit' => 4,
        'a_inscrire' => 3,
        'a_programmer' => 2,
        'partiel' => 1,
        'non_fait' => 0,
    ];
    return $map[$status] ?? 0;
}

function portalApiMergeAnnualStatus(string $current, string $incoming): string
{
    return portalApiAnnualStatusPriority($incoming) > portalApiAnnualStatusPriority($current)
        ? $incoming
        : $current;
}

function portalApiFirstTrainingDateIso(array $training): ?string
{
    $dates = $training['startDates'] ?? [];
    if ($dates instanceof \MongoDB\Model\BSONArray) {
        $dates = $dates->getArrayCopy();
    }
    foreach ((array) $dates as $raw) {
        $raw = trim((string) $raw);
        if ($raw === '') {
            continue;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
            return $m[1];
        }
        $normalized = str_replace('/', '-', $raw);
        $ts = strtotime($normalized);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }
    }
    return null;
}

function portalApiTrainingAllocationAnnualStatus(?array $allocation, ?array $application, ?array $validation): string
{
    if ($validation && ($validation['status'] ?? '') === 'Validé') {
        return 'fait';
    }
    if ($application) {
        $appStatus = strtolower(trim((string) ($application['status'] ?? '')));
        if ($appStatus === 'confirmed' || $appStatus === 'confirmé') {
            return !empty($allocation['active']) ? 'en_cours' : 'inscrit';
        }
    }
    if (!empty($allocation['active'])) {
        return 'en_cours';
    }
    return 'a_inscrire';
}

/**
 * PIF filiale : planningStatus + stats par marque (Define trainings / allocations / validations).
 *
 * @param list<ObjectId> $techOids
 * @return array{planningStatus: array<string, string>, brandPifStats: list<array<string, mixed>>}
 */
function portalApiDpsPifPayload($db, array $techOids): array
{
    $empty = [
        'planningStatus' => [],
        'brandPifStats' => [],
        'brandPlanningSummary' => [],
        'pifTechnicians' => [],
    ];
    if (!$techOids) {
        return $empty;
    }

    $allocations = $db->allocations;
    $trainings = $db->trainings;
    $validations = $db->validations;
    $applications = $db->applications;

    $allocCursor = $allocations->find([
        'user' => ['$in' => $techOids],
        'training' => ['$exists' => true, '$ne' => null],
    ]);

    $allAllocs = [];
    $trainingIds = [];
    foreach ($allocCursor as $alloc) {
        $arr = $alloc instanceof \MongoDB\Model\BSONDocument ? $alloc->getArrayCopy() : (array) $alloc;
        if (empty($arr['training'])) {
            continue;
        }
        $allAllocs[] = $arr;
        $trainingIds[(string) $arr['training']] = $arr['training'];
    }

    if (!$allAllocs) {
        return $empty;
    }

    $trainingMap = [];
    $trainingCursor = $trainings->find([
        '_id' => ['$in' => array_values($trainingIds)],
        'active' => true,
    ]);
    foreach ($trainingCursor as $doc) {
        $arr = $doc instanceof \MongoDB\Model\BSONDocument ? $doc->getArrayCopy() : (array) $doc;
        $trainingMap[(string) $arr['_id']] = $arr;
    }

    $validationMap = [];
    $valCursor = $validations->find([
        'user' => ['$in' => $techOids],
        'training' => ['$in' => array_values($trainingIds)],
        'status' => 'Validé',
        'active' => true,
    ]);
    foreach ($valCursor as $doc) {
        $arr = $doc instanceof \MongoDB\Model\BSONDocument ? $doc->getArrayCopy() : (array) $doc;
        $validationMap[(string) $arr['user'] . '|' . (string) $arr['training']] = $arr;
    }

    $applicationMap = [];
    $appCursor = $applications->find([
        'user' => ['$in' => $techOids],
        'training' => ['$in' => array_values($trainingIds)],
        'active' => true,
    ]);
    foreach ($appCursor as $doc) {
        $arr = $doc instanceof \MongoDB\Model\BSONDocument ? $doc->getArrayCopy() : (array) $doc;
        $applicationMap[(string) $arr['user'] . '|' . (string) $arr['training']] = $arr;
    }

    $planningStatus = [];
    $brandPif = [];
    $brandPlanningSummary = [];
    /** @var array<string, array{codesByBrand: array<string, array<string, true>>, byBrand: array<string, int>, planningStatus: array<string, string>}> */
    $byUser = [];

    foreach ($allAllocs as $alloc) {
        $trainingId = (string) $alloc['training'];
        $training = $trainingMap[$trainingId] ?? null;
        if (!$training) {
            continue;
        }

        $userKey = (string) $alloc['user'];
        $pairKey = $userKey . '|' . $trainingId;
        $validation = $validationMap[$pairKey] ?? null;
        $application = $applicationMap[$pairKey] ?? null;
        $status = portalApiTrainingAllocationAnnualStatus($alloc, $application, $validation);

        $code = trim((string) ($training['code'] ?? ''));
        if ($code === '') {
            continue;
        }

        $dateIso = portalApiFirstTrainingDateIso($training);
        $planKey = ($dateIso ?? '') . '|' . $code;
        if (isset($planningStatus[$planKey])) {
            $planningStatus[$planKey] = portalApiMergeAnnualStatus($planningStatus[$planKey], $status);
        } else {
            $planningStatus[$planKey] = $status;
        }

        $brandSlug = portalApiBrandToSlug((string) ($training['brand'] ?? ''));
        if ($brandSlug === '') {
            continue;
        }
        if (!isset($brandPif[$brandSlug])) {
            $brandPif[$brandSlug] = ['brand' => $brandSlug, 'pifProposed' => 0, 'pifValidated' => 0];
        }
        $brandPif[$brandSlug]['pifProposed']++;
        if ($status === 'fait') {
            $brandPif[$brandSlug]['pifValidated']++;
        }

        if (!isset($brandPlanningSummary[$brandSlug])) {
            $brandPlanningSummary[$brandSlug] = [
                'total' => 0,
                'done' => 0,
                'byYear' => [],
                '_keys' => [],
            ];
        }
        $yearKey = ($dateIso && strlen($dateIso) >= 4) ? substr($dateIso, 0, 4) : 'unknown';
        if (!isset($brandPlanningSummary[$brandSlug]['byYear'][$yearKey])) {
            $brandPlanningSummary[$brandSlug]['byYear'][$yearKey] = ['total' => 0, 'done' => 0, '_keys' => []];
        }
        if (!isset($brandPlanningSummary[$brandSlug]['_keys'][$planKey])) {
            $brandPlanningSummary[$brandSlug]['_keys'][$planKey] = true;
            $brandPlanningSummary[$brandSlug]['total']++;
            if ($status === 'fait') {
                $brandPlanningSummary[$brandSlug]['done']++;
            }
        }
        if (!isset($brandPlanningSummary[$brandSlug]['byYear'][$yearKey]['_keys'][$planKey])) {
            $brandPlanningSummary[$brandSlug]['byYear'][$yearKey]['_keys'][$planKey] = true;
            $brandPlanningSummary[$brandSlug]['byYear'][$yearKey]['total']++;
            if ($status === 'fait') {
                $brandPlanningSummary[$brandSlug]['byYear'][$yearKey]['done']++;
            }
        }

        if (!isset($byUser[$userKey])) {
            $byUser[$userKey] = [
                'codesByBrand' => [],
                'byBrand' => [],
                'planningStatus' => [],
            ];
        }
        if (!isset($byUser[$userKey]['codesByBrand'][$brandSlug])) {
            $byUser[$userKey]['codesByBrand'][$brandSlug] = [];
        }
        $codeKey = strtoupper($code);
        if (!isset($byUser[$userKey]['codesByBrand'][$brandSlug][$codeKey])) {
            $byUser[$userKey]['codesByBrand'][$brandSlug][$codeKey] = true;
            $byUser[$userKey]['byBrand'][$brandSlug] = ($byUser[$userKey]['byBrand'][$brandSlug] ?? 0) + 1;
        }
        if (isset($byUser[$userKey]['planningStatus'][$planKey])) {
            $byUser[$userKey]['planningStatus'][$planKey] = portalApiMergeAnnualStatus(
                $byUser[$userKey]['planningStatus'][$planKey],
                $status
            );
        } else {
            $byUser[$userKey]['planningStatus'][$planKey] = $status;
        }
    }

    foreach ($brandPlanningSummary as $slug => $row) {
        unset($brandPlanningSummary[$slug]['_keys']);
        foreach ($brandPlanningSummary[$slug]['byYear'] as $year => $yearRow) {
            unset($brandPlanningSummary[$slug]['byYear'][$year]['_keys']);
        }
    }

    $brandPifStats = array_values($brandPif);
    usort($brandPifStats, static function (array $a, array $b): int {
        return ($b['pifProposed'] ?? 0) <=> ($a['pifProposed'] ?? 0)
            ?: strcmp($a['brand'], $b['brand']);
    });

    $userDocs = [];
    if ($byUser) {
        $userOids = [];
        foreach (array_keys($byUser) as $uid) {
            try {
                $userOids[] = new ObjectId($uid);
            } catch (Throwable $e) {
                // ignore invalid id
            }
        }
        if ($userOids) {
            foreach ($db->users->find(['_id' => ['$in' => $userOids]]) as $doc) {
                $arr = $doc instanceof \MongoDB\Model\BSONDocument ? $doc->getArrayCopy() : (array) $doc;
                $userDocs[(string) $arr['_id']] = $arr;
            }
        }
    }

    $pifTechnicians = [];
    foreach ($byUser as $userKey => $agg) {
        $doc = $userDocs[$userKey] ?? null;
        $codesByBrand = [];
        $pifCodes = [];
        foreach ($agg['codesByBrand'] as $slug => $codeMap) {
            $list = array_keys($codeMap);
            sort($list);
            $codesByBrand[$slug] = $list;
            foreach ($list as $code) {
                $pifCodes[$code] = true;
            }
        }
        ksort($codesByBrand);
        $pifCodesList = array_keys($pifCodes);
        sort($pifCodesList);

        $level = strtoupper(trim((string) (($doc['level'] ?? '') )));
        if ($level === 'EXPERT') {
            $primaryTier = 'expert';
        } elseif ($level === 'SENIOR') {
            $primaryTier = 'senior';
        } else {
            $primaryTier = 'junior';
        }

        $assignedBrands = [];
        if ($doc) {
            foreach (portalApiExtractUserBrands($doc) as $brand) {
                $slug = portalApiBrandToSlug((string) $brand);
                if ($slug !== '') {
                    $assignedBrands[$slug] = true;
                }
            }
        }
        foreach (array_keys($codesByBrand) as $slug) {
            $assignedBrands[$slug] = true;
        }

        $pifTechnicians[] = [
            'id' => $userKey,
            'name' => $doc ? portalApiTechnicianDisplayName($doc) : $userKey,
            'username' => $doc ? (string) ($doc['username'] ?? '') : '',
            'agency' => $doc ? (trim((string) ($doc['agency'] ?? '')) ?: null) : null,
            'level' => $doc ? (trim((string) ($doc['level'] ?? '')) ?: null) : null,
            'primaryTier' => $primaryTier,
            'assignedBrands' => array_keys($assignedBrands),
            'brands' => array_keys($assignedBrands),
            'byBrand' => $agg['byBrand'],
            'codesByBrand' => $codesByBrand,
            'pifCodes' => $pifCodesList,
            'pifTotal' => count($pifCodesList),
            'total' => count($pifCodesList),
            'planningStatus' => $agg['planningStatus'],
        ];
    }

    usort($pifTechnicians, static function (array $a, array $b): int {
        return strcmp((string) $a['name'], (string) $b['name']);
    });

    return [
        'planningStatus' => $planningStatus,
        'brandPifStats' => $brandPifStats,
        'brandPlanningSummary' => $brandPlanningSummary,
        'pifTechnicians' => $pifTechnicians,
    ];
}

/**
 * PIF apprenant (1 technicien).
 *
 * @return array{planningStatus: array<string, string>, planPathway: list<array<string, mixed>>}
 */
function portalApiLearnerPifPayload($db, ObjectId $userOid): array
{
    $pif = portalApiDpsPifPayload($db, [$userOid]);
    $planPathway = [];

    $allocations = $db->allocations->find([
        'user' => $userOid,
        'training' => ['$exists' => true, '$ne' => null],
    ]);

    $trainingIds = [];
    foreach ($allocations as $alloc) {
        $arr = $alloc instanceof \MongoDB\Model\BSONDocument ? $alloc->getArrayCopy() : (array) $alloc;
        if (!empty($arr['training'])) {
            $trainingIds[(string) $arr['training']] = $arr['training'];
        }
    }

    if ($trainingIds) {
        $trainings = $db->trainings->find(['_id' => ['$in' => array_values($trainingIds)], 'active' => true]);
        foreach ($trainings as $doc) {
            $t = $doc instanceof \MongoDB\Model\BSONDocument ? $doc->getArrayCopy() : (array) $doc;
            $code = trim((string) ($t['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $planPathway[] = [
                'code' => $code,
                'brand' => portalApiBrandToSlug((string) ($t['brand'] ?? '')),
                'date' => portalApiFirstTrainingDateIso($t),
                'level' => $t['level'] ?? null,
            ];
        }
    }

    return [
        'planningStatus' => $pif['planningStatus'],
        'planPathway' => $planPathway,
        'brandPifStats' => $pif['brandPifStats'],
    ];
}
