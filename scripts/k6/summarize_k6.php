<?php
function argValue(array $argv, string $name, ?string $default = null): ?string {
    $prefix = "--{$name}=";
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

$summaryPath = (string)(argValue($argv, "summary", ""));
if ($summaryPath === "" || !is_file($summaryPath)) {
    fwrite(STDERR, "ERROR: missing/invalid --summary path\n");
    exit(1);
}

$raw = file_get_contents($summaryPath);
$data = json_decode($raw, true);
if (!is_array($data)) {
    fwrite(STDERR, "ERROR: cannot parse summary json\n");
    exit(1);
}

$m = $data["metrics"] ?? [];
$httpReqDuration = $m["http_req_duration"] ?? [];
$httpReqFailed = $m["http_req_failed"] ?? [];
$iterations = $m["iterations"] ?? [];
$vusMax = $m["vus_max"] ?? [];

function metric_values(array $metrics, string $key): array {
    $node = $metrics[$key] ?? [];
    if (!is_array($node)) return [];
    if (isset($node["values"]) && is_array($node["values"])) return $node["values"];
    return $node;
}

function metric_brief(array $values): array {
    return [
        "count" => $values["count"] ?? null,
        "rate" => $values["rate"] ?? null,
        "avg" => $values["avg"] ?? null,
        "p95" => $values["p(95)"] ?? null,
        "p99" => $values["p(99)"] ?? null,
    ];
}

$out = [
    "ok" => true,
    "summary_file" => $summaryPath,
    "kpis" => [
        "iterations" => $iterations["count"] ?? null,
        "vus_max" => $vusMax["max"] ?? null,
        "http_req_failed_rate" => $httpReqFailed["rate"] ?? ($httpReqFailed["value"] ?? null),
        "http_req_duration_avg_ms" => $httpReqDuration["avg"] ?? null,
        "http_req_duration_p95_ms" => $httpReqDuration["p(95)"] ?? null,
        "http_req_duration_p99_ms" => $httpReqDuration["p(99)"] ?? null,
    ],
    "steps" => [
        "login" => [
            "http_req_duration" => metric_brief(metric_values($m, "http_req_duration{step:login}")),
            "http_req_failed" => metric_brief(metric_values($m, "http_req_failed{step:login}")),
        ],
        "candidate_home" => [
            "http_req_duration" => metric_brief(metric_values($m, "http_req_duration{step:candidate_home}")),
            "http_req_failed" => metric_brief(metric_values($m, "http_req_failed{step:candidate_home}")),
        ],
        "start_paper" => [
            "http_req_duration" => metric_brief(metric_values($m, "http_req_duration{step:start_paper}")),
            "http_req_failed" => metric_brief(metric_values($m, "http_req_failed{step:start_paper}")),
        ],
        "open_quiz" => [
            "http_req_duration" => metric_brief(metric_values($m, "http_req_duration{step:open_quiz}")),
            "http_req_failed" => metric_brief(metric_values($m, "http_req_failed{step:open_quiz}")),
        ],
        "autosave" => [
            "http_req_duration" => metric_brief(metric_values($m, "http_req_duration{step:autosave}")),
            "http_req_failed" => metric_brief(metric_values($m, "http_req_failed{step:autosave}")),
        ],
    ],
    "checks" => [
        "login_http_ok_rate" => (metric_values($m, "checks{check:login http ok}")["rate"] ?? null),
        "candidate_home_authenticated_rate" => (metric_values($m, "checks{check:candidate home authenticated}")["rate"] ?? null),
        "start_paper_reached_quiz_rate" => (metric_values($m, "checks{check:start paper reached quiz}")["rate"] ?? null),
        "autosave_json_ok_rate" => (metric_values($m, "checks{check:autosave json ok}")["rate"] ?? null),
    ],
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
