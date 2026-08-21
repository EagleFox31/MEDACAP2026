<?php
/**
 * Compat MongoDB : le projet est verrouillé sur mongodb/mongodb 1.0.0, qui ne
 * connaît que Collection::count(). countDocuments() n'existe qu'à partir de 1.4.0
 * et déclenche sinon un « Call to undefined method » (Error, pas Exception).
 * Ce helper fonctionne sur les deux versions.
 */

if (!function_exists('medacapCountDocuments')) {
    function medacapCountDocuments($collection, array $filter = [], array $options = [])
    {
        if (method_exists($collection, 'countDocuments')) {
            return $collection->countDocuments($filter, $options);
        }
        return $collection->count($filter, $options);
    }
}
