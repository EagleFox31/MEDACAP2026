<?php

/**
 * Parse un nom CFAO (format NOM PRENOM…) avec gestion des lignes incomplètes "MICHEL -".
 * @return array{0:string,1:string} [firstName, lastName]
 */
function cfaoParseName(string $raw): array
{
    $raw = trim(preg_replace('/\s+/', ' ', $raw));
    if ($raw === '' || $raw === '-') {
        return ['', ''];
    }

    // Prénom seul avec tiret placeholder : "MICHEL -" ou "- MICHEL"
    if (preg_match('/^(.+?)\s+-\s*$/u', $raw, $m)) {
        $word = trim($m[1]);
        if ($word !== '' && $word !== '-') {
            return [ucwords(mb_strtolower($word, 'UTF-8')), ''];
        }
    }
    if (preg_match('/^-\s+(.+)$/u', $raw, $m)) {
        $word = trim($m[1]);
        if ($word !== '' && $word !== '-') {
            return [ucwords(mb_strtolower($word, 'UTF-8')), ''];
        }
    }

    $parts = array_values(array_filter(explode(' ', $raw), function ($p) {
        return $p !== '-' && $p !== '—';
    }));

    if (count($parts) === 0) {
        return ['', ''];
    }
    if (count($parts) === 1) {
        // Un seul mot sans tiret = NOM seul (convention CFAO)
        return ['', mb_strtoupper($parts[0], 'UTF-8')];
    }

    $lastName = array_shift($parts);
    $firstName = implode(' ', $parts);

    return [ucwords(mb_strtolower($firstName, 'UTF-8')), mb_strtoupper($lastName, 'UTF-8')];
}

function cfaoFirstToken(string $s): string
{
    $s = trim($s);
    if ($s === '') {
        return '';
    }
    return (string)preg_split('/\s+/u', $s, 2)[0];
}

function cfaoBuildUsername(string $firstName, string $lastName): string
{
    $fp = ucfirst(mb_strtolower(cfaoFirstToken($firstName), 'UTF-8'));
    $ln = mb_strtoupper(cfaoFirstToken($lastName), 'UTF-8');
    if ($fp === '') {
        return $ln;
    }
    if ($ln === '') {
        return $fp;
    }
    return trim($fp . ' ' . $ln);
}

function cfaoBuildPassword(string $firstName): string
{
    $token = cfaoFirstToken($firstName);
    if ($token === '' || $token === '-') {
        $token = 'User';
    }
    return 'Academy.' . ucfirst(mb_strtolower($token, 'UTF-8')) . '@' . date('Y');
}
