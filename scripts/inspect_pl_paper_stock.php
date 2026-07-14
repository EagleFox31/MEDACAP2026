<?php
require __DIR__ . '/../vendor/autoload.php';

$uri = getenv('MEDACAP_MONGO_URI') ?: 'mongodb://10.68.0.7:27017';
$db = (new MongoDB\Client($uri))->academy;

function poolBrandQuizzes(MongoDB\Database $db, array $brands): array
{
    $quizIdMap = [];
    $vehicles = $db->vehicles->find(
        ['brand' => ['$in' => $brands], 'type' => 'Factuel', 'active' => true],
        ['projection' => ['quizzes' => 1, 'brand' => 1]]
    );
    foreach ($vehicles as $veh) {
        $raw = $veh['quizzes'] ?? [];
        if ($raw instanceof MongoDB\Model\BSONArray) {
            $raw = $raw->getArrayCopy();
        }
        if (!is_array($raw)) {
            continue;
        }
        foreach ($raw as $id) {
            $str = $id instanceof MongoDB\BSON\ObjectId ? (string) $id : (string) $id;
            if (preg_match('/^[0-9a-f]{24}$/i', $str)) {
                $quizIdMap[$str] = new MongoDB\BSON\ObjectId($str);
            }
        }
    }
    if (empty($quizIdMap)) {
        return [];
    }
    $out = [];
    $cursor = $db->quizzes->find(
        [
            '_id' => ['$in' => array_values($quizIdMap)],
            'active' => true,
            'type' => ['$in' => ['Factuel', 'Factuelle']],
        ],
        ['projection' => ['speciality' => 1, 'level' => 1, 'total' => 1, 'questions' => 1]]
    );
    foreach ($cursor as $q) {
        $out[] = $q;
    }
    return $out;
}

function stockSize($q): int
{
    $t = (int) ($q['total'] ?? 0);
    if ($t > 0) {
        return $t;
    }
    $raw = $q['questions'] ?? [];
    if ($raw instanceof MongoDB\Model\BSONArray) {
        $raw = $raw->getArrayCopy();
    }
    return is_array($raw) ? count($raw) : 0;
}

$brands = ['RENAULT TRUCK', 'MERCEDES TRUCK', 'SINOTRUK', 'HINO', 'FUSO'];
echo "Mongo: $uri\n\n";

foreach ($brands as $brand) {
    $pool = poolBrandQuizzes($db, [$brand]);
    $bySpec = [];
    foreach ($pool as $q) {
        $spec = (string) ($q['speciality'] ?? 'Inconnu');
        $level = (string) ($q['level'] ?? 'Inconnu');
        if (!isset($bySpec[$spec])) {
            $bySpec[$spec] = ['Junior' => 0, 'Senior' => 0, 'Expert' => 0];
        }
        if (isset($bySpec[$spec][$level])) {
            $bySpec[$spec][$level] += stockSize($q);
        }
    }
    $j = array_sum(array_column($bySpec, 'Junior'));
    $s = array_sum(array_column($bySpec, 'Senior'));
    echo "=== $brand === quizzes=" . count($pool) . " Junior=$j Senior=$s\n";
}

$rt = poolBrandQuizzes($db, ['RENAULT TRUCK']);
$keywords = '/mainten|pneumat|électri|electri|freinage|frein|moteur|transversale|vidange|périodique|periodique/i';
echo "\n=== RENAULT TRUCK — spécialités pertinentes ===\n";
$bySpec = [];
foreach ($rt as $q) {
    $spec = (string) ($q['speciality'] ?? '');
    $level = (string) ($q['level'] ?? '');
    if (!isset($bySpec[$spec])) {
        $bySpec[$spec] = ['Junior' => 0, 'Senior' => 0];
    }
    if ($level === 'Junior' || $level === 'Senior') {
        $bySpec[$spec][$level] += stockSize($q);
    }
}
ksort($bySpec);
$matchJ = 0;
$matchS = 0;
foreach ($bySpec as $spec => $lv) {
    if (preg_match($keywords, $spec)) {
        echo "$spec | J:{$lv['Junior']} S:{$lv['Senior']}\n";
        $matchJ += $lv['Junior'];
        $matchS += $lv['Senior'];
    }
}
echo "Total thématiques cibles J=$matchJ S=$matchS\n";

$allJ = 0;
$allS = 0;
foreach ($bySpec as $lv) {
    $allJ += $lv['Junior'];
    $allS += $lv['Senior'];
}
echo "Total RENAULT TRUCK J=$allJ S=$allS\n";

echo "\n=== Toutes spécialités RENAULT TRUCK ===\n";
foreach ($bySpec as $spec => $lv) {
    echo "$spec | J:{$lv['Junior']} S:{$lv['Senior']}\n";
}
