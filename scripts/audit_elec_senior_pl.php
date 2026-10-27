<?php
require_once __DIR__ . '/../core/medacap_config.php';
require __DIR__ . '/../vendor/autoload.php';

$db = (new MongoDB\Client(medacapMongoUri()))->academy;
$level = 'Senior';
$elecSpecs = ['Electricité et Electronique', 'Réseaux de Communication', 'Assistance à la Conduite'];
$truckBrands = ['RENAULT TRUCK', 'MERCEDES TRUCK', 'SINOTRUK', 'HINO', 'FUSO', 'KING LONG'];

function bsonArr($v): array {
    if ($v instanceof MongoDB\Model\BSONArray) return $v->getArrayCopy();
    return is_array($v) ? $v : [];
}
function oidStr($id): ?string {
    if ($id instanceof MongoDB\BSON\ObjectId) return (string) $id;
    $s = (string) $id;
    return preg_match('/^[0-9a-f]{24}$/i', $s) ? $s : null;
}

$questions = [];
$quizMap = [];

// From vehicle quizzes PL Senior Factuel
foreach ($truckBrands as $brand) {
    foreach ($db->vehicles->find(['brand' => $brand, 'type' => 'Factuel', 'level' => $level, 'active' => true], ['projection' => ['quizzes' => 1]]) as $veh) {
        foreach (bsonArr($veh['quizzes'] ?? []) as $qzid) {
            $s = oidStr($qzid);
            if (!$s) continue;
            $qz = $db->quizzes->findOne(['_id' => new MongoDB\BSON\ObjectId($s), 'active' => true]);
            if (!$qz) continue;
            $spec = (string) ($qz['speciality'] ?? '');
            if (!in_array($spec, $elecSpecs, true)) continue;
            foreach (bsonArr($qz['questions'] ?? []) as $qid) {
                $qs = oidStr($qid);
                if ($qs) {
                    $questions[$qs] = ['source' => "vehicle:$brand", 'quiz' => $s, 'spec' => $spec];
                    $quizMap[$s] = $spec;
                }
            }
        }
    }
}

// From specialities collection Senior Factuelle
foreach ($db->specialities->find(['label' => 'Electricité et Electronique', 'type' => 'Factuelle', 'level' => $level, 'active' => true], ['projection' => ['quizzes' => 1]]) as $sp) {
    foreach (bsonArr($sp['quizzes'] ?? []) as $qzid) {
        $s = oidStr($qzid);
        if (!$s) continue;
        foreach (bsonArr($db->quizzes->findOne(['_id' => new MongoDB\BSON\ObjectId($s)])->questions ?? []) as $qid) {
            $qs = oidStr($qid);
            if ($qs) {
                $questions[$qs] = ['source' => 'speciality:Senior', 'quiz' => $s, 'spec' => 'Electricité et Electronique'];
                $quizMap[$s] = 'Electricité et Electronique';
            }
        }
    }
}

$out = [
    'level' => $level,
    'target_test' => 100,
    'macro_electricite_pl' => [],
    'by_spec' => [],
    'questions' => [],
    'stats' => ['with_image' => 0, 'without_image' => 0, 'refs' => []],
    'themes' => [],
    'gaps' => [],
];

foreach ($elecSpecs as $spec) {
    $out['by_spec'][$spec] = ['count' => 0, 'ids' => []];
}

$themeKeywords = [
    'batterie/alim' => '/batter|alternat|démarr|demarr|24\s*v|12\s*v|masse|terre|ground/i',
    'éclairage/signalisation' => '/éclair|eclair|feu|lampe|phare|clignot|signal/i',
    'moteur/ECU/capteurs' => '/capteur|sonde|inject|ECU|calculateur|bus CAN|CAN|OBD|diagnostic/i',
    'freinage électrique/ABS/EBS' => '/ABS|EBS|frein.*élect|frein.*elect|capteur.*roue/i',
    'cabine/multimédia' => '/radio|GPS|écran|ecran|retro|climat|HVAC/i',
    'réseaux/communication' => '/réseau|reseau|LIN|CAN|J1939|multiplex|communication|Ethernet/i',
    'assistance conduite/ADAS' => '/ADAS|radar|caméra|camera|aide.*conduite|lane|freinage.*urgence|ESP|ACC/i',
    'câblage/connectique' => '/connect|câbl|cabl|fiche|broch|harnais|pin|cosse/i',
    'protection/fusibles' => '/fusible|disjonct|relais|protection|courant/i',
    'pneumatique électrique' => '/électrov|electrov|soupape.*élect|ECAS|suspension.*pneu/i',
];

$themeCounts = array_fill_keys(array_keys($themeKeywords), 0);
$unclassified = 0;

foreach (array_keys($questions) as $qid) {
    $q = $db->questions->findOne(['_id' => new MongoDB\BSON\ObjectId($qid), 'active' => true]);
    if (!$q) continue;
    $spec = (string) ($q['speciality'] ?? $questions[$qid]['spec']);
    $label = (string) ($q['label'] ?? '');
    $ref = (string) ($q['ref'] ?? '');
    $img = trim((string) ($q['image'] ?? ''));

    $out['by_spec'][$spec]['count']++;
    $out['by_spec'][$spec]['ids'][] = $qid;

    if ($img !== '') $out['stats']['with_image']++; else $out['stats']['without_image']++;
    if ($ref !== '') $out['stats']['refs'][$ref] = ($out['stats']['refs'][$ref] ?? 0) + 1;

    $matched = false;
    foreach ($themeKeywords as $theme => $rx) {
        if (preg_match($rx, $label . ' ' . $ref)) {
            $themeCounts[$theme]++;
            $matched = true;
            break;
        }
    }
    if (!$matched) $unclassified++;

    $out['questions'][] = [
        'id' => $qid,
        'ref' => $ref,
        'spec' => $spec,
        'image' => $img !== '',
        'label' => mb_substr($label, 0, 120),
        'source' => $questions[$qid]['source'],
    ];
}

$totalMacro = array_sum(array_column($out['by_spec'], 'count'));
$out['macro_electricite_pl'] = [
    'total_unique' => $totalMacro,
    'gap_to_100' => max(0, 100 - $totalMacro),
    'by_spec' => array_map(fn($x) => $x['count'], $out['by_spec']),
];
$out['themes'] = $themeCounts;
$out['themes']['non_classé'] = $unclassified;
$out['quizzes'] = [];
foreach ($quizMap as $qzid => $spec) {
    $qz = $db->quizzes->findOne(['_id' => new MongoDB\BSON\ObjectId($qzid)], ['projection' => ['total' => 1, 'questions' => 1, 'speciality' => 1]]);
    $out['quizzes'][] = [
        'id' => $qzid,
        'spec' => $spec,
        'count' => count(bsonArr($qz['questions'] ?? [])),
    ];
}

// Declaratif Senior for same specs
$declCount = 0;
foreach ($truckBrands as $brand) {
    foreach ($db->vehicles->find(['brand' => $brand, 'type' => 'Declaratif', 'level' => $level, 'active' => true], ['projection' => ['quizzes' => 1]]) as $veh) {
        foreach (bsonArr($veh['quizzes'] ?? []) as $qzid) {
            $s = oidStr($qzid);
            if (!$s) continue;
            $qz = $db->quizzes->findOne(['_id' => new MongoDB\BSON\ObjectId($s)]);
            if (!in_array((string)($qz['speciality']??''), $elecSpecs, true)) continue;
            $declCount += count(bsonArr($qz['questions'] ?? []));
        }
    }
}
$out['declaratif_pl_senior_approx'] = $declCount;

// Global Senior electricity in questions collection
$global = [];
foreach ($elecSpecs as $spec) {
    $global[$spec] = $db->questions->count(['speciality' => $spec, 'level' => $level, 'active' => true]);
}
$out['global_senior_all_brands'] = $global;

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
