<?php
/**
 * Filtres portail embed — marque, niveau (palier), période (pluri / année).
 *
 * @var array $embedFilters
 */
$embed = $embedFilters ?? [];
$brands = $embed['brands'] ?? [];
$levelOptions = $embed['levelOptions'] ?? [];
$yearOptions = $embed['yearOptions'] ?? [];
$selectedBrand = $embed['selectedBrand'] ?? 'all';
$selectedLevel = $embed['selectedLevel'] ?? 'all';
$selectedScope = $embed['selectedScope'] ?? 'pluri';
$pageBase = $supervisorPage ?? './collaboratorDashboard';
$selectedBrandLabel = $selectedBrand === 'all' ? 'Toutes les marques' : $selectedBrand;
$selectedBrandLogo = $selectedBrand === 'all' ? '' : collaboratorDashboardBrandLogoSrc($selectedBrand);
?>
<div class="card mb-5 embed-plan-filter-card" style="background: rgba(255,255,255,0.92); backdrop-filter: blur(10px); border-radius: 12px;">
    <div class="card-body py-4">
        <p id="embed-plan-filter-summary" class="embed-plan-filter-summary fw-semibold text-dark mb-4">
            Parcourez les plans de formation en filtrant par&nbsp;:
        </p>
        <form id="embedPlanFilterForm" method="get" action="<?= htmlspecialchars($pageBase) ?>" class="row g-3 align-items-end">
            <?php if (!empty($_GET['technicianId'])): ?>
                <input type="hidden" name="technicianId" value="<?= htmlspecialchars((string) $_GET['technicianId']) ?>">
            <?php endif; ?>
            <?php if (!empty($_GET['agency'])): ?>
                <input type="hidden" name="agency" value="<?= htmlspecialchars((string) $_GET['agency']) ?>">
            <?php endif; ?>
            <input type="hidden" name="embed" value="1">
            <?php if (!empty($_GET['returnUrl'])): ?>
                <input type="hidden" name="returnUrl" value="<?= htmlspecialchars((string) $_GET['returnUrl']) ?>">
            <?php endif; ?>

            <div class="col-md-4">
                <label for="embedFilterBrandToggle" class="form-label fw-semibold embed-plan-filter-label">
                    <svg class="embed-plan-filter-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z"/>
                        <path d="M12 12v9"/>
                        <path d="m21 7.5-9 4.5L3 7.5"/>
                    </svg>
                    Marque
                </label>
                <div class="embed-brand-picker" data-embed-brand-picker>
                    <input type="hidden" name="brand" id="embedFilterBrand" value="<?= htmlspecialchars($selectedBrand, ENT_QUOTES, 'UTF-8') ?>">
                    <button
                        type="button"
                        class="embed-brand-picker-toggle form-select"
                        aria-haspopup="listbox"
                        aria-expanded="false"
                        aria-controls="embedFilterBrandMenu"
                        id="embedFilterBrandToggle"
                    >
                        <span class="embed-brand-picker-current">
                            <?php if ($selectedBrand === 'all'): ?>
                                <span class="embed-brand-picker-all-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                                    </svg>
                                </span>
                            <?php else: ?>
                                <img
                                    src="<?= htmlspecialchars($selectedBrandLogo, ENT_QUOTES, 'UTF-8') ?>"
                                    alt=""
                                    class="embed-brand-picker-logo"
                                    loading="lazy"
                                    decoding="async"
                                    onerror="this.src='/MEDACAP/public/images/default.png'"
                                >
                            <?php endif; ?>
                            <span class="embed-brand-picker-label"><?= htmlspecialchars($selectedBrandLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                    </button>
                    <div class="embed-brand-picker-menu" id="embedFilterBrandMenu" role="listbox" hidden>
                        <button
                            type="button"
                            class="embed-brand-picker-option<?= $selectedBrand === 'all' ? ' is-active' : '' ?>"
                            role="option"
                            data-value="all"
                            data-label="Toutes les marques"
                            aria-selected="<?= $selectedBrand === 'all' ? 'true' : 'false' ?>"
                        >
                            <span class="embed-brand-picker-all-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                                </svg>
                            </span>
                            <span class="embed-brand-picker-label">Toutes les marques</span>
                        </button>
                        <?php foreach ($brands as $brand):
                            $logoSrc = collaboratorDashboardBrandLogoSrc($brand);
                            $isActive = $selectedBrand === $brand;
                        ?>
                            <button
                                type="button"
                                class="embed-brand-picker-option<?= $isActive ? ' is-active' : '' ?>"
                                role="option"
                                data-value="<?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?>"
                                data-label="<?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?>"
                                data-logo="<?= htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8') ?>"
                                aria-selected="<?= $isActive ? 'true' : 'false' ?>"
                            >
                                <img
                                    src="<?= htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8') ?>"
                                    alt=""
                                    class="embed-brand-picker-logo"
                                    loading="lazy"
                                    decoding="async"
                                    onerror="this.src='/MEDACAP/public/images/default.png'"
                                >
                                <span class="embed-brand-picker-label"><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <label for="embedFilterLevel" class="form-label fw-semibold embed-plan-filter-label">
                    <svg class="embed-plan-filter-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 19h16"/>
                        <path d="M7 15V9"/>
                        <path d="M12 15V5"/>
                        <path d="M17 15v-3"/>
                    </svg>
                    Niveau
                </label>
                <select id="embedFilterLevel" name="level" class="form-select" aria-label="Filtrer par niveau">
                    <?php foreach ($levelOptions as $option): ?>
                        <option
                            value="<?= htmlspecialchars($option['value']) ?>"
                            <?= $selectedLevel === $option['value'] ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($option['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label for="embedFilterScope" class="form-label fw-semibold embed-plan-filter-label">
                    <svg class="embed-plan-filter-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="5" width="18" height="16" rx="2"/>
                        <path d="M16 3v4"/>
                        <path d="M8 3v4"/>
                        <path d="M3 11h18"/>
                    </svg>
                    Période
                </label>
                <select id="embedFilterScope" name="scope" class="form-select" aria-label="Filtrer par période">
                    <?php foreach ($yearOptions as $option): ?>
                        <option
                            value="<?= htmlspecialchars($option['value']) ?>"
                            <?= $selectedScope === $option['value'] ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($option['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>
<style>
    .embed-plan-filter-card {
        position: relative;
        z-index: 500;
        overflow: visible;
    }
    .embed-plan-filter-card .card-body,
    .embed-plan-filter-card .row,
    .embed-plan-filter-card [class*="col-"] {
        overflow: visible;
    }
    #supervisor-dashboard-pane {
        position: relative;
        z-index: 1;
    }
    .embed-plan-filter-summary {
        font-size: 1.05rem;
        line-height: 1.5;
        margin: 0;
    }
    .embed-plan-filter-card .form-label {
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
    }
    .embed-plan-filter-label {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }
    .embed-plan-filter-icon {
        width: 1rem;
        height: 1rem;
        flex-shrink: 0;
        color: #E3001B;
    }
    .embed-brand-picker {
        position: relative;
    }
    .embed-brand-picker-toggle {
        display: flex;
        align-items: center;
        width: 100%;
        min-height: calc(1.5em + 0.75rem + 2px);
        text-align: left;
        cursor: pointer;
    }
    .embed-brand-picker-current,
    .embed-brand-picker-option {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        min-width: 0;
    }
    .embed-brand-picker-option {
        width: 100%;
        border: 0;
        background: transparent;
        padding: 0.55rem 0.75rem;
        text-align: left;
        cursor: pointer;
    }
    .embed-brand-picker-option:hover,
    .embed-brand-picker-option.is-active {
        background: rgba(227, 0, 27, 0.06);
    }
    .embed-brand-picker-logo {
        width: 72px;
        height: 28px;
        object-fit: contain;
        object-position: left center;
        flex-shrink: 0;
    }
    .embed-brand-picker-all-icon {
        width: 72px;
        height: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: flex-start;
        flex-shrink: 0;
        color: #64748b;
    }
    .embed-brand-picker-all-icon svg {
        width: 22px;
        height: 22px;
    }
    .embed-brand-picker-label {
        font-size: 0.95rem;
        color: #1e293b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .embed-brand-picker-menu {
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        z-index: 1200;
        max-height: 280px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 0.475rem;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
        padding: 0.35rem 0;
    }
    .embed-brand-picker.is-open {
        z-index: 1201;
    }
    .embed-brand-picker-menu[hidden] {
        display: none !important;
    }
</style>
