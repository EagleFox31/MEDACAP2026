<?php
/**
 * Filter Helper Functions for Measure Module
 * Provides functions for rendering filter boxes and components
 */

require_once __DIR__ . '/../../includes/brand_logo_lib.php';

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
            if (($filter['key'] ?? '') === 'brand' && !empty($filter['useLogos'])) {
                renderBrandFilterWithLogos($filter);
            } else {
                renderFilterSelectAjax($filter);
            }
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

if (!function_exists('renderBrandFilterWithLogos')) {
    /**
     * Filtre marque avec logos (dropdown Bootstrap).
     *
     * @param array $filter
     */
    function renderBrandFilterWithLogos($filter)
    {
        $key = $filter['key'] ?? 'brand';
        $label = $filter['label'] ?? 'Marques';
        $icon = $filter['icon'] ?? '';
        $iconColor = $filter['iconColor'] ?? 'text-primary';
        $selected = $filter['selected'] ?? '';
        $options = $filter['value'] ?? array();

        if ($selected === '' || $selected === null) {
            $selected = 'all';
        }

        $isActive = ($selected !== '' && $selected !== 'all');
        $wrapClass = 'filter-field filter-field-brand' . ($isActive ? ' filter-field--active' : '');

        $selectedLabel = isset($options[$selected]) ? (string) $options[$selected] : (string) $selected;
        if ($selected === 'all' && isset($options['all'])) {
            $selectedLabel = (string) $options['all'];
        }

        $selectedLogo = ($selected !== 'all') ? medacapBrandLogoWebPath($selectedLabel) : '';

        echo '<div class="' . $wrapClass . '">';
        echo '<label class="filter-field-label" for="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
        if ($icon) {
            echo '<i class="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($iconColor, ENT_QUOTES, 'UTF-8') . '"></i>';
        }
        echo htmlspecialchars($label);
        echo '</label>';

        echo '<div class="brand-filter-dropdown dropdown w-100">';
        echo '<button type="button" class="brand-filter-toggle dropdown-toggle w-100" data-bs-toggle="dropdown" aria-expanded="false">';
        if ($selectedLogo !== '') {
            echo '<img src="' . htmlspecialchars($selectedLogo, ENT_QUOTES, 'UTF-8') . '" alt="" class="brand-filter-logo" loading="lazy">';
        } else {
            echo '<span class="brand-filter-logo brand-filter-logo--all" aria-hidden="true"><i class="bi bi-grid-3x3-gap"></i></span>';
        }
        echo '<span class="brand-filter-label">' . htmlspecialchars($selectedLabel, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</button>';

        echo '<ul class="dropdown-menu brand-filter-menu w-100 shadow-sm">';

        foreach ($options as $optValue => $optText) {
            if (is_numeric($optValue)) {
                $optValue = $optText;
            }
            $optValue = (string) $optValue;
            $optText = (string) $optText;
            $activeClass = ($selected === $optValue) ? ' active' : '';
            $logoSrc = ($optValue !== 'all') ? medacapBrandLogoWebPath($optText) : '';

            echo '<li>';
            echo '<button type="button" class="dropdown-item brand-filter-option' . $activeClass . '" data-brand-value="' . htmlspecialchars($optValue, ENT_QUOTES, 'UTF-8') . '">';
            if ($logoSrc !== '') {
                echo '<img src="' . htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8') . '" alt="" class="brand-filter-logo" loading="lazy">';
            } else {
                echo '<span class="brand-filter-logo brand-filter-logo--all" aria-hidden="true"><i class="bi bi-grid-3x3-gap"></i></span>';
            }
            echo '<span class="brand-filter-option-text">' . htmlspecialchars($optText, ENT_QUOTES, 'UTF-8') . '</span>';
            echo '</button>';
            echo '</li>';
        }

        echo '</ul>';

        echo '<select id="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" class="d-none ajax-filter brand-filter-native" data-filter-key="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
        foreach ($options as $optValue => $optText) {
            if (is_numeric($optValue)) {
                $optValue = $optText;
            }
            $isSelected = ($selected == $optValue) ? ' selected' : '';
            echo '<option value="' . htmlspecialchars((string) $optValue, ENT_QUOTES, 'UTF-8') . '"' . $isSelected . '>';
            echo htmlspecialchars((string) $optText, ENT_QUOTES, 'UTF-8');
            echo '</option>';
        }
        echo '</select>';

        echo '</div>';
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