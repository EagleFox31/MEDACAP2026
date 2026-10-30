<?php
require_once __DIR__ . '/../../core/medacap_config.php';
session_start();

include_once __DIR__ . '/../partials/background-manager.php';

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/includes/brand_results_lib.php';
require_once __DIR__ . '/includes/brand_logo_lib.php';

if (isset($_GET['lang']) && ($_GET['lang'] === 'EN' || $_GET['lang'] === 'FR')) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'FR';
if ($lang === 'EN') {
    include_once __DIR__ . '/../language_EN.php';
} else {
    include_once __DIR__ . '/../language.php';
}

$rmEn = $lang === 'EN';
$rmTxt = array(
    'title'     => $rmEn ? 'Test results by brand' : 'Résultats des tests par marque',
    'export'    => $rmEn ? 'Export Excel' : 'Exporter Excel',
    'techCol'   => $rmEn ? 'Technician' : 'Technicien',
    'techsFull' => $rmEn ? 'Technicians' : 'Techniciens',
    'fac'       => $rmEn ? 'Knowl.' : 'Conn.',
    'tech'      => $rmEn ? 'Tasks tech.' : 'Tâches tech.',
    'mgr'       => $rmEn ? 'Tasks mgr' : 'Tâches mgr',
    'level'     => $rmEn ? 'Level' : 'Niveau',
    'allLevels' => $rmEn ? 'All levels' : 'Tous niveaux',
    'legend'    => $rmEn ? 'Scores in %' : 'Notes en %',
    'notDone'   => $rmEn ? 'Not completed' : 'Pas complété',
    'noBrand'   => $rmEn ? 'Brand not handled at this level' : 'Marque non pratiquée à ce niveau',
    'team'      => $rmEn ? 'Your team' : 'Votre équipe',
    'brands'    => $rmEn ? 'brands' : 'marques',
    'noData'    => $rmEn ? 'No brand assigned to the technicians of your team at this level.' : 'Aucune marque renseignée pour les techniciens de votre équipe à ce niveau.',
    'denied'    => $rmEn ? 'This page is reserved for managers.' : 'Cette page est réservée aux managers.',
    'error'     => $rmEn ? 'Results could not be loaded.' : 'Impossible de charger les résultats.',
);
$rmKindLabels = array('fac' => $rmTxt['fac'], 'tech' => $rmTxt['tech'], 'mgr' => $rmTxt['mgr']);
$rmKindTitles = array('fac' => $qcm_connaissances_tech, 'tech' => $qcm_tache_pro_tech, 'mgr' => $qcm_tache_pro_manager);

$rmIsManager = isset($_SESSION['profile']) && $_SESSION['profile'] === 'Manager';
$rmData = null;
$rmError = false;
if ($rmIsManager) {
    try {
        $conn = new MongoDB\Client(medacapMongoUri());
        $rmLevel = isset($_GET['level']) ? (string) $_GET['level'] : 'all';
        $rmData = brLoadTeamTechBrandScores($conn->academy, new MongoDB\BSON\ObjectId((string) $_SESSION['id']), $rmLevel);
    } catch (Throwable $e) {
        error_log('resultatsMarque: ' . get_class($e) . ' ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        $rmError = true;
    }
}

/** Classe de couleur d'une note (mêmes seuils que bilanTestNotes / detailTechnicien). */
function rmNoteClass($pct)
{
    if ($pct === null) {
        return 'td-na';
    }
    if ($pct >= 70) {
        return 'td-good';
    }
    return $pct >= 50 ? 'td-mid' : 'td-low';
}

/** Pastille de note, « — » si absente, « · » si marque non pratiquée. */
function rmNoteHtml($pct, $hasBrand, $txt)
{
    if (!$hasBrand) {
        return '<span class="td-na rm-none" title="' . htmlspecialchars($txt['noBrand']) . '">·</span>';
    }
    if ($pct === null) {
        return '<span class="td-na" title="' . htmlspecialchars($txt['notDone']) . '">—</span>';
    }
    return '<span class="td-note ' . rmNoteClass($pct) . '">' . (int) $pct . '%</span>';
}

$rmHasRows = $rmData && !empty($rmData['rows']);
$rmLevelLabel = $rmData && $rmData['level'] !== 'all' ? $rmTxt['level'] . ' ' . $rmData['level'] : $rmTxt['allLevels'];
?>
<?php include_once __DIR__ . '/partials/header.php'; ?>
<title><?php echo htmlspecialchars($rmTxt['title']) ?> | CFAO Mobility Academy</title>

<style>
    .page-background-container.td-page-scope { overflow: visible !important; height: auto !important; min-height: calc(100vh - 80px); }
    .page-background-container.td-page-scope .page-content-container { overflow: visible; }
    .td-scope { --td-ink: #101828; --td-muted: #667085; --td-line: #eaecf0; --td-head: #f9fafb;
        font-family: Inter, system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--td-ink); }
    .td-title-card { background: #fff !important; border-radius: 25px !important;
        box-shadow: 0 8px 32px 0 rgba(31,38,135,.15); border: 1px solid rgba(255,255,255,.18); }
    .td-glass { background: rgba(255,255,255,.85) !important; backdrop-filter: blur(18px);
        border-radius: 15px !important; border: 1px solid rgba(255,255,255,.18) !important;
        box-shadow: 0 8px 32px 0 rgba(31,38,135,.2) !important; }
    .td-head { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; }
    .td-avatar { width: 56px; height: 56px; border-radius: 50%; flex: none; display: flex; align-items: center; justify-content: center;
        background: #eff8ff; color: #175cd3; font-size: 1.3rem; font-weight: 700; }
    .td-name { margin: 0; font-size: 1.8rem; font-weight: 700; letter-spacing: -.01em; color: var(--td-ink); }
    .td-meta { margin: 4px 0 0; font-size: 1.05rem; color: var(--td-muted); }
    .td-actions { margin-left: auto; display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
    .td-btn { display: inline-flex; align-items: center; gap: 6px; height: 40px; padding: 0 16px; border-radius: 8px;
        font-size: 1.02rem; font-weight: 600; text-decoration: none; white-space: nowrap; border: 1px solid transparent; cursor: pointer;
        transition: background .15s, box-shadow .15s; }
    .td-btn:focus-visible { outline: none; box-shadow: 0 0 0 4px rgba(41,112,255,.24); }
    .td-btn i { color: inherit; }
    .td-btn-primary { background: #175cd3; color: #fff; }
    .td-btn-primary:hover { background: #1849a9; color: #fff; }
    .td-section-title { font-size: 1.15rem; font-weight: 700; color: var(--td-ink); margin: 0 0 12px; }
    .td-table-wrap { background: #fff; border: 1px solid var(--td-line); border-radius: 12px; overflow-x: auto; }
    .td-table { width: 100%; border-collapse: separate; border-spacing: 0; margin: 0; }
    .td-table th { padding: 11px 10px; font-size: .95rem; font-weight: 700; letter-spacing: .01em;
        border-bottom: 1px solid var(--td-line); white-space: nowrap; text-align: center; background: var(--td-head); color: #475467; }
    .td-table th.td-spec-h { text-align: left; min-width: 260px; position: sticky; left: 0; z-index: 2; font-size: 1.05rem; color: var(--td-ink); }
    .td-table th + th, .td-table td + td { border-left: 1px solid var(--td-line); }
    .td-table td { padding: 12px 10px; border-bottom: 1px solid var(--td-line); vertical-align: middle; text-align: center; background: #fff; }
    .td-table td.td-spec { text-align: left; font-size: 1.15rem; font-weight: 600; color: var(--td-ink); position: sticky; left: 0; z-index: 1; white-space: nowrap; }
    .td-table tbody tr:hover td { background: #fcfcfd; }
    .td-note { display: inline-block; min-width: 64px; padding: 5px 12px; border-radius: 99px; font-size: 1.1rem; font-weight: 700;
        font-variant-numeric: tabular-nums; }
    .td-good { background: #ecfdf3; color: #067647; }
    .td-mid { background: #fffaeb; color: #b54708; }
    .td-low { background: #fef3f2; color: #b42318; }
    .td-na { color: #98a2b3; }
    .td-legend { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; font-size: .92rem; color: var(--td-muted); }
    .td-empty { padding: 48px 24px; text-align: center; color: var(--td-muted); font-size: 1.1rem; }
    .td-empty i { display: block; font-size: 1.75rem; margin-bottom: 10px; color: #98a2b3; }

    .rm-none { font-weight: 700; }
    .rm-level-select { height: 40px; padding: 0 12px; border: 1px solid #d0d5dd; border-radius: 8px; background-color: #fff;
        font-size: 1.02rem; font-weight: 600; color: var(--td-ink); cursor: pointer; }
    .rm-level-select:focus-visible { outline: none; box-shadow: 0 0 0 4px rgba(41,112,255,.24); }
    .td-table th.rm-brand-h { font-size: 1.15rem; color: var(--td-ink); }
    .rm-brand-h .rm-brand { display: inline-flex; align-items: center; gap: 10px; }
    .rm-logo { flex: none; width: 48px; height: 30px; object-fit: contain; }
    .rm-logo-fallback { flex: none; width: 48px; height: 30px; display: inline-flex; align-items: center; justify-content: center;
        border-radius: 6px; background: #f2f4f7; color: var(--td-muted); font-size: .85rem; font-weight: 700; }
    .td-table th.rm-gs, .td-table td.rm-gs { border-left: 2px solid #d0d5dd; }
    .rm-tech-link { color: var(--td-ink); text-decoration: none; }
    .rm-tech-link:hover { color: #175cd3; text-decoration: underline; }
    .rm-lvl { display: inline-block; margin-left: 8px; padding: 2px 10px; border-radius: 99px; font-size: .9rem; font-weight: 600; }
    .rm-lvl-Junior { background: #eff8ff; color: #175cd3; }
    .rm-lvl-Senior { background: #fffaeb; color: #b54708; }
    .rm-lvl-Expert { background: #f4f3ff; color: #5925dc; }
    @media (max-width: 767.98px) {
        .td-actions { margin-left: 0; width: 100%; }
        .td-name { font-size: 1.45rem; }
    }
</style>

<?php
setPageBackground('bg-dashboard', true);
openBackgroundContainer('td-page-scope', 'id="kt_content"');
?>

<div class="container-xxl pt-5 td-scope">
    <div class="card mb-3 td-title-card">
        <div class="card-body py-4 px-6">
            <div class="td-head">
                <div class="td-avatar" aria-hidden="true"><i class="fas fa-car-side"></i></div>
                <div>
                    <h1 class="td-name"><?php echo htmlspecialchars($rmTxt['title']) ?></h1>
                    <?php if ($rmData) { ?>
                    <p class="td-meta">
                        <?php echo htmlspecialchars($rmTxt['team'] . ' · ' . count($rmData['rows']) . ' ' . strtolower($rmTxt['techsFull'])
                            . ' · ' . count($rmData['brands']) . ' ' . $rmTxt['brands']) ?>
                    </p>
                    <?php } ?>
                </div>
                <?php if ($rmData) { ?>
                <div class="td-actions">
                    <form method="get" class="m-0">
                        <label class="visually-hidden" for="rm-level"><?php echo $rmTxt['level'] ?></label>
                        <select id="rm-level" name="level" class="rm-level-select" onchange="this.form.submit()">
                            <option value="all"<?php echo $rmData['level'] === 'all' ? ' selected' : '' ?>><?php echo $rmTxt['allLevels'] ?></option>
                            <?php foreach ($rmData['levelOptions'] as $lv) { ?>
                                <option value="<?php echo $lv ?>"<?php echo $rmData['level'] === $lv ? ' selected' : '' ?>><?php echo $rmTxt['level'] . ' ' . $lv ?></option>
                            <?php } ?>
                        </select>
                    </form>
                    <?php if ($rmHasRows) { ?>
                    <button type="button" class="td-btn td-btn-primary" onclick="exportBrands()">
                        <i class="fas fa-file-excel" aria-hidden="true"></i><?php echo $rmTxt['export'] ?>
                    </button>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<?php if (!$rmIsManager || $rmError || !$rmHasRows) { ?>
<div class="container-xxl mb-5 td-scope">
    <div class="card td-glass"><div class="card-body">
        <?php if (!$rmIsManager) { ?>
            <div class="td-empty"><i class="fas fa-lock" aria-hidden="true"></i><?php echo $rmTxt['denied'] ?></div>
        <?php } elseif ($rmError) { ?>
            <div class="td-empty"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i><?php echo $rmTxt['error'] ?></div>
        <?php } else { ?>
            <div class="td-empty"><i class="fas fa-inbox" aria-hidden="true"></i><?php echo $rmTxt['noData'] ?></div>
        <?php } ?>
    </div></div>
</div>
<?php } else { ?>

<div class="container-xxl mb-5 td-scope">
    <div class="card td-glass"><div class="card-body py-4 px-5">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <h2 class="td-section-title mb-0"><i class="fas fa-table text-secondary me-2" aria-hidden="true"></i><?php echo htmlspecialchars($rmTxt['legend'] . ' · ' . $rmLevelLabel) ?></h2>
            <div class="td-legend">
                <span class="td-note td-good">&ge; 70%</span>
                <span class="td-note td-mid">50–69%</span>
                <span class="td-note td-low">&lt; 50%</span>
                <span>— <?php echo $rmTxt['notDone'] ?></span>
            </div>
        </div>
        <div class="td-table-wrap">
            <table class="td-table" id="rm-table">
                <thead>
                    <tr>
                        <th rowspan="2" class="td-spec-h"><?php echo $rmTxt['techCol'] ?></th>
                        <?php foreach ($rmData['brands'] as $brand) {
                            $logo = medacapBrandLogoWebPath($brand); ?>
                            <th colspan="3" class="rm-brand-h rm-gs">
                                <span class="rm-brand">
                                    <?php if ($logo !== '') { ?>
                                        <img class="rm-logo" src="<?php echo htmlspecialchars($logo) ?>" alt="" loading="lazy">
                                    <?php } else { ?>
                                        <span class="rm-logo-fallback" aria-hidden="true"><?php echo htmlspecialchars(substr($brand, 0, 2)) ?></span>
                                    <?php } ?>
                                    <?php echo htmlspecialchars($brand) ?>
                                </span>
                            </th>
                        <?php } ?>
                    </tr>
                    <tr>
                        <?php foreach ($rmData['brands'] as $brand) {
                            foreach ($rmKindLabels as $kind => $label) { ?>
                            <th<?php echo $kind === 'fac' ? ' class="rm-gs"' : '' ?> title="<?php echo htmlspecialchars($rmKindTitles[$kind]) ?>"><?php echo $label ?></th>
                        <?php }
                        } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rmData['rows'] as $row) { ?>
                    <tr>
                        <td class="td-spec">
                            <a class="rm-tech-link" href="./detailTechnicien?user=<?php echo urlencode($row['id']) ?>"><?php echo htmlspecialchars($row['name']) ?></a>
                            <?php if ($rmData['level'] === 'all') { ?>
                                <span class="rm-lvl rm-lvl-<?php echo $row['level'] ?>"><?php echo $row['level'] ?></span>
                            <?php } ?>
                        </td>
                        <?php foreach ($rmData['brands'] as $brand) {
                            $hasBrand = isset($row['scores'][$brand]);
                            foreach ($rmKindLabels as $kind => $label) { ?>
                            <td<?php echo $kind === 'fac' ? ' class="rm-gs"' : '' ?>><?php echo rmNoteHtml($hasBrand ? $row['scores'][$brand][$kind] : null, $hasBrand, $rmTxt) ?></td>
                        <?php }
                        } ?>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<?php } ?>

<?php closeBackgroundContainer(); ?>

<?php if ($rmHasRows) { ?>
<!-- xlsx-js-style : SheetJS + styles de cellules (même lib que bilanTestNotes) -->
<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js" defer></script>
<script>
    var rmExport = <?php echo json_encode(array(
        'brands' => $rmData['brands'],
        'rows'   => $rmData['rows'],
        'kinds'  => array_values($rmKindLabels),
        'labels' => array('title' => $rmTxt['title'] . ' — ' . $rmLevelLabel, 'tech' => $rmTxt['techCol'], 'level' => $rmTxt['level'],
            'notDone' => $rmTxt['notDone']),
    ), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    // Export Excel : une ligne par technicien, 3 colonnes par marque (niveau filtré).
    function exportBrands() {
        if (typeof XLSX === 'undefined') { alert('Bibliothèque XLSX non chargée'); return; }
        var d = rmExport;
        var kindKeys = ['fac', 'tech', 'mgr'];
        var border = { style: 'thin', color: { rgb: 'D0D5DD' } };
        var borders = { top: border, bottom: border, left: border, right: border };
        var noteCell = function (pct, hasBrand) {
            var s = { alignment: { horizontal: 'center', vertical: 'center' }, border: borders };
            if (!hasBrand || pct === null || pct === undefined) {
                s.font = { italic: true, color: { rgb: '667085' } };
                s.fill = { fgColor: { rgb: 'F9FAFB' } };
                return { v: hasBrand ? d.labels.notDone : '', t: 's', s: s };
            }
            var c = pct >= 70 ? ['D1FADF', '067647'] : (pct >= 50 ? ['FEF0C7', 'B54708'] : ['FEE4E2', 'B42318']);
            s.font = { bold: true, color: { rgb: c[1] } };
            s.fill = { fgColor: { rgb: c[0] } };
            return { v: pct / 100, t: 'n', z: '0%', s: s };
        };
        var textCell = function (v, bold) {
            return { v: v, t: 's', s: { font: { bold: !!bold, color: { rgb: '101828' } },
                alignment: { horizontal: 'left', vertical: 'center' }, border: borders } };
        };

        var ws = {};
        var merges = [];
        var nf = 2;
        var nbCols = nf + d.brands.length * 3;
        var put = function (r, c, cell) { ws[XLSX.utils.encode_cell({ r: r, c: c })] = cell; };
        put(0, 0, { v: d.labels.title, t: 's', s: { font: { bold: true, sz: 14, color: { rgb: '101828' } } } });
        merges.push({ s: { r: 0, c: 0 }, e: { r: 0, c: nbCols - 1 } });
        put(1, 0, { v: 'Export du ' + new Date().toLocaleDateString('fr-FR'), t: 's',
            s: { font: { italic: true, color: { rgb: '667085' } } } });
        merges.push({ s: { r: 1, c: 0 }, e: { r: 1, c: nbCols - 1 } });

        var headStyle = { font: { bold: true, color: { rgb: 'FFFFFF' } }, fill: { fgColor: { rgb: '1F2A44' } },
            alignment: { horizontal: 'center', vertical: 'center' }, border: borders };
        [d.labels.tech, d.labels.level].forEach(function (label, c) {
            put(2, c, { v: label, t: 's', s: headStyle });
            put(3, c, { v: '', t: 's', s: headStyle });
            merges.push({ s: { r: 2, c: c }, e: { r: 3, c: c } });
        });
        d.brands.forEach(function (brand, i) {
            var c0 = nf + i * 3;
            for (var k = 0; k < 3; k++) {
                put(2, c0 + k, { v: k === 0 ? brand : '', t: 's', s: headStyle });
                put(3, c0 + k, { v: d.kinds[k], t: 's', s: { font: { bold: true, color: { rgb: '101828' } },
                    fill: { fgColor: { rgb: 'E4E7EC' } }, alignment: { horizontal: 'center', vertical: 'center', wrapText: true }, border: borders } });
            }
            merges.push({ s: { r: 2, c: c0 }, e: { r: 2, c: c0 + 2 } });
        });

        var r = 4;
        d.rows.forEach(function (row) {
            put(r, 0, textCell(row.name, true));
            put(r, 1, textCell(row.level));
            d.brands.forEach(function (brand, i) {
                var sc = row.scores[brand];
                kindKeys.forEach(function (kind, k) {
                    put(r, nf + i * 3 + k, noteCell(sc ? sc[kind] : null, !!sc));
                });
            });
            r++;
        });

        ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: r - 1, c: nbCols - 1 } });
        ws['!merges'] = merges;
        ws['!cols'] = [{ wch: 30 }, { wch: 10 }].concat(new Array(nbCols - nf).fill({ wch: 13 }));
        ws['!rows'] = [{ hpt: 26 }, { hpt: 18 }, { hpt: 22 }, { hpt: 30 }];

        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Marques');
        XLSX.writeFile(wb, 'resultats_par_marque_' + new Date().toISOString().slice(0, 10) + '.xlsx');
    }
</script>
<?php } ?>

<?php include_once __DIR__ . '/partials/footer.php'; ?>
