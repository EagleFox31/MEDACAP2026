<?php
require __DIR__ . '/../vendor/autoload.php';
$db = (new MongoDB\Client('mongodb://localhost:27017'))->academy;
$seen = [];
foreach ($db->questions->find(['image' => ['$ne' => null, '$ne' => ''], 'active' => true], ['limit' => 30, 'projection' => ['image' => 1, 'ref' => 1, '_id' => 1]]) as $q) {
    $img = (string)($q['image'] ?? '');
    if ($img === '') continue;
    echo (string)$q['_id'] . " | ref=" . ($q['ref'] ?? '') . " | image=" . $img . "\n";
}
