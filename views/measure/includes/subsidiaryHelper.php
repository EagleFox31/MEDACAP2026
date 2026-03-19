<?php
/**
 * Subsidiary Helper — Gestion centralisée des filiales pour les filtres
 *
 * Usage dans n'importe quelle vue :
 *   require_once "./includes/subsidiaryHelper.php";
 *   $options = buildSubsidiaryFilterOptions($rawSubsidiaries);
 */

/**
 * Valeurs brutes à exclure des filtres (codes internes non affichables).
 * "CFAO" seul et "CFR" sont des valeurs parasites présentes en base.
 */
const SUBSIDIARY_EXCLUDED = ['CFAO', 'CFR'];

/**
 * Correspondance valeur brute → libellé lisible.
 * La clé est la valeur stockée en MongoDB (utilisée pour filtrer).
 * La valeur est le libellé affiché dans le select.
 */
const SUBSIDIARY_LABELS = [
    'CFAO MOTORS BURKINA'        => 'CFAO MOTORS BURKINA',
    'CAMEROON MOTORS INDUSTRIES' => 'CAMEROON MOTORS INDUSTRIES',
    'CFAO MOTORS CONGO'          => 'CFAO MOTORS CONGO',
    "CFAO MOTORS COTE D'IVOIRE"  => "CFAO MOTORS CÔTE D'IVOIRE",
    'CFAO MOTORS GABON'          => 'CFAO MOTORS GABON',
    'CFAO MOTORS MADAGASCAR'     => 'CFAO MOTORS MADAGASCAR',
    'CFAO MOTORS MALI'           => 'CFAO MOTORS MALI',
    'CFAO MOTORS CENTRAFRIQUE'   => 'CFAO MOTORS CENTRAFRIQUE',
    'CFAO MOTORS RDC'            => 'CFAO MOTORS RDC',
    'CFAO MOTORS SENEGAL'        => 'CFAO MOTORS SÉNÉGAL',
    'CFAO MOTORS RWANDA'         => 'CFAO MOTORS RWANDA',
];

/**
 * Retourne le libellé d'affichage pour une filiale brute.
 * Si pas de mapping connu, retourne la valeur telle quelle.
 */
function getSubsidiaryLabel(string $raw): string {
    return SUBSIDIARY_LABELS[$raw] ?? $raw;
}

/**
 * Filtre et formate les filiales pour un select de filtre.
 *
 * @param  string[] $rawSubsidiaries  Valeurs brutes issues de MongoDB
 * @return array<string,string>       [valeur_db => libellé_affiché]
 */
function buildSubsidiaryFilterOptions(array $rawSubsidiaries): array {
    $options = [];
    foreach ($rawSubsidiaries as $raw) {
        if (in_array(strtoupper(trim($raw)), SUBSIDIARY_EXCLUDED, true)) {
            continue; // exclure CFAO seul, CFR
        }
        $options[$raw] = getSubsidiaryLabel($raw);
    }
    return $options;
}
