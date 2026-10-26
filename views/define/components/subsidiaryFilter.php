<?php
// components/subsidiaryFilter.php — délègue au helper central (alias filiales).

require_once __DIR__ . '/../../../core/subsidiary_aliases.php';

/**
 * Résout un filtre filiale vers une clause MongoDB (string ou $in).
 *
 * @param string $subsidiary Valeur du filtre (ex. CFAO MOBILITY CAMEROON)
 * @return string|array       Valeur exacte ou ['$in' => [...]]
 */
function resolveSubsidiaryMongoFilter($subsidiary)
{
    return medacapResolveSubsidiaryMongoFilter($subsidiary);
}

/**
 * Liste toutes les filiales couvertes par un filtre (pour agences, etc.).
 *
 * @return string[]
 */
function expandSubsidiaryFilter($subsidiary)
{
    $resolved = medacapResolveSubsidiaryMongoFilter($subsidiary);
    if ($resolved === 'all') {
        return array();
    }
    if (is_array($resolved) && isset($resolved['$in'])) {
        return $resolved['$in'];
    }

    return array($resolved);
}
