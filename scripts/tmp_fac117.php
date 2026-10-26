<?php
require_once __DIR__ . '/../core/medacap_config.php';
require __DIR__ . '/../vendor/autoload.php';
$db = (new MongoDB\Client(medacapMongoUri()))->academy;
$q = $db->questions->findOne(['ref' => 'Fac117'], ['projection' => ['ref' => 1, 'image' => 1, 'label' => 1]]);
echo json_encode($q, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
