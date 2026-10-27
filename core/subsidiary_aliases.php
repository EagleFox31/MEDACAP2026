<?php
/**
 * Alias filiales MEDACAP — libellés historiques vs actuels (sans migration Mongo).
 * PHP 7.3
 *
 * Ex. Cameroun : CFAO MOBILITY CAMEROON est le libellé stocké.
 * CAMEROON MOTORS INDUSTRIES reste accepté s'il réapparaît dans d'anciennes données.
 */

if (!defined('MEDACAP_SUBSIDIARY_ALIASES')) {
    define('MEDACAP_SUBSIDIARY_ALIASES', serialize(array(
        'CFAO MOBILITY CAMEROON' => array(
            'CFAO MOBILITY CAMEROON',
            'CAMEROON MOTORS INDUSTRIES',
        ),
    )));
}

if (!function_exists('medacapSubsidiaryAliasGroups')) {
    function medacapSubsidiaryAliasGroups()
    {
        return unserialize(MEDACAP_SUBSIDIARY_ALIASES);
    }
}

if (!function_exists('medacapExpandSubsidiary')) {
    /**
     * Toutes les variantes MongoDB équivalentes à une filiale donnée.
     *
     * @param string|null $subsidiary
     * @return string[]
     */
    function medacapExpandSubsidiary($subsidiary)
    {
        $subsidiary = trim((string) $subsidiary);
        if ($subsidiary === '') {
            return array();
        }

        foreach (medacapSubsidiaryAliasGroups() as $variants) {
            foreach ($variants as $variant) {
                if (strcasecmp($variant, $subsidiary) === 0) {
                    return array_values(array_unique($variants));
                }
            }
        }

        return array($subsidiary);
    }
}

if (!function_exists('medacapResolveSubsidiaryMongoFilter')) {
    /**
     * Valeur MongoDB pour filtrer une filiale (string ou array('$in' => ...)).
     *
     * @param string|null $subsidiary
     * @return string|array|'all'
     */
    function medacapResolveSubsidiaryMongoFilter($subsidiary)
    {
        if ($subsidiary === null || $subsidiary === '' || $subsidiary === 'all') {
            return 'all';
        }

        $expanded = medacapExpandSubsidiary($subsidiary);
        if (count($expanded) === 0) {
            return 'all';
        }
        if (count($expanded) === 1) {
            return $expanded[0];
        }

        return array('$in' => $expanded);
    }
}

if (!function_exists('medacapSubsidiariesMatch')) {
    /**
     * Compare deux filiales en tenant compte des alias.
     */
    function medacapSubsidiariesMatch($a, $b)
    {
        $a = trim((string) $a);
        $b = trim((string) $b);
        if ($a === $b) {
            return true;
        }

        $groupA = array_map('strtolower', medacapExpandSubsidiary($a));
        $groupB = array_map('strtolower', medacapExpandSubsidiary($b));

        foreach ($groupA as $va) {
            if (in_array($va, $groupB, true)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('medacapApplySubsidiaryToQuery')) {
    /**
     * @param array<string, mixed> $query
     * @param string|null $subsidiary
     * @param string $field
     */
    function medacapApplySubsidiaryToQuery(&$query, $subsidiary, $field = 'subsidiary')
    {
        $resolved = medacapResolveSubsidiaryMongoFilter($subsidiary);
        if ($resolved === 'all') {
            return;
        }
        $query[$field] = $resolved;
    }
}

if (!function_exists('medacapSubsidiaryCanonicalKey')) {
    /**
     * Clé canonique pour regrouper les libellés alias dans les filtres UI.
     */
    function medacapSubsidiaryCanonicalKey($subsidiary)
    {
        $subsidiary = trim((string) $subsidiary);
        foreach (medacapSubsidiaryAliasGroups() as $canonical => $variants) {
            foreach ($variants as $variant) {
                if (strcasecmp($variant, $subsidiary) === 0) {
                    return $canonical;
                }
            }
        }

        return $subsidiary;
    }
}

if (!function_exists('medacapSubsidiaryFilterToMongoClause')) {
    /**
     * Convertit une valeur de filtre filiale (session, GET, array) en clause MongoDB.
     *
     * @param string|array|null $subsidiaryFilter
     * @return string|array|null
     */
    function medacapSubsidiaryFilterToMongoClause($subsidiaryFilter)
    {
        if ($subsidiaryFilter === null || $subsidiaryFilter === '' || $subsidiaryFilter === 'all') {
            return null;
        }

        if (is_array($subsidiaryFilter)) {
            $all = array();
            foreach ($subsidiaryFilter as $item) {
                $all = array_merge($all, medacapExpandSubsidiary($item));
            }
            $all = array_values(array_unique($all));
            if (count($all) === 0) {
                return null;
            }
            if (count($all) === 1) {
                return $all[0];
            }

            return array('$in' => $all);
        }

        $resolved = medacapResolveSubsidiaryMongoFilter($subsidiaryFilter);

        return ($resolved === 'all') ? null : $resolved;
    }
}
