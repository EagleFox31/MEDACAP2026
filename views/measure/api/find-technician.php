<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

portalApiCors();

global $portalBridgeKey, $mongoUri, $mongoDb;

$key = $_GET['key'] ?? '';
if ($key !== $portalBridgeKey) {
    portalApiJson(['error' => 'Unauthorized'], 401);
}

$country = isset($_GET['country']) ? trim((string) $_GET['country']) : '';
$minMatch = isset($_GET['minMatch']) ? max(1, (int) $_GET['minMatch']) : 3;

$brandAliases = [
    'FUSO' => ['FUSO'],
    'RENAULT TRUCKS' => ['RENAULT TRUCKS', 'RENAULT TRUCK', 'RENAULT-TRUCKS'],
    'KING LONG' => ['KING LONG', 'KINGLONG'],
    'JCB' => ['JCB'],
    'LOVOL' => ['LOVOL'],
];

function portalNormalizeBrand(string $b): string
{
    return strtoupper(preg_replace('/\s+/', ' ', trim($b)));
}

function portalBrandHits(array $allBrands, array $brandAliases): array
{
    $normalized = array_filter(array_map('portalNormalizeBrand', $allBrands));
    $hits = [];
    foreach ($brandAliases as $canonical => $aliases) {
        foreach ($aliases as $alias) {
            if (in_array(portalNormalizeBrand($alias), $normalized, true)) {
                $hits[] = $canonical;
                break;
            }
        }
    }
    return array_values(array_unique($hits));
}

try {
    $client = portalApiDb($mongoUri, $mongoDb);
    $users = $client->selectDatabase($mongoDb)->users;

    $filter = [
        'active' => true,
        'profile' => ['$regex' => '^Technicien$', '$options' => 'i'],
        'department' => ['$regex' => 'Equipment', '$options' => 'i'],
    ];
    if ($country !== '') {
        $filter['country'] = ['$regex' => preg_quote($country, '/'), '$options' => 'i'];
    }

    $cursor = $users->find($filter, ['limit' => 800]);

    $matches = [];
    foreach ($cursor as $doc) {
        $arr = $doc instanceof MongoDB\Model\BSONDocument ? $doc->getArrayCopy() : (array) $doc;
        $allBrands = array_unique(array_merge(
            (array) ($arr['brandJunior'] ?? []),
            (array) ($arr['brandSenior'] ?? []),
            (array) ($arr['brandExpert'] ?? [])
        ));

        $normalized = array_map(static fn($b) => strtoupper(trim((string) $b)), $allBrands);
        $hit = portalBrandHits($allBrands, $brandAliases);
        if (count($hit) < $minMatch) {
            continue;
        }

        $matches[] = [
            'id' => (string) ($arr['_id'] ?? ''),
            'username' => $arr['username'] ?? null,
            'firstName' => $arr['firstName'] ?? null,
            'lastName' => $arr['lastName'] ?? null,
            'email' => $arr['email'] ?? null,
            'country' => $arr['country'] ?? null,
            'subsidiary' => $arr['subsidiary'] ?? null,
            'agency' => $arr['agency'] ?? null,
            'department' => $arr['department'] ?? null,
            'brandJunior' => array_values((array) ($arr['brandJunior'] ?? [])),
            'brandSenior' => array_values((array) ($arr['brandSenior'] ?? [])),
            'brandExpert' => array_values((array) ($arr['brandExpert'] ?? [])),
            'brandsMatched' => array_values($hit),
            'matchCount' => count($hit),
        ];
    }

    usort($matches, static fn($a, $b) => $b['matchCount'] <=> $a['matchCount']);

    portalApiJson([
        'targetBrands' => array_keys($brandAliases),
        'countryFilter' => $country ?: null,
        'minMatch' => $minMatch,
        'count' => count($matches),
        'users' => array_slice($matches, 0, 15),
    ]);
} catch (Throwable $e) {
    portalApiJson(['error' => $e->getMessage()], 500);
}
