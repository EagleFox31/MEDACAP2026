<?php
// components/subsidiaryFilter.php

/**
 * Résout un filtre filiale vers une clause MongoDB (string ou $in).
 *
 * @param string $subsidiary Valeur du filtre (ex. CFAO MOBILITY CAMEROON)
 * @return string|array       Valeur exacte ou ['$in' => [...]]
 */
function resolveSubsidiaryMongoFilter(string $subsidiary)
{
    static $aliases = null;

    if ($aliases === null) {
        $path = __DIR__ . '/subsidiaryAliases.php';
        $aliases = file_exists($path) ? include $path : [];
    }

    $subsidiary = trim($subsidiary);
    if ($subsidiary === '' || $subsidiary === 'all') {
        return 'all';
    }

    if (isset($aliases[$subsidiary])) {
        $values = array_values(array_unique(array_filter(array_map('trim', $aliases[$subsidiary]))));
        if (count($values) === 1) {
            return $values[0];
        }
        if (count($values) > 1) {
            return ['$in' => $values];
        }
    }

    return $subsidiary;
}

/**
 * Liste toutes les filiales couvertes par un filtre (pour agences, etc.).
 *
 * @return string[]
 */
function expandSubsidiaryFilter(string $subsidiary): array
{
    $resolved = resolveSubsidiaryMongoFilter($subsidiary);
    if ($resolved === 'all') {
        return [];
    }
    if (is_array($resolved) && isset($resolved['$in'])) {
        return $resolved['$in'];
    }
    return [$resolved];
}
