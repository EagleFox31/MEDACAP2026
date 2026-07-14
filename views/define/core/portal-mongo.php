<?php
/**
 * Connexion MongoDB Define — même résolution que l'API portail (10.68.0.7 en dev WAMP).
 */

function defineResolvePortalMongoConfig(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $portalApiLocal = [];
    $portalApiConfigPath = __DIR__ . '/../../measure/api/config.local.php';
    if (is_file($portalApiConfigPath)) {
        $portalApiLocal = require $portalApiConfigPath;
    }

    require_once __DIR__ . '/../../measure/api/mongo-resolver.php';
    $mongoResolved = portalApiResolveMongoUri($portalApiLocal);

    $cached = [
        'uri' => $mongoResolved['uri'],
        'db'  => $portalApiLocal['mongoDb'] ?? 'academy',
    ];

    return $cached;
}

function definePortalMongoClient(): MongoDB\Client
{
    static $client = null;
    if ($client instanceof MongoDB\Client) {
        return $client;
    }

    $cfg = defineResolvePortalMongoConfig();
    $client = new MongoDB\Client($cfg['uri'], [
        'serverSelectionTimeoutMS' => 60000,
        'connectTimeoutMS' => 15000,
    ]);

    return $client;
}

function definePortalMongoDatabase(): MongoDB\Database
{
    if (!empty($GLOBALS['defineAcademy']) && $GLOBALS['defineAcademy'] instanceof MongoDB\Database) {
        return $GLOBALS['defineAcademy'];
    }

    static $db = null;
    if ($db instanceof MongoDB\Database) {
        return $db;
    }

    $cfg = defineResolvePortalMongoConfig();
    $db = definePortalMongoClient()->selectDatabase($cfg['db']);

    return $db;
}
