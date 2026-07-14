<?php
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

$tag = (string)(argValue($argv, "tag", ""));
$mongoUri = (string)(argValue($argv, "mongo-uri", "mongodb://10.68.0.7:27017"));

if ($tag === "") {
    fwrite(STDERR, "ERROR: missing --tag\n");
    exit(1);
}

$client = new Client($mongoUri);
$db = $client->academy;
$users = $db->users;
$allocations = $db->allocations;
$exams = $db->exams;
$results = $db->results;
$connections = $db->connections;

$cursor = $users->find(["loadtestTag" => $tag], ["projection" => ["_id" => 1]]);
$userIds = [];
foreach ($cursor as $u) {
    if ($u["_id"] instanceof ObjectId) {
        $userIds[] = $u["_id"];
    }
}

if (empty($userIds)) {
    echo json_encode([
        "ok" => true,
        "tag" => $tag,
        "message" => "No users found for tag",
        "deleted" => ["users" => 0, "allocations" => 0, "exams" => 0, "results" => 0, "connections" => 0],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
    exit(0);
}

$allocDel = $allocations->deleteMany(["user" => ['$in' => $userIds]])->getDeletedCount();
$examDel = $exams->deleteMany(["user" => ['$in' => $userIds]])->getDeletedCount();
$resDel = $results->deleteMany(["user" => ['$in' => $userIds]])->getDeletedCount();
$connDel = $connections->deleteMany(["user" => ['$in' => $userIds]])->getDeletedCount();
$userDel = $users->deleteMany(["_id" => ['$in' => $userIds]])->getDeletedCount();

echo json_encode([
    "ok" => true,
    "tag" => $tag,
    "deleted" => [
        "users" => $userDel,
        "allocations" => $allocDel,
        "exams" => $examDel,
        "results" => $resDel,
        "connections" => $connDel,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
