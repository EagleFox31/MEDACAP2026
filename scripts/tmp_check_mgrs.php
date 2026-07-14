<?php
require __DIR__ . '/../vendor/autoload.php';
$db = (new MongoDB\Client('mongodb://10.68.0.7:27017'))->academy;

$usernames = ['cl4fo', 'ma66ku', 'gi07pa', 'la51di'];
foreach ($usernames as $u) {
    $m = $db->users->findOne(['username' => new MongoDB\BSON\Regex('^' . preg_quote($u, '/') . '$', 'i'), 'active' => true]);
    if (!$m) { echo "$u: NOT FOUND\n"; continue; }
    echo "$u | profile=" . ($m['profile'] ?? '-') . " | level=" . ($m['level'] ?? '-') . "\n";
    echo "  brands J:" . json_encode($m['brandJunior'] ?? []) . "\n";
    echo "  brands S:" . json_encode($m['brandSenior'] ?? []) . "\n";
    echo "  brands E:" . json_encode($m['brandExpert'] ?? []) . "\n";
    echo "  manager=" . (isset($m['manager']) ? (string)$m['manager'] : 'none') . " | test=" . var_export($m['test'] ?? null, true) . "\n\n";
}
