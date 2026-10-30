<?php
require_once __DIR__ . '/../../core/medacap_config.php';
require __DIR__ . "/../../vendor/autoload.php";

use MongoDB\Client;
use MongoDB\BSON\ObjectId;

function argValue(array $argv, string $name, ?string $default = null): ?string {
    $prefix = "--{$name}=";
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

$paperId = (string)(argValue($argv, "paper-id", ""));
$count = max(1, (int)(argValue($argv, "count", "100")));
$password = (string)(argValue($argv, "password", "0000"));
$mongoUri = (string)(argValue($argv, "mongo-uri", medacapMongoUri('mongodb://10.68.0.7:27017')));
$tag = (string)(argValue($argv, "tag", "lt_" . date("Ymd_His")));
$country = (string)(argValue($argv, "country", "Cameroun"));

if (!preg_match('/^[0-9a-f]{24}$/i', $paperId)) {
    fwrite(STDERR, "ERROR: --paper-id must be a valid ObjectId.\n");
    exit(1);
}

$client = new Client($mongoUri);
$db = $client->academy;
$users = $db->users;
$papers = $db->papers;

$paper = $papers->findOne(["_id" => new ObjectId($paperId), "active" => true], ["projection" => ["label" => 1]]);
if (!$paper) {
    fwrite(STDERR, "ERROR: paper not found/active for id {$paperId}.\n");
    exit(1);
}
$paperLabel = (string)($paper["label"] ?? "Paper");

$outDir = __DIR__ . "/out";
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}
$csvPath = $outDir . "/{$tag}-users.csv";
$metaPath = $outDir . "/{$tag}-meta.json";

$inserted = [];
$lines = [];
for ($i = 1; $i <= $count; $i++) {
    $idx = str_pad((string)$i, 3, "0", STR_PAD_LEFT);
    $username = "{$tag}_{$idx}";
    $firstName = "Load";
    $lastName = "User {$idx}";
    $now = date("d-m-Y H:i:s");

    $doc = [
        "username" => $username,
        "firstName" => $firstName,
        "lastName" => strtoupper($lastName),
        "password" => sha1($password),
        "visiblePassword" => $password,
        "profile" => "Candidat",
        "country" => $country,
        "subsidiary" => "CFAO MOBILITY CAMEROON",
        "department" => "Load Test",
        "role" => $paperLabel,
        "roles" => [$paperLabel],
        "papers" => [new ObjectId($paperId)],
        "active" => true,
        "status" => "New",
        "test" => true,
        "loadtestTag" => $tag,
        "created" => $now,
        "updated" => $now,
    ];

    $res = $users->insertOne($doc);
    $inserted[] = (string)$res->getInsertedId();
    $lines[] = "{$username},{$password}";
}

file_put_contents($csvPath, implode(PHP_EOL, $lines) . PHP_EOL);
file_put_contents($metaPath, json_encode([
    "tag" => $tag,
    "paperId" => $paperId,
    "paperLabel" => $paperLabel,
    "count" => $count,
    "mongoUri" => $mongoUri,
    "csvPath" => $csvPath,
    "userIds" => $inserted,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode([
    "ok" => true,
    "tag" => $tag,
    "paperId" => $paperId,
    "paperLabel" => $paperLabel,
    "createdUsers" => $count,
    "usersCsv" => $csvPath,
    "metaFile" => $metaPath,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
