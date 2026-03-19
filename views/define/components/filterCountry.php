<?php
// components/filterCountry.php

function renderFilterCountry($selectedCountry = 'all', $countries = [], $disabled = false) {
    $wrapperClass = 'filter-wrapper';
    if ($disabled) $wrapperClass .= ' filter-disabled';

    echo '<div class="' . $wrapperClass . '" id="countryFilterWrapper">';
    echo '<label for="filterCountry" class="form-label">Filtrer par Pays</label>';
    echo '<select id="filterCountry" class="form-select" aria-label="Filtrer par Pays" name="subsidiary"' . ($disabled ? ' disabled' : '') . '>';
    echo '<option value="all">Tous les Pays</option>';
    foreach ($countries as $countryName) {
        $selected = ($selectedCountry === $countryName) ? 'selected' : '';
        echo '<option value="' . htmlspecialchars($countryName) . '" ' . $selected . '>' . htmlspecialchars($countryName) . '</option>';
    }
    echo '</select>';
    echo '</div>';
}
?>