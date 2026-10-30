<?php
/**
 * Compat MongoDB. La bibliothèque 1.0.0 ne connaît que Collection::count().
 * countDocuments() existe à partir de 1.4.0. Ce dossier vise 1.21, le site
 * WAMP encore en service reste en 1.0.0 : le helper couvre les deux.
 * Ne pas appeler countDocuments() directement dans les pages.
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
