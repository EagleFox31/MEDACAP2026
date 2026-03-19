<?php
// components/filterBrand.php

/**
 * Rend un filtre de sélection de marque
 * 
 * @param string $selectedBrand Marque actuellement sélectionnée
 * @param array $brands Liste des marques disponibles
 * @param bool $isDisabled Si le filtre doit être désactivé
 * @param bool $isLocked Si le filtre doit être verrouillé (préselectionné)
 */
function renderFilterBrand($selectedBrand = 'all', $brands = [], $isDisabled = false, $isLocked = false) {
    $wrapperClass = 'filter-wrapper';
    if ($isDisabled) {
        $wrapperClass .= ' filter-disabled';
    } elseif ($isLocked) {
        $wrapperClass .= ' filter-locked';
    }
    $disabledAttr = ($isDisabled || $isLocked) ? 'disabled' : '';

    echo '<div class="' . $wrapperClass . '" id="brandFilterWrapper">';
    echo '<label for="filterBrand" class="form-label">Brand (Marque)</label>';
    echo '<select id="filterBrand" name="brand" class="form-select" aria-label="Filtrer par Marque" ' . $disabledAttr . '>';
    echo '<option value="all"' . ($selectedBrand === 'all' ? ' selected' : '') . '>Toutes les marques</option>';

    foreach ($brands as $brand) {
        $selected = ($selectedBrand === $brand) ? 'selected' : '';
        echo '<option value="' . htmlspecialchars($brand) . '" ' . $selected . '>' . htmlspecialchars($brand) . '</option>';
    }

    echo '</select>';
    echo '</div>';
}
?>