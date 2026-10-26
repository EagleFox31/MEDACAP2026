<?php
require_once __DIR__ . '/../core/medacap_config.php';
require __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\Client;

$uri = medacapMongoUri();
$db = (new Client($uri))->academy;

$id = $argv[1] ?? '6944559e72d68a2edb25d835';
$q = $db->orientation_questions->findOne(['_id' => new ObjectId($id)]);
if (!$q) {
    echo "Not found\n";
    exit(1);
}
echo json_encode([
    'id' => (string)$q['_id'],
    'title' => $q['title'] ?? '',
    'minSelect' => $q['minSelect'] ?? null,
    'maxSelect' => $q['maxSelect'] ?? null,
    'type' => $q['type'] ?? null,
    'multi' => $q['multi'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
