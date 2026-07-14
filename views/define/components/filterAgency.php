<?php
// components/filterAgency.php

require_once __DIR__ . '/subsidiaryFilter.php';

/**
 * Liste complète des agences pour une filiale (config statique + alias filiale).
 */
function getSupervisorAgencyOptions(string $subsidiary, array $dbAgencies = []): array
{
    $agencyMap = include __DIR__ . '/agencyData.php';
    $subsidiaries = expandSubsidiaryFilter($subsidiary);
    if (empty($subsidiaries) && $subsidiary !== '' && $subsidiary !== 'all') {
        $subsidiaries = [$subsidiary];
    }

    $agencies = [];
    foreach ($subsidiaries as $sub) {
        if (!isset($agencyMap[$sub])) {
            continue;
        }
        foreach ($agencyMap[$sub] as $agencyName) {
            $agencyName = trim((string) $agencyName);
            if ($agencyName !== '' && !in_array($agencyName, $agencies, true)) {
                $agencies[] = $agencyName;
            }
        }
    }

    if (!empty($agencies)) {
        sort($agencies);
        return $agencies;
    }

    return $dbAgencies;
}

/**
 * Retourne la liste des agences pour un pays donné.
 * Les mappings proviennent du header (views/define/partials/header.php).
 */
function getAgenciesFromConfig(string $country): array
{
    $mapping = [
        'Burkina Faso'   => ['Ouaga'],
        'Cameroun'       => ['Bafoussam', 'Bertoua', 'Douala', 'Garoua', 'Ngaoundere', 'Yaoundé'],
        "Cote d'Ivoire"  => ['Vridi - Equip'],
        'Gabon'          => ['Libreville'],
        'Madagascar'     => ['Ankorondrano', 'Anosizato', 'Diego', 'Moramanga', 'Tamatave'],
        'Mali'           => ['Bamako'],
        'RCA'            => ['Bangui'],
        'RDC'            => ['Kinshasa', 'Kolwezi', 'Lubumbashi'],
        'Senegal'        => ['Dakar'],
        'Congo'          => ['Brazzaville', 'Pointe-Noire'],
    ];
    return $mapping[$country] ?? [];
}

function renderFilterAgency($selectedAgency = 'all', $agencies = [], $isEnabled = false)
{
    $disabled = $isEnabled ? '' : 'disabled';

    // Fallback si vide (utilise agencyData.php selon la filiale GET/SESSION)
    if ($isEnabled && empty($agencies)) {
        $subsidiary = $_GET['subsidiary'] ?? $_SESSION['subsidiary'] ?? '';
        $agencyMap = include __DIR__ . '/agencyData.php';
        if ($subsidiary && isset($agencyMap[$subsidiary])) {
            $agencies = $agencyMap[$subsidiary];
        }
    }

    echo '<label for="filterAgency" class="form-label">Filtrer par Agence</label>';
    echo '<select id="filterAgency" class="form-select" name="agency" aria-label="Filtrer par Agence" ' . $disabled . '>';
    echo '<option value="all">Toutes les Agences</option>';
    if ($isEnabled && !empty($agencies)) {
        foreach ($agencies as $agencyName) {
            $selected = ($selectedAgency === $agencyName) ? 'selected' : '';
            echo '<option value="' . htmlspecialchars($agencyName) . '" ' . $selected . '>' . htmlspecialchars($agencyName) . '</option>';
        }
    }
    echo '</select>';
}
?>