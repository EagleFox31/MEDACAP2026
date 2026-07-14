<?php
/**
 * Filter Helper Functions for Measure Module
 * Provides functions for rendering filter boxes and components
 */

if (!function_exists('renderFilterBox')) {
    /**
     * Render a filter box with horizontal or vertical layout
     *
     * @param array $filters Array of filter configurations
     * @param string $formId ID of the form to submit
     * @param string $layout 'horizontal' or 'vertical'
     * @return void
     */
    function renderFilterBox($filters, $formId = 'kt_post', $layout = 'horizontal') {
        $layoutClass = $layout === 'horizontal' ? 'd-flex flex-wrap gap-3 w-100 justify-content-center' : 'd-flex flex-column gap-3 w-100 align-items-center';

        echo '<div id="' . htmlspecialchars($formId) . '" class="' . $layoutClass . ' align-items-end" data-ajax-filters="true">';

        foreach ($filters as $filter) {
            renderFilterSelectAjax($filter);
        }

        echo '</div>';
    }
}

if (!function_exists('renderFilterSelectAjax')) {
    /**
     * Render a single filter select element with AJAX
     *
     * @param array $filter Filter configuration
     * @return void
     */
    function renderFilterSelectAjax($filter) {
        $key         = $filter['key'] ?? '';
        $label       = $filter['label'] ?? '';
        $icon        = $filter['icon'] ?? '';
        $iconColor   = $filter['iconColor'] ?? 'text-primary';
        $selected    = $filter['selected'] ?? '';
        $options     = $filter['value'] ?? [];
        $description = $filter['description'] ?? '';

        $isActive   = ($selected !== '' && $selected !== 'all');
        $wrapClass  = 'filter-field' . ($isActive ? ' filter-field--active' : '');

        $displayLabel = $label;
        if ($isActive && isset($filter['resultCount'])) {
            $displayLabel .= ' (' . (int)$filter['resultCount'] . ')';
        }

        echo '<div class="' . $wrapClass . '">';
        echo '<label for="' . htmlspecialchars($key) . '" class="filter-field-label">';
        if ($icon) {
            echo '<i class="' . htmlspecialchars($icon) . ' ' . htmlspecialchars($iconColor) . '"></i>';
        }
        echo htmlspecialchars($displayLabel);
        echo '</label>';
        echo '<select id="' . htmlspecialchars($key) . '" name="' . htmlspecialchars($key) . '" class="form-select ajax-filter" data-filter-key="' . htmlspecialchars($key) . '" onchange="this.form.submit()">';

        foreach ($options as $optValue => $optText) {
            if (is_numeric($optValue)) {
                $optValue = $optText;
            }
            $isSelected = ($selected == $optValue) ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($optValue) . '"' . $isSelected . '>';
            echo htmlspecialchars($optText);
            echo '</option>';
        }

        echo '</select>';
        if ($description) {
            echo '<div class="form-text mt-1">' . htmlspecialchars($description) . '</div>';
        }
        echo '</div>';
    }
}

if (!function_exists('renderFilterSelect')) {
    /**
     * Render a single filter select element (legacy)
     *
     * @param array $filter Filter configuration
     * @return void
     */
    function renderFilterSelect($filter) {
        $key = $filter['key'] ?? '';
        $label = $filter['label'] ?? '';
        $icon = $filter['icon'] ?? '';
        $selected = $filter['selected'] ?? '';
        $options = $filter['value'] ?? [];

        echo '<div class="filter-item">';
        echo '<label class="form-label fw-bold d-flex align-items-center">';
        if ($icon) {
            echo '<i class="' . htmlspecialchars($icon) . ' me-2"></i>';
        }
        echo htmlspecialchars($label);
        echo '</label>';
        echo '<select name="' . htmlspecialchars($key) . '" class="form-select" onchange="this.form.submit()">';

        foreach ($options as $value) {
            $isSelected = ($selected == $value) ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($value) . '"' . $isSelected . '>';
            echo htmlspecialchars($value);
            echo '</option>';
        }

        echo '</select>';
        echo '</div>';
    }
}

if (!function_exists('renderDropdownFilter')) {
    /**
     * Render a dropdown-style filter
     *
     * @param array $filter Filter configuration
     * @return void
     */
    function renderDropdownFilter($filter) {
        $key = $filter['key'] ?? '';
        $label = $filter['label'] ?? '';
        $icon = $filter['icon'] ?? '';
        $selected = $filter['selected'] ?? '';
        $options = $filter['value'] ?? [];

        echo '<div class="dropdown-filter">';
        echo '<button class="dropdown-toggle" type="button" data-bs-toggle="dropdown">';
        if ($icon) {
            echo '<i class="' . htmlspecialchars($icon) . '"></i> ';
        }
        echo htmlspecialchars($label);
        echo ' <i class="ki-duotone ki-down"></i>';
        echo '</button>';
        echo '<ul class="dropdown-menu">';

        foreach ($options as $value) {
            $activeClass = ($selected == $value) ? ' active' : '';
            echo '<li><a class="dropdown-item' . $activeClass . '" href="?'.$key.'=' . urlencode($value) . '">' . htmlspecialchars($value) . '</a></li>';
        }

        echo '</ul>';
        echo '</div>';
    }
}
?>