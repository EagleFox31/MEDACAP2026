<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/medacap_config.php';

/**
 * Résolution MongoDB pour l’API portail.
 *
 * Dev  : 127.0.0.1 → 10.68.0.7 si indispo ; si les deux échouent, on garde 127 (erreur à l’usage).
 * Prod : 10.68.0.7 uniquement.
 *
 * Même schéma de config prévu pour Postgres (clé `postgres` — non implémenté).
 */

function portalApiDetectEnv(array $local = []): string
{
    $fromConfig = strtolower(trim((string) ($local['env'] ?? '')));
    if (in_array($fromConfig, ['dev', 'development', 'local'], true)) {
        return 'dev';
    }
    if (in_array($fromConfig, ['prod', 'production'], true)) {
        return 'prod';
    }

    $fromEnv = strtolower(trim((string) (getenv('PORTAL_API_ENV') ?: getenv('APP_ENV') ?: '')));
    if (in_array($fromEnv, ['dev', 'development', 'local'], true)) {
        return 'dev';
    }
    if (in_array($fromEnv, ['prod', 'production'], true)) {
        return 'prod';
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    if ($host === 'localhost'
        || strpos($host, '127.0.0.1') === 0
        || strpos($host, 'localhost:') === 0
    ) {
        return 'dev';
    }

    $addr = (string) ($_SERVER['SERVER_ADDR'] ?? '');
    if ($addr === '127.0.0.1' || $addr === '::1') {
        return 'dev';
    }

    return 'prod';
}

function portalApiMongoNetworkHost(array $local = []): string
{
    $host = trim((string) ($local['mongoHost'] ?? $local['mongo']['host'] ?? '10.68.0.7'));
    return $host !== '' ? $host : '10.68.0.7';
}

function portalApiMongoUriFromHost(string $host, int $port = 27017): string
{
    return sprintf('mongodb://%s:%d', $host, $port);
}

/** @return list<string> */
function portalApiMongoUriCandidates(string $env, array $local = []): array
{
    $networkHost = portalApiMongoNetworkHost($local);
    $port = (int) ($local['mongoPort'] ?? $local['mongo']['port'] ?? 27017);
    $networkUri = portalApiMongoUriFromHost($networkHost, $port);

    if ($env === 'prod') {
        return [$networkUri];
    }

    $localHost = trim((string) ($local['mongoLocalHost'] ?? $local['mongo']['localHost'] ?? '127.0.0.1'));
    if ($localHost === '') {
        $localHost = '127.0.0.1';
    }
    $localUri = portalApiMongoUriFromHost($localHost, $port);

    if ($localUri === $networkUri) {
        return [$localUri];
    }

    return [$localUri, $networkUri];
}

function portalApiParseMongoHostPort(string $uri): ?array
{
    if (!preg_match('#^mongodb://([^:/]+)(?::(\d+))?#', $uri, $matches)) {
        return null;
    }

    return [
        'host' => $matches[1],
        'port' => isset($matches[2]) ? (int) $matches[2] : 27017,
    ];
}

function portalApiHostReachable(string $host, int $port, float $timeoutSec = 1.0): bool
{
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeoutSec);
    if ($fp === false) {
        return false;
    }

    fclose($fp);
    return true;
}

function portalApiProbeMongoUri(string $uri, int $timeoutMs = 1500): bool
{
    if (!class_exists(MongoDB\Client::class)) {
        return false;
    }

    $parsed = portalApiParseMongoHostPort($uri);
    if ($parsed && !portalApiHostReachable($parsed['host'], $parsed['port'], min(1.0, $timeoutMs / 1000))) {
        return false;
    }

    try {
        $client = new MongoDB\Client($uri, [
            'serverSelectionTimeoutMS' => $timeoutMs,
            'connectTimeoutMS' => $timeoutMs,
        ]);
        $client->selectDatabase('admin')->command(['ping' => 1]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function portalApiMongoUriCachePath(string $cacheKey): string
{
    return sys_get_temp_dir() . '/cfao_portal_mongo_' . md5($cacheKey) . '.json';
}

function portalApiReadMongoUriCache(string $cacheKey, int $ttlSeconds): ?string
{
    if ($ttlSeconds <= 0) {
        return null;
    }

    $path = portalApiMongoUriCachePath($cacheKey);
    if (!is_file($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if ($raw === false) {
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['uri']) || empty($data['expires'])) {
        return null;
    }

    if ((int) $data['expires'] < time()) {
        @unlink($path);
        return null;
    }

    return (string) $data['uri'];
}

function portalApiWriteMongoUriCache(string $cacheKey, string $uri, int $ttlSeconds): void
{
    if ($ttlSeconds <= 0) {
        return;
    }

    $path = portalApiMongoUriCachePath($cacheKey);
    $payload = json_encode([
        'uri' => $uri,
        'expires' => time() + $ttlSeconds,
        'savedAt' => date('c'),
    ], JSON_UNESCAPED_SLASHES);

    if ($payload !== false) {
        @file_put_contents($path, $payload, LOCK_EX);
    }
}

/**
 * @return array{uri: string, source: string, env: string, probed: bool}
 */
function portalApiResolveMongoUri(array $local = []): array
{
    $fromEnv = medacapEnvString('MEDACAP_MONGO_URI');
    if ($fromEnv !== '') {
        return [
            'uri' => $fromEnv,
            'source' => 'MEDACAP_MONGO_URI',
            'env' => portalApiDetectEnv($local),
            'probed' => false,
        ];
    }

    $explicit = trim((string) ($local['mongoUri'] ?? ''));
    if ($explicit !== '') {
        return [
            'uri' => $explicit,
            'source' => 'explicit',
            'env' => portalApiDetectEnv($local),
            'probed' => false,
        ];
    }

    $env = portalApiDetectEnv($local);
    $candidates = portalApiMongoUriCandidates($env, $local);
    $timeoutMs = (int) ($local['mongoProbeTimeoutMs'] ?? $local['mongo']['probeTimeoutMs'] ?? 1500);
    $cacheTtl = (int) ($local['mongoProbeCacheTtl'] ?? $local['mongo']['probeCacheTtl'] ?? 30);
    $cacheKey = $env . '|' . implode('|', $candidates);

    $cached = portalApiReadMongoUriCache($cacheKey, $cacheTtl);
    if ($cached && portalApiProbeMongoUri($cached, min(800, $timeoutMs))) {
        return [
            'uri' => $cached,
            'source' => 'cache',
            'env' => $env,
            'probed' => true,
        ];
    }

    foreach ($candidates as $candidate) {
        if (portalApiProbeMongoUri($candidate, $timeoutMs)) {
            portalApiWriteMongoUriCache($cacheKey, $candidate, $cacheTtl);
            return [
                'uri' => $candidate,
                'source' => $candidate,
                'env' => $env,
                'probed' => true,
            ];
        }
    }

    // Aucun serveur joignable : on laisse la première URI (dev → 127, prod → 10.68).
    return [
        'uri' => $candidates[0],
        'source' => 'unavailable',
        'env' => $env,
        'probed' => false,
    ];
}
