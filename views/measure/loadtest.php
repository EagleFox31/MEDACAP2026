<?php
session_start();
if (!isset($_SESSION["id"])) { header("Location: ../../"); exit(); }
if (!in_array($_SESSION["profile"] ?? "", ["Super Admin","Admin"], true)) {
    http_response_code(403);
    echo "Accès réservé aux administrateurs.";
    exit();
}

require_once "../../vendor/autoload.php";
require_once __DIR__ . "/../../core/medacap_config.php";

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\Model\BSONArray;

function connect_mongo_with_fallback(array $uris): array {
    foreach ($uris as $uri) {
        try {
            $client = new Client($uri, [], [
                "serverSelectionTimeoutMS" => 2000,
                "connectTimeoutMS" => 2000,
                "socketTimeoutMS" => 4000,
            ]);
            $client->selectDatabase("admin")->command(["ping" => 1]);
            return [$client, $uri];
        } catch (\Throwable $e) {
            // try next
        }
    }
    throw new \RuntimeException("Impossible de se connecter à MongoDB (localhost et 10.68.0.7).");
}

$mongoCandidates = medacapEnvString('MEDACAP_MONGO_URI') !== ''
    ? array(medacapMongoUri())
    : array("mongodb://localhost:27017", "mongodb://10.68.0.7:27017");
[$client, $mongoUriUsed] = connect_mongo_with_fallback($mongoCandidates);
$db = $client->academy;
$papers = $db->papers;
$users = $db->users;
$exams = $db->exams;
$results = $db->results;

$k6Dir = realpath(__DIR__ . "/../../scripts/k6");
$k6OutDir = $k6Dir . DIRECTORY_SEPARATOR . "out";
if (!is_dir($k6OutDir)) @mkdir($k6OutDir, 0777, true);

function json_out($arr, $code = 200) {
    if (function_exists("ob_get_length") && ob_get_length()) {
        @ob_clean();
    }
    http_response_code($code);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}
function to_oid_or_null($id) {
    try { return (is_string($id) && preg_match('/^[0-9a-f]{24}$/i',$id)) ? new ObjectId($id) : null; }
    catch (\Throwable $e) { return null; }
}
function shell_run($cmd) {
    $out = [];
    $code = 0;
    exec($cmd . " 2>&1", $out, $code);
    return ["code"=>$code, "out"=>implode("\n", $out)];
}
function parse_hms_to_sec($hms) {
    if (!is_string($hms) || !preg_match('/^(\d{1,2}):(\d{1,2}):(\d{1,2})$/', trim($hms), $m)) return null;
    return ((int)$m[1])*3600 + ((int)$m[2])*60 + (int)$m[3];
}
function sec_to_hms($s) {
    $s = max(0,(int)$s);
    $h = intdiv($s, 3600); $m = intdiv($s % 3600, 60); $sc = $s % 60;
    return sprintf("%02d:%02d:%02d", $h, $m, $sc);
}
function count_selected_orientation($orientationAnswers) {
    if ($orientationAnswers instanceof BSONArray) $orientationAnswers = $orientationAnswers->getArrayCopy();
    if (!is_array($orientationAnswers)) return 0;
    $c = 0;
    foreach ($orientationAnswers as $selected) {
        if ($selected instanceof BSONArray) $selected = $selected->getArrayCopy();
        if (!is_array($selected)) continue;
        $nonEmpty = array_filter($selected, function($v){ return is_string($v) && trim($v) !== ""; });
        if (count($nonEmpty) > 0) $c++;
    }
    return $c;
}
function tail_lines($file, $maxLines = 80) {
    if (!is_file($file)) return [];
    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) return [];
    return array_slice($lines, -$maxLines);
}
function parse_k6_summary($summaryPath) {
    if (!is_file($summaryPath)) return null;
    $raw = @file_get_contents($summaryPath);
    $j = json_decode((string)$raw, true);
    if (!is_array($j)) return null;
    $m = $j["metrics"] ?? [];
    $get = function(array $metrics, string $metric, string $field) {
        if (!isset($metrics[$metric]) || !is_array($metrics[$metric])) return null;
        $node = $metrics[$metric];
        if (isset($node["values"]) && is_array($node["values"])) return $node["values"][$field] ?? null;
        return $node[$field] ?? null;
    };
    return [
        "iterations" => $get($m, "iterations", "count"),
        "vus_max" => $get($m, "vus_max", "max"),
        "http_req_failed_rate" => $get($m, "http_req_failed", "rate") ?? $get($m, "http_req_failed", "value"),
        "http_req_duration_avg_ms" => $get($m, "http_req_duration", "avg"),
        "http_req_duration_p95_ms" => $get($m, "http_req_duration", "p(95)"),
        "http_req_duration_p99_ms" => $get($m, "http_req_duration", "p(99)"),
        "checks_rate" => $get($m, "checks", "rate") ?? $get($m, "checks", "value"),
    ];
}
function parse_k6_issues($summaryPath, array $errorLines = []): array {
    $issues = [];
    $summary = parse_k6_summary($summaryPath);
    if (is_array($summary)) {
        $failRate = (float)($summary["http_req_failed_rate"] ?? 0);
        $p95 = (float)($summary["http_req_duration_p95_ms"] ?? 0);
        $checksRate = (float)($summary["checks_rate"] ?? 1);

        if ($failRate > 0.02) {
            $issues[] = [
                "severity" => $failRate > 0.05 ? "high" : "medium",
                "title" => "Taux d'erreurs HTTP élevé",
                "detail" => sprintf("http_req_failed = %.2f%%", $failRate * 100),
            ];
        }
        if ($p95 > 2000) {
            $issues[] = [
                "severity" => $p95 > 5000 ? "high" : "medium",
                "title" => "Latence p95 élevée",
                "detail" => sprintf("http_req_duration p95 = %.0f ms", $p95),
            ];
        }
        if ($checksRate < 0.98) {
            $issues[] = [
                "severity" => $checksRate < 0.95 ? "high" : "medium",
                "title" => "Checks fonctionnels insuffisants",
                "detail" => sprintf("checks = %.2f%%", $checksRate * 100),
            ];
        }
    }
    foreach (array_slice($errorLines, -12) as $line) {
        $issues[] = [
            "severity" => "medium",
            "title" => "Erreur détectée dans les logs",
            "detail" => (string)$line,
        ];
    }
    return $issues;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    $action = (string)$_POST["action"];

    if ($action === "start") {
        $paperId = (string)($_POST["paper_id"] ?? "");
        $count = max(1, (int)($_POST["count"] ?? 100));
        $targetVus = max(1, (int)($_POST["target_vus"] ?? 100));
        $durationMin = max(1, (int)($_POST["duration_min"] ?? 30));
        $warmup = max(10, (int)($_POST["warmup_sec"] ?? 60));
        $rampdown = max(10, (int)($_POST["rampdown_sec"] ?? 60));

        $paperOid = to_oid_or_null($paperId);
        if (!$paperOid) json_out(["ok"=>false,"message"=>"Paper invalide"], 400);
        $paper = $papers->findOne(["_id"=>$paperOid, "active"=>true], ["projection"=>["label"=>1]]);
        if (!$paper) json_out(["ok"=>false,"message"=>"Paper introuvable/actif"], 404);

        $which = shell_run("where k6");
        if ((int)$which["code"] !== 0) {
            json_out(["ok"=>false,"message"=>"k6 non installé sur le serveur (commande 'k6' introuvable)."], 500);
        }

        $tag = "lt_" . date("Ymd_His");
        $mongoUri = $mongoUriUsed;
        $setupScript = $k6Dir . DIRECTORY_SEPARATOR . "setup_fake_candidates.php";
        $setupCmd = "php " . escapeshellarg($setupScript)
            . " --paper-id=" . escapeshellarg($paperId)
            . " --count=" . escapeshellarg((string)$count)
            . " --mongo-uri=" . escapeshellarg($mongoUri)
            . " --tag=" . escapeshellarg($tag);

        $setup = shell_run($setupCmd);
        if ((int)$setup["code"] !== 0) {
            json_out(["ok"=>false,"message"=>"Échec création candidats fictifs","details"=>$setup["out"]], 500);
        }

        $setupJson = json_decode((string)$setup["out"], true);
        if (!is_array($setupJson) || empty($setupJson["usersCsv"])) {
            json_out(["ok"=>false,"message"=>"Sortie setup invalide","details"=>$setup["out"]], 500);
        }

        $summaryFile = $k6OutDir . DIRECTORY_SEPARATOR . $tag . "-summary.json";
        $stateFile = $k6OutDir . DIRECTORY_SEPARATOR . $tag . "-state.json";
        $workerLog = $k6OutDir . DIRECTORY_SEPARATOR . $tag . "-worker.log";
        $workerScript = $k6Dir . DIRECTORY_SEPARATOR . "run_worker.php";
        $k6Script = $k6Dir . DIRECTORY_SEPARATOR . "medacap-quiz-load.js";
        $configuredBase = medacapPublicUrl();
        if ($configuredBase !== '') {
            $baseUrl = rtrim($configuredBase, '/');
        } else {
            $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
            $baseUrl = $scheme . "://" . ($_SERVER["HTTP_HOST"] ?? "10.68.0.7") . "/medacap";
        }
        $steady = $durationMin . "m";

        $cmd = 'cmd /c start "" /B php '
            . escapeshellarg($workerScript)
            . " --tag=" . escapeshellarg($tag)
            . " --base-url=" . escapeshellarg($baseUrl)
            . " --paper-id=" . escapeshellarg($paperId)
            . " --target-vus=" . escapeshellarg((string)$targetVus)
            . " --warmup=" . escapeshellarg($warmup . "s")
            . " --steady=" . escapeshellarg($steady)
            . " --rampdown=" . escapeshellarg($rampdown . "s")
            . " --users-file=" . escapeshellarg((string)$setupJson["usersCsv"])
            . " --summary-file=" . escapeshellarg($summaryFile)
            . " --state-file=" . escapeshellarg($stateFile)
            . " --script-file=" . escapeshellarg($k6Script)
            . " --log-file=" . escapeshellarg($workerLog);
        pclose(popen($cmd, "r"));

        json_out([
            "ok" => true,
            "message" => "Test de charge lancé",
            "tag" => $tag,
            "paperLabel" => (string)($paper["label"] ?? "Paper"),
            "createdUsers" => (int)$setupJson["createdUsers"],
            "stateFile" => $stateFile,
        ]);
    }

    if ($action === "status") {
        $tag = (string)($_POST["tag"] ?? "");
        if ($tag === "") json_out(["ok"=>false,"message"=>"Tag manquant"], 400);

        $stateFile = $k6OutDir . DIRECTORY_SEPARATOR . $tag . "-state.json";
        $summaryFile = $k6OutDir . DIRECTORY_SEPARATOR . $tag . "-summary.json";
        $logFile = $k6OutDir . DIRECTORY_SEPARATOR . $tag . "-worker.log";
        $snapshotFile = $k6OutDir . DIRECTORY_SEPARATOR . $tag . "-snapshot.json";
        $state = is_file($stateFile) ? json_decode((string)file_get_contents($stateFile), true) : null;
        if (!is_array($state)) {
            $state = [
                "tag" => $tag,
                "status" => "external",
                "message" => "Suivi d'un test lancé hors serveur web",
                "started_at" => null,
                "paperId" => "",
                "source" => "external",
            ];
        }

        $usersCur = $users->find(["loadtestTag"=>$tag], ["projection"=>["_id"=>1,"username"=>1,"firstName"=>1,"lastName"=>1]]);
        $userRows = [];
        $userOids = [];
        foreach ($usersCur as $u) {
            $oid = $u["_id"] ?? null;
            if ($oid instanceof ObjectId) $userOids[] = $oid;
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

        $paperDurationSec = 3600;
        $paperId = $state["paperId"] ?? "";
        $paperOid = to_oid_or_null((string)$paperId);
        if ($paperOid) {
            $p = $papers->findOne(["_id"=>$paperOid], ["projection"=>["duration"=>1,"hour"=>1,"minute"=>1,"second"=>1]]);
            if ($p) {
                $d = is_array($p["duration"] ?? null) ? $p["duration"] : [];
                $h = isset($d["hour"]) ? (int)$d["hour"] : (int)($p["hour"] ?? 1);
                $m = isset($d["minute"]) ? (int)$d["minute"] : (int)($p["minute"] ?? 0);
                $s = isset($d["second"]) ? (int)$d["second"] : (int)($p["second"] ?? 0);
                $paperDurationSec = max(60, $h*3600 + $m*60 + $s);
            }
        }

        if (!empty($userOids)) {
            $examCur = $exams->find(["user"=>['$in'=>$userOids]], ["sort"=>["created"=>-1]]);
            foreach ($examCur as $ex) {
                $uid = (string)($ex["user"] ?? "");
                if (!isset($userRows[$uid])) continue;
                if ($userRows[$uid]["status"] !== "not_started") continue;
                $answered = (int)($ex["total"] ?? 0);
                $oa = count_selected_orientation($ex["orientationAnswers"] ?? []);
                $h = (int)($ex["hour"] ?? 0); $m = (int)($ex["minute"] ?? 0); $s = (int)($ex["second"] ?? 0);
                $remaining = max(0, $h*3600 + $m*60 + $s);
                $spent = max(0, $paperDurationSec - $remaining);
                $userRows[$uid]["status"] = ((bool)($ex["active"] ?? false)) ? "in_progress" : "exam_closed";
                $userRows[$uid]["answered"] = $answered;
                $userRows[$uid]["orientation_answered"] = $oa;
                $userRows[$uid]["time_spent_estimated"] = sec_to_hms($spent);
            }

            $resCur = $results->find(
                ["user"=>['$in'=>$userOids], "type"=>"Factuel", "level"=>"Mixed"],
                ["sort"=>["created"=>-1], "projection"=>["user"=>1,"score"=>1,"time"=>1]]
            );
            foreach ($resCur as $r) {
                $uid = (string)($r["user"] ?? "");
                if (!isset($userRows[$uid])) continue;
                $userRows[$uid]["status"] = "done";
                $userRows[$uid]["score"] = (int)($r["score"] ?? 0);
                $remaining = parse_hms_to_sec((string)($r["time"] ?? ""));
                if ($remaining !== null) $userRows[$uid]["time_spent_estimated"] = sec_to_hms(max(0, $paperDurationSec - $remaining));
            }
        }

        $allRows = array_values($userRows);
        usort($allRows, function($a,$b){ return strcmp($a["username"], $b["username"]); });

        $counts = ["not_started"=>0,"in_progress"=>0,"done"=>0,"other"=>0];
        foreach ($allRows as $r) {
            if ($r["status"] === "not_started") $counts["not_started"]++;
            elseif ($r["status"] === "in_progress") $counts["in_progress"]++;
            elseif ($r["status"] === "done") $counts["done"]++;
            else $counts["other"]++;
        }
        if (($state["status"] ?? "") === "external") {
            if (($counts["done"] + $counts["other"]) > 0 && $counts["in_progress"] === 0 && $counts["not_started"] === 0) {
                $state["status"] = "completed";
                $state["message"] = "Test externe terminé";
            } else {
                $state["status"] = "running";
                $state["message"] = "Test externe en cours";
            }
        }

        $tail = tail_lines($logFile, 120);
        $errLines = array_values(array_filter($tail, function($ln){
            return (bool)preg_match('/error|failed|timeout|exception/i', (string)$ln);
        }));
        $snapshotData = is_file($snapshotFile) ? json_decode((string)file_get_contents($snapshotFile), true) : null;
        if (is_array($snapshotData) && count($allRows) === 0 && !empty($snapshotData["candidates"])) {
            $allRows = is_array($snapshotData["candidates"]) ? $snapshotData["candidates"] : [];
            $counts = is_array($snapshotData["counters"] ?? null) ? $snapshotData["counters"] : $counts;
            if (!empty($snapshotData["state"]) && is_array($snapshotData["state"])) {
                $state = $snapshotData["state"];
            }
            $tail = is_array($snapshotData["logTail"] ?? null) ? $snapshotData["logTail"] : $tail;
            $errLines = is_array($snapshotData["errors"] ?? null) ? $snapshotData["errors"] : $errLines;
        }
        $summary = parse_k6_summary($summaryFile);
        if (is_array($snapshotData) && is_array($snapshotData["summary"] ?? null)) {
            $summary = $snapshotData["summary"];
        }
        $issues = parse_k6_issues($summaryFile, $errLines);
        if (is_array($snapshotData) && is_array($snapshotData["issues"] ?? null) && count($snapshotData["issues"]) > 0) {
            $issues = $snapshotData["issues"];
        }

        json_out([
            "ok" => true,
            "tag" => $tag,
            "state" => $state,
            "mongoUri" => $mongoUriUsed,
            "summary" => $summary,
            "counters" => $counts,
            "candidates" => $allRows,
            "errors" => array_slice($errLines, -25),
            "logTail" => array_slice($tail, -40),
            "issues" => array_slice($issues, 0, 40),
            "snapshotFile" => is_file($snapshotFile) ? $snapshotFile : null,
        ]);
    }

    if ($action === "cleanup") {
        $tag = (string)($_POST["tag"] ?? "");
        if ($tag === "") json_out(["ok"=>false,"message"=>"Tag manquant"], 400);
        $snapshotScript = $k6Dir . DIRECTORY_SEPARATOR . "create_snapshot.php";
        $summaryFile = $k6OutDir . DIRECTORY_SEPARATOR . $tag . "-summary.json";
        $stateFile = $k6OutDir . DIRECTORY_SEPARATOR . $tag . "-state.json";
        $logFile = $k6OutDir . DIRECTORY_SEPARATOR . $tag . "-worker.log";
        $snapshotFile = $k6OutDir . DIRECTORY_SEPARATOR . $tag . "-snapshot.json";
        $snapshotCmd = "php " . escapeshellarg($snapshotScript)
            . " --tag=" . escapeshellarg($tag)
            . " --mongo-uri=" . escapeshellarg($mongoUriUsed)
            . " --summary=" . escapeshellarg($summaryFile)
            . " --state=" . escapeshellarg($stateFile)
            . " --log=" . escapeshellarg($logFile)
            . " --out=" . escapeshellarg($snapshotFile);
        $snapshotRes = shell_run($snapshotCmd);

        $cleanupScript = $k6Dir . DIRECTORY_SEPARATOR . "cleanup_fake_candidates.php";
        $cmd = "php " . escapeshellarg($cleanupScript)
            . " --tag=" . escapeshellarg($tag)
            . " --mongo-uri=" . escapeshellarg($mongoUriUsed);
        $res = shell_run($cmd);
        if ((int)$res["code"] !== 0) {
            json_out(["ok"=>false,"message"=>"Cleanup échoué","details"=>$res["out"]], 500);
        }
        $decoded = json_decode((string)$res["out"], true);
        json_out([
            "ok"=>true,
            "snapshot_created" => ((int)$snapshotRes["code"] === 0),
            "snapshot_details" => $snapshotRes["out"],
            "snapshot_file" => is_file($snapshotFile) ? $snapshotFile : null,
            "cleanup"=>$decoded
        ]);
    }

    json_out(["ok"=>false,"message"=>"Action inconnue"], 400);
}

$paperCursor = $papers->find(
    ["active"=>true, "type"=>['$in'=>['Factuel','Factuelle']], "mode"=>"mixed"],
    ["projection"=>["_id"=>1,"label"=>1,"counts"=>1], "sort"=>["created"=>-1], "limit"=>200]
);
$paperOptions = [];
foreach ($paperCursor as $p) {
    $qTotal = isset($p["counts"]["QCM_total"]) ? (int)$p["counts"]["QCM_total"] : 0;
    $paperOptions[] = [
        "id" => (string)$p["_id"],
        "label" => (string)($p["label"] ?? "Paper"),
        "qTotal" => $qTotal,
    ];
}

include_once "../partials/background-manager.php";
setPageBackground("bg-dashboard");
include_once "partials/header.php";
openBackgroundContainer();
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Test de charge candidats — MEDACAP</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root{--bg:#ffffffcc;--bd:#e2e8f0;--tx:#0f172a;--mut:#475569;--acc:#0ea5e9;--ok:#16a34a;--warn:#d97706;--err:#dc2626}
    body{font-family:Inter,Arial,sans-serif;color:var(--tx)}
    .shell{max-width:1400px;margin:0 auto;padding:18px}
    .card{background:var(--bg);backdrop-filter:blur(14px);border:1px solid var(--bd);border-radius:14px;padding:16px;margin-bottom:12px}
    .h{font-weight:800;font-size:1.1rem;margin:0 0 8px}
    .sub{color:var(--mut);font-size:.88rem}
    .row{display:grid;grid-template-columns:repeat(4,minmax(180px,1fr));gap:10px}
    .row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .f label{display:block;font-size:.76rem;color:var(--mut);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px}
    .f input,.f select{width:100%;padding:10px;border:1px solid var(--bd);border-radius:10px;background:#fff}
    .btn{border:none;border-radius:10px;padding:10px 14px;font-weight:700;cursor:pointer}
    .btn-primary{background:var(--acc);color:#fff}
    .btn-danger{background:var(--err);color:#fff}
    .btn-secondary{background:#f1f5f9;color:#334155}
    .badges{display:flex;gap:8px;flex-wrap:wrap}
    .badge{padding:4px 10px;border-radius:999px;font-size:.75rem;font-weight:700}
    .b-run{background:#fef3c7;color:#92400e}.b-ok{background:#dcfce7;color:#166534}.b-ko{background:#fee2e2;color:#991b1b}
    .kpis{display:grid;grid-template-columns:repeat(6,minmax(110px,1fr));gap:8px}
    .kpi{border:1px solid var(--bd);border-radius:10px;background:#fff;padding:10px}
    .kpi .v{font-size:1.05rem;font-weight:800}.kpi .l{font-size:.72rem;color:var(--mut);text-transform:uppercase}
    .issues{margin-top:10px;border:1px solid var(--bd);border-radius:10px;background:#fff;padding:10px;max-height:190px;overflow:auto}
    .issue{padding:8px 10px;border-radius:8px;margin-bottom:6px;font-size:.82rem}
    .sev-high{background:#fee2e2;color:#991b1b}
    .sev-medium{background:#fff7ed;color:#9a3412}
    .sev-low{background:#eff6ff;color:#1d4ed8}
    .table-wrap{max-height:360px;overflow:auto;border:1px solid var(--bd);border-radius:10px;background:#fff}
    table{width:100%;border-collapse:collapse;font-size:.84rem}
    th,td{padding:8px 10px;border-bottom:1px solid #eef2f7;text-align:left;white-space:nowrap}
    th{position:sticky;top:0;background:#f8fafc;z-index:1}
    .ok{color:#166534}.warn{color:#92400e}.mut{color:#64748b}
    .logs{background:#0b1020;color:#dbe7ff;border-radius:10px;padding:10px;height:240px;overflow:auto;font-family:Consolas,monospace;font-size:.78rem}
    @media(max-width:1100px){.row{grid-template-columns:1fr 1fr}.kpis{grid-template-columns:repeat(3,1fr)}.row2{grid-template-columns:1fr}}
    @media(max-width:700px){.row,.kpis{grid-template-columns:1fr 1fr}}
  </style>
</head>
<body>
<div class="shell">
  <div class="card">
    <h2 class="h">Test de charge candidats (k6)</h2>
    <p class="sub">Crée des candidats fictifs, lance une composition simultanée, suit l’exécution en temps réel, puis nettoyage.</p>
  </div>

  <div class="card">
    <div class="row">
      <div class="f">
        <label>Paper à tester</label>
        <select id="paper_id">
          <?php foreach ($paperOptions as $po): ?>
            <option value="<?= htmlspecialchars($po["id"]) ?>"><?= htmlspecialchars($po["label"]) ?><?= $po["qTotal"] ? " ({$po["qTotal"]} Q)" : "" ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="f"><label>Candidats fictifs</label><input id="count" type="number" min="1" max="500" value="100"></div>
      <div class="f"><label>VU simultanés</label><input id="target_vus" type="number" min="1" max="500" value="100"></div>
      <div class="f"><label>Durée steady (min)</label><input id="duration_min" type="number" min="1" max="180" value="30"></div>
    </div>
    <div style="display:flex;gap:8px;margin-top:10px">
      <button class="btn btn-primary" id="btnStart">Lancer le test de charge</button>
      <button class="btn btn-danger" id="btnCleanup" disabled>Nettoyer candidats fictifs + traces</button>
      <button class="btn btn-secondary" id="btnRefresh" disabled>Rafraîchir</button>
    </div>
    <div id="msg" class="sub" style="margin-top:8px"></div>
    <div class="sub" style="margin-top:8px">
      Si k6 tourne sur ta machine (et non sur le serveur), lance le test en CLI puis colle le tag ci-dessous pour suivre ici.
    </div>
    <div class="row" style="margin-top:8px">
      <div class="f">
        <label>Tag d'un test externe</label>
        <input id="external_tag" type="text" placeholder="ex: lt_20260617_113000">
      </div>
      <div class="f" style="align-self:end">
        <button class="btn btn-secondary" id="btnWatchTag">Suivre ce tag</button>
      </div>
    </div>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px">
      <div>
        <div class="h" style="margin:0">Exécution</div>
        <div class="sub">Tag: <strong id="tagVal">—</strong></div>
      </div>
      <div class="badges">
        <span class="badge b-run" id="statusBadge">Aucun test</span>
        <span class="badge" id="counterBadge">—</span>
      </div>
    </div>
    <div class="kpis" style="margin-top:10px">
      <div class="kpi"><div class="v" id="k_iter">—</div><div class="l">Iterations</div></div>
      <div class="kpi"><div class="v" id="k_vus">—</div><div class="l">VU max</div></div>
      <div class="kpi"><div class="v" id="k_fail">—</div><div class="l">Fail rate</div></div>
      <div class="kpi"><div class="v" id="k_avg">—</div><div class="l">Avg ms</div></div>
      <div class="kpi"><div class="v" id="k_p95">—</div><div class="l">P95 ms</div></div>
      <div class="kpi"><div class="v" id="k_p99">—</div><div class="l">P99 ms</div></div>
    </div>
    <div class="issues" id="issuesBox">
      <div class="h" style="margin:0 0 8px 0;font-size:.9rem">Failles détectées</div>
      <div id="issuesList" class="mut">Aucune faille détectée pour l'instant.</div>
    </div>
  </div>

  <div class="row2">
    <div class="card">
      <div class="h">Résultats candidats fictifs</div>
      <div class="table-wrap">
        <table id="candTable">
          <thead>
            <tr>
              <th>Username</th>
              <th>Statut</th>
              <th>QCM répondues</th>
              <th>Orientation</th>
              <th>Temps estimé</th>
              <th>Score</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
    <div class="card">
      <div class="h">Logs / erreurs (temps réel)</div>
      <div id="logs" class="logs"></div>
    </div>
  </div>
</div>

<script>
let currentTag = null;
let pollTimer = null;
const $ = (id) => document.getElementById(id);

function setMsg(txt, isErr=false){ $("msg").textContent = txt || ""; $("msg").style.color = isErr ? "#b91c1c" : "#334155"; }
function fmtPct(v){ return v == null ? "—" : `${(Number(v)*100).toFixed(2)}%`; }
function fmtMs(v){ return v == null ? "—" : `${Math.round(Number(v))}`; }

function renderStatus(data){
  const st = data?.state?.status || "unknown";
  $("tagVal").textContent = data?.tag || currentTag || "—";
  $("statusBadge").textContent = st;
  $("statusBadge").className = "badge " + (st === "completed" ? "b-ok" : st === "failed" ? "b-ko" : "b-run");
  const c = data?.counters || {};
  $("counterBadge").textContent = `done:${c.done||0} in_progress:${c.in_progress||0} idle:${c.not_started||0}`;

  const s = data?.summary || {};
  $("k_iter").textContent = s.iterations ?? "—";
  $("k_vus").textContent = s.vus_max ?? "—";
  $("k_fail").textContent = fmtPct(s.http_req_failed_rate);
  $("k_avg").textContent = fmtMs(s.http_req_duration_avg_ms);
  $("k_p95").textContent = fmtMs(s.http_req_duration_p95_ms);
  $("k_p99").textContent = fmtMs(s.http_req_duration_p99_ms);

  const issues = data?.issues || [];
  const issuesList = $("issuesList");
  if (!issues.length) {
    issuesList.className = "mut";
    issuesList.textContent = "Aucune faille détectée pour l'instant.";
  } else {
    issuesList.className = "";
    issuesList.innerHTML = issues.slice(0, 20).map(it => {
      const sev = (it?.severity || "low").toLowerCase();
      const cls = sev === "high" ? "sev-high" : (sev === "medium" ? "sev-medium" : "sev-low");
      const title = it?.title || "Signal";
      const detail = it?.detail || "";
      return `<div class="issue ${cls}"><strong>${title}</strong><div>${detail}</div></div>`;
    }).join("");
  }

  const tbody = $("candTable").querySelector("tbody");
  tbody.innerHTML = "";
  (data?.candidates || []).forEach(r => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${r.username || ""}</td>
      <td>${r.status || ""}</td>
      <td>${r.answered ?? 0}</td>
      <td>${r.orientation_answered ?? 0}</td>
      <td>${r.time_spent_estimated || "—"}</td>
      <td>${r.score == null ? "—" : r.score}</td>
    `;
    tbody.appendChild(tr);
  });

  const logs = [];
  (data?.errors || []).forEach(l => logs.push(`[ERR] ${l}`));
  (data?.logTail || []).forEach(l => logs.push(l));
  $("logs").textContent = logs.join("\n");
  $("logs").scrollTop = $("logs").scrollHeight;

  const ended = st === "completed" || st === "failed";
  $("btnCleanup").disabled = !currentTag;
  $("btnRefresh").disabled = !currentTag;
  if (ended && pollTimer){ clearInterval(pollTimer); pollTimer = null; }
}

async function callAction(action, extra = {}) {
  const fd = new FormData();
  fd.append("action", action);
  Object.entries(extra).forEach(([k,v]) => fd.append(k, String(v)));
  const res = await fetch("", { method: "POST", body: fd, headers: { "Accept": "application/json" } });
  const txt = await res.text();
  let j = null;
  try {
    j = JSON.parse(txt);
  } catch (_e) {
    const preview = (txt || "").replace(/\s+/g, " ").slice(0, 160);
    throw new Error(`Réponse non-JSON du serveur: ${preview}`);
  }
  if (!res.ok || j.ok === false) throw new Error(j.message || "Erreur");
  return j;
}

async function refreshStatus(){
  if (!currentTag) return;
  try {
    const j = await callAction("status", { tag: currentTag });
    renderStatus(j);
  } catch (e) {
    setMsg(`Status error: ${e.message}`, true);
  }
}

$("btnStart").addEventListener("click", async () => {
  try {
    setMsg("Lancement du test en cours...");
    const payload = {
      paper_id: $("paper_id").value,
      count: $("count").value,
      target_vus: $("target_vus").value,
      duration_min: $("duration_min").value,
      warmup_sec: 60,
      rampdown_sec: 60,
    };
    const j = await callAction("start", payload);
    currentTag = j.tag;
    setMsg(`Test lancé: ${j.tag} | ${j.createdUsers} candidats fictifs créés`);
    await refreshStatus();
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(refreshStatus, 3000);
    $("btnCleanup").disabled = false;
    $("btnRefresh").disabled = false;
  } catch (e) {
    setMsg(`Start error: ${e.message}`, true);
  }
});

$("btnRefresh").addEventListener("click", refreshStatus);

$("btnWatchTag").addEventListener("click", async () => {
  const t = ($("external_tag").value || "").trim();
  if (!t) { setMsg("Entre un tag de test externe.", true); return; }
  currentTag = t;
  setMsg(`Suivi du tag externe: ${t}`);
  try {
    await refreshStatus();
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(refreshStatus, 3000);
    $("btnCleanup").disabled = false;
    $("btnRefresh").disabled = false;
  } catch (e) {
    setMsg(`Watch error: ${e.message}`, true);
  }
});

$("btnCleanup").addEventListener("click", async () => {
  if (!currentTag) return;
  if (!confirm("Supprimer les candidats fictifs + traces pour ce test ?")) return;
  try {
    const j = await callAction("cleanup", { tag: currentTag });
    const snap = j.snapshot_created ? "snapshot sauvegardé" : "snapshot indisponible";
    setMsg(`Cleanup terminé pour ${currentTag} (${snap})`);
    console.log(j.cleanup);
  } catch (e) {
    setMsg(`Cleanup error: ${e.message}`, true);
  }
});
</script>
</body>
</html>
<?php
closeBackgroundContainer();
include_once "partials/footer.php";
