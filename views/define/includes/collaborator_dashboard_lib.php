<?php

require_once __DIR__ . '/../components/filterAgency.php';
require_once __DIR__ . '/../controllers/TechnicianDashboardController.php';
require_once __DIR__ . '/../controllers/FilterSystem/Filters.php';
require_once __DIR__ . '/../controllers/FilterSystem/FilterSystem.php';
require_once __DIR__ . '/../controllers/FilterSystem/RefactoredFilterController.php';

use MongoDB\BSON\ObjectId;

/** @return array<int, array{value: string, label: string}> */
function collaboratorDashboardEmbedYearOptions(): array
{
    return [
        ['value' => 'pluri', 'label' => 'Pluriannuel'],
        ['value' => '2026', 'label' => '2026'],
        ['value' => '2025', 'label' => '2025'],
        ['value' => '2024', 'label' => '2024'],
    ];
}

/** @return array<string, string> */
function collaboratorDashboardEmbedLevelLabels(): array
{
    return [
        'Junior' => 'Maintenance',
        'Senior' => 'Réparation de composants',
        'Expert' => 'Diagnostic',
    ];
}

/** @return array<string, string> */
function collaboratorDashboardLevelTierBadgeLabels(): array
{
    return [
        'Junior' => 'Maintenance',
        'Senior' => 'Réparation',
        'Expert' => 'Diagnostic',
    ];
}

function collaboratorDashboardLevelTierBadgeClass(string $level): string
{
    $map = [
        'Junior' => 'medacap-tier-junior',
        'Senior' => 'medacap-tier-senior',
        'Expert' => 'medacap-tier-expert',
    ];

    return $map[$level] ?? 'medacap-tier-junior';
}

function collaboratorDashboardRenderLevelBadge(string $level): string
{
    $labels = collaboratorDashboardLevelTierBadgeLabels();
    $label = htmlspecialchars($labels[$level] ?? 'Maintenance', ENT_QUOTES, 'UTF-8');
    $class = collaboratorDashboardLevelTierBadgeClass($level);

    return '<span class="medacap-level-badge ' . $class . '">' . $label . '</span>';
}

/**
 * Niveaux visibles selon le palier global du technicien (Junior ⊂ Senior ⊂ Expert).
 *
 * @return array<int, array{value: string, label: string}>
 */
function collaboratorDashboardEmbedLevelOptions(string $techLevel): array
{
    $labels = collaboratorDashboardEmbedLevelLabels();
    $rank = ['Junior' => 1, 'Senior' => 2, 'Expert' => 3];
    $max = $rank[$techLevel] ?? 1;

    $options = [['value' => 'all', 'label' => 'Tous les niveaux']];
    foreach (['Junior', 'Senior', 'Expert'] as $level) {
        if (($rank[$level] ?? 99) <= $max) {
            $options[] = ['value' => $level, 'label' => $labels[$level]];
        }
    }

    return $options;
}

function collaboratorDashboardResolveScope(array $src): string
{
    $scope = isset($src['scope']) ? trim((string) $src['scope']) : '';
    if ($scope === '' && isset($src['year'])) {
        $scope = trim((string) $src['year']);
    }
    if ($scope === '' || $scope === 'all') {
        return 'pluri';
    }

    return $scope;
}

/** @return array<string, string> */
function collaboratorDashboardBrandLogoMap(): array
{
    return [
        'RENAULT TRUCK' => 'renaultTrucks.png',
        'HINO' => 'hino_logo.png',
        'TOYOTA BT' => 'bt.png',
        'SINOTRUK' => 'sinotruk.png',
        'JCB' => 'jcb.png',
        'MERCEDES TRUCK' => 'mercedestruck.png',
        'TOYOTA FORKLIFT' => 'forklift.png',
        'FUSO' => 'fuso.png',
        'LOVOL' => 'lovol.png',
        'KING LONG' => 'kl2.png',
        'MERCEDES' => 'mercedestruck.png',
        'TOYOTA' => 'toyota-logo.png',
        'SUZUKI' => 'suzuki-logo.png',
        'PEUGEOT' => 'peugeot-logo.png',
        'MITSUBISHI' => 'mitsubishi-logo.png',
        'FORD' => 'ford-logo.png',
    ];
}

function collaboratorDashboardBrandLogoSrc(string $brand): string
{
    $brand = trim($brand);
    if ($brand === '' || $brand === 'all') {
        return '/MEDACAP/public/images/default.png';
    }

    $map = collaboratorDashboardBrandLogoMap();
    if (isset($map[$brand])) {
        return '/MEDACAP/public/images/' . $map[$brand];
    }

    $upper = strtoupper($brand);
    if (isset($map[$upper])) {
        return '/MEDACAP/public/images/' . $map[$upper];
    }

    foreach ($map as $name => $file) {
        if (strcasecmp($name, $brand) === 0) {
            return '/MEDACAP/public/images/' . $file;
        }
    }

    $globalMap = $GLOBALS['brandLogos'] ?? [];
    if (isset($globalMap[$brand])) {
        $path = (string) $globalMap[$brand];
        if (str_starts_with($path, '/')) {
            return $path;
        }
        if (str_starts_with($path, '../assets/')) {
            return '/MEDACAP/public/assets/' . substr($path, strlen('../assets/'));
        }

        return '/MEDACAP/public/images/' . ltrim($path, '/');
    }

    return '/MEDACAP/public/images/default.png';
}

/**
 * @return array<string, mixed>
 */
function collaboratorDashboardResolveContext(MongoDB\Database $academy): array
{
    $filterController = new RefactoredFilterController($academy);
    $filters = $filterController->getFilters();
    $filters['scope'] = collaboratorDashboardResolveScope($_GET);

    $technicians = $filters['availableTechnicians'] ?? [];
    $subsidiary = $filters['subsidiary'] ?? ($_SESSION['subsidiary'] ?? 'all');
    $agencyOptions = getSupervisorAgencyOptions(
        $subsidiary,
        $filters['availableAgencies'] ?? []
    );
    $technicianId = $filters['technicianId'] ?? 'all';

    if ($technicianId !== 'all' && !isset($technicians[$technicianId])) {
        $technicianId = 'all';
    }

    $embedFilters = collaboratorDashboardEmbedFilterMeta($academy, $technicianId, $filters);

    return [
        'filters' => $filters,
        'technicians' => $technicians,
        'agencyOptions' => $agencyOptions,
        'technicianId' => $technicianId,
        'selectedAgency' => $filters['agency'] ?? 'all',
        'selectedTechnicianId' => $technicianId,
        'technicianChoices' => $technicians,
        'supervisorPage' => './collaboratorDashboard',
        'supervisorView' => true,
        'embedFilters' => $embedFilters,
    ];
}

/**
 * @return array<string, mixed>
 */
function collaboratorDashboardEmbedFilterMeta(MongoDB\Database $academy, string $technicianId, array $filters): array
{
    $selectedBrand = is_array($filters['brand'] ?? null)
        ? 'all'
        : (string) ($filters['brand'] ?? 'all');
    $selectedLevel = (string) ($filters['level'] ?? 'all');
    $selectedScope = collaboratorDashboardResolveScope($filters);

    $brands = [];
    $techLevel = 'Junior';

    if ($technicianId !== 'all') {
        try {
            $doc = $academy->users->findOne([
                '_id' => new ObjectId($technicianId),
                'profile' => 'Technicien',
            ]);
            if ($doc) {
                $techLevel = (string) ($doc['level'] ?? 'Junior');
                $brandSet = [];
                foreach (['brandJunior', 'brandSenior', 'brandExpert'] as $field) {
                    foreach (($doc[$field] ?? []) as $brand) {
                        $brand = trim((string) $brand);
                        if ($brand !== '') {
                            $brandSet[$brand] = true;
                        }
                    }
                }
                $brands = array_keys($brandSet);
                sort($brands);
            }
        } catch (Exception $e) {
            error_log('collaboratorDashboardEmbedFilterMeta: ' . $e->getMessage());
        }
    }

    return [
        'brands' => $brands,
        'levelOptions' => collaboratorDashboardEmbedLevelOptions($techLevel),
        'yearOptions' => collaboratorDashboardEmbedYearOptions(),
        'selectedBrand' => $selectedBrand === '' ? 'all' : $selectedBrand,
        'selectedLevel' => $selectedLevel === '' ? 'all' : $selectedLevel,
        'selectedScope' => $selectedScope,
    ];
}

function collaboratorDashboardRenderPane(MongoDB\Database $academy, array $ctx): string
{
    extract($ctx, EXTR_SKIP);

    ob_start();
    include __DIR__ . '/../views/technician/partials/collaborator_dashboard_pane.php';
    return (string) ob_get_clean();
}

/**
 * @return array<string, string>
 */
function collaboratorDashboardTechniciansForJson(array $technicians): array
{
    $out = [];
    foreach ($technicians as $id => $name) {
        $out[(string) $id] = (string) $name;
    }
    return $out;
}

function collaboratorDashboardDocumentTitle(array $ctx): string
{
    $base = 'Plans de formation de mes collaborateurs';
    $technicianId = $ctx['technicianId'] ?? 'all';
    $technicians = $ctx['technicians'] ?? [];

    if ($technicianId !== 'all' && isset($technicians[$technicianId])) {
        return $base . ' — ' . $technicians[$technicianId];
    }

    return $base;
}
