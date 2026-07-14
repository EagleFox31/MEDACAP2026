<?php
session_start();
require_once "../vendor/autoload.php";
require_once "../views/sendMail.php";

use MongoDB\Client as MongoClient;
use MongoDB\BSON\UTCDateTime;

$token    = isset($_GET['token'])   ? trim($_GET['token'])         : '';
$redirect = isset($_GET['redirect']) ? basename($_GET['redirect']) : 'responses.php';

if (empty($token)) {
    header("Location: ../index.php");
    exit();
}

$mongoClient = new MongoClient("mongodb://localhost:27017");
$db = $mongoClient->academy;

$tokenDoc = $db->admin_tokens->findOne([
    'token'      => $token,
    'used'       => false,
    'expires_at' => ['$gt' => new UTCDateTime()],
]);

if (!$tokenDoc) {
    header("Location: ../index.php?error=token_expired");
    exit();
}

$db->admin_tokens->updateOne(
    ['token' => $token],
    ['$set' => ['used' => true]]
);

$user = $db->users->findOne(['email' => (string)$tokenDoc['admin_email']]);

if (!$user) {
    header("Location: ../index.php?error=user_not_found");
    exit();
}

$_SESSION['username']   = isset($user['username'])   ? (string)$user['username']   : '';
$_SESSION['profile']    = isset($user['profile'])    ? (string)$user['profile']    : 'Super Admin';
$_SESSION['lastName']   = isset($user['lastName'])   ? (string)$user['lastName']   : '';
$_SESSION['firstName']  = isset($user['firstName'])  ? (string)$user['firstName']  : '';
$_SESSION['email']      = isset($user['email'])      ? (string)$user['email']      : '';
$_SESSION['test']       = isset($user['test'])       ? (string)$user['test']       : '';
$_SESSION['id']         = (string)$user['_id'];
$_SESSION['subsidiary'] = isset($user['subsidiary']) ? (string)$user['subsidiary'] : '';
$_SESSION['agency']     = isset($user['agency'])     ? (string)$user['agency']     : '';
$_SESSION['department'] = isset($user['department']) ? (string)$user['department'] : '';
$_SESSION['lang']       = isset($user['lang'])       ? (string)$user['lang']       : 'FR';
$_SESSION['country']    = isset($user['country'])    ? (string)$user['country']    : 'CFAO';

header("Location: " . $redirect);
exit();
