<?php
require_once __DIR__ . '/../core/medacap_config.php';
require __DIR__ . '/../vendor/autoload.php';
try {
    $db = (new MongoDB\Client(medacapMongoUri()))->academy;
    $q = $db->questions->findOne(['ref' => 'Fac2o25'], ['projection' => ['ref' => 1, 'image' => 1, 'label' => 1]]);
    echo json_encode($q, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    echo 'mongo: ' . $e->getMessage() . PHP_EOL;
}
