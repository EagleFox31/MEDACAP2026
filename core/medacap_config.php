<?php
/**
 * Une seule lecture de l'environnement.
 * Sans variable, Mongo reste mongodb://localhost:27017
 * et les liens gardent les hôtes actuels (10.68.0.7 / 129.0.64.34).
 */

if (!function_exists('medacapEnvString')) {
    function medacapEnvString($name)
    {
        $value = getenv($name);
        if (!is_string($value)) {
            return '';
        }
        return trim($value);
    }
}

if (!function_exists('medacapMongoUri')) {
    function medacapMongoUri($default = 'mongodb://localhost:27017')
    {
        $env = medacapEnvString('MEDACAP_MONGO_URI');
        if ($env !== '') {
            return $env;
        }
        if (!is_string($default) || $default === '') {
            return 'mongodb://localhost:27017';
        }
        return $default;
    }
}

if (!function_exists('medacapPublicUrl')) {
    function medacapPublicUrl()
    {
        $env = medacapEnvString('MEDACAP_PUBLIC_URL');
        if ($env === '') {
            return '';
        }
        return rtrim($env, '/') . '/';
    }
}

if (!function_exists('medacapAppBaseUrl')) {
    function medacapAppBaseUrl($country = null)
    {
        $configured = medacapPublicUrl();
        if ($configured !== '') {
            return $configured;
        }
        $country = is_string($country) ? $country : '';
        if ($country === 'Cameroun' || $country === 'Cameroon') {
            return 'http://10.68.0.7/medacap/';
        }
        return 'http://129.0.64.34/medacap';
    }
}

if (!function_exists('medacapCommercialUrl')) {
    function medacapCommercialUrl($path)
    {
        $path = ltrim((string) $path, '/');
        $configured = medacapPublicUrl();
        if ($configured !== '') {
            return rtrim($configured, '/') . '/' . $path;
        }
        return 'http://129.0.64.34/MEDACAP/' . $path;
    }
}
