<?php
require __DIR__ . "/../../vendor/autoload.php";

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\Model\BSONArray;

function argValue(array $argv, string $name, ?string $default = null): ?string {
    $prefix = "--{$name}=";
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

function to_oid_or_null(?string $id): ?ObjectId {
    try {
        return ($id && preg_match('/^[0-9a-f]{24}$/i', $id)) ? new ObjectId($id) : null;
    } catch (\Throwable $e) {
        return null;
    }
}

function parse_hms_to_sec($hms): ?int {
    if (!is_string($hms) || !preg_match('/^(\d{1,2}):(\d{1,2}):(\d{1,2})$/', trim($hms), $m)) return null;
    return ((int)$m[1])*3600 + ((int)$m[2])*60 + (int)$m[3];
}

function sec_to_hms(int $s): string {
    $s = max(0, $s);
    $h = intdiv($s, 3600);
    $m = intdiv($s % 3600, 60);
    $sc = $s % 60;
    return sprintf("%02d:%02d:%02d", $h, $m, $sc);
}

function to_php_array($v): array {
    if ($v instanceof BSONArray) return $v->getArrayCopy();
    return is_array($v) ? $v : [];
}

function count_selected_orientation($orientationAnswers): int {
    $orientationAnswers = to_php_array($orientationAnswers);
    $c = 0;
    foreach ($orientationAnswers as $selected) {
        $selected = to_php_array($selected);
        $nonEmpty = array_filter($selected, function($v){ return is_string($v) && trim($v) !== ""; });
        if (count($nonEmpty) > 0) $c++;
    }
    return $c;
}

function parse_k6_summary_with_issues(string $summaryPath): array {
    if (!is_file($summaryPath)) return ["summary" => null, "issues" => []];
    $raw = @file_get_contents($summaryPath);
    $j = json_decode((string)$raw, true);
    if (!is_array($j)) return ["summary" => null, "issues" => [["severity"=>"high","title"=>"summary.json illisible","detail"=>"JSON invalide"]]];

    $m = $j["metrics"] ?? [];
    $httpReq = $m["http_req_duration"] ?? [];
    $failed = $m["http_req_failed"] ?? [];
    $iter = $m["iterations"] ?? [];
    $vus = $m["vus_max"] ?? [];
    $checks = $m["checks"] ?? [];

    $summary = [
        "iterations" => $iter["count"] ?? null,
        "vus_max" => $vus["max"] ?? null,
        "http_req_failed_rate" => $failed["value"] ?? ($failed["rate"] ?? null),
        "http_req_duration_avg_ms" => $httpReq["avg"] ?? null,
        "http_req_duration_p95_ms" => $httpReq["p(95)"] ?? null,
        "http_req_duration_p99_ms" => $httpReq["p(99)"] ?? null,
        "checks_rate" => $checks["value"] ?? null,
    ];

    $issues = [];
    $failRate = (float)($summary["http_req_failed_rate"] ?? 0);
    if ($failRate > 0.02) {
        $issues[] = [
            "severity" => $failRate > 0.05 ? "high" : "medium",
            "title" => "Taux d'erreurs HTTP élevé",
            "detail" => sprintf("http_req_failed=%.2f%% (seuil cible < 2%%)", $failRate * 100),
        ];
    }
    $p95 = (float)($summary["http_req_duration_p95_ms"] ?? 0);
    if ($p95 > 2000) {
        $issues[] = [
            "severity" => $p95 > 5000 ? "high" : "medium",
            "title" => "Latence p95 élevée",
            "detail" => sprintf("http_req_duration p95=%.0f ms (seuil cible < 2000 ms)", $p95),
        ];
    }
    $checkRate = (float)($summary["checks_rate"] ?? 1);
    if ($checkRate < 0.98) {
        $issues[] = [
            "severity" => $checkRate < 0.95 ? "high" : "medium",
            "title" => "Checks fonctionnels insuffisants",
            "detail" => sprintf("checks=%.2f%% (seuil cible > 98%%)", $checkRate * 100),
        ];
    }

    $rootChecks = $j["root_group"]["checks"] ?? [];
    if (is_array($rootChecks)) {
        foreach ($rootChecks as $name => $chk) {
            $passes = (int)($chk["passes"] ?? 0);
            $fails = (int)($chk["fails"] ?? 0);
            $total = $passes + $fails;
            if ($total <= 0) continue;
            $rate = $passes / $total;
            if ($rate < 0.98) {
                $issues[] = [
                    "severity" => $rate < 0.9 ? "high" : "medium",
                    "title" => "Check en échec: " . $name,
                    "detail" => sprintf("success=%.2f%% (%d/%d)", $rate * 100, $passes, $total),
                ];
            }
        }
    }

    return ["summary" => $summary, "issues" => $issues];
}

$tag = (string)argValue($argv, "tag", "");
$mongoUri = (string)argValue($argv, "mongo-uri", "mongodb://10.68.0.7:27017");
$summaryPath = (string)argValue($argv, "summary", __DIR__ . "/out/{$tag}-summary.json");
$statePath = (string)argValue($argv, "state", __DIR__ . "/out/{$tag}-state.json");
$logPath = (string)argValue($argv, "log", __DIR__ . "/out/{$tag}-worker.log");
$outPath = (string)argValue($argv, "out", __DIR__ . "/out/{$tag}-snapshot.json");

if ($tag === "") {
    fwrite(STDERR, "ERROR: --tag is required.\n");
    exit(1);
}

$state = is_file($statePath) ? json_decode((string)file_get_contents($statePath), true) : [];
$paperId = (string)($state["paperId"] ?? "");
$paperOid = to_oid_or_null($paperId);

$client = new Client($mongoUri);
$db = $client->academy;
$users = $db->users;
$exams = $db->exams;
$results = $db->results;
$papers = $db->papers;

$paperDurationSec = 3600;
if ($paperOid) {
    $p = $papers->findOne(["_id" => $paperOid], ["projection" => ["duration"=>1,"hour"=>1,"minute"=>1,"second"=>1]]);
    if ($p) {
        $d = to_php_array($p["duration"] ?? []);
        $h = isset($d["hour"]) ? (int)$d["hour"] : (int)($p["hour"] ?? 1);
        $m = isset($d["minute"]) ? (int)$d["minute"] : (int)($p["minute"] ?? 0);
        $s = isset($d["second"]) ? (int)$d["second"] : (int)($p["second"] ?? 0);
        $paperDurationSec = max(60, $h*3600 + $m*60 + $s);
    }
}

$userRows = [];
$userOids = [];
$uc = $users->find(["loadtestTag" => $tag], ["projection" => ["_id"=>1,"username"=>1,"firstName"=>1,"lastName"=>1]]);
foreach ($uc as $u) {
    $oid = $u["_id"] ?? null;
    if (!$oid instanceof ObjectId) continue;
    $userOids[] = $oid;
    $userRows[(string)$oid] = [
        "user_id" => (string)$oid,
        "username" => (string)($u["username"] ?? ""),
        "candidate" => trim((string)($u["firstName"] ?? "") . " " . (string)($u["lastName"] ?? "")),
        "status" => "not_started",
        "answered" => 0,
        "orientation_answered" => 0,
        "score" => null,
        "time_spent_estimated" => null,
    ];
}

if (!empty($userOids)) {
    $ec = $exams->find(["user" => ['$in' => $userOids]], ["sort" => ["created" => -1]]);
    foreach ($ec as $ex) {
        $uid = (string)($ex["user"] ?? "");
        if (!isset($userRows[$uid])) continue;
        if ($userRows[$uid]["status"] !== "not_started") continue;
        $answered = (int)($ex["total"] ?? 0);
        $oa = count_selected_orientation($ex["orientationAnswers"] ?? []);
        $h = (int)($ex["hour"] ?? 0);
        $m = (int)($ex["minute"] ?? 0);
        $s = (int)($ex["second"] ?? 0);
        $remaining = max(0, $h*3600 + $m*60 + $s);
        $spent = max(0, $paperDurationSec - $remaining);
        $userRows[$uid]["status"] = ((bool)($ex["active"] ?? false)) ? "in_progress" : "exam_closed";
        $userRows[$uid]["answered"] = $answered;
        $userRows[$uid]["orientation_answered"] = $oa;
        $userRows[$uid]["time_spent_estimated"] = sec_to_hms($spent);
    }

    $rc = $results->find(["user" => ['$in' => $userOids], "type" => "Factuel", "level" => "Mixed"], ["sort" => ["created" => -1], "projection" => ["user"=>1,"score"=>1,"time"=>1]]);
    foreach ($rc as $r) {
        $uid = (string)($r["user"] ?? "");
        if (!isset($userRows[$uid])) continue;
        $userRows[$uid]["status"] = "done";
        $userRows[$uid]["score"] = (int)($r["score"] ?? 0);
        $remaining = parse_hms_to_sec((string)($r["time"] ?? ""));
        if ($remaining !== null) $userRows[$uid]["time_spent_estimated"] = sec_to_hms(max(0, $paperDurationSec - $remaining));
    }
}

$allRows = array_values($userRows);
usort($allRows, function($a, $b){ return strcmp($a["username"], $b["username"]); });

$counts = ["not_started"=>0,"in_progress"=>0,"done"=>0,"other"=>0];
foreach ($allRows as $r) {
    if ($r["status"] === "not_started") $counts["not_started"]++;
    elseif ($r["status"] === "in_progress") $counts["in_progress"]++;
    elseif ($r["status"] === "done") $counts["done"]++;
    else $counts["other"]++;
}

$lines = [];
if (is_file($logPath)) {
    $raw = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($raw)) $lines = array_slice($raw, -180);
}
$errLines = array_values(array_filter($lines, function($ln){
    return (bool)preg_match('/error|failed|timeout|exception| 4\d\d | 5\d\d /i', (string)$ln);
}));

$sum = parse_k6_summary_with_issues($summaryPath);
$issues = $sum["issues"];
foreach (array_slice($errLines, -15) as $e) {
    $issues[] = ["severity" => "medium", "title" => "Log d'erreur détecté", "detail" => (string)$e];
}

$snapshot = [
    "ok" => true,
    "tag" => $tag,
    "created_at" => date("Y-m-d H:i:s"),
    "mongoUri" => $mongoUri,
    "state" => $state,
    "summary" => $sum["summary"],
    "counters" => $counts,
    "candidates" => $allRows,
    "errors" => array_slice($errLines, -25),
    "logTail" => array_slice($lines, -40),
    "issues" => $issues,
];

@file_put_contents($outPath, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode([
    "ok" => true,
    "tag" => $tag,
    "snapshotFile" => $outPath,
    "candidates" => count($allRows),
    "issues" => count($issues),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
