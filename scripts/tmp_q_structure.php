<?php
require_once __DIR__ . '/../core/medacap_config.php';
require __DIR__ . '/../vendor/autoload.php';
$db = (new MongoDB\Client(medacapMongoUri()))->academy;
$ids = ['PUT_ID_HERE'];
foreach (['6944559e72d68a2edb25d835'] as $id) {}
$q = $db->questions->findOne([], ['limit' => 1]);
echo json_encode($q, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
