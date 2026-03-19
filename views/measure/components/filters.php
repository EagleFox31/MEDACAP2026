<?php

/**
 * Génère une boîte de filtres dynamique avec sélecteurs
 *
 * @param array $filters Tableau de configuration des filtres contenant :
 *   [
 *     'key' => string,          // Clé identifiant le filtre
 *     'label' => string,        // Libellé affiché
 *     'icon' => string,         // Classe CSS de l'icône
 *     'selected' => string,     // Valeur sélectionnée (optionnel)
 *     'value' => array,         // Valeurs possibles (array simple ou associatif)
 *     'optionValue' => mixed,   // Clé ou callback pour la valeur (si array associatif)
 *     'optionLabel' => mixed    // Clé ou callback pour le libellé (si array associatif)
 *   ]
 * @param string $targetElementId ID de l'élément DOM à actualiser
 * @param string $layout Type de disposition : 'horizontal' ou 'vertical'. Par défaut 'vertical'.
 *
 * @return string HTML généré
 *
 * @example
 * // Exemple 1: Filtres simples
 * $filters = [
 *     [
 *         'key' => 'status',
 *         'label' => 'Statut',
 *         'icon' => 'fas fa-flag',
 *         'selected' => 'active',
 *         'value' => ['active', 'inactive', 'pending']
 *     ]
 * ];
 * echo renderFilterBox($filters, 'results-container');
 *
 * @example
 * // Exemple 2: Filtres avec données complexes
 * $filters = [
 *     [
 *         'key' => 'department',
 *         'label' => 'Département',
 *         'icon' => 'fas fa-building',
 *         'value' => $departments, // Array d'objets/arrays
 *         'optionValue' => 'id',   // Propriété/clé pour la valeur
 *         'optionLabel' => 'name'  // Propriété/clé pour le libellé
 *     ]
 * ];
 *
 * @example
 * // Exemple 3: Avec callbacks pour personnalisation
 * $filters = [
 *     [
 *         'key' => 'user',
 *         'label' => 'Utilisateur',
 *         'value' => $users,
 *         'optionValue' => fn($u) => $u['id'],
 *         'optionLabel' => fn($u) => "{$u['firstname']} {$u['lastname']}"
 *     ]
 * ];
 */
function renderFilterBox(array $filters, string $targetElementId, string $layout = 'vertical'): string
{
    ob_start();
    $isHorizontal = $layout === 'horizontal';
    ?>
    <div id="dynamicFilters" <?= $isHorizontal ? '' : 'class="card-body py-5"' ?>>
        <form id="filters-form" <?= $isHorizontal ? 'class="row g-3"' : '' ?>>
            <?php foreach ($filters as $filter): ?>
                <?php
                $filterId = htmlspecialchars($filter['key']) . '-filter';
                $filterName = htmlspecialchars($filter['key']);
                $selectedValue = $filter['selected'] ?? 'all';
                $label = htmlspecialchars($filter['label']);
                $icon = htmlspecialchars($filter['icon']);
                $containerClass = $isHorizontal ? 'col-12 col-md-3' : 'mb-5';
                ?>
                <div class="<?= $containerClass ?>">
                    <label for="<?= $filterId ?>" class="form-label d-flex align-items-center">
                        <i class="<?= $icon ?> fs-5 me-2"></i>
                        <span class="fw-bold"><?= $label ?></span>
                    </label>

                    <select
                            id="<?= $filterId ?>"
                            name="<?= $filterName ?>"
                            class="form-select form-select-sm <?= $isHorizontal ? '' : 'mt-2' ?> filter-control"
                            data-target="<?= htmlspecialchars($targetElementId) ?>"
                    >
                        <option value="all" <?= $selectedValue === 'all' ? 'selected' : '' ?>>
                            Tous les <?= strtolower($label) ?>
                        </option>

                        <?php foreach ($filter['value'] as $val): ?>
                            <?php
                            if (is_array($val)) {
                                $optionValue = is_callable($filter['optionValue'] ?? null)
                                    ? ($filter['optionValue'])($val)
                                    : $val[$filter['optionValue']];
                                $optionLabel = is_callable($filter['optionLabel'] ?? null)
                                    ? ($filter['optionLabel'])($val)
                                    : $val[$filter['optionLabel']];
                            } else {
                                $optionValue = $val;
                                $optionLabel = $val;
                            }

                            $isSelected = ($selectedValue === $optionValue) ? 'selected' : '';
                            ?>
                            <option value="<?= htmlspecialchars($optionValue) ?>" <?= $isSelected ?>>
                                <?= htmlspecialchars($optionLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!$isHorizontal): ?>
                    <div class="spacer py-4"></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </form>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const selects = document.querySelectorAll('.filter-control');
            selects.forEach(select => {
                select.addEventListener('change', () => {
                    const filters = {};
                    document.querySelectorAll('.filter-control').forEach(s => {
                        if (s.value !== 'all') {
                            filters[s.name] = s.value;
                        }
                    });

                    const baseUrl = window.location.pathname;
                    const query = new URLSearchParams(filters).toString();
                    const newUrl = query ? `${baseUrl}?${query}` : baseUrl;

                    // Redirection simple - recharge toute la page
                    window.location.href = newUrl;
                });
            });
        });
    </script>
    <?php
    return ob_get_clean();
}