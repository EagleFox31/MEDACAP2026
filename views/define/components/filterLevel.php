<?php
// components/filterLevel.php

/**
 * Rend un filtre de sélection de niveau
 * 
 * @param string $selectedLevel Niveau actuellement sélectionné
 * @param array $availableLevels Niveaux disponibles à afficher
 * @param bool $isDisabled Si le filtre doit être désactivé
 * @param bool $isLocked Si le filtre doit être verrouillé (préselectionné)
 */
function renderFilterLevel($selectedLevel = 'all', $availableLevels = [], $isDisabled = false, $isLocked = false) {
    $wrapperClass = 'filter-wrapper';
    if ($isDisabled) {
        $wrapperClass .= ' filter-disabled';
    } elseif ($isLocked) {
        $wrapperClass .= ' filter-locked';
    }
    $disabledAttr = ($isDisabled || $isLocked) ? 'disabled' : '';

    echo '<div class="' . $wrapperClass . '" id="levelFilterWrapper">';
    echo '<label for="filterLevel" class="form-label">Level</label>';
    echo '<select id="filterLevel" name="level" class="form-select" aria-label="Filtrer par Niveau" ' . $disabledAttr . '>';
    echo '<option value="all"' . ($selectedLevel === 'all' ? ' selected' : '') . '>Tous les niveaux</option>';

    if (empty($availableLevels)) {
        $availableLevels = ['Junior', 'Senior', 'Expert'];
    }
    foreach ($availableLevels as $level) {
        $selected = ($selectedLevel === $level) ? 'selected' : '';
        echo '<option value="' . htmlspecialchars($level) . '" ' . $selected . '>' . htmlspecialchars($level) . '</option>';
    }

    echo '</select>';
    echo '</div>';
}
?>