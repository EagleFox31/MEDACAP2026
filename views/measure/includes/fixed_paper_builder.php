<?php
/** Fixed paper builder — shared by UI and CLI scripts */
use MongoDB\BSON\ObjectId;
use MongoDB\Model\BSONArray;

function s($v){if(!is_string($v))return $v;return trim(filter_var($v,FILTER_SANITIZE_STRING));}
function arr_str(array $a):array{$o=[];foreach($a as $v){$v=s($v);if($v!=='')$o[]=$v;}return array_values(array_unique($o));}
function toOid($v):?ObjectId{if($v instanceof ObjectId)return $v;if(is_string($v)&&preg_match('/^[0-9a-f]{24}$/i',$v))return new ObjectId($v);return null;}
function dedupOids(array $a):array{$m=[];$o=[];foreach($a as $x){if(!($x instanceof ObjectId))continue;$k=(string)$x;if(isset($m[$k]))continue;$m[$k]=1;$o[]=$x;}return $o;}
function pickRandom(array $arr,int $n):array{if($n<=0||empty($arr))return[];shuffle($arr);return array_slice($arr,0,min($n,count($arr)));}
function poolBrandQuizzes(array $brands, ?string $level = null): array
{
    global $vehicles, $quizzes;
    if (empty($brands)) {
        return [];
    }
    $vehFilter = ['brand' => ['$in' => $brands], 'type' => 'Factuel', 'active' => true];
    if ($level !== null) {
        $vehFilter['level'] = $level;
    }
    $quizIdMap = [];
    $cur = $vehicles->find($vehFilter, ['projection' => ['quizzes' => 1]]);
    foreach ($cur as $veh) {
        $raw = $veh['quizzes'] ?? [];
        if ($raw instanceof BSONArray) {
            $raw = $raw->getArrayCopy();
        }
        if (!is_array($raw)) {
            continue;
        }
        foreach ($raw as $id) {
            $oid = toOid(is_string($id) ? $id : ($id instanceof ObjectId ? (string) $id : null));
            if ($oid) {
                $quizIdMap[(string) $oid] = $oid;
            }
        }
    }
    if (empty($quizIdMap)) {
        return [];
    }
    $quizFilter = [
        '_id' => ['$in' => array_values($quizIdMap)],
        'active' => true,
        'type' => ['$in' => ['Factuel', 'Factuelle']],
    ];
    if ($level !== null) {
        $quizFilter['level'] = $level;
    }
    $docs = [];
    $qCur = $quizzes->find($quizFilter, ['projection' => ['_id' => 1, 'speciality' => 1, 'total' => 1, 'questions' => 1, 'level' => 1]]);
    foreach ($qCur as $q) {
        $docs[] = $q;
    }
    return $docs;
}

function poolCpQuizzes(?string $level = null): array
{
    global $quizzes;
    $filter = [
        'active' => true,
        'type' => ['$in' => ['Factuel', 'Factuelle']],
        'speciality' => CP_SPEC,
    ];
    if ($level !== null) {
        $filter['level'] = $level;
    }
    $out = [];
    foreach ($quizzes->find($filter, ['projection' => ['_id' => 1, 'speciality' => 1, 'total' => 1, 'questions' => 1, 'level' => 1]]) as $q) {
        $out[] = $q;
    }
    return $out;
}

function quizStockSize($quizDoc, ?string $level = null): int
{
    if ($level === null) {
        $t = (int) ($quizDoc['total'] ?? 0);
        if ($t > 0) {
            return $t;
        }
        $raw = $quizDoc['questions'] ?? [];
        if ($raw instanceof BSONArray) {
            $raw = $raw->getArrayCopy();
        }
        return is_array($raw) ? count($raw) : 0;
    }
    return count(sampleQuestionsOfQuiz($quizDoc, 999999, $level));
}

function distributeByStock(array $quizDocs, int $target, ?string $level = null): array
{
    $stock = 0;
    $ids = [];
    foreach ($quizDocs as $q) {
        $t = quizStockSize($q, $level);
        $stock += $t;
        $ids[(string) $q['_id']] = $t;
    }
    if ($stock <= 0 || $target <= 0) {
        return [];
    }
    $alloc = [];
    $frac = [];
    foreach ($ids as $k => $t) {
        $r = ($t / $stock) * $target;
        $alloc[$k] = (int) floor($r);
        $frac[$k] = $r - $alloc[$k];
    }
    $rem = $target - array_sum($alloc);
    if ($rem > 0) {
        arsort($frac, SORT_NUMERIC);
        foreach (array_keys($frac) as $k) {
            if ($rem <= 0) {
                break;
            }
            $alloc[$k]++;
            $rem--;
        }
    }
    return $alloc;
}

function sampleQuestionsOfQuiz($quizDoc, int $n, ?string $level = null): array
{
    global $questions;
    if ($n <= 0) {
        return [];
    }
    $raw = $quizDoc['questions'] ?? [];
    if ($raw instanceof BSONArray) {
        $raw = $raw->getArrayCopy();
    }
    if (!is_array($raw) || empty($raw)) {
        return [];
    }
    $ids = [];
    foreach ($raw as $x) {
        $oid = toOid(is_string($x) ? $x : ($x instanceof ObjectId ? (string) $x : null));
        if ($oid) {
            $ids[(string) $oid] = $oid;
        }
    }
    if (empty($ids)) {
        return [];
    }
    $qFilter = ['_id' => ['$in' => array_values($ids)], 'active' => true];
    if ($level !== null) {
        $qFilter['level'] = $level;
    }
    $active = [];
    $cur = $questions->find($qFilter, ['projection' => ['_id' => 1]]);
    foreach ($cur as $qd) {
        $active[] = $qd['_id'];
    }
    if (empty($active)) {
        return [];
    }
    return pickRandom($active, $n);
}

function filterPoolBySpecialities(array $quizDocs, array $specs): array
{
    if (empty($specs)) {
        return $quizDocs;
    }
    $o = [];
    foreach ($quizDocs as $q) {
        if (in_array((string) ($q['speciality'] ?? ''), $specs, true)) {
            $o[] = $q;
        }
    }
    return $o;
}

/** Répartit $total questions selon les ratios Junior/Senior/Expert (méthode des plus grands restes). */
function ratioLevelTargets(array $ratio, int $total): array
{
    $levels = ['Junior', 'Senior', 'Expert'];
    $weights = [];
    $sum = 0;
    foreach ($levels as $lv) {
        $w = max(0, (int) ($ratio[$lv] ?? 0));
        $weights[$lv] = $w;
        $sum += $w;
    }
    if ($sum <= 0) {
        return ['Senior' => $total, 'Junior' => 0, 'Expert' => 0];
    }
    $alloc = [];
    $frac = [];
    foreach ($levels as $lv) {
        $r = ($weights[$lv] / $sum) * $total;
        $alloc[$lv] = (int) floor($r);
        $frac[$lv] = $r - $alloc[$lv];
    }
    $rem = $total - array_sum($alloc);
    if ($rem > 0) {
        arsort($frac, SORT_NUMERIC);
        foreach (array_keys($frac) as $lv) {
            if ($rem <= 0 || $weights[$lv] <= 0) {
                continue;
            }
            $alloc[$lv]++;
            $rem--;
        }
    }
    return $alloc;
}

function registerQuizMeta($q, array &$sourceQuizIds, array &$specQuizMap): void
{
    $qid = (string) $q['_id'];
    $sourceQuizIds[$qid] = $q['_id'];
    $spec = (string) ($q['speciality'] ?? '');
    if ($spec !== '' && !isset($specQuizMap[$spec])) {
        $specQuizMap[$spec] = $q['_id'];
    }
}

function sampleFromPools(array $brandPool, array $cpPool, int $brandTarget, int $cpTarget, ?string $level, array &$sourceQuizIds, array &$specQuizMap): array
{
    $pickedQ = [];
    $byIdDoc = [];
    foreach ($brandPool as $q) {
        $byIdDoc[(string) $q['_id']] = $q;
    }
    foreach ($cpPool as $q) {
        $byIdDoc[(string) $q['_id']] = $q;
    }
    $brandAlloc = distributeByStock($brandPool, $brandTarget, $level);
    $cpAlloc = distributeByStock($cpPool, $cpTarget, $level);
    foreach ($brandAlloc as $qid => $take) {
        if ($take <= 0 || !isset($byIdDoc[$qid])) {
            continue;
        }
        $doc = $byIdDoc[$qid];
        registerQuizMeta($doc, $sourceQuizIds, $specQuizMap);
        $pickedQ = array_merge($pickedQ, sampleQuestionsOfQuiz($doc, $take, $level));
    }
    foreach ($cpAlloc as $qid => $take) {
        if ($take <= 0 || !isset($byIdDoc[$qid])) {
            continue;
        }
        $doc = $byIdDoc[$qid];
        registerQuizMeta($doc, $sourceQuizIds, $specQuizMap);
        $pickedQ = array_merge($pickedQ, sampleQuestionsOfQuiz($doc, $take, $level));
    }
    return $pickedQ;
}

function buildFixedPaper(string $label, array $brands, int $qcmCount, array $ratio, array $orientationOids = [], bool $includeCp = true, int $durationHour = 1, int $durationMinute = 0, array $specialities = []): ObjectId
{
    global $papers;

    $levelTargets = ratioLevelTargets($ratio, $qcmCount);
    $pickedQ = [];
    $sourceQuizIds = [];
    $specQuizMap = [];
    $brandAllocSum = 0;
    $cpAllocSum = 0;
    $countsByLevel = ['Junior' => 0, 'Senior' => 0, 'Expert' => 0];

    foreach (['Junior', 'Senior', 'Expert'] as $level) {
        $levelTarget = (int) ($levelTargets[$level] ?? 0);
        if ($levelTarget <= 0) {
            continue;
        }

        $cpTarget = 0;
        if ($includeCp) {
            $cpTarget = (int) round($levelTarget * 0.10);
            if ($cpTarget === 0) {
                $cpTarget = 1;
            }
            if ($cpTarget >= $levelTarget) {
                $cpTarget = max(1, $levelTarget - 1);
            }
        }
        $brandTarget = max(0, $levelTarget - $cpTarget);
        $brandAllocSum += $brandTarget;
        $cpAllocSum += $cpTarget;

        $brandPool = poolBrandQuizzes($brands, $level);
        if (!empty($specialities)) {
            $brandPool = filterPoolBySpecialities($brandPool, $specialities);
        }
        $cpPool = $includeCp ? poolCpQuizzes($level) : [];

        if (empty($brandPool) && empty($cpPool)) {
            throw new \RuntimeException("Aucun quiz disponible pour le niveau $level avec les marques / spécialités choisies.");
        }

        $levelPicked = sampleFromPools($brandPool, $cpPool, $brandTarget, $cpTarget, $level, $sourceQuizIds, $specQuizMap);
        $levelPicked = dedupOids($levelPicked);

        $need = $levelTarget - count($levelPicked);
        if ($need > 0) {
            $allActive = [];
            foreach (array_merge($brandPool, $cpPool) as $doc) {
                registerQuizMeta($doc, $sourceQuizIds, $specQuizMap);
                $allActive = array_merge($allActive, sampleQuestionsOfQuiz($doc, 999999, $level));
            }
            $allActive = dedupOids($allActive);
            $seen = [];
            foreach (array_merge($pickedQ, $levelPicked) as $x) {
                $seen[(string) $x] = 1;
            }
            $rest = [];
            foreach ($allActive as $x) {
                $k = (string) $x;
                if (!isset($seen[$k])) {
                    $rest[] = $x;
                }
            }
            shuffle($rest);
            $levelPicked = array_merge($levelPicked, array_slice($rest, 0, $need));
            $levelPicked = dedupOids($levelPicked);
        }

        if (count($levelPicked) < $levelTarget) {
            throw new \RuntimeException(
                "Stock insuffisant pour le niveau $level : " . count($levelPicked) . " / $levelTarget questions disponibles."
            );
        }
        if (count($levelPicked) > $levelTarget) {
            shuffle($levelPicked);
            $levelPicked = array_slice($levelPicked, 0, $levelTarget);
        }

        $countsByLevel[$level] = count($levelPicked);
        $pickedQ = array_merge($pickedQ, $levelPicked);
    }

    $pickedQ = dedupOids($pickedQ);
    if (count($pickedQ) !== $qcmCount) {
        throw new \RuntimeException(
            'Écart sur le total après application des ratios : ' . count($pickedQ) . " / $qcmCount questions."
        );
    }
    shuffle($pickedQ);

    $doc = [
        'label' => $label,
        'type' => 'Factuel',
        'mode' => 'mixed',
        'level' => 'Mixed',
        'brand' => array_values(array_unique($brands)),
        'specialities' => array_values($specialities),
        'ratios' => [
            'Junior' => (int) ($ratio['Junior'] ?? 50),
            'Senior' => (int) ($ratio['Senior'] ?? 45),
            'Expert' => (int) ($ratio['Expert'] ?? 5),
        ],
        'counts' => [
            'QCM_total' => $qcmCount,
            'QCM_brand' => $brandAllocSum,
            'QCM_cp' => $cpAllocSum,
            'by_level' => $countsByLevel,
        ],
        'source_quizzes' => array_values($sourceQuizIds),
        'specQuizMap' => $specQuizMap,
        'questions' => $pickedQ,
        'orientation' => dedupOids($orientationOids),
        'duration' => ['hour' => $durationHour, 'minute' => $durationMinute, 'second' => 0],
        'hour' => $durationHour,
        'minute' => $durationMinute,
        'second' => 0,
        'active' => true,
        'created' => date('Y-m-d H:i:s'),
    ];
    $res = $papers->insertOne($doc);
    return $res->getInsertedId();
}
