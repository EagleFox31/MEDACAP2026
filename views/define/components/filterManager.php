<?php
// components/filterManager.php

/**
 * Rend un filtre de sélection de manager
 * 
 * @param string $selectedManagerId ID du manager actuellement sélectionné
 * @param array $managers Liste des managers disponibles [id => nom]
 * @param bool $isDisabled Si le filtre doit être désactivé
 * @param bool $isLocked Si le filtre doit être verrouillé (préselectionné)
 */
function renderFilterManager($selectedManagerId = 'all', $managers = [], $isDisabled = false, $isLocked = false) {
    // Déterminer la classe CSS basée sur l'état
    $wrapperClass = 'filter-wrapper';
    if ($isDisabled) {
        $wrapperClass .= ' filter-disabled';
    } elseif ($isLocked) {
        $wrapperClass .= ' filter-locked';
    }

    // Déterminer l'attribut disabled
    $disabledAttr = ($isDisabled || $isLocked) ? 'disabled' : '';

    echo '<div class="' . $wrapperClass . '" id="managerFilterWrapper">';
    echo '<label for="filterManager" class="form-label">Manager</label>';
    echo '<select id="filterManager" name="managerId" class="form-select" aria-label="Filtrer par Manager" ' . $disabledAttr . '>';
    echo '<option value="all"' . ($selectedManagerId === 'all' ? ' selected' : '') . '>Tous les managers</option>';

    foreach ($managers as $id => $name) {
        $selected = ($selectedManagerId === $id) ? 'selected' : '';
        echo '<option value="' . htmlspecialchars($id) . '" ' . $selected . '>' . htmlspecialchars($name) . '</option>';
    }
    
    echo '</select>';
    echo '</div>';
}
?>