<?php
// components/filterTechnician.php

/**
 * Rend un filtre de sélection de technicien
 *
 * @param string $selectedTechnicianId ID du technicien sélectionné ("all" ou ObjectId string)
 * @param array  $technicians          Liste [id => "Prénom Nom"]
 * @param bool   $isDisabled
 * @param bool   $isLocked
 */
function renderFilterTechnician($selectedTechnicianId = 'all', $technicians = [], $isDisabled = false, $isLocked = false) {
    $wrapperClass = 'filter-wrapper';
    if ($isDisabled) {
        $wrapperClass .= ' filter-disabled';
    } elseif ($isLocked) {
        $wrapperClass .= ' filter-locked';
    }

    $disabledAttr = ($isDisabled || $isLocked) ? 'disabled' : '';

    echo '<div class="' . $wrapperClass . '" id="technicianFilterWrapper">';
    echo '<label for="filterTechnician" class="form-label">Technician</label>';
    echo '<select id="filterTechnician" name="technicianId" class="form-select" aria-label="Filtrer par Technicien" ' . $disabledAttr . '>';
    echo '<option value="all"' . ($selectedTechnicianId === 'all' ? ' selected' : '') . '>Tous les techniciens</option>';

    foreach ($technicians as $id => $name) {
        $selected = ((string)$selectedTechnicianId === (string)$id) ? 'selected' : '';
        echo '<option value="' . htmlspecialchars((string)$id) . '" ' . $selected . '>' . htmlspecialchars($name) . '</option>';
    }

    echo '</select>';
    echo '</div>';
}
?>