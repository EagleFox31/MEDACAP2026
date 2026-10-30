<?php
require_once __DIR__ . '/../../core/medacap_config.php';
session_start();

include_once __DIR__ . '/../partials/background-manager.php';

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/includes/technician_detail_lib.php';

if (isset($_GET['lang']) && ($_GET['lang'] === 'EN' || $_GET['lang'] === 'FR')) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'FR';
if ($lang === 'EN') {
    include_once __DIR__ . '/../language_EN.php';
} else {
    include_once __DIR__ . '/../language.php';
}

$tdEn = $lang === 'EN';
$tdTxt = array(
    'title'     => $tdEn ? 'Technician detail' : 'Détail du technicien',
    'back'      => $tdEn ? 'Back to results' : 'Retour aux résultats',
    'export'    => $tdEn ? 'Export Excel' : 'Exporter Excel',
    'spec'      => $tdEn ? 'Speciality' : 'Spécialité',
    'fac'       => $tdEn ? 'Knowledge' : 'Connaissances',
    'tech'      => $tdEn ? 'Tasks (self)' : 'Tâches (tech.)',
    'mgr'       => $tdEn ? 'Tasks (manager)' : 'Tâches (manager)',
    'global'    => $tdEn ? 'Overall' : 'Global',
    'weakTitle' => $tdEn ? 'Areas to work on' : 'Points à travailler',
    'weakNone'  => $tdEn ? 'No score below 70%.' : 'Aucune note sous 70 %.',
    'noData'    => $tdEn ? 'No results recorded yet for this technician.' : 'Aucun résultat enregistré pour ce technicien.',
    'notFound'  => $tdEn ? 'Technician not found or not in your scope.' : 'Technicien introuvable ou hors de votre périmètre.',
    'legend'    => $tdEn ? 'Scores in %' : 'Notes en %',
    'notDone'   => $tdEn ? 'Not completed' : 'Pas complété',
    'level'     => $tdEn ? 'Level' : 'Niveau',
);
$tdKindLabels = array('fac' => $tdTxt['fac'], 'tech' => $tdTxt['tech'], 'mgr' => $tdTxt['mgr']);
$tdKindTitles = array('fac' => $qcm_connaissances_tech, 'tech' => $qcm_tache_pro_tech, 'mgr' => $qcm_tache_pro_manager);

$tdUser = null;
$tdData = null;
try {
    $conn = new MongoDB\Client(medacapMongoUri());
    $academy = $conn->academy;
    $userIdRaw = isset($_GET['user']) ? (string) $_GET['user'] : '';
    if (preg_match('/^[a-f0-9]{24}$/i', $userIdRaw)) {
        $userId = new MongoDB\BSON\ObjectId($userIdRaw);
        $candidate = $academy->users->findOne(array('_id' => $userId, 'active' => true));
        if ($candidate && tdCanView($_SESSION, $candidate)) {
            $tdUser = $candidate;
            $tdData = tdLoadTechnicianDetail($academy, $userId, isset($candidate['level']) ? (string) $candidate['level'] : 'Junior');
        }
    }
} catch (Throwable $e) {
    error_log('detailTechnicien: ' . get_class($e) . ' ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $tdUser = null;
}

$tdName = $tdUser ? trim($tdUser['firstName'] . ' ' . $tdUser['lastName']) : '';
$tdInitials = '';
if ($tdUser) {
    $tdInitials = strtoupper(substr((string) $tdUser['firstName'], 0, 1) . substr((string) $tdUser['lastName'], 0, 1));
}

/** Classe de couleur d'une note (mêmes seuils que bilanTestNotes). */
function tdNoteClass($pct)
{
    if ($pct === null) {
        return 'td-na';
    }
    if ($pct >= 70) {
        return 'td-good';
    }
    return $pct >= 50 ? 'td-mid' : 'td-low';
}

// Repérage des 3 points faibles dans le tableau
$tdWeakKeys = array();
if ($tdData) {
    foreach ($tdData['weak'] as $w) {
        $tdWeakKeys[$w['spec'] . '|' . $w['level'] . '|' . $w['kind']] = true;
    }
}
?>
<?php include_once __DIR__ . '/partials/header.php'; ?>
<title><?php echo htmlspecialchars($tdTxt['title'] . ($tdName !== '' ? ' · ' . $tdName : '')) ?> | CFAO Mobility Academy</title>

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
    .td-actions { margin-left: auto; display: flex; flex-wrap: wrap; gap: 10px; }
    .td-btn { display: inline-flex; align-items: center; gap: 6px; height: 40px; padding: 0 16px; border-radius: 8px;
        font-size: 1.02rem; font-weight: 600; text-decoration: none; white-space: nowrap; border: 1px solid transparent; cursor: pointer;
        transition: background .15s, box-shadow .15s; }
    .td-btn:focus-visible { outline: none; box-shadow: 0 0 0 4px rgba(41,112,255,.24); }
    .td-btn i { color: inherit; }
    .td-btn-light { background: #fff; color: #344054; border-color: #d0d5dd; }
    .td-btn-light:hover { background: #f9fafb; color: #344054; }
    .td-btn-primary { background: #175cd3; color: #fff; }
    .td-btn-primary:hover { background: #1849a9; color: #fff; }
    .td-section-title { font-size: 1.15rem; font-weight: 700; color: var(--td-ink); margin: 0 0 12px; }
    .td-weak-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
    .td-weak { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: #fff;
        border: 1px solid var(--td-line); border-left: 4px solid #f04438; border-radius: 10px; }
    .td-weak.td-weak-mid { border-left-color: #f79009; }
    .td-weak-spec { font-weight: 600; color: var(--td-ink); }
    .td-weak-sub { font-size: .92rem; color: var(--td-muted); }
    .td-weak .td-note { margin-left: auto; }
    .td-weak-none { color: #067647; font-weight: 500; }
    .td-table-wrap { background: #fff; border: 1px solid var(--td-line); border-radius: 12px; overflow-x: auto; }
    .td-table { width: 100%; border-collapse: separate; border-spacing: 0; margin: 0; }
    .td-table th { padding: 9px 8px; font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .02em;
        border-bottom: 1px solid var(--td-line); white-space: nowrap; text-align: center; background: var(--td-head); color: var(--td-muted); }
    .td-table th.td-spec-h { text-align: left; min-width: 200px; position: sticky; left: 0; z-index: 2; }
    .td-table th.td-grp { font-size: .95rem; }
    .td-table th + th, .td-table td + td { border-left: 1px solid var(--td-line); }
    .td-table th.td-lvl-Junior { background: #eff8ff; color: #175cd3; }
    .td-table th.td-lvl-Senior { background: #fffaeb; color: #b54708; }
    .td-table th.td-lvl-Expert { background: #f4f3ff; color: #5925dc; }
    .td-table td { padding: 9px 8px; border-bottom: 1px solid var(--td-line); vertical-align: middle; text-align: center; background: #fff; }
    .td-table td.td-spec { text-align: left; font-weight: 600; color: var(--td-ink); position: sticky; left: 0; z-index: 1; }
    .td-table tbody tr:hover td { background: #fcfcfd; }
    .td-table tfoot td { background: #f9fafb; font-weight: 700; border-bottom: 0; border-top: 2px solid #d0d5dd; }
    .td-note { display: inline-block; min-width: 58px; padding: 4px 10px; border-radius: 99px; font-size: .98rem; font-weight: 600;
        font-variant-numeric: tabular-nums; }
    .td-good { background: #ecfdf3; color: #067647; }
    .td-mid { background: #fffaeb; color: #b54708; }
    .td-low { background: #fef3f2; color: #b42318; }
    .td-na { color: #98a2b3; }
    td.td-is-weak .td-note { box-shadow: 0 0 0 2px #f04438; }
    .td-legend { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; font-size: .92rem; color: var(--td-muted); }
    .td-empty { padding: 48px 24px; text-align: center; color: var(--td-muted); font-size: 1.1rem; }
    .td-empty i { display: block; font-size: 1.75rem; margin-bottom: 10px; color: #98a2b3; }
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
                <?php if ($tdUser) { ?>
                    <div class="td-avatar" aria-hidden="true"><?php echo htmlspecialchars($tdInitials) ?></div>
                    <div>
                        <h1 class="td-name"><?php echo htmlspecialchars($tdName) ?></h1>
                        <p class="td-meta">
                            <?php
                            $tdMeta = array();
                            if (!empty($tdUser['level'])) { $tdMeta[] = $tdTxt['level'] . ' ' . (string) $tdUser['level']; }
                            if (!empty($tdUser['agency'])) { $tdMeta[] = (string) $tdUser['agency']; }
                            if (!empty($tdUser['subsidiary'])) { $tdMeta[] = (string) $tdUser['subsidiary']; }
                            echo htmlspecialchars(implode(' · ', $tdMeta));
                            ?>
                        </p>
                    </div>
                <?php } else { ?>
                    <h1 class="td-name"><i class="fas fa-user text-secondary me-3" aria-hidden="true"></i><?php echo $tdTxt['title'] ?></h1>
                <?php } ?>
                <div class="td-actions">
                    <a href="./bilanTestNotes" class="td-btn td-btn-light" onclick="if (document.referrer.indexOf('bilanTestNotes') !== -1) { history.back(); return false; }">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i><?php echo $tdTxt['back'] ?>
                    </a>
                    <?php if ($tdData && !empty($tdData['specs'])) { ?>
                    <button type="button" class="td-btn td-btn-primary" onclick="exportDetail()">
                        <i class="fas fa-file-excel" aria-hidden="true"></i><?php echo $tdTxt['export'] ?>
                    </button>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!$tdUser) { ?>
<div class="container-xxl mb-5 td-scope">
    <div class="card td-glass"><div class="card-body">
        <div class="td-empty"><i class="fas fa-user-slash" aria-hidden="true"></i><?php echo $tdTxt['notFound'] ?></div>
    </div></div>
</div>
<?php } elseif (empty($tdData['specs'])) { ?>
<div class="container-xxl mb-5 td-scope">
    <div class="card td-glass"><div class="card-body">
        <div class="td-empty"><i class="fas fa-inbox" aria-hidden="true"></i><?php echo $tdTxt['noData'] ?></div>
    </div></div>
</div>
<?php } else { ?>

<!-- Points à travailler -->
<div class="container-xxl mb-4 td-scope">
    <div class="card td-glass"><div class="card-body py-4 px-5">
        <h2 class="td-section-title"><i class="fas fa-bullseye text-danger me-2" aria-hidden="true"></i><?php echo $tdTxt['weakTitle'] ?></h2>
        <?php if (empty($tdData['weak'])) { ?>
            <div class="td-weak-none"><i class="fas fa-check-circle me-2" aria-hidden="true"></i><?php echo $tdTxt['weakNone'] ?></div>
        <?php } else { ?>
            <div class="td-weak-list">
                <?php foreach ($tdData['weak'] as $w) { ?>
                    <div class="td-weak<?php echo $w['pct'] >= 50 ? ' td-weak-mid' : '' ?>">
                        <div>
                            <div class="td-weak-spec"><?php echo htmlspecialchars($w['spec']) ?></div>
                            <div class="td-weak-sub"><?php echo htmlspecialchars($w['level'] . ' · ' . $tdKindLabels[$w['kind']]) ?></div>
                        </div>
                        <span class="td-note <?php echo tdNoteClass($w['pct']) ?>"><?php echo (int) $w['pct'] ?>%</span>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div></div>
</div>

<!-- Notes par spécialité × niveau -->
<div class="container-xxl mb-5 td-scope">
    <div class="card td-glass"><div class="card-body py-4 px-5">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <h2 class="td-section-title mb-0"><i class="fas fa-table text-secondary me-2" aria-hidden="true"></i><?php echo $tdTxt['legend'] ?></h2>
            <div class="td-legend">
                <span class="td-note td-good">&ge; 70%</span>
                <span class="td-note td-mid">50–69%</span>
                <span class="td-note td-low">&lt; 50%</span>
                <span>— <?php echo $tdTxt['notDone'] ?></span>
            </div>
        </div>
        <div class="td-table-wrap">
            <table class="td-table" id="td-table">
                <thead>
                    <tr>
                        <th rowspan="2" class="td-spec-h"><?php echo $tdTxt['spec'] ?></th>
                        <?php foreach ($tdData['levels'] as $level) { ?>
                            <th colspan="3" class="td-grp td-lvl-<?php echo $level ?>"><?php echo $tdTxt['level'] . ' ' . $level ?></th>
                        <?php } ?>
                    </tr>
                    <tr>
                        <?php foreach ($tdData['levels'] as $level) {
                            foreach ($tdKindLabels as $kind => $label) { ?>
                            <th class="td-lvl-<?php echo $level ?>" title="<?php echo htmlspecialchars($tdKindTitles[$kind]) ?>"><?php echo $label ?></th>
                        <?php }
                        } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tdData['specs'] as $spec => $byLevel) { ?>
                    <tr>
                        <td class="td-spec"><?php echo htmlspecialchars($spec) ?></td>
                        <?php foreach ($tdData['levels'] as $level) {
                            foreach ($tdKindLabels as $kind => $label) {
                                $pct = $byLevel[$level][$kind];
                                $isWeak = isset($tdWeakKeys[$spec . '|' . $level . '|' . $kind]);
                        ?>
                            <td<?php echo $isWeak ? ' class="td-is-weak"' : '' ?>>
                                <?php if ($pct === null) { ?>
                                    <span class="td-na" title="<?php echo $tdTxt['notDone'] ?>">—</span>
                                <?php } else { ?>
                                    <span class="td-note <?php echo tdNoteClass($pct) ?>"><?php echo (int) $pct ?>%</span>
                                <?php } ?>
                            </td>
                        <?php }
                        } ?>
                    </tr>
                    <?php } ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="td-spec"><?php echo $tdTxt['global'] ?></td>
                        <?php foreach ($tdData['levels'] as $level) {
                            foreach ($tdKindLabels as $kind => $label) {
                                $pct = $tdData['global'][$level][$kind];
                        ?>
                            <td>
                                <?php if ($pct === null) { ?>
                                    <span class="td-na" title="<?php echo $tdTxt['notDone'] ?>">—</span>
                                <?php } else { ?>
                                    <span class="td-note <?php echo tdNoteClass($pct) ?>"><?php echo (int) $pct ?>%</span>
                                <?php } ?>
                            </td>
                        <?php }
                        } ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div></div>
</div>

<?php } ?>

<?php closeBackgroundContainer(); ?>

<?php if ($tdData && !empty($tdData['specs'])) { ?>
<!-- xlsx-js-style : SheetJS + styles de cellules (même lib que bilanTestNotes) -->
<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js" defer></script>
<script>
    var tdExport = <?php echo json_encode(array(
        'name'   => $tdName,
        'meta'   => isset($tdMeta) ? implode(' · ', $tdMeta) : '',
        'levels' => $tdData['levels'],
        'kinds'  => array_values($tdKindLabels),
        'specs'  => $tdData['specs'],
        'global' => $tdData['global'],
        'labels' => array('spec' => $tdTxt['spec'], 'global' => $tdTxt['global'], 'level' => $tdTxt['level'], 'notDone' => $tdTxt['notDone']),
    ), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    // Export Excel mis en forme : titre, en-têtes fusionnés colorés par niveau, notes colorées (seuils 70 / 50).
    function exportDetail() {
        if (typeof XLSX === 'undefined') { alert('Bibliothèque XLSX non chargée'); return; }
        var d = tdExport;
        var kindKeys = ['fac', 'tech', 'mgr'];
        var border = { style: 'thin', color: { rgb: 'D0D5DD' } };
        var borders = { top: border, bottom: border, left: border, right: border };
        var levelColors = {
            tech:   ['1F2A44', 'E4E7EC'],
            Junior: ['1570EF', 'D1E9FF'],
            Senior: ['DC6803', 'FEF0C7'],
            Expert: ['6938EF', 'EBE9FE']
        };
        var noteStyle = function (pct) {
            if (pct >= 70) { return { fill: 'D1FADF', font: '067647' }; }
            if (pct >= 50) { return { fill: 'FEF0C7', font: 'B54708' }; }
            return { fill: 'FEE4E2', font: 'B42318' };
        };
        var nbCols = 1 + d.levels.length * 3;
        var ws = {};
        var merges = [];
        var put = function (r, c, cell) { ws[XLSX.utils.encode_cell({ r: r, c: c })] = cell; };
        var noteCell = function (pct, bold) {
            var s = { alignment: { horizontal: 'center', vertical: 'center' }, border: borders };
            if (pct === null || pct === undefined) {
                s.font = { italic: true, color: { rgb: '667085' } };
                s.fill = { fgColor: { rgb: 'F9FAFB' } };
                return { v: d.labels.notDone, t: 's', s: s };
            }
            var ns = noteStyle(pct);
            s.font = { bold: true, color: { rgb: ns.font } };
            s.fill = { fgColor: { rgb: ns.fill } };
            return { v: pct / 100, t: 'n', z: '0%', s: s };
        };

        put(0, 0, { v: d.name + (d.meta ? ' — ' + d.meta : ''), t: 's', s: { font: { bold: true, sz: 14, color: { rgb: '101828' } } } });
        merges.push({ s: { r: 0, c: 0 }, e: { r: 0, c: nbCols - 1 } });
        put(1, 0, { v: 'Export du ' + new Date().toLocaleDateString('fr-FR'), t: 's', s: { font: { italic: true, color: { rgb: '667085' } } } });
        merges.push({ s: { r: 1, c: 0 }, e: { r: 1, c: nbCols - 1 } });

        // En-têtes : groupe niveau (ligne 2) + types de test (ligne 3)
        var headGroup = { font: { bold: true, color: { rgb: 'FFFFFF' } }, alignment: { horizontal: 'center', vertical: 'center' }, border: borders };
        put(2, 0, { v: d.labels.spec, t: 's', s: Object.assign({}, headGroup, { fill: { fgColor: { rgb: levelColors.tech[0] } } }) });
        put(3, 0, { v: '', t: 's', s: Object.assign({}, headGroup, { fill: { fgColor: { rgb: levelColors.tech[0] } } }) });
        merges.push({ s: { r: 2, c: 0 }, e: { r: 3, c: 0 } });
        d.levels.forEach(function (level, i) {
            var c0 = 1 + i * 3;
            for (var k = 0; k < 3; k++) {
                put(2, c0 + k, { v: k === 0 ? d.labels.level + ' ' + level : '', t: 's',
                    s: Object.assign({}, headGroup, { fill: { fgColor: { rgb: levelColors[level][0] } } }) });
                put(3, c0 + k, { v: d.kinds[k], t: 's', s: { font: { bold: true, color: { rgb: '101828' } },
                    fill: { fgColor: { rgb: levelColors[level][1] } },
                    alignment: { horizontal: 'center', vertical: 'center', wrapText: true }, border: borders } });
            }
            merges.push({ s: { r: 2, c: c0 }, e: { r: 2, c: c0 + 2 } });
        });

        var r = 4;
        var writeRow = function (label, byLevel, isTotal) {
            put(r, 0, { v: label, t: 's', s: { font: { bold: true, color: { rgb: '101828' } },
                fill: isTotal ? { fgColor: { rgb: 'EAECF0' } } : undefined,
                alignment: { horizontal: 'left', vertical: 'center' }, border: borders } });
            d.levels.forEach(function (level, i) {
                kindKeys.forEach(function (kind, k) {
                    var v = byLevel[level] ? byLevel[level][kind] : null;
                    put(r, 1 + i * 3 + k, noteCell(v, isTotal));
                });
            });
            r++;
        };
        Object.keys(d.specs).forEach(function (spec) { writeRow(spec, d.specs[spec], false); });
        writeRow(d.labels.global, d.global, true);

        ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: r - 1, c: nbCols - 1 } });
        ws['!merges'] = merges;
        ws['!cols'] = [{ wch: 34 }].concat(new Array(nbCols - 1).fill({ wch: 16 }));
        ws['!rows'] = [{ hpt: 26 }, { hpt: 18 }, { hpt: 22 }, { hpt: 36 }];

        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Détail');
        var slug = d.name.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^A-Za-z0-9]+/g, '_').replace(/^_|_$/g, '');
        XLSX.writeFile(wb, 'notes_' + slug + '_' + new Date().toISOString().slice(0, 10) + '.xlsx');
    }
</script>
<?php } ?>

<?php include_once __DIR__ . '/partials/footer.php'; ?>
