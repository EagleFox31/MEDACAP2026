<?php
require __DIR__ . "/../../vendor/autoload.php";

function argValue(array $argv, string $name, ?string $default = null): ?string {
    $prefix = "--{$name}=";
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

function state_write(string $path, array $state): void {
    $state["updated_at"] = date("Y-m-d H:i:s");
    file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$tag = (string)argValue($argv, "tag", "");
$baseUrl = (string)argValue($argv, "base-url", "http://10.68.0.7/medacap");
$paperId = (string)argValue($argv, "paper-id", "");
$targetVus = (string)argValue($argv, "target-vus", "100");
$warmup = (string)argValue($argv, "warmup", "60s");
$steady = (string)argValue($argv, "steady", "30m");
$rampdown = (string)argValue($argv, "rampdown", "60s");
$usersFile = (string)argValue($argv, "users-file", "");
$summaryFile = (string)argValue($argv, "summary-file", "");
$stateFile = (string)argValue($argv, "state-file", "");
$scriptFile = (string)argValue($argv, "script-file", __DIR__ . "/medacap-quiz-load.js");
$logFile = (string)argValue($argv, "log-file", __DIR__ . "/out/{$tag}-worker.log");

if ($tag === "" || $paperId === "" || $usersFile === "" || $summaryFile === "" || $stateFile === "") {
    fwrite(STDERR, "Missing required args for worker.\n");
    exit(1);
}

$state = [
    "tag" => $tag,
    "status" => "running",
    "message" => "Load test started",
    "started_at" => date("Y-m-d H:i:s"),
    "baseUrl" => $baseUrl,
    "paperId" => $paperId,
    "targetVus" => (int)$targetVus,
    "warmup" => $warmup,
    "steady" => $steady,
    "rampdown" => $rampdown,
    "usersFile" => $usersFile,
    "summaryFile" => $summaryFile,
    "logFile" => $logFile,
    "last_line" => "",
    "error" => null,
    "exit_code" => null,
];
state_write($stateFile, $state);

$cmd = "k6 run "
    . escapeshellarg($scriptFile)
    . " -e " . escapeshellarg("BASE_URL={$baseUrl}")
    . " -e " . escapeshellarg("PAPER_ID={$paperId}")
    . " -e " . escapeshellarg("TARGET_VUS={$targetVus}")
    . " -e " . escapeshellarg("WARMUP_DURATION={$warmup}")
    . " -e " . escapeshellarg("STEADY_DURATION={$steady}")
    . " -e " . escapeshellarg("RAMPDOWN_DURATION={$rampdown}")
    . " -e " . escapeshellarg("USERS_FILE={$usersFile}")
    . " --summary-export " . escapeshellarg($summaryFile);

$desc = [
    0 => ["pipe", "r"],
    1 => ["pipe", "w"],
    2 => ["pipe", "w"],
];

$proc = proc_open($cmd, $desc, $pipes, dirname(__DIR__, 2));
if (!is_resource($proc)) {
    $state["status"] = "failed";
    $state["message"] = "Unable to start k6 process";
    $state["error"] = "proc_open failed";
    state_write($stateFile, $state);
    exit(1);
}

fclose($pipes[0]);
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);

$logFp = fopen($logFile, "ab");
$lastStateAt = 0;

while (true) {
    $status = proc_get_status($proc);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    $chunk = "";
    if ($stdout !== false && $stdout !== "") $chunk .= $stdout;
    if ($stderr !== false && $stderr !== "") $chunk .= $stderr;
    if ($chunk !== "") {
        if ($logFp) fwrite($logFp, $chunk);
        $lines = preg_split("/\r\n|\n|\r/", trim($chunk));
        $line = trim((string)end($lines));
        if ($line !== "") $state["last_line"] = $line;
    }

    $now = time();
    if ($now - $lastStateAt >= 2) {
        $state["message"] = $status["running"] ? "Load test running" : "Load test finishing";
        state_write($stateFile, $state);
        $lastStateAt = $now;
    }

    if (!$status["running"]) {
        break;
    }
    usleep(250000);
}

$exitCode = proc_close($proc);
if ($logFp) fclose($logFp);

$state["exit_code"] = $exitCode;
$state["finished_at"] = date("Y-m-d H:i:s");
if ($exitCode === 0 && is_file($summaryFile)) {
    $state["status"] = "completed";
    $state["message"] = "Load test completed";
} else {
    $state["status"] = "failed";
    $state["message"] = "Load test failed";
    if (!is_file($summaryFile)) {
        $state["error"] = "summary-export file missing (k6 might be unavailable)";
    }
}
state_write($stateFile, $state);
