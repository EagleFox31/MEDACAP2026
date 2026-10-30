<?php
require_once __DIR__ . '/../../core/medacap_config.php';
/**
 * Rapport recrutement — vue épreuve, détail candidat, export Excel, envoi RH.
 * Design Moderne, Immersif, Interactif & Compatible PHP 7.3.
 */
session_start();

if (!isset($_SESSION['id'])) {
    header('Location: ../../');
    exit();
}

$profile = $_SESSION['profile'] ?? '';
$readProfiles = array('Super Admin', 'Admin', 'Ressource Humaine');
$sendProfiles = array('Super Admin', 'Admin');

if (!in_array($profile, $readProfiles, true)) {
    header('Location: ../portal.php');
    exit();
}

include_once __DIR__ . '/includes/language.php';

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/includes/paper_candidate_report_lib.php';
require_once __DIR__ . '/../../scripts/export_paper_scores.php';
require_once __DIR__ . '/includes/paper_report_excel_export.php';

use MongoDB\Client;
use MongoDB\BSON\ObjectId;

function rr_post_str(string $k, ?string $d = null): ?string
{
    if (!isset($_POST[$k]) || !is_string($_POST[$k])) {
        return $d;
    }
    $v = trim($_POST[$k]);
    return $v === '' ? $d : $v;
}

function h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function fmtNote($v): string
{
    if ($v === null || $v === '') {
        return '—';
    }
    if (is_numeric($v)) {
        return number_format((float)$v, 1, '.', '');
    }
    return (string) $v;
}

/** Formate un libellé pour le radar Chart.js en le découpant sur plusieurs lignes si nécessaire. */
function rr_format_radar_label(string $prefix, string $name): array
{
    $lines = array();
    if ($prefix !== '') {
        $lines[] = $prefix;
    }
    if (mb_strlen($name) > 14 && strpos($name, 'et ') !== false) {
        $parts = explode('et ', $name);
        $lines[] = trim($parts[0]) . ' et';
        $lines[] = trim($parts[1]);
    } elseif (mb_strlen($name) > 14 && strpos($name, ' ') !== false) {
        $words = explode(' ', $name);
        $cur = '';
        foreach ($words as $w) {
            if ($cur !== '' && mb_strlen($cur . ' ' . $w) > 12) {
                $lines[] = $cur;
                $cur = $w;
            } else {
                $cur = $cur === '' ? $w : ($cur . ' ' . $w);
            }
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }
    } else {
        $lines[] = $name;
    }
    return $lines;
}

/** Drapeau pays à partir du libellé filiale (flags define/flags/1x1). */
function rrFlagIconForSubsidiary(?string $subsidiary): string
{
    $subsidiaryUpper = strtoupper(trim((string) $subsidiary));
    $map = array(
        'CAMEROON' => array('cm.svg', 'Cameroun'),
        'COTE' => array('ci.svg', "Cote d'Ivoire"),
        'IVOIRE' => array('ci.svg', "Cote d'Ivoire"),
        'SENEGAL' => array('sn.svg', 'Senegal'),
        'GABON' => array('ga.svg', 'Gabon'),
        'MALI' => array('ml.svg', 'Mali'),
        'BURKINA' => array('bf.svg', 'Burkina Faso'),
        'RCA' => array('cf.svg', 'RCA'),
        'CONGO' => array('cd.svg', 'RDC'),
        'RDC' => array('cd.svg', 'RDC'),
    );
    $flagFile = 'cm.svg';
    $countryLabel = 'Cameroun';
    foreach ($map as $needle => $info) {
        if (strpos($subsidiaryUpper, $needle) !== false) {
            $flagFile = $info[0];
            $countryLabel = $info[1];
            break;
        }
    }
    $src = '../define/flags/1x1/' . $flagFile;
    return '<img src="' . h($src) . '" alt="' . h($countryLabel) . '" class="rr-flag-icon" width="18" height="18" title="' . h($countryLabel) . '">';
}

/** Rendu d'un badge de note stylisé. */
function renderNoteBadge($note, string $max = '/20'): string
{
    if ($note === null || $note === '' || $note === '—') {
        return '<span class="rr-note-pill rr-note-none">—</span>';
    }
    $val = (float) $note;
    $class = 'rr-note-low';
    if ($val >= 14.0) {
        $class = 'rr-note-high';
    } elseif ($val >= 10.0) {
        $class = 'rr-note-mid';
    }
    return '<span class="rr-note-pill ' . $class . '">' . number_format($val, 1, '.', '') . '<small>' . $max . '</small></span>';
}

/** Affiche le bloc détail d'un candidat (réutilisé en panneau JS). */
function rr_render_candidate_detail(array $c, $panelId = null): void
{
    $g = $c['global'];
    $idAttr = $panelId ? ' id="' . h($panelId) . '"' : '';
    $userUid = h($c['user_id']);

    // Préparation des données JSON pour Chart.js radar (libellés multi-lignes pour un affichage aéré)
    $radarLabels = array();
    $radarScores = array();
    foreach (array('Junior', 'Senior', 'Expert') as $lv) {
        if (isset($c['levels'][$lv]) && ($c['levels'][$lv]['total'] ?? 0) > 0) {
            $radarLabels[] = rr_format_radar_label('Niveau', $lv);
            $radarScores[] = $c['levels'][$lv]['note_maitrise'] !== null ? (float)$c['levels'][$lv]['note_maitrise'] : 0;
        }
    }
    foreach ($c['macro_notes'] as $macro => $mn) {
        $radarLabels[] = rr_format_radar_label('Macro :', $macro);
        $radarScores[] = $mn['note_maitrise'] !== null ? (float)$mn['note_maitrise'] : 0;
    }
    $chartConfigJson = json_encode(array(
        'labels' => $radarLabels,
        'scores' => $radarScores,
    ));
    ?>
    <div class="rr-detail-panel card-glass"<?= $idAttr ?> data-user-id="<?= $userUid ?>" data-chart='<?= h($chartConfigJson) ?>' style="display:none;">
        
        <!-- Detail Header -->
        <div class="rr-detail-header">
            <div class="rr-candidate-identity">
                <div class="rr-avatar-circle">
                    <?= h(strtoupper(substr($c['name'], 0, 2))) ?>
                </div>
                <div>
                    <h2 class="rr-candidate-name"><?= h($c['name']) ?></h2>
                    <div class="rr-candidate-meta">
                        <span><i class="fas fa-user-tag me-1"></i><?= h($c['username']) ?></span>
                        <span class="rr-candidate-subline"><?= h($c['subsidiary']) ?> <?= rrFlagIconForSubsidiary($c['subsidiary']) ?></span>
                        <span class="rr-status-chip rr-status-<?= strtolower(str_replace(' ', '-', $c['status'])) ?>">
                            <?= h($c['status']) ?>
                        </span>
                    </div>
                </div>
            </div>
            <button type="button" class="btn-rr btn-glass js-close-detail">
                <i class="fas fa-times"></i> Fermer
            </button>
        </div>

        <!-- Detail Navigation Tabs -->
        <div class="rr-detail-tabs">
            <button type="button" class="rr-tab-btn active" data-tab="overview-<?= $userUid ?>">
                <i class="fas fa-chart-pie me-1"></i> Synthèse & Graphique
            </button>
            <button type="button" class="rr-tab-btn" data-tab="levels-<?= $userUid ?>">
                <i class="fas fa-layer-group me-1"></i> Niveaux & Macro-Spécialités
            </button>
            <button type="button" class="rr-tab-btn" data-tab="specs-<?= $userUid ?>">
                <i class="fas fa-tools me-1"></i> Spécialités Métier
            </button>
            <button type="button" class="rr-tab-btn" data-tab="questions-<?= $userUid ?>">
                <i class="fas fa-list-ol me-1"></i> Réponses (<?= count($c['questions']) ?>)
            </button>
        </div>

        <!-- TAB 1: OVERVIEW & CHART -->
        <div class="rr-tab-content active" id="tab-overview-<?= $userUid ?>">
            <div class="rr-kpi-grid">
                <div class="rr-kpi-card">
                    <div class="rr-kpi-icon icon-blue"><i class="fas fa-check-double"></i></div>
                    <div class="rr-kpi-body">
                        <div class="rr-kpi-val"><?= (int) $g['answered'] ?> / <?= (int) $g['total'] ?></div>
                        <div class="rr-kpi-lbl">Questions Répondues</div>
                    </div>
                </div>
                <div class="rr-kpi-card">
                    <div class="rr-kpi-icon icon-emerald"><i class="fas fa-award"></i></div>
                    <div class="rr-kpi-body">
                        <div class="rr-kpi-val"><?= renderNoteBadge($g['note_maitrise']) ?></div>
                        <div class="rr-kpi-lbl">Maîtrise Globale</div>
                    </div>
                </div>
                <div class="rr-kpi-card">
                    <div class="rr-kpi-icon icon-crimson"><i class="fas fa-book-open"></i></div>
                    <div class="rr-kpi-body">
                        <div class="rr-kpi-val"><?= renderNoteBadge($g['note_completude']) ?></div>
                        <div class="rr-kpi-lbl">Complétude Globale</div>
                    </div>
                </div>
                <div class="rr-kpi-card">
                    <div class="rr-kpi-icon icon-amber"><i class="fas fa-stopwatch"></i></div>
                    <div class="rr-kpi-body">
                        <div class="rr-kpi-val"><?= h($c['time_elapsed'] ?: '—') ?></div>
                        <div class="rr-kpi-lbl">Temps Écoulé</div>
                    </div>
                </div>
                <div class="rr-kpi-card">
                    <div class="rr-kpi-icon icon-purple"><i class="fas fa-tachometer-alt"></i></div>
                    <div class="rr-kpi-body">
                        <div class="rr-kpi-val"><?= fmtNote($c['avg_speed_per_min']) ?> <small>q/min</small></div>
                        <div class="rr-kpi-lbl">Vitesse Moyenne</div>
                    </div>
                </div>
            </div>

            <div class="rr-chart-grid" style="margin-top:20px;">
                <div class="rr-chart-card">
                    <h3 class="rr-card-subtitle"><i class="fas fa-chart-radar text-primary me-2"></i> Profil des Compétences</h3>
                    <div class="rr-chart-wrapper">
                        <canvas class="js-radar-chart"></canvas>
                    </div>
                </div>
                <div class="rr-summary-card">
                    <div class="rr-summary-header">
                        <h3 class="rr-card-subtitle mb-0">
                            <i class="fas fa-clipboard-check text-primary me-2"></i> Bilan des Connaissances
                        </h3>
                        <span class="rr-summary-header-badge">
                            <i class="fas fa-check-double text-primary me-1"></i> <?= (int) $g['answered'] ?> / <?= (int) $g['total'] ?> questions
                        </span>
                    </div>

                    <div class="rr-summary-body">
                        <!-- Hero Metric Highlights (Maîtrise & Complétude) -->
                        <div class="rr-metric-highlights">
                            <!-- Maîtrise Card -->
                            <div class="rr-highlight-box highlight-primary">
                                <div class="rr-highlight-header">
                                    <span class="rr-highlight-title"><i class="fas fa-award text-primary me-1"></i> Maîtrise</span>
                                    <span class="rr-highlight-sub">Qualité des réponses</span>
                                </div>
                                <div class="rr-highlight-main">
                                    <span class="rr-highlight-val"><?= fmtNote($g['note_maitrise']) ?></span>
                                    <span class="rr-highlight-denom">/20</span>
                                </div>
                                <div class="rr-highlight-footer">
                                    <div class="rr-mini-track">
                                        <div class="rr-mini-fill fill-primary" style="width: <?= min(100, ((float)($g['note_maitrise'] ?? 0) / 20) * 100) ?>%;"></div>
                                    </div>
                                    <span class="rr-mini-caption"><strong><?= (int)$g['correct_maitrise'] ?></strong> justes sur <?= (int)$g['answered'] ?> traitées</span>
                                </div>
                            </div>

                            <!-- Complétude Card -->
                            <div class="rr-highlight-box highlight-slate">
                                <div class="rr-highlight-header">
                                    <span class="rr-highlight-title"><i class="fas fa-tasks text-secondary me-1"></i> Complétude</span>
                                    <span class="rr-highlight-sub">Sur la banque totale</span>
                                </div>
                                <div class="rr-highlight-main">
                                    <span class="rr-highlight-val"><?= fmtNote($g['note_completude']) ?></span>
                                    <span class="rr-highlight-denom">/20</span>
                                </div>
                                <div class="rr-highlight-footer">
                                    <div class="rr-mini-track">
                                        <div class="rr-mini-fill fill-slate" style="width: <?= min(100, ((float)($g['note_completude'] ?? 0) / 20) * 100) ?>%;"></div>
                                    </div>
                                    <span class="rr-mini-caption">Pénalise les non-réponses</span>
                                </div>
                            </div>
                        </div>

                        <!-- Progress Section -->
                        <div class="rr-summary-progress-box">
                            <div class="rr-progress-info">
                                <span class="rr-progress-label"><i class="fas fa-chart-line text-primary me-1"></i> Taux de complétion</span>
                                <span class="rr-progress-value"><strong><?= fmtNote($g['pct_completion']) ?>%</strong></span>
                            </div>
                            <div class="rr-progress-bar-bg">
                                <div class="rr-progress-bar-fill" style="width: <?= min(100, (float)($g['pct_completion'] ?? 0)) ?>%;"></div>
                            </div>
                        </div>

                        <!-- Metadata Row (Orientation & Cadence) -->
                        <div class="rr-summary-meta-grid">
                            <div class="rr-meta-item">
                                <div class="rr-meta-icon"><i class="fas fa-compass text-primary"></i></div>
                                <div class="rr-meta-text">
                                    <div class="rr-meta-label">Test d'Orientation</div>
                                    <div class="rr-meta-val">
                                        <?= !empty($c['orientation_done']) 
                                            ? '<span class="rr-badge-info"><i class="fas fa-check me-1"></i>Complété</span>' 
                                            : '<span class="rr-badge-neutral">En attente</span>' ?>
                                    </div>
                                </div>
                            </div>
                            <div class="rr-meta-item">
                                <div class="rr-meta-icon"><i class="fas fa-stopwatch text-amber"></i></div>
                                <div class="rr-meta-text">
                                    <div class="rr-meta-label">Cadence & Durée</div>
                                    <div class="rr-meta-val">
                                        <strong><?= fmtNote($c['avg_speed_per_min']) ?></strong> <small>q/min</small>
                                        <span class="text-muted ms-1" style="font-size:0.75rem;">(<?= h($c['time_elapsed'] ?: '—') ?>)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: LEVELS & MACRO-SPECIALTIES -->
        <div class="rr-tab-content" id="tab-levels-<?= $userUid ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <h3 class="rr-section-title"><i class="fas fa-layer-group text-primary me-2"></i> Répartition par Niveau</h3>
                    <div class="table-responsive">
                        <table class="rr-table-modern">
                            <thead>
                                <tr>
                                    <th>Niveau</th>
                                    <th>Répondues</th>
                                    <th>Maîtrise /20</th>
                                    <th>Complétude</th>
                                    <th>% Complétion</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach (array('Junior', 'Senior', 'Expert') as $lv):
                                $b = isset($c['levels'][$lv]) ? $c['levels'][$lv] : null;
                                if (!$b || ($b['total'] ?? 0) <= 0) continue; ?>
                                <tr>
                                    <td><span class="badge-level level-<?= strtolower($lv) ?>"><?= h($lv) ?></span></td>
                                    <td><?= (int) $b['answered'] ?> / <?= (int) $b['total'] ?></td>
                                    <td><?= renderNoteBadge($b['note_maitrise']) ?></td>
                                    <td><?= renderNoteBadge($b['note_completude']) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span><?= fmtNote($b['pct_completion']) ?>%</span>
                                            <div class="rr-mini-bar"><div class="rr-mini-fill" style="width:<?= min(100, (float)$b['pct_completion']) ?>%"></div></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-md-6">
                    <h3 class="rr-section-title"><i class="fas fa-cogs text-primary me-2"></i> Macro-Spécialités</h3>
                    <div class="table-responsive">
                        <table class="rr-table-modern">
                            <thead>
                                <tr>
                                    <th>Macro-Spécialité</th>
                                    <th>Répondues</th>
                                    <th>Total</th>
                                    <th>Maîtrise /20</th>
                                    <th>% Complétion</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($c['macro_notes'] as $macro => $mn): ?>
                                <tr>
                                    <td><strong><?= h($macro) ?></strong></td>
                                    <td><?= (int) $mn['answered'] ?></td>
                                    <td><?= (int) $mn['total'] ?></td>
                                    <td><?= renderNoteBadge($mn['note_maitrise']) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span><?= fmtNote($mn['pct_completion']) ?>%</span>
                                            <div class="rr-mini-bar"><div class="rr-mini-fill" style="width:<?= min(100, (float)$mn['pct_completion']) ?>%"></div></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: SPECIALTIES -->
        <div class="rr-tab-content" id="tab-specs-<?= $userUid ?>">
            <h3 class="rr-section-title"><i class="fas fa-tools text-primary me-2"></i> Détail par Spécialité Métier</h3>
            <div class="table-responsive">
                <table class="rr-table-modern">
                    <thead>
                        <tr>
                            <th>Spécialité</th>
                            <th>Questions Répondues</th>
                            <th>Note Maîtrise /20</th>
                            <th>% Complétion</th>
                            <th>Progression</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($c['specialty_notes'] as $spec => $sn): ?>
                        <tr>
                            <td><strong><?= h($spec) ?></strong></td>
                            <td><?= (int) $sn['answered'] ?> / <?= (int) $sn['total'] ?></td>
                            <td><?= renderNoteBadge($sn['note_maitrise']) ?></td>
                            <td><?= fmtNote($sn['pct_completion']) ?>%</td>
                            <td style="width:160px;">
                                <div class="rr-progress-bar-bg">
                                    <div class="rr-progress-bar-fill" style="width: <?= min(100, (float)$sn['pct_completion']) ?>%;"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 4: QUESTIONS DETAIL -->
        <div class="rr-tab-content" id="tab-questions-<?= $userUid ?>">
            <div class="rr-filter-toolbar">
                <div class="rr-search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" class="js-filter-q-text form-control" placeholder="Filtrer questions ou mots-clés...">
                </div>
                <div class="rr-btn-group-filter">
                    <button type="button" class="rr-filter-btn active js-filter-q-btn" data-filter="all">Toutes</button>
                    <button type="button" class="rr-filter-btn js-filter-q-btn" data-filter="Correct">Correctes</button>
                    <button type="button" class="rr-filter-btn js-filter-q-btn" data-filter="Incorrect">Incorrectes</button>
                    <button type="button" class="rr-filter-btn js-filter-q-btn" data-filter="Non répondu">Non répondues</button>
                </div>
            </div>
            <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                <table class="rr-table-modern js-questions-table">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Réf.</th>
                            <th>Niveau</th>
                            <th>Spécialité</th>
                            <th style="min-width:240px;">Énoncé</th>
                            <th>Réponse Candidat</th>
                            <th>Bonne Réponse</th>
                            <th>Résultat</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($c['questions'] as $qr):
                        $resClass = 'rr-res-neutral';
                        if ($qr['resultat'] === 'Correct') {
                            $resClass = 'rr-res-ok';
                        } elseif ($qr['resultat'] === 'Incorrect') {
                            $resClass = 'rr-res-ko';
                        }
                    ?>
                        <tr class="js-q-row" data-result="<?= h($qr['resultat']) ?>">
                            <td><strong>#<?= (int) $qr['num'] ?></strong></td>
                            <td><code><?= h($qr['ref']) ?></code></td>
                            <td><span class="badge-level level-<?= strtolower($qr['niveau']) ?>"><?= h($qr['niveau']) ?></span></td>
                            <td><?= h($qr['speciality']) ?></td>
                            <td><?= h($qr['label']) ?></td>
                            <td><span class="rr-user-ans"><?= h($qr['reponse_candidat'] ?: '—') ?></span></td>
                            <td><span class="rr-correct-ans"><?= h($qr['bonne_reponse']) ?></span></td>
                            <td><span class="rr-res-badge <?= $resClass ?>"><?= h($qr['resultat']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    <?php
}

$mongoUri = medacapMongoUri();
$mongoClient = new Client($mongoUri);
$db = $mongoClient->academy;

$papersParam = isset($_GET['papers']) ? trim((string) $_GET['papers']) : '';
$paperIdStr = isset($_GET['paper']) ? trim((string) $_GET['paper']) : '';
$initialUserId = isset($_GET['user']) ? trim((string) $_GET['user']) : '';
$initialPaperId = isset($_GET['detail_paper']) ? trim((string) $_GET['detail_paper']) : '';
$flash = null;
$flashType = 'info';

$selectedPaperIds = array();
if (isset($_GET['papers']) && is_array($_GET['papers'])) {
    foreach ($_GET['papers'] as $part) {
        $part = trim((string) $part);
        if (preg_match('/^[0-9a-f]{24}$/i', $part) && !in_array($part, $selectedPaperIds, true)) {
            $selectedPaperIds[] = $part;
        }
    }
} elseif ($papersParam !== '') {
    foreach (explode(',', $papersParam) as $part) {
        $part = trim($part);
        if (preg_match('/^[0-9a-f]{24}$/i', $part) && !in_array($part, $selectedPaperIds, true)) {
            $selectedPaperIds[] = $part;
        }
    }
} elseif ($paperIdStr !== '' && preg_match('/^[0-9a-f]{24}$/i', $paperIdStr)) {
    $selectedPaperIds[] = $paperIdStr;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = rr_post_str('action');
    $postPaper = rr_post_str('paper_id');
    if ($postPaper && preg_match('/^[0-9a-f]{24}$/i', $postPaper)) {
        $paperIdStr = $postPaper;
    }

    if ($action === 'export_excel' && $paperIdStr !== '' && preg_match('/^[0-9a-f]{24}$/i', $paperIdStr)) {
        try {
            $exportOptions = paperReportExcelOptionsFromPost($_POST);
            $export = paperReportExportExcel($db, new ObjectId($paperIdStr), null, $exportOptions);
            $filename = basename($export['path']);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($export['path']));
            readfile($export['path']);
            @unlink($export['path']);
            exit;
        } catch (Throwable $e) {
            $flash = 'Export Excel impossible : ' . $e->getMessage();
            $flashType = 'danger';
        }
    }

    if ($action === 'export_multi_excel') {
        $selected = isset($_POST['paper_ids']) && is_array($_POST['paper_ids']) ? $_POST['paper_ids'] : array();
        $oids = array();
        foreach ($selected as $pid) {
            $pid = trim((string) $pid);
            if (preg_match('/^[0-9a-f]{24}$/i', $pid)) {
                $oids[] = new ObjectId($pid);
            }
        }
        if ($oids === array()) {
            $flash = 'Sélectionnez au moins une épreuve.';
            $flashType = 'warning';
        } else {
            try {
                $bundleLabel = rr_post_str('bundle_label', 'Recrutement_PL_associe');
                $exportOptions = paperReportExcelOptionsFromPost($_POST);
                $export = paperReportExportMultiExcel($db, $oids, sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'medacap_paper_reports', $bundleLabel, $exportOptions);
                $filename = basename($export['path']);
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($export['path']));
                readfile($export['path']);
                @unlink($export['path']);
                exit;
            } catch (Throwable $e) {
                $flash = 'Export multi-épreuves impossible : ' . $e->getMessage();
                $flashType = 'danger';
            }
        }
    }

    if ($action === 'send_rh_report') {
        if (!in_array($profile, $sendProfiles, true)) {
            $flash = 'Action réservée aux administrateurs.';
            $flashType = 'danger';
        } elseif ($paperIdStr === '' || !preg_match('/^[0-9a-f]{24}$/i', $paperIdStr)) {
            $flash = 'Épreuve invalide.';
            $flashType = 'danger';
        } else {
            $force = isset($_POST['force_resend']) && $_POST['force_resend'] === '1';
            $result = paperReportSendManualRhReport($db, new ObjectId($paperIdStr), $mongoClient, $force);
            if (!empty($result['ok'])) {
                $flash = 'Rapport envoyé au RH avec succès.';
                $flashType = 'success';
            } elseif (($result['error'] ?? '') === 'already_sent') {
                $flash = 'Un rapport a déjà été envoyé le ' . ($result['sent_at'] ?? '') . '. Utilisez « Forcer renvoi RH » pour renvoyer.';
                $flashType = 'warning';
            } else {
                $flash = 'Échec de l\'envoi : ' . ($result['error'] ?? 'inconnu');
                $flashType = 'danger';
            }
        }
    }
}

$papers = paperReportListRecruitmentPapers($db);
$paperBlocks = array();
$ctx = null;
$candidates = array();
$completionStats = null;
$campaign = null;

foreach ($selectedPaperIds as $pid) {
    $paperOid = new ObjectId($pid);
    $blockCtx = paperReportLoadPaperContext($db, $paperOid);
    if (!$blockCtx) {
        continue;
    }
    $blockCandidates = paperReportBuildAllCandidates($db, $blockCtx);
    $blockStats = paperReportGetCompletionStats($blockCandidates, (float) $blockCtx['threshold_pct']);
    paperReportUpsertCampaignStatus($db, $paperOid, $blockStats);
    $blockCampaign = paperReportGetCampaignStatus($db, $paperOid);
    $paperBlocks[] = array(
        'paper_id' => $pid,
        'ctx' => $blockCtx,
        'candidates' => $blockCandidates,
        'completionStats' => $blockStats,
        'campaign' => $blockCampaign,
    );
}

if (count($paperBlocks) === 1) {
    $ctx = $paperBlocks[0]['ctx'];
    $candidates = $paperBlocks[0]['candidates'];
    $completionStats = $paperBlocks[0]['completionStats'];
    $campaign = $paperBlocks[0]['campaign'];
    $paperIdStr = $paperBlocks[0]['paper_id'];
}

$multiPaperView = count($paperBlocks) > 1;
$paperSelectSize = min(10, max(4, count($papers)));

include_once __DIR__ . '/../partials/background-manager.php';
setPageBackground('bg-dashboard');

$title = 'Rapport recrutement';
include_once __DIR__ . '/partials/header.php';
openBackgroundContainer('rr-report-scope');
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

/* Scope container overrides */
.page-background-container.rr-report-scope {
    overflow: visible !important;
    height: auto !important;
    min-height: calc(100vh - 80px);
}
.page-background-container.rr-report-scope .page-content-container {
    padding-top: 12px;
    overflow: visible;
}
#kt_header .title-banner {
    overflow: visible;
    min-height: 52px;
}

/* Base Design Tokens & Styles */
:root {
    --rr-primary: #005BA9;
    --rr-primary-gradient: linear-gradient(135deg, #005BA9 0%, #003B73 100%);
    --rr-accent: #d70006;
    --rr-accent-gradient: linear-gradient(135deg, #d70006 0%, #b00005 100%);
    --rr-emerald: #10b981;
    --rr-emerald-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
    --rr-amber: #f59e0b;
    --rr-purple: #8b5cf6;
    --rr-dark: #0f172a;
    --rr-slate: #64748b;
    --rr-light-bg: #f8fafc;
    --rr-glass: rgba(255, 255, 255, 0.92);
    --rr-border: #e2e8f0;
    --rr-shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    --rr-shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.rr-page {
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
    padding: 8px 16px 80px;
    max-width: 1280px;
    margin: 0 auto;
    color: var(--rr-dark);
}

/* Glassmorphism Cards */
.card-glass {
    background: var(--rr-glass);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.8);
    border-radius: 20px;
    box-shadow: var(--rr-shadow-lg);
    padding: 26px;
    margin-bottom: 24px;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

/* Custom Multiselect Dropdown */
.rr-dropdown-container {
    position: relative;
    width: 100%;
}

.rr-dropdown-trigger {
    width: 100%;
    background: #ffffff;
    border: 1.5px solid var(--rr-border);
    border-radius: 12px;
    padding: 10px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    font-weight: 600;
    color: var(--rr-dark);
    transition: all 0.2s ease;
    box-shadow: var(--rr-shadow-sm);
}

.rr-dropdown-trigger:hover, .rr-dropdown-container.open .rr-dropdown-trigger {
    border-color: var(--rr-primary);
    box-shadow: 0 0 0 3px rgba(0, 91, 169, 0.12);
}

.rr-dropdown-trigger-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 0.92rem;
    padding-right: 12px;
}

.rr-dropdown-badge {
    background: #e0f2fe;
    color: #0369a1;
    font-size: 0.78rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    margin-right: 8px;
    flex-shrink: 0;
}

.rr-dropdown-menu {
    display: none;
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    background: #ffffff;
    border: 1px solid var(--rr-border);
    border-radius: 14px;
    box-shadow: 0 16px 36px rgba(0,0,0,0.12);
    z-index: 1050;
    padding: 14px;
    animation: dropdownFadeIn 0.2s ease;
}

@keyframes dropdownFadeIn {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

.rr-dropdown-container.open .rr-dropdown-menu {
    display: block;
}

.rr-dropdown-search {
    position: relative;
    margin-bottom: 10px;
}

.rr-dropdown-search i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--rr-slate);
    font-size: 0.85rem;
}

.rr-dropdown-search input {
    width: 100%;
    padding: 8px 12px 8px 34px;
    border-radius: 8px;
    border: 1px solid var(--rr-border);
    font-size: 0.85rem;
}

.rr-dropdown-actions-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 8px;
    margin-bottom: 8px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.82rem;
}

.rr-dropdown-action-link {
    color: var(--rr-primary);
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    background: none;
    border: none;
    padding: 0;
}

.rr-dropdown-action-link:hover {
    text-decoration: underline;
}

.rr-dropdown-list {
    max-height: 240px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.rr-dropdown-option {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.88rem;
    font-weight: 500;
    color: var(--rr-dark);
    transition: background 0.15s ease;
    user-select: none;
}

.rr-dropdown-option:hover {
    background: #f0f7ff;
    color: var(--rr-primary);
}

.rr-dropdown-option input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: var(--rr-primary);
    cursor: pointer;
}

/* Hero Banner */
.rr-hero-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid var(--rr-border);
    border-radius: 16px;
    padding: 16px 24px;
    margin-bottom: 20px;
    box-shadow: var(--rr-shadow-sm);
}
.rr-hero-title {
    display: flex;
    align-items: center;
    gap: 14px;
}
.rr-hero-icon {
    width: 44px;
    height: 44px;
    background: #eff6ff;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 1.2rem;
    color: var(--rr-primary);
    flex-shrink: 0;
}
.rr-hero-text h1 {
    font-size: 1.2rem;
    font-weight: 800;
    margin: 0;
    color: var(--rr-dark);
    letter-spacing: -0.01em;
}
.rr-hero-text p {
    margin: 2px 0 0;
    font-size: 0.85rem;
    color: var(--rr-slate);
}
.rr-hero-badge {
    background: #e0f2fe;
    color: #0369a1;
    padding: 6px 14px;
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.82rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* Modern Buttons */
.btn-rr {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 4px rgba(0,0,0,0.04);
}
.btn-rr:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0,0,0,0.1);
    text-decoration: none;
}
.btn-primary-gradient {
    background: var(--rr-accent-gradient);
    color: #ffffff !important;
}
.btn-secondary-gradient {
    background: var(--rr-primary-gradient);
    color: #ffffff !important;
}
.btn-glass {
    background: rgba(255, 255, 255, 0.9);
    color: #334155 !important;
    border: 1px solid var(--rr-border);
}
.btn-glass:hover {
    background: #ffffff;
    color: var(--rr-primary) !important;
}

/* KPI Cards */
.rr-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin: 20px 0;
}
.rr-kpi-card {
    background: #ffffff;
    border: 1px solid var(--rr-border);
    border-radius: 16px;
    padding: 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: var(--rr-shadow-sm);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.rr-kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.06);
}
.rr-kpi-icon {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.icon-blue { background: #eff6ff; color: var(--rr-primary); }
.icon-crimson { background: #fef2f2; color: var(--rr-accent); }
.icon-emerald { background: #ecfdf5; color: var(--rr-emerald); }
.icon-amber { background: #fffbeb; color: var(--rr-amber); }
.icon-purple { background: #f3e8ff; color: var(--rr-purple); }

.rr-kpi-val {
    font-size: 1.45rem;
    font-weight: 800;
    color: var(--rr-dark);
    line-height: 1.2;
}
.rr-kpi-lbl {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--rr-slate);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-top: 3px;
}

/* Seuil Progress Bar */
.rr-threshold-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid var(--rr-border);
    margin-top: 16px;
}
.rr-threshold-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.rr-progress-bar-bg {
    width: 100%;
    height: 12px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
    position: relative;
}
.rr-progress-bar-fill {
    height: 100%;
    border-radius: 999px;
    background: var(--rr-emerald-gradient);
    transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}
.rr-progress-bar-fill.fill-warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
.rr-progress-bar-fill.fill-danger { background: var(--rr-accent-gradient); }

.rr-threshold-mark {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 3px;
    background: #0f172a;
    box-shadow: 0 0 4px rgba(0,0,0,0.4);
    z-index: 2;
}

/* Paper multi-select */
.rr-paper-multiselect {
    min-width: 320px;
    flex: 1;
    max-width: 640px;
    border-radius: 12px;
    font-weight: 600;
}
.rr-paper-multiselect option {
    padding: 8px 10px;
}
.rr-paper-multiselect option:checked {
    background: linear-gradient(0deg, #005BA9 0%, #005BA9 100%);
    color: #ffffff;
}

/* Modern Tables */
.rr-table-container {
    overflow-x: auto;
    border-radius: 14px;
    border: 1px solid var(--rr-border);
    background: #ffffff;
}
.rr-table-modern {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.88rem;
    text-align: left;
}
.rr-table-modern th {
    background: #f1f5f9;
    color: #475569;
    font-weight: 700;
    padding: 14px 16px;
    border-bottom: 2px solid var(--rr-border);
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.04em;
}
.rr-table-modern td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    color: var(--rr-dark);
    vertical-align: middle;
}
.rr-table-modern tbody tr {
    transition: background 0.15s ease;
}
.rr-table-modern tbody tr:hover {
    background: #f8fafc;
}
.rr-table-modern tbody tr.rr-row-active {
    background: #eff6ff !important;
    border-left: 4px solid var(--rr-primary);
}

.rr-col-epreuve {
    width: 150px;
    min-width: 150px;
    max-width: 180px;
    white-space: normal;
    overflow-wrap: break-word;
    word-break: normal;
    line-height: 1.35;
    vertical-align: top;
}
.rr-paper-label-wrap {
    display: block;
    font-size: 0.82rem;
    font-weight: 600;
    color: #334155;
}
.rr-col-candidat {
    min-width: 240px;
}
.rr-candidate-subline {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.rr-flag-icon {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
}

/* Status & Note Pills */
.rr-status-chip {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: capitalize;
}
.rr-status-terminé { background: #dcfce7; color: #15803d; }
.rr-status-en-cours { background: #e0f2fe; color: #0369a1; }
.rr-status-non-démarré { background: #f1f5f9; color: #64748b; }
.rr-status-orientation-en-cours { background: #f3e8ff; color: #7e22ce; }

.rr-note-pill {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.88rem;
}
.rr-note-high { background: #dcfce7; color: #166534; }
.rr-note-mid { background: #e0f2fe; color: #0369a1; }
.rr-note-low { background: #fee2e2; color: #991b1b; }
.rr-note-none { background: #f1f5f9; color: #94a3b8; }

/* Candidate Avatar */
.rr-avatar-circle {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: var(--rr-primary-gradient);
    color: #ffffff;
    font-weight: 800;
    display: grid;
    place-items: center;
    font-size: 1rem;
    flex-shrink: 0;
}

/* Candidate Detail Drawer Panel */
.rr-detail-panel {
    margin-top: 24px;
    border-top: 4px solid var(--rr-accent);
    animation: fadeInUp 0.35s cubic-bezier(0, 0, 0.2, 1);
}
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
}

.rr-detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--rr-border);
}
.rr-candidate-identity {
    display: flex;
    align-items: center;
    gap: 16px;
}
.rr-candidate-name {
    font-size: 1.35rem;
    font-weight: 800;
    margin: 0;
    color: var(--rr-dark);
}
.rr-candidate-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 4px;
    font-size: 0.85rem;
    color: var(--rr-slate);
}

/* Detail Tabs */
.rr-detail-tabs {
    display: flex;
    gap: 8px;
    border-bottom: 2px solid var(--rr-border);
    margin: 20px 0;
    overflow-x: auto;
}
.rr-tab-btn {
    background: none;
    border: none;
    padding: 12px 18px;
    font-weight: 700;
    font-size: 0.88rem;
    color: var(--rr-slate);
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.rr-tab-btn:hover {
    color: var(--rr-primary);
}
.rr-tab-btn.active {
    color: var(--rr-primary);
    border-bottom-color: var(--rr-primary);
}

.rr-tab-content {
    display: none;
    padding-top: 10px;
}
.rr-tab-content.active {
    display: block;
}

/* Chart Container */
.rr-chart-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
@media (max-width: 991px) {
    .rr-chart-grid { grid-template-columns: 1fr; }
}
.rr-chart-card, .rr-summary-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid var(--rr-border);
    padding: 20px;
}
.rr-card-subtitle {
    font-size: 1rem;
    font-weight: 700;
    color: var(--rr-dark);
    margin: 0 0 16px;
}
.rr-chart-wrapper {
    position: relative;
    height: 290px;
    display: flex;
    justify-content: center;
    align-items: center;
}

/* Summary Card Styling */
.rr-summary-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid var(--rr-border);
    padding: 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.rr-summary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}
.rr-summary-header-badge {
    background: #f1f5f9;
    color: #475569;
    font-size: 0.78rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
}
.rr-summary-body {
    display: flex;
    flex-direction: column;
    gap: 12px;
    flex: 1;
    justify-content: space-between;
}

/* 2 Hero Highlights */
.rr-metric-highlights {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.rr-highlight-box {
    border-radius: 12px;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.rr-highlight-box:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}
.rr-highlight-box.highlight-primary {
    background: linear-gradient(135deg, #f0f7ff 0%, #e6f0fa 100%);
    border: 1px solid #c7dcf7;
}
.rr-highlight-box.highlight-slate {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
}
.rr-highlight-header {
    display: flex;
    flex-direction: column;
    margin-bottom: 4px;
}
.rr-highlight-title {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--rr-dark);
}
.rr-highlight-sub {
    font-size: 0.72rem;
    color: #64748b;
    margin-top: 1px;
}
.rr-highlight-main {
    display: flex;
    align-items: baseline;
    margin: 4px 0 6px;
}
.rr-highlight-val {
    font-size: 1.65rem;
    font-weight: 800;
    line-height: 1;
}
.highlight-primary .rr-highlight-val {
    color: #005BA9;
}
.highlight-slate .rr-highlight-val {
    color: #1e293b;
}
.rr-highlight-denom {
    font-size: 0.88rem;
    font-weight: 600;
    color: #94a3b8;
    margin-left: 3px;
}
.rr-highlight-footer {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.rr-mini-track {
    height: 4px;
    background: rgba(0, 0, 0, 0.06);
    border-radius: 999px;
    overflow: hidden;
}
.rr-mini-fill {
    height: 100%;
    border-radius: 999px;
}
.rr-mini-fill.fill-primary {
    background: #005BA9;
}
.rr-mini-fill.fill-slate {
    background: #475569;
}
.rr-mini-caption {
    font-size: 0.72rem;
    color: #64748b;
}

/* Progress Box */
.rr-summary-progress-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 10px 14px;
}
.rr-progress-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
    font-size: 0.84rem;
}
.rr-progress-label {
    font-weight: 600;
    color: #334155;
    font-size: 0.8rem;
}
.rr-progress-value {
    color: #005BA9;
    font-size: 0.85rem;
}
.rr-progress-bar-bg {
    height: 6px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}
.rr-progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #005BA9 0%, #0284c7 100%);
    border-radius: 999px;
    transition: width 0.4s ease;
}

/* Meta Grid */
.rr-summary-meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.rr-meta-item {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #f8fafc;
    border: 1px solid #edf2f7;
    border-radius: 10px;
    padding: 8px 12px;
}
.rr-meta-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}
.rr-meta-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.rr-meta-label {
    font-size: 0.68rem;
    color: #64748b;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.rr-meta-val {
    font-size: 0.82rem;
    color: #1e293b;
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
@media (max-width: 576px) {
    .rr-metric-highlights, .rr-summary-meta-grid {
        grid-template-columns: 1fr;
    }
}

/* Legacy summary classes fallback */
.rr-summary-box {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.rr-summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    background: #f8fafc;
    border-radius: 10px;
    font-size: 0.88rem;
}
.rr-badge-success { background: #dcfce7; color: #15803d; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.8rem; }
.rr-badge-warning { background: #fef3c7; color: #b45309; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.8rem; }
.rr-badge-info { background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.8rem; }
.rr-badge-neutral { background: #f1f5f9; color: #64748b; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.8rem; }

/* Filter Toolbar */
.rr-filter-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
}
.rr-search-box {
    position: relative;
    min-width: 260px;
}
.rr-search-box i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--rr-slate);
}
.rr-search-box input {
    padding-left: 38px;
    border-radius: 10px;
    border: 1px solid var(--rr-border);
}
.rr-btn-group-filter {
    display: flex;
    gap: 6px;
}
.rr-filter-btn {
    background: #ffffff;
    border: 1px solid var(--rr-border);
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--rr-slate);
    cursor: pointer;
    transition: all 0.15s ease;
}
.rr-filter-btn.active, .rr-filter-btn:hover {
    background: var(--rr-primary);
    color: #ffffff;
    border-color: var(--rr-primary);
}

.rr-col-dropdown {
    position: relative;
    width: auto;
    flex-shrink: 0;
}
.rr-col-dropdown .rr-dropdown-trigger {
    padding: 6px 14px;
    font-size: 0.82rem;
    min-height: auto;
    width: auto;
}
.rr-col-dropdown-menu {
    min-width: 200px;
    width: auto;
    right: 0;
    left: auto;
}
.rr-col-dropdown-menu .rr-dropdown-list {
    max-height: 280px;
}
#rr-candidates-table .rr-col-hidden {
    display: none;
}

/* Question Badges */
.rr-res-badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 0.78rem; font-weight: 700; }
.rr-res-ok { background: #dcfce7; color: #15803d; }
.rr-res-ko { background: #fee2e2; color: #b91c1c; }
.rr-res-neutral { background: #f1f5f9; color: #64748b; }

.badge-level { display: inline-block; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 0.78rem; }
.level-junior { background: #e0f2fe; color: #0369a1; }
.level-senior { background: #f3e8ff; color: #6b21a8; }
.level-expert { background: #fef3c7; color: #92400e; }

.rr-mini-bar { width: 60px; height: 6px; background: #e2e8f0; border-radius: 999px; overflow: hidden; display: inline-block; }
.rr-mini-fill { height: 100%; background: var(--rr-primary); }
</style>

<div class="rr-page">
    
    <!-- Navigation Top Bar -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="btn-rr btn-glass" href="../portal.php">
            <i class="fas fa-arrow-left"></i> Retour portail
        </a>
        <?php if ($paperBlocks !== array()): ?>
            <a class="btn-rr btn-glass" href="rapportRecrutement.php">
                <i class="fas fa-undo"></i> Réinitialiser sélection
            </a>
        <?php endif; ?>
    </div>



    <!-- Flash Alert Message -->
    <?php if ($flash): ?>
        <div class="alert alert-<?= h($flashType) ?> alert-dismissible fade show card-glass p-3 mb-4" role="alert">
            <i class="fas fa-info-circle me-2"></i> <?= h($flash) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Main Control Card: Paper Selector & Actions -->
    <div class="card-glass mb-4">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <label class="fw-bold mb-1 text-dark d-block" style="font-size:0.95rem;">
                    <i class="fas fa-layer-group me-1 text-primary"></i> Épreuves Figées
                </label>
                <p class="text-muted mb-2" style="font-size:0.85rem;">
                    Sélectionnez une ou plusieurs épreuves dans le menu déroulant ci-dessous pour afficher les candidats.
                </p>

                <!-- Custom Multiselect Dropdown -->
                <div class="rr-dropdown-container" id="rr-paper-dropdown">
                    <div class="rr-dropdown-trigger" id="js-dropdown-trigger">
                        <div class="d-flex align-items-center overflow-hidden">
                            <span class="rr-dropdown-badge" id="js-dropdown-count">0</span>
                            <span class="rr-dropdown-trigger-text" id="js-dropdown-label">Sélectionner les épreuves...</span>
                        </div>
                        <i class="fas fa-chevron-down text-muted" id="js-dropdown-arrow"></i>
                    </div>

                    <div class="rr-dropdown-menu" id="js-dropdown-menu">
                        <div class="rr-dropdown-search">
                            <i class="fas fa-search"></i>
                            <input type="text" id="js-dropdown-search-input" placeholder="Filtrer la liste des épreuves...">
                        </div>
                        <div class="rr-dropdown-actions-bar">
                            <button type="button" class="rr-dropdown-action-link" id="js-dd-select-all">
                                <i class="fas fa-check-square me-1"></i> Tout cocher
                            </button>
                            <button type="button" class="rr-dropdown-action-link text-muted" id="js-dd-clear-all">
                                <i class="fas fa-minus-square me-1"></i> Tout décocher
                            </button>
                        </div>
                        <div class="rr-dropdown-list" id="js-dropdown-options-list">
                            <?php foreach ($papers as $p):
                                $isSelected = in_array($p['id'], $selectedPaperIds, true);
                            ?>
                                <label class="rr-dropdown-option js-dd-option" data-label="<?= h(strtolower($p['label'])) ?>">
                                    <input type="checkbox" class="js-paper-checkbox" value="<?= h($p['id']) ?>" data-title="<?= h($p['label']) ?>" <?= $isSelected ? 'checked' : '' ?>>
                                    <span><?= h($p['label']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="border-top pt-2 mt-2 d-flex justify-content-end gap-2">
                            <button type="button" class="btn-rr btn-primary-gradient btn-sm" id="js-apply-dropdown-selection">
                                <i class="fas fa-eye me-1"></i> Afficher les épreuves
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 d-flex justify-content-lg-end align-items-center gap-2 flex-wrap pt-2 pt-lg-0">
                <button type="button" class="btn-rr btn-primary-gradient" id="js-apply-papers">
                    <i class="fas fa-eye"></i> Afficher
                </button>
                <?php if ($paperBlocks !== array()): ?>
                    <a class="btn-rr btn-glass" href="?papers=<?= h(implode(',', $selectedPaperIds)) ?>">
                        <i class="fas fa-sync-alt"></i> Actualiser
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Multi Paper Overview Cards -->
        <?php if ($multiPaperView): ?>
            <div class="border-top pt-4 mt-4">
                <div class="row g-3">
                    <?php foreach ($paperBlocks as $block):
                        $bs = $block['completionStats'];
                        $bc = $block['campaign']; ?>
                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 bg-white shadow-sm">
                                <h3 class="fw-bold text-primary mb-2" style="font-size:0.95rem;"><?= h($block['ctx']['paper_label']) ?></h3>
                                <div class="d-flex justify-content-between align-items-center text-dark flex-wrap gap-2" style="font-size:0.85rem;">
                                    <span>Inscrits: <strong><?= (int) $bs['enrolled'] ?></strong></span>
                                    <span>Composés: <strong><?= (int) $bs['composed'] ?></strong></span>
                                    <span>Complétion: <strong><?= h($bs['completion_pct']) ?>%</strong></span>
                                    <span><?= !empty($bs['threshold_reached']) ? '<span class="rr-badge-success">Seuil 60% OK</span>' : '<span class="rr-badge-warning">En cours</span>' ?></span>
                                </div>
                                <?php if (!empty($bc['autoAlertSentAt']) || !empty($bc['manualReportSentAt'])): ?>
                                    <div class="text-muted mt-2" style="font-size:0.8rem;">
                                        <?php if (!empty($bc['autoAlertSentAt'])): ?>Alerte auto : <?= h($bc['autoAlertSentAt']) ?><?php endif; ?>
                                        <?php if (!empty($bc['manualReportSentAt'])): ?> · Rapport RH : <?= h($bc['manualReportSentAt']) ?><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Single Paper KPI Stats Dashboard -->
        <?php if ($ctx && $completionStats && !$multiPaperView): ?>
            <div class="border-top pt-4 mt-4">
                <h2 class="fw-bold text-dark mb-3" style="font-size:1.15rem;"><?= h($ctx['paper_label']) ?></h2>
                
                <div class="rr-kpi-grid">
                    <div class="rr-kpi-card">
                        <div class="rr-kpi-icon icon-blue"><i class="fas fa-users"></i></div>
                        <div class="rr-kpi-body">
                            <div class="rr-kpi-val"><?= (int) $completionStats['enrolled'] ?></div>
                            <div class="rr-kpi-lbl">Inscrits</div>
                        </div>
                    </div>
                    <div class="rr-kpi-card">
                        <div class="rr-kpi-icon icon-emerald"><i class="fas fa-user-check"></i></div>
                        <div class="rr-kpi-body">
                            <div class="rr-kpi-val"><?= (int) $completionStats['composed'] ?></div>
                            <div class="rr-kpi-lbl">Composés</div>
                        </div>
                    </div>
                    <div class="rr-kpi-card">
                        <div class="rr-kpi-icon icon-purple"><i class="fas fa-percentage"></i></div>
                        <div class="rr-kpi-body">
                            <div class="rr-kpi-val"><?= h($completionStats['completion_pct']) ?>%</div>
                            <div class="rr-kpi-lbl">Complétion</div>
                        </div>
                    </div>
                    <div class="rr-kpi-card">
                        <div class="rr-kpi-icon <?= !empty($completionStats['threshold_reached']) ? 'icon-emerald' : 'icon-crimson' ?>">
                            <i class="fas <?= !empty($completionStats['threshold_reached']) ? 'fa-shield-alt' : 'fa-exclamation-triangle' ?>"></i>
                        </div>
                        <div class="rr-kpi-body">
                            <div class="rr-kpi-val"><?= !empty($completionStats['threshold_reached']) ? 'Atteint' : 'Non atteint' ?></div>
                            <div class="rr-kpi-lbl">Seuil <?= (int) $completionStats['threshold_pct'] ?>%</div>
                        </div>
                    </div>
                </div>

                <!-- Visual Seuil Bar -->
                <?php
                $pctVal = (float) $completionStats['completion_pct'];
                $barClass = '';
                if ($pctVal < 30.0) {
                    $barClass = 'fill-danger';
                } elseif ($pctVal < (float)$completionStats['threshold_pct']) {
                    $barClass = 'fill-warning';
                }
                ?>
                <div class="rr-threshold-card">
                    <div class="rr-threshold-header">
                        <span class="fw-bold text-dark">
                            Avancement du Seuil de Complétion (Objectif <?= (int) $completionStats['threshold_pct'] ?>%)
                        </span>
                        <span>
                            <?= !empty($completionStats['threshold_reached']) ? '<span class="rr-badge-success"><i class="fas fa-check"></i> SEUIL 60% VALIDE</span>' : '<span class="rr-badge-warning"><i class="fas fa-clock"></i> SEUIL NON ENCORE ATTEINT</span>' ?>
                        </span>
                    </div>
                    <div class="rr-progress-bar-bg">
                        <div class="rr-progress-bar-fill <?= $barClass ?>" style="width: <?= min(100, $pctVal) ?>%;"></div>
                        <div class="rr-threshold-mark" style="left: <?= (int)$completionStats['threshold_pct'] ?>%;" title="Seuil minimal (60%)"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-2 text-muted" style="font-size:0.8rem;">
                        <span>0%</span>
                        <span>Seuil 60%</span>
                        <span>100%</span>
                    </div>
                    <?php if (!empty($campaign['autoAlertSentAt']) || !empty($campaign['manualReportSentAt'])): ?>
                        <div class="d-flex gap-3 mt-2 pt-2 border-top text-muted" style="font-size:0.82rem;">
                            <?php if (!empty($campaign['autoAlertSentAt'])): ?>
                                <span><i class="fas fa-envelope-open-text text-primary me-1"></i> Alerte auto : <strong><?= h($campaign['autoAlertSentAt']) ?></strong></span>
                            <?php endif; ?>
                            <?php if (!empty($campaign['manualReportSentAt'])): ?>
                                <span><i class="fas fa-paper-plane text-success me-1"></i> Rapport RH : <strong><?= h($campaign['manualReportSentAt']) ?></strong></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Action Button Group -->
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <form method="post" id="rr-single-export-form" style="display:inline;" onsubmit="if(window.rrSyncExportOptions)rrSyncExportOptions(this);">
                        <input type="hidden" name="action" value="export_excel">
                        <input type="hidden" name="paper_id" value="<?= h($paperIdStr) ?>">
                        <button type="submit" class="btn-rr btn-secondary-gradient">
                            <i class="fas fa-file-excel"></i> Exporter Excel (Épreuve)
                        </button>
                    </form>
                    <?php if (in_array($profile, $sendProfiles, true)): ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Envoyer le rapport Excel officiel par email aux responsables RH ?');">
                            <input type="hidden" name="action" value="send_rh_report">
                            <input type="hidden" name="paper_id" value="<?= h($paperIdStr) ?>">
                            <button type="submit" class="btn-rr btn-primary-gradient">
                                <i class="fas fa-paper-plane"></i> Envoyer Rapport aux RH
                            </button>
                        </form>
                        <?php if (!empty($campaign['manualReportSentAt'])): ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Forcer un nouvel envoi du rapport au RH ?');">
                                <input type="hidden" name="action" value="send_rh_report">
                                <input type="hidden" name="paper_id" value="<?= h($paperIdStr) ?>">
                                <input type="hidden" name="force_resend" value="1">
                                <button type="submit" class="btn-rr btn-glass">
                                    <i class="fas fa-redo"></i> Forcer Renvoi RH
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($papers !== array()): ?>
            <div class="mt-4 pt-3 border-top" id="rr-export-options">
                <h3 class="fw-bold text-dark mb-2" style="font-size:0.95rem;">
                    <i class="fas fa-sliders-h text-primary me-1"></i> Options d'export Excel
                </h3>
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <label class="d-flex align-items-center gap-2" style="cursor:pointer;font-size:0.88rem;">
                        <input type="checkbox" class="js-export-opt" data-option="include_completude_globale" value="1">
                        Inclure la complétude globale /20
                    </label>
                    <label class="d-flex align-items-center gap-2" style="cursor:pointer;font-size:0.88rem;">
                        <input type="checkbox" class="js-export-opt" data-option="include_charts" value="1" checked>
                        Inclure les graphiques de maîtrise (feuilles candidats)
                    </label>
                </div>
            </div>
            <form method="post" id="rr-multi-export-form" class="mt-2" onsubmit="if(window.rrSyncExportOptions)rrSyncExportOptions(this); if(window.rrSyncExportHiddenPaperIds)rrSyncExportHiddenPaperIds();">
                <input type="hidden" name="action" value="export_multi_excel">
                <div class="row align-items-end g-3">
                    <div class="col-md-6">
                        <label class="fw-bold text-dark mb-1" style="font-size:0.85rem;">Nom personnalisé de l'export multi-épreuves</label>
                        <input type="text" name="bundle_label" class="form-control" style="border-radius:10px;"
                            placeholder="Ex: Recrutement_PL_Aout_2026" value="">
                    </div>
                    <div class="col-md-6">
                        <button type="submit" class="btn-rr btn-secondary-gradient w-100 justify-content-center">
                            <i class="fas fa-file-archive me-1"></i> Exporter le bundle Excel (épreuves sélectionnées)
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Candidate Directory Table Section -->
    <?php if ($paperBlocks !== array()): ?>
        <div class="card-glass">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="fw-bold text-dark m-0" style="font-size:1.15rem;">
                        <i class="fas fa-user-graduate text-primary me-2"></i> Candidats Inscrits & Résultats
                    </h2>
                    <p class="text-muted m-0" style="font-size:0.85rem;">
                        <?= count($paperBlocks) ?> épreuve(s) ·
                        <?php
                        $totalListed = 0;
                        foreach ($paperBlocks as $pb) {
                            $totalListed += count($pb['candidates']);
                        }
                        echo (int) $totalListed;
                        ?> candidat(s)
                    </p>
                </div>
            </div>

            <!-- Candidate Search & Filter Toolbar -->
            <div class="rr-filter-toolbar">
                <div class="rr-search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="js-search-candidate" class="form-control" placeholder="Rechercher par nom ou identifiant...">
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="rr-btn-group-filter">
                        <button type="button" class="rr-filter-btn active js-filter-status-btn" data-status="all">Tous</button>
                        <button type="button" class="rr-filter-btn js-filter-status-btn" data-status="Terminé">Terminés</button>
                        <button type="button" class="rr-filter-btn js-filter-status-btn" data-status="En cours">En cours</button>
                        <button type="button" class="rr-filter-btn js-filter-status-btn" data-status="Non démarré">Non démarrés</button>
                    </div>
                    <div class="rr-dropdown-container rr-col-dropdown" id="rr-col-dropdown">
                        <div class="rr-dropdown-trigger rr-col-dropdown-trigger" id="js-col-dropdown-trigger">
                            <i class="fas fa-columns me-1"></i> Colonnes
                        </div>
                        <div class="rr-dropdown-menu rr-col-dropdown-menu" id="js-col-dropdown-menu">
                            <div class="rr-dropdown-list">
                                <?php if ($multiPaperView): ?>
                                <label class="rr-dropdown-option js-col-toggle" data-col="epreuve">
                                    <input type="checkbox" checked> Épreuve
                                </label>
                                <?php endif; ?>
                                <label class="rr-dropdown-option js-col-toggle" data-col="statut">
                                    <input type="checkbox" checked> Statut
                                </label>
                                <label class="rr-dropdown-option js-col-toggle" data-col="repondues">
                                    <input type="checkbox" checked> Répondues
                                </label>
                                <label class="rr-dropdown-option js-col-toggle" data-col="temps">
                                    <input type="checkbox" checked> Temps écoulé
                                </label>
                                <label class="rr-dropdown-option js-col-toggle" data-col="vitesse">
                                    <input type="checkbox" checked> Vitesse
                                </label>
                                <label class="rr-dropdown-option js-col-toggle" data-col="maitrise">
                                    <input type="checkbox" checked> Maîtrise /20
                                </label>
                                <label class="rr-dropdown-option js-col-toggle" data-col="completude">
                                    <input type="checkbox" checked> Complétude /20
                                </label>
                                <label class="rr-dropdown-option js-col-toggle" data-col="orientation">
                                    <input type="checkbox" checked> Orientation
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rr-table-container">
                <table class="rr-table-modern" id="rr-candidates-table">
                    <thead>
                        <tr>
                            <?php if ($multiPaperView): ?><th class="rr-col-epreuve rr-col-toggle" data-col="epreuve">Épreuve</th><?php endif; ?>
                            <th class="rr-col-candidat">Candidat</th>
                            <th class="rr-col-toggle" data-col="statut">Statut</th>
                            <th class="rr-col-toggle" data-col="repondues">Répondues</th>
                            <th class="rr-col-toggle" data-col="temps">Temps écoulé</th>
                            <th class="rr-col-toggle" data-col="vitesse">Vitesse</th>
                            <th class="rr-col-toggle" data-col="maitrise">Maîtrise /20</th>
                            <th class="rr-col-toggle" data-col="completude">Complétude /20</th>
                            <th class="rr-col-toggle" data-col="orientation">Orientation</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($paperBlocks as $block):
                        foreach ($block['candidates'] as $c):
                            $g = $c['global']; ?>
                        <tr class="rr-row-clickable js-cand-row" 
                            data-paper-id="<?= h($block['paper_id']) ?>" 
                            data-user-id="<?= h($c['user_id']) ?>"
                            data-status="<?= h($c['status']) ?>"
                            data-name="<?= h(strtolower($c['name'] . ' ' . $c['username'] . ' ' . $c['subsidiary'])) ?>">
                            
                            <?php if ($multiPaperView): ?>
                                <td class="rr-col-epreuve rr-col-toggle" data-col="epreuve">
                                    <span class="rr-paper-label-wrap"><?= h($block['ctx']['paper_label']) ?></span>
                                </td>
                            <?php endif; ?>
                            
                            <td class="rr-col-candidat">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rr-avatar-circle" style="width:34px;height:34px;font-size:0.85rem;">
                                        <?= h(strtoupper(substr($c['name'], 0, 2))) ?>
                                    </div>
                                    <div>
                                        <strong class="d-block text-dark"><?= h($c['name']) ?></strong>
                                        <small class="text-muted rr-candidate-subline">
                                            <?= h($c['subsidiary']) ?>
                                            <?= rrFlagIconForSubsidiary($c['subsidiary']) ?>
                                        </small>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="rr-col-toggle" data-col="statut">
                                <span class="rr-status-chip rr-status-<?= strtolower(str_replace(' ', '-', $c['status'])) ?>">
                                    <?= h($c['status']) ?>
                                </span>
                            </td>
                            
                            <td class="rr-col-toggle" data-col="repondues"><strong><?= (int) $g['answered'] ?></strong> / <?= (int) $g['total'] ?></td>
                            <td class="rr-col-toggle" data-col="temps"><?= h($c['time_elapsed'] ?: '—') ?></td>
                            <td class="rr-col-toggle" data-col="vitesse"><?= fmtNote($c['avg_speed_per_min']) ?> <small class="text-muted">q/min</small></td>
                            <td class="rr-col-toggle" data-col="maitrise"><?= renderNoteBadge($g['note_maitrise']) ?></td>
                            <td class="rr-col-toggle" data-col="completude"><?= renderNoteBadge($g['note_completude']) ?></td>
                            
                            <td class="rr-col-toggle" data-col="orientation">
                                <?= !empty($c['orientation_done']) ? '<span class="rr-badge-info"><i class="fas fa-compass me-1"></i> Fait</span>' : '<span class="text-muted">—</span>' ?>
                            </td>
                            
                            <td class="text-end">
                                <button type="button" class="btn-rr btn-glass btn-sm js-show-detail" 
                                    data-paper-id="<?= h($block['paper_id']) ?>" 
                                    data-user-id="<?= h($c['user_id']) ?>">
                                    <i class="fas fa-chart-pie me-1"></i> Détail
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Candidate Detail Drawer Panels Zone -->
            <div id="rr-detail-zone">
                <?php foreach ($paperBlocks as $block):
                    foreach ($block['candidates'] as $c):
                        rr_render_candidate_detail($c, 'rr-detail-' . $block['paper_id'] . '-' . $c['user_id']);
                    endforeach;
                endforeach; ?>
            </div>
        </div>
    <?php elseif ($papersParam !== '' || $paperIdStr !== ''): ?>
        <div class="card-glass">
            <p class="text-danger m-0 fw-bold"><i class="fas fa-exclamation-triangle me-2"></i> Aucune épreuve valide trouvée dans la sélection.</p>
        </div>
    <?php endif; ?>

</div>

<script>
(function () {
    var activeUserId = null;
    var activePaperId = null;
    var activeCharts = {};

    function rrGetSelectedPaperIds() {
        var ids = [];
        var boxes = document.querySelectorAll('.js-paper-checkbox:checked');
        for (var i = 0; i < boxes.length; i++) {
            ids.push(boxes[i].value);
        }
        return ids;
    }

    function updateDropdownSummary() {
        var boxes = document.querySelectorAll('.js-paper-checkbox');
        var checkedBoxes = document.querySelectorAll('.js-paper-checkbox:checked');
        var countEl = document.getElementById('js-dropdown-count');
        var labelEl = document.getElementById('js-dropdown-label');

        if (countEl) countEl.innerText = checkedBoxes.length;

        if (labelEl) {
            if (checkedBoxes.length === 0) {
                labelEl.innerText = 'Aucune épreuve sélectionnée...';
            } else if (checkedBoxes.length === 1) {
                labelEl.innerText = checkedBoxes[0].getAttribute('data-title');
            } else if (checkedBoxes.length === boxes.length) {
                labelEl.innerText = 'Toutes les épreuves (' + checkedBoxes.length + ')';
            } else {
                var titles = [];
                for (var i = 0; i < Math.min(2, checkedBoxes.length); i++) {
                    titles.push(checkedBoxes[i].getAttribute('data-title'));
                }
                var text = titles.join(', ');
                if (checkedBoxes.length > 2) {
                    text += ' (+' + (checkedBoxes.length - 2) + ')';
                }
                labelEl.innerText = text;
            }
        }
    }

    var ddContainer = document.getElementById('rr-paper-dropdown');
    var ddTrigger = document.getElementById('js-dropdown-trigger');
    if (ddTrigger && ddContainer) {
        ddTrigger.addEventListener('click', function (e) {
            e.stopPropagation();
            ddContainer.classList.toggle('open');
        });
    }

    document.addEventListener('click', function (e) {
        if (ddContainer && !ddContainer.contains(e.target)) {
            ddContainer.classList.remove('open');
        }
    });

    var ddSearchInput = document.getElementById('js-dropdown-search-input');
    if (ddSearchInput) {
        ddSearchInput.addEventListener('input', function () {
            var q = this.value.toLowerCase().trim();
            var options = document.querySelectorAll('.js-dd-option');
            for (var i = 0; i < options.length; i++) {
                var lbl = options[i].getAttribute('data-label') || '';
                if (!q || lbl.indexOf(q) !== -1) {
                    options[i].style.display = '';
                } else {
                    options[i].style.display = 'none';
                }
            }
        });
    }

    var ddSelectAllBtn = document.getElementById('js-dd-select-all');
    if (ddSelectAllBtn) {
        ddSelectAllBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var boxes = document.querySelectorAll('.js-paper-checkbox');
            for (var i = 0; i < boxes.length; i++) { boxes[i].checked = true; }
            updateDropdownSummary();
        });
    }

    var ddClearAllBtn = document.getElementById('js-dd-clear-all');
    if (ddClearAllBtn) {
        ddClearAllBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var boxes = document.querySelectorAll('.js-paper-checkbox');
            for (var i = 0; i < boxes.length; i++) { boxes[i].checked = false; }
            updateDropdownSummary();
        });
    }

    var checkboxes = document.querySelectorAll('.js-paper-checkbox');
    for (var cb = 0; cb < checkboxes.length; cb++) {
        checkboxes[cb].addEventListener('change', updateDropdownSummary);
    }
    updateDropdownSummary();

    var applyDropdownBtn = document.getElementById('js-apply-dropdown-selection');
    if (applyDropdownBtn) {
        applyDropdownBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (ddContainer) ddContainer.classList.remove('open');
            rrApplyPaperSelection();
        });
    }

    function rrSyncExportOptions(form) {
        if (!form) {
            return;
        }
        var old = form.querySelectorAll('input.js-sync-export-opt');
        for (var i = 0; i < old.length; i++) {
            old[i].parentNode.removeChild(old[i]);
        }
        var opts = document.querySelectorAll('.js-export-opt');
        for (var j = 0; j < opts.length; j++) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.className = 'js-sync-export-opt';
            inp.name = opts[j].getAttribute('data-option');
            inp.value = opts[j].checked ? '1' : '0';
            form.appendChild(inp);
        }
    }
    window.rrSyncExportOptions = rrSyncExportOptions;

    function rrSyncExportHiddenPaperIds() {
        var form = document.getElementById('rr-multi-export-form');
        if (!form) {
            return;
        }
        var old = form.querySelectorAll('input.js-sync-paper');
        for (var i = 0; i < old.length; i++) {
            old[i].parentNode.removeChild(old[i]);
        }
        var ids = rrGetSelectedPaperIds();
        for (var j = 0; j < ids.length; j++) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'paper_ids[]';
            inp.value = ids[j];
            inp.className = 'js-sync-paper';
            form.appendChild(inp);
        }
    }

    function rrApplyPaperSelection() {
        if (window.rrCloseDetail) {
            rrCloseDetail();
        }
        var ids = rrGetSelectedPaperIds();
        var url = new URL(window.location.href);
        url.searchParams.delete('paper');
        url.searchParams.delete('user');
        url.searchParams.delete('detail_paper');
        if (ids.length) {
            url.searchParams.set('papers', ids.join(','));
        } else {
            url.searchParams.delete('papers');
        }
        window.location.href = url.pathname + url.search;
    }

    var applyBtn = document.getElementById('js-apply-papers');
    if (applyBtn) {
        applyBtn.addEventListener('click', rrApplyPaperSelection);
    }

    var exportForm = document.getElementById('rr-multi-export-form');
    if (exportForm) {
        exportForm.addEventListener('submit', function () {
            rrSyncExportHiddenPaperIds();
        });
    }

    var rrColStorageKey = 'medacap_rr_table_cols';
    var colDropdown = document.getElementById('rr-col-dropdown');
    var colDropdownTrigger = document.getElementById('js-col-dropdown-trigger');

    function rrLoadColPrefs() {
        try {
            var raw = localStorage.getItem(rrColStorageKey);
            if (!raw) {
                return {};
            }
            var parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function rrSaveColPrefs(prefs) {
        try {
            localStorage.setItem(rrColStorageKey, JSON.stringify(prefs));
        } catch (e) {}
    }

    function rrApplyColVisibility(colId, visible) {
        var cells = document.querySelectorAll('#rr-candidates-table [data-col="' + colId + '"]');
        for (var i = 0; i < cells.length; i++) {
            if (visible) {
                cells[i].classList.remove('rr-col-hidden');
            } else {
                cells[i].classList.add('rr-col-hidden');
            }
        }
    }

    function rrInitColumnToggles() {
        var toggles = document.querySelectorAll('.js-col-toggle');
        if (!toggles.length) {
            return;
        }
        var prefs = rrLoadColPrefs();

        for (var i = 0; i < toggles.length; i++) {
            var colId = toggles[i].getAttribute('data-col');
            var checkbox = toggles[i].querySelector('input[type="checkbox"]');
            if (!colId || !checkbox) {
                continue;
            }
            if (Object.prototype.hasOwnProperty.call(prefs, colId)) {
                checkbox.checked = !!prefs[colId];
            }
            rrApplyColVisibility(colId, checkbox.checked);
        }

        for (var j = 0; j < toggles.length; j++) {
            toggles[j].addEventListener('click', function (e) {
                e.stopPropagation();
            });
            var cb = toggles[j].querySelector('input[type="checkbox"]');
            if (!cb) {
                continue;
            }
            cb.addEventListener('change', function () {
                var label = this.closest('.js-col-toggle');
                if (!label) {
                    return;
                }
                var id = label.getAttribute('data-col');
                if (!id) {
                    return;
                }
                rrApplyColVisibility(id, this.checked);
                var nextPrefs = rrLoadColPrefs();
                nextPrefs[id] = this.checked;
                rrSaveColPrefs(nextPrefs);
            });
        }
    }

    if (colDropdownTrigger && colDropdown) {
        colDropdownTrigger.addEventListener('click', function (e) {
            e.stopPropagation();
            colDropdown.classList.toggle('open');
        });
    }

    document.addEventListener('click', function (e) {
        if (colDropdown && !colDropdown.contains(e.target)) {
            colDropdown.classList.remove('open');
        }
    });

    rrInitColumnToggles();

    // Candidate Search Filtering
    var searchInput = document.getElementById('js-search-candidate');
    if (searchInput) {
        searchInput.addEventListener('input', filterCandidates);
    }

    var statusBtns = document.querySelectorAll('.js-filter-status-btn');
    var currentStatusFilter = 'all';
    for (var sb = 0; sb < statusBtns.length; sb++) {
        statusBtns[sb].addEventListener('click', function () {
            for (var k = 0; k < statusBtns.length; k++) { statusBtns[k].classList.remove('active'); }
            this.classList.add('active');
            currentStatusFilter = this.getAttribute('data-status');
            filterCandidates();
        });
    }

    function filterCandidates() {
        var query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        var rows = document.querySelectorAll('.js-cand-row');
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var nameAttr = row.getAttribute('data-name') || '';
            var statusAttr = row.getAttribute('data-status') || '';
            var matchesSearch = !query || nameAttr.indexOf(query) !== -1;
            var matchesStatus = (currentStatusFilter === 'all') || (statusAttr === currentStatusFilter);

            if (matchesSearch && matchesStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    }

    function rrCloseDetail() {
        var panels = document.querySelectorAll('.rr-detail-panel');
        for (var i = 0; i < panels.length; i++) {
            panels[i].style.display = 'none';
        }
        var rows = document.querySelectorAll('#rr-candidates-table tr.rr-row-active');
        for (var j = 0; j < rows.length; j++) {
            rows[j].classList.remove('rr-row-active');
        }
        activeUserId = null;
        activePaperId = null;
        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.delete('user');
            url.searchParams.delete('detail_paper');
            window.history.replaceState({}, '', url.pathname + url.search);
        }
    }
    window.rrCloseDetail = rrCloseDetail;

    function rrShowDetail(paperId, userId) {
        if (!paperId || !userId) return;
        rrCloseDetail();
        var panel = document.getElementById('rr-detail-' + paperId + '-' + userId);
        var row = document.querySelector('#rr-candidates-table tr[data-paper-id="' + paperId + '"][data-user-id="' + userId + '"]');
        if (!panel) return;
        
        panel.style.display = 'block';
        if (row) row.classList.add('rr-row-active');
        activeUserId = userId;
        activePaperId = paperId;

        // Render Radar Chart for candidate if Chart.js is present
        initCandidateChart(panel, paperId, userId);

        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.set('user', userId);
            url.searchParams.set('detail_paper', paperId);
            window.history.replaceState({}, '', url.pathname + url.search);
        }
        
        var header = document.getElementById('kt_header');
        var headerH = header ? header.offsetHeight : 100;
        var y = panel.getBoundingClientRect().top + window.pageYOffset - headerH - 16;
        window.scrollTo({ top: Math.max(0, y), behavior: 'smooth' });
    }

    function initCandidateChart(panel, paperId, userId) {
        if (typeof Chart === 'undefined') return;
        var chartKey = paperId + '-' + userId;
        if (activeCharts[chartKey]) return; // Chart already initialized

        var canvas = panel.querySelector('.js-radar-chart');
        if (!canvas) return;

        var chartDataRaw = panel.getAttribute('data-chart');
        if (!chartDataRaw) return;

        try {
            var dataObj = JSON.parse(chartDataRaw);
            if (!dataObj.labels || !dataObj.labels.length) return;

            // Garantir que chaque libellé est découpé sur plusieurs lignes pour ne jamais être rogné
            var formattedLabels = dataObj.labels.map(function(lbl) {
                if (Array.isArray(lbl)) return lbl;
                if (typeof lbl === 'string') {
                    if (lbl.indexOf('Niveau ') === 0) {
                        return ['Niveau', lbl.substring(7)];
                    }
                    if (lbl.indexOf('Macro: ') === 0 || lbl.indexOf('Macro : ') === 0) {
                        var macroName = lbl.replace(/^Macro\s*:\s*/i, '');
                        if (macroName.length > 14 && macroName.indexOf('et ') !== -1) {
                            var parts = macroName.split('et ');
                            return ['Macro :', parts[0].trim() + ' et', parts[1].trim()];
                        }
                        return ['Macro :', macroName];
                    }
                    if (lbl.length > 14) {
                        var words = lbl.split(' ');
                        var lines = [];
                        var cur = '';
                        for (var w = 0; w < words.length; w++) {
                            if (cur && (cur + ' ' + words[w]).length > 12) {
                                lines.push(cur);
                                cur = words[w];
                            } else {
                                cur = cur ? (cur + ' ' + words[w]) : words[w];
                            }
                        }
                        if (cur) lines.push(cur);
                        return lines;
                    }
                }
                return lbl;
            });

            var ctx = canvas.getContext('2d');
            activeCharts[chartKey] = new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: formattedLabels,
                    datasets: [{
                        label: 'Note /20',
                        data: dataObj.scores,
                        backgroundColor: 'rgba(0, 91, 169, 0.2)',
                        borderColor: '#005BA9',
                        borderWidth: 2,
                        pointBackgroundColor: '#d70006',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: '#d70006'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 12,
                            bottom: 12,
                            left: 24,
                            right: 24
                        }
                    },
                    scales: {
                        r: {
                            min: 0,
                            max: 20,
                            ticks: { 
                                stepSize: 5,
                                backdropColor: 'transparent',
                                font: { size: 10 }
                            },
                            pointLabels: { 
                                font: { size: 11, weight: '600', lineHeight: 1.25 },
                                color: '#334155',
                                padding: 8
                            }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    var raw = context[0].label;
                                    return Array.isArray(raw) ? raw.join(' ') : raw;
                                },
                                label: function(context) {
                                    return ' Note: ' + context.parsed.r + ' / 20';
                                }
                            }
                        }
                    }
                }
            });
        } catch (e) {
            console.error('Err radar chart', e);
        }
    }

    // Detail Tabs Switching
    document.addEventListener('click', function (e) {
        var tabBtn = e.target.closest('.rr-tab-btn');
        if (tabBtn) {
            e.preventDefault();
            var targetTabId = tabBtn.getAttribute('data-tab');
            var parentPanel = tabBtn.closest('.rr-detail-panel');
            if (!parentPanel) return;

            var tabs = parentPanel.querySelectorAll('.rr-tab-btn');
            for (var t = 0; t < tabs.length; t++) { tabs[t].classList.remove('active'); }
            tabBtn.classList.add('active');

            var contents = parentPanel.querySelectorAll('.rr-tab-content');
            for (var c = 0; c < contents.length; c++) { contents[c].classList.remove('active'); }
            
            var targetEl = parentPanel.querySelector('#tab-' + targetTabId);
            if (targetEl) targetEl.classList.add('active');
            return;
        }

        // Show/Close Detail Handlers
        var btn = e.target.closest('.js-show-detail');
        if (btn) {
            e.preventDefault();
            rrShowDetail(btn.getAttribute('data-paper-id'), btn.getAttribute('data-user-id'));
            return;
        }
        if (e.target.closest('.js-close-detail')) {
            e.preventDefault();
            rrCloseDetail();
            return;
        }
        var row = e.target.closest('#rr-candidates-table tr.js-cand-row');
        if (row && !e.target.closest('button')) {
            var pid = row.getAttribute('data-paper-id');
            var uid = row.getAttribute('data-user-id');
            if (uid === activeUserId && pid === activePaperId) {
                rrCloseDetail();
            } else {
                rrShowDetail(pid, uid);
            }
        }

        // Question Filter Buttons inside detail panel
        var qFilterBtn = e.target.closest('.js-filter-q-btn');
        if (qFilterBtn) {
            var panel = qFilterBtn.closest('.rr-detail-panel');
            if (!panel) return;
            var filterGroup = panel.querySelectorAll('.js-filter-q-btn');
            for (var qf = 0; qf < filterGroup.length; qf++) { filterGroup[qf].classList.remove('active'); }
            qFilterBtn.classList.add('active');
            filterQuestions(panel);
        }
    });

    // Question Filter Search Input inside detail panel
    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('js-filter-q-text')) {
            var panel = e.target.closest('.rr-detail-panel');
            if (panel) filterQuestions(panel);
        }
    });

    function filterQuestions(panel) {
        var textInput = panel.querySelector('.js-filter-q-text');
        var query = textInput ? textInput.value.toLowerCase().trim() : '';
        var activeFilterBtn = panel.querySelector('.js-filter-q-btn.active');
        var filterVal = activeFilterBtn ? activeFilterBtn.getAttribute('data-filter') : 'all';

        var rows = panel.querySelectorAll('.js-q-row');
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var resAttr = row.getAttribute('data-result') || '';
            var rowText = row.innerText.toLowerCase();

            var matchesText = !query || rowText.indexOf(query) !== -1;
            var matchesFilter = (filterVal === 'all') || (resAttr === filterVal);

            if (matchesText && matchesFilter) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    }

    // Auto open deep-linked candidate detail on page load
    var initialPaper = <?= json_encode($initialPaperId) ?>;
    var initialUser = <?= json_encode($initialUserId) ?>;
    if (initialPaper && initialUser && document.getElementById('rr-detail-' + initialPaper + '-' + initialUser)) {
        rrShowDetail(initialPaper, initialUser);
    } else if (initialUser) {
        var fallbackRow = document.querySelector('#rr-candidates-table tr[data-user-id="' + initialUser + '"]');
        if (fallbackRow) {
            rrShowDetail(fallbackRow.getAttribute('data-paper-id'), initialUser);
        }
    }
})();
</script>

<?php
closeBackgroundContainer();
include_once __DIR__ . '/partials/footer.php';
