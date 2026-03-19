<?php
/**
 * Vue du dashboard manager/directeur
 */
$GLOBALS['currentPage'] = 'dashboard';
require_once __DIR__ . '/../../partials/header.php';
require_once __DIR__ . '/../../components/widgets/title-main.php';
require_once __DIR__ . '/../../components/widgets/title-section.php';
require_once __DIR__ . '/../../components/widgets/card-level.php';
require_once __DIR__ . '/../../components/widgets/histogram-row.php';
?>
<style>
    :root {
        --primary-black: #1a1a1a;
        --border-radius: 12px;
    }

    /* Effet de glassmorphisme */
    .glass-effect {
        background: rgba(255, 255, 255, 0.7) !important;
        backdrop-filter: blur(10px) !important;
        -webkit-backdrop-filter: blur(10px) !important;
        border: 1px solid rgba(255, 255, 255, 0.18) !important;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1) !important;
        border-radius: 15px !important;
        transition: all 0.3s ease !important;
    }
    .glass-effect:hover {
        background: rgba(255, 255, 255, 0.8) !important;
        box-shadow: 0 10px 40px 0 rgba(31, 38, 135, 0.2) !important;
        transform: translateY(-3px) !important;
    }

    /* Cards principales */
    .card {
        background: rgba(255, 255, 255, 0.7) !important;
        border: 1px solid rgba(255, 255, 255, 0.18) !important;
        border-radius: 20px !important;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1) !important;
        transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        overflow: hidden;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }
    .card:hover {
        transform: translateY(-5px);
    }

    .depth-effect {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 12px 20px -2px rgba(0, 0, 0, 0.05) !important;
    }

    .card-brand {
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 20px;
        margin-bottom: 15px;
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(10px);
        transition: transform 0.3s ease;
    }
    .card-brand:hover { transform: translateY(-5px); }
    .brand-logo { max-height: 50px; max-width: 100%; display: block; margin: 0 auto; }
    .brand-progress { height: 8px; margin-bottom: 10px; border-radius: 4px; }
    .technician-card { margin-bottom: 15px; border-radius: 15px; overflow: hidden; }
    
    .brand-pill {
        display: inline-block; padding: 3px 10px; margin-right: 5px; margin-bottom: 5px;
        border-radius: 20px; font-size: 0.8rem; background: rgba(233, 236, 239, 0.6);
        border: 1px solid rgba(0,0,0,0.05);
    }

    /* Image de fond avec blur — pseudo-element pour ne pas toucher le header */
    body::before {
        content: '';
        position: fixed;
        top: -5%; left: -5%;
        width: 110%; height: 110%;
        background: linear-gradient(rgba(0,0,0,0.25), rgba(0,0,0,0.15)),
                    url('/MEDACAP/public/images/welcome_tech.png') no-repeat center center;
        background-size: cover;
        filter: blur(6px);
        z-index: -1;
    }
    body {
        background: #1a1a1a;
    }

    /* Rendre transparent uniquement la zone de contenu (pas le sidebar) */
    #kt_wrapper,
    #kt_content,
    .content.fs-6 {
        background: transparent !important;
    }

    /* Glassmorphisme sur les cartes */
    .glass-effect, .card {
        background: rgba(255, 255, 255, 0.82) !important;
        backdrop-filter: blur(10px) !important;
        -webkit-backdrop-filter: blur(10px) !important;
        border: 1px solid rgba(255, 255, 255, 0.4) !important;
        box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.15) !important;
        border-radius: 20px !important;
    }

    /* Carrousel des marques */
    .brand-carousel-wrapper {
        position: relative;
        padding: 0 45px;
        display: flex;
        align-items: center;
    }
    .horizontal-scroll-container {
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        scroll-behavior: smooth;
        gap: 1.5rem;
        padding: 1rem 0.5rem;
        -ms-overflow-style: none;
        scrollbar-width: none;
        width: 100%;
    }
    .horizontal-scroll-container::-webkit-scrollbar { display: none; }
    .brand-card-item { flex: 0 0 auto; width: 260px; }
    .brand-arrow-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.85) !important;
        border: 1px solid rgba(255, 255, 255, 0.5);
        backdrop-filter: blur(5px);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transition: all 0.2s ease;
        color: #1a1a2e;
    }
    .brand-arrow-btn:hover {
        background: white !important;
        transform: translateY(-50%) scale(1.1);
        box-shadow: 0 6px 16px rgba(0,0,0,0.15);
    }
    .brand-arrow-btn.left { left: 0; }
    .brand-arrow-btn.right { right: 0; }
</style>

<div class="content fs-6 d-flex flex-column flex-column-fluid" id="kt_content"
     style="position: relative; background: none;">
    <!-- Background décoratif (identique au dashboard Directeur) -->
    <div style="position: absolute; top:0; left:0; width:100%; height:100%; z-index:-1; background-size:cover; background-position:center; border-radius:20px; overflow:hidden;">
        <div style="position:absolute; top:0; left:0; width:100%; height:100%; filter:blur(10px); transform:scale(1.05);
                    background-image: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.1)), url('/MEDACAP/public/images/welcome_tech.png'); background-size:cover; background-position:center;">
        </div>
    </div>

    <div class="container-fluid mt-4 px-4">
        <div class="row">
            <div class="col-12">
        <!-- Header -->
        <div class="mb-8">
            <?php renderTitleMain('ki-duotone ki-element-11 fs-2hx text-primary me-4', 3, $tableau); ?>
        </div>

        <div class="row g-7">
            <!-- Filtres (Masqués pour le Manager car déplacés plus bas) -->
            <?php $isManager = ($_SESSION['profile'] === 'Manager'); ?>
            <?php if (!$isManager): ?>
            <div class="col-12">
                <div class="card glass-effect depth-effect mb-8">
                    <div class="card-body p-6">
                        <form method="GET" action="" class="d-flex flex-wrap gap-3 align-items-end justify-content-center" id="filterForm">
                            <?php if ($_SESSION['profile'] === 'Directeur Groupe' || $_SESSION['profile'] === 'Super Admin'): ?>
                            <div class="col-auto">
                                <label class="form-label fw-bold">Filiale</label>
                                <select class="form-select" name="filiale" id="filialeSelect">
                                    <option value="all" <?php echo $selectedFiliale === 'all' ? 'selected' : ''; ?>>Toutes les filiales</option>
                                    <?php 
                                    $clefsFiliales = array_filter(array_keys($fullData), function($cle) {
                                        return $cle !== 'ALL_FILIALES' && $cle !== 'CFR';
                                    });
                                    sort($clefsFiliales);
                                    foreach ($clefsFiliales as $filiale): ?>
                                        <option value="<?php echo htmlspecialchars($filiale); ?>" <?php echo $selectedFiliale === $filiale ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($filiale); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="col-auto">
                                <label class="form-label fw-bold">Agence</label>
                                <select class="form-select" name="agence" id="agenceSelect">
                                    <option value="all" <?php echo $selectedAgence === 'all' ? 'selected' : ''; ?>>Toutes les agences</option>
                                    <?php 
                                    $allAgencies = [];
                                    if ($selectedFiliale === 'all') {
                                        foreach ($fullData as $sub => $data) {
                                            if ($sub !== 'ALL_FILIALES' && $sub !== 'CFR' && isset($data['agencies'])) {
                                                foreach (array_keys($data['agencies']) as $ag) {
                                                    $allAgencies[$ag] = true;
                                                }
                                            }
                                        }
                                    } elseif (isset($fullData[$selectedFiliale]['agencies'])) {
                                        foreach (array_keys($fullData[$selectedFiliale]['agencies']) as $ag) {
                                            $allAgencies[$ag] = true;
                                        }
                                    }
                                    $agenceNames = array_keys($allAgencies);
                                    sort($agenceNames);
                                    foreach ($agenceNames as $agence): ?>
                                        <option value="<?php echo htmlspecialchars($agence); ?>" <?php echo $selectedAgence === $agence ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($agence); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if (in_array($_SESSION['profile'], ['Super Admin', 'Admin', 'Directeur Groupe', 'Directeur Général', 'Directeur Pièce et Service', 'Directeur des Opérations'])): ?>
                            <div class="col-auto">
                                <label class="form-label fw-bold">Manager</label>
                                <select class="form-select" name="managerId" id="managerSelect">
                                    <option value="all" <?php echo $managerId === 'all' ? 'selected' : ''; ?>>Tous les managers</option>
                                    <?php foreach ($managersList as $mId => $mName): ?>
                                        <option value="<?php echo htmlspecialchars($mId); ?>" <?php echo $managerId === $mId ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($mName); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="col-auto">
                                <label class="form-label fw-bold">Niveau</label>
                                <select class="form-select" name="level">
                                    <?php foreach ($levelsToShow as $lvl): ?>
                                        <option value="<?php echo htmlspecialchars($lvl); ?>" <?php echo $filterLevel === $lvl ? 'selected' : ''; ?>>
                                            <?php echo $lvl === 'all' ? 'Tous les niveaux' : htmlspecialchars($lvl); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-auto">
                                <label class="form-label fw-bold">Marque</label>
                                <select class="form-select" name="brand">
                                    <option value="all" <?php echo $filterBrand === 'all' ? 'selected' : ''; ?>>Toutes les marques</option>
                                    <?php foreach ($teamBrands as $brand): ?>
                                        <option value="<?php echo htmlspecialchars($brand); ?>" <?php echo $filterBrand === $brand ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($brand); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary px-8">Filtrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Effectifs -->
            <div class="col-12">
                <?php renderTitleSection('ki-duotone ki-people fs-2 me-3', 4, 'Effectifs par Niveau'); ?>
                <div class="mb-8"><?php renderLevelRow($levelCards); ?></div>

                <?php renderTitleSection('ki-duotone ki-check-circle fs-2 me-3', 2, "PIFs proposés par l'Academy par Niveau"); ?>
                <div class="d-flex flex-wrap flex-lg-nowrap align-items-center justify-content-center mb-8 mt-3 gap-4 p-2">
                    <?php renderHistogramRow($histograms); ?>
                </div>

                <?php renderTitleSection('ki-duotone ki-chart-pie-3 fs-2 me-3', 2, 'Analyse Détaillée'); ?>
                
                <?php if ($isManager): ?>
                <!-- Filtres spécifiques Manager pour Analyse Détaillée -->
                <div class="card glass-effect depth-effect mb-8">
                    <div class="card-body p-4">
                        <form method="GET" action="" class="d-flex flex-wrap gap-4 align-items-end justify-content-center">
                            <div class="col-auto">
                                <label class="form-label fw-bold">Niveau</label>
                                <select class="form-select" name="level">
                                    <?php foreach ($levelsToShow as $lvl): ?>
                                        <option value="<?php echo htmlspecialchars($lvl); ?>" <?php echo $filterLevel === $lvl ? 'selected' : ''; ?>>
                                            <?php echo $lvl === 'all' ? 'Tous les niveaux' : htmlspecialchars($lvl); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-auto">
                                <label class="form-label fw-bold">Marque</label>
                                <select class="form-select" name="brand">
                                    <option value="all" <?php echo $filterBrand === 'all' ? 'selected' : ''; ?>>Toutes les marques</option>
                                    <?php foreach ($teamBrands as $brand): ?>
                                        <option value="<?php echo htmlspecialchars($brand); ?>" <?php echo $filterBrand === $brand ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($brand); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-sm btn-primary px-6">Appliquer</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <div class="row g-7 mb-8 mt-1">
                    <div class="col-lg-6">
                        <div class="card h-100 glass-effect">
                            <div class="card-header border-0 pt-6 d-flex justify-content-center text-center bg-transparent position-relative" style="border-radius: 20px 20px 0 0; min-height: 80px;">
                                <div style="position:absolute; left:0; top:20%; bottom:20%; width:4px; background:#4e73df; border-radius:0 4px 4px 0;"></div>
                                <h3 class="card-title text-center d-flex flex-column align-items-center">
                                    <span class="card-label fw-bold text-dark fs-5 text-uppercase ls-1">Scores par marque</span>
                                    <span class="text-muted mt-2 fw-semibold fs-7 d-block">Avancement moyen par technologie</span>
                                </h3>
                            </div>
                            <div class="card-body pb-2">
                                <div id="brandScoresChart" style="min-height: 350px;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card h-100 glass-effect">
                            <div class="card-header border-0 pt-6 d-flex justify-content-center text-center bg-transparent position-relative" style="border-radius: 20px 20px 0 0; min-height: 80px;">
                                <div style="position:absolute; left:0; top:20%; bottom:20%; width:4px; background:#1cc88a; border-radius:0 4px 4px 0;"></div>
                                <h3 class="card-title text-center d-flex flex-column align-items-center">
                                    <span class="card-label fw-bold text-dark fs-5 text-uppercase ls-1"><?= $recommaded_training; ?></span>
                                    <span class="text-muted mt-2 fw-semibold fs-7 d-block">Volume de modules recommandés</span>
                                </h3>
                            </div>
                            <div class="card-body pb-2">
                                <div id="trainingChart" style="min-height: 350px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php renderTitleSection('ki-duotone ki-briefcase fs-2 me-3', 2, 'Répartition par Marque'); ?>

                <div class="card glass-effect depth-effect mt-3 mb-8">
                    <div class="card-header border-0 pt-5 d-flex justify-content-center text-center bg-transparent" style="border-radius: 20px 20px 0 0;">
                        <h3 class="card-title text-center">
                            <span class="card-label fw-bold text-dark">Avancement par Marque</span>
                            <span class="text-muted mt-1 fw-semibold fs-7 d-block">Moyenne de l'équipe</span>
                        </h3>
                    </div>
                    <div class="card-body p-6">
                        <div class="brand-carousel-wrapper">
                            <button class="brand-arrow-btn left" id="brandScrollLeft">
                                <i class="ki-duotone ki-left fs-2"><span class="path1"></span><span class="path2"></span></i>
                            </button>

                            <div id="brandScrollContainer" class="horizontal-scroll-container">
                                <?php foreach ($teamBrands as $brand): ?>
                                    <div class="brand-card-item">
                                        <div class="card-brand p-5 text-center h-100 shadow-sm border border-white border-opacity-25 d-flex flex-column justify-content-between">
                                            <div>
                                                <div class="mb-4 d-flex align-items-center justify-content-center" style="height: 60px;">
                                                    <img src="<?= htmlspecialchars($brandLogos[strtoupper($brand)] ?? '/MEDACAP/public/assets/brands/placeholder.png'); ?>" 
                                                         alt="<?= $brand; ?>" 
                                                         class="brand-logo" 
                                                         style="max-height: 50px; width: auto; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));">
                                                </div>
                                                <h6 class="mb-3 fw-bold text-dark text-uppercase fs-7 ls-1"><?= htmlspecialchars($brand); ?></h6>
                                            </div>
                                            
                                            <div>
                                                <?php 
                                                $avgScore = 0;
                                                foreach ($brandScores as $score) { if ($score['x'] === $brand) { $avgScore = $score['y']; break; } }
                                                $pColor = $avgScore >= 80 ? 'bg-success' : ($avgScore >= 60 ? 'bg-warning' : 'bg-danger');
                                                ?>
                                                <div class="progress brand-progress mb-3 bg-light bg-opacity-50" style="height: 10px;">
                                                    <div class="progress-bar <?= $pColor; ?> rounded" style="width: <?= $avgScore; ?>%"></div>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="fs-9 text-muted fw-bold text-uppercase opacity-75">Avancement</span>
                                                    <span class="fs-7 fw-bolder text-dark"><?= number_format($avgScore, 1); ?>%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <button class="brand-arrow-btn right" id="brandScrollRight">
                                <i class="ki-duotone ki-right fs-2"><span class="path1"></span><span class="path2"></span></i>
                            </button>
                        </div>
                    </div>
                </div>

                <?php renderTitleSection('ki-duotone ki-address-book fs-2 me-3', 2, 'Liste des Collaborateurs'); ?>
                <div class="card glass-effect depth-effect mt-3 mb-8">
                    <div class="card-header border-0 pt-5 d-flex flex-column align-items-center bg-transparent" style="border-radius: 20px 20px 0 0;">
                        <h3 class="card-title text-center mb-4">
                            <span class="card-label fw-bold text-dark">Détail des Techniciens</span>
                            <span class="text-muted mt-1 fw-semibold fs-7 d-block"><?= count($technicians); ?> au total</span>
                        </h3>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-light-primary px-4" id="expandAllBtn">Tout développer</button>
                            <button class="btn btn-sm btn-light-secondary px-4" id="collapseAllBtn">Tout réduire</button>
                        </div>
                    </div>
                    <div class="card-body p-6">
                        <div class="row g-4" id="techniciansList">
                            <?php foreach ($technicians as $index => $tech): ?>
                                <div class="col-xl-4 col-md-6">
                                    <div class="card technician-card bg-white bg-opacity-60 border border-white border-opacity-25 shadow-sm h-100">
                                        <div class="card-header border-0 py-5 d-flex justify-content-between align-items-center bg-white bg-opacity-25" 
                                             style="cursor: pointer; border-radius: 15px 15px 0 0;" 
                                             data-bs-toggle="collapse" 
                                             href="#collapseTech<?= $index; ?>">
                                            <div class="d-flex align-items-center">
                                                <div class="symbol symbol-45px symbol-circle me-4 shadow-sm">
                                                    <span class="symbol-label bg-primary bg-opacity-10 text-primary fw-bolder fs-5"><?= substr($tech['name'],0,1); ?></span>
                                                </div>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-bolder text-dark fs-6"><?= htmlspecialchars($tech['name']); ?></span>
                                                    <span class="text-muted fs-8 fw-semibold">Technicien</span>
                                                </div>
                                            </div>
                                            <span class="badge badge-light-<?= $tech['level']==='Expert'?'success':($tech['level']==='Senior'?'primary':'info'); ?> fw-bold px-3 py-2 rounded-pill"><?= $tech['level']; ?></span>
                                        </div>
                                        <div class="collapse" id="collapseTech<?= $index; ?>">
                                            <div class="card-body pt-0 pb-4">
                                                <div class="separator separator-dashed mb-4"></div>
                                                <div class="mb-4">
                                                    <span class="d-block text-muted fs-8 fw-bold mb-2">MARQUES</span>
                                                    <div class="d-flex flex-wrap">
                                                        <?php foreach ($tech['brands'] as $b): ?><span class="brand-pill"><?= htmlspecialchars($b); ?></span><?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <?php foreach ($tech['scoresLevels'] as $lvl => $bScores): ?>
                                                    <div class="mb-3">
                                                        <span class="d-block text-muted fs-8 fw-bold mb-2"><?= strtoupper($lvl); ?></span>
                                                        <div class="d-flex flex-wrap">
                                                            <?php foreach ($bScores as $bn => $sc): 
                                                                $scCol = $sc >= 80 ? 'success' : ($sc >= 60 ? 'warning' : 'danger'); ?>
                                                                <span class="badge badge-light-<?= $scCol; ?> me-2 mb-2"><?= htmlspecialchars($bn); ?>: <?= number_format($sc,1); ?>%</span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- /.row -->
    </div><!-- /.col-12 -->
</div><!-- /.container-fluid -->
</div><!-- /#kt_content -->

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script id="dashboard-data" type="application/json">
    <?php echo json_encode($dashboardData); ?>
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const dashData = JSON.parse(document.getElementById('dashboard-data').textContent);

    // Initialisation des histogrammes PIF — style Directeur
    document.querySelectorAll('.histogram-card').forEach(card => {
        if (card.dataset.chartInit === '1') return;
        card.dataset.chartInit = '1';

        const doneArr = JSON.parse(card.dataset.done || '[]');
        const todoArr = JSON.parse(card.dataset.todo || '[]');
        const color   = card.dataset.color || '#8b5cf6';
        const target  = card.querySelector('.histogram');

        const doneRaw = [doneArr[0]||0, doneArr[1]||0, doneArr[2]||0];
        const todoRaw = [todoArr[0]||0, todoArr[1]||0, todoArr[2]||0];
        const totals  = doneRaw.map((v,i) => v + todoRaw[i]);

        const d = totals.map((t,i) => t > 0 ? (doneRaw[i]/t)*100 : 0);
        const u = totals.map((t,i) => t > 0 ? (todoRaw[i]/t)*100 : 0);

        Object.assign(target.style, {
            background:'#ffffff', borderRadius:'10px', padding:'8px 8px 4px 8px', width:'100%'
        });

        const categories = ['Tests complétés', 'PIF Proposés', 'PIF Validés'];

        new ApexCharts(target, {
            series:[{name:'Réalisé',data:d},{name:'Non réalisé',data:u}],
            chart:{type:'bar',height:300,stacked:true,toolbar:{show:false},fontFamily:'Inter, sans-serif',background:'transparent'},
            plotOptions:{bar:{columnWidth:'90%',borderRadius:4}},
            colors:[color,'#e2e8f0'],
            dataLabels:{
                enabled:true,
                formatter:function(val,ctx){
                    if(ctx.seriesIndex!==0) return '';
                    return Math.round(d[ctx.dataPointIndex])+'%';
                },
                style:{fontSize:'14px',fontWeight:'bold',colors:['#FFFFFF']}
            },
            grid:{show:true,borderColor:'#e7e7e7',strokeDashArray:5,
                  row:{colors:['#f8f8f8','transparent'],opacity:0.5},
                  padding:{top:10,right:10,bottom:10,left:10}},
            stroke:{width:0},
            xaxis:{categories:categories,labels:{style:{fontSize:'10px',fontWeight:600}},
                   axisBorder:{show:true,color:'#e0e0e0'},axisTicks:{show:true}},
            yaxis:{min:0,max:100,tickAmount:10,decimalsInFloat:0,
                   labels:{formatter:v=>Math.round(v)+'%',style:{fontSize:'12px'},offsetX:-8},
                   title:{text:'Pourcentage',style:{fontSize:'12px',fontWeight:600}}},
            tooltip:{
                enabled:true,shared:true,intersect:false,
                custom:function({series,dataPointIndex}){
                    const done=doneRaw[dataPointIndex]||0;
                    const tot=totals[dataPointIndex]||0;
                    const pct=Math.round(d[dataPointIndex])||0;
                    return '<div style="padding:8px 12px;font-size:13px;font-weight:bold;">'
                        +categories[dataPointIndex]+': '+done+'/'+tot+' ('+pct+'%)</div>';
                }
            },
            legend:{show:false},
            fill:{opacity:1},
            responsive:[{breakpoint:768,options:{plotOptions:{bar:{columnWidth:'72%'}},
                xaxis:{categories:['Tests','PIF Prop.','PIF Val.']},
                dataLabels:{style:{fontSize:'11px'}}}}]
        }).render();
    });

    // Chart Scores par marque
    new ApexCharts(document.getElementById('brandScoresChart'), {
        series: [{ name: 'Score (%)', data: dashData.brandScores.map(s => s.y) }],
        chart: { type: 'bar', height: 350, toolbar: { show: false } },
        plotOptions: { bar: { horizontal: true, borderRadius: 6, distributed: true } },
        colors: dashData.brandScores.map(s => s.fillColor),
        xaxis: { categories: dashData.brandScores.map(s => s.x), max: 100 },
        legend: { show: false }
    }).render();

    // Chart Formations
    new ApexCharts(document.getElementById('trainingChart'), {
        series: [{ name: 'Recommandées', data: Object.values(dashData.trainingsCountsForGraph2) }, 
                 { name: 'Validées', data: Object.values(dashData.validationsCountsForGraph2) }],
        chart: { type: 'bar', height: 350, toolbar: { show: false } },
        colors: ['#0dcaf0', '#198754'],
        xaxis: { categories: Object.keys(dashData.trainingsCountsForGraph2) },
        legend: { position: 'top' }
    }).render();

    // Accordions
    document.getElementById('expandAllBtn').onclick = () => document.querySelectorAll('.collapse').forEach(el => new bootstrap.Collapse(el, {toggle: false}).show());
    document.getElementById('collapseAllBtn').onclick = () => document.querySelectorAll('.collapse.show').forEach(el => new bootstrap.Collapse(el, {toggle: false}).hide());

    // Cascade filters
    document.getElementById('filialeSelect')?.addEventListener('change', () => {
        const ag = document.getElementById('agenceSelect');
        const mg = document.getElementById('managerSelect');
        if(ag) ag.value = 'all';
        if(mg) mg.value = 'all';
    });
    document.getElementById('agenceSelect')?.addEventListener('change', () => {
        const mg = document.getElementById('managerSelect');
        if(mg) mg.value = 'all';
    });

    // Brand Carousel Scroll
    const brandContainer = document.getElementById('brandScrollContainer');
    if (brandContainer) {
        const leftBtn = document.getElementById('brandScrollLeft');
        const rightBtn = document.getElementById('brandScrollRight');
        const scrollAmount = 300;

        const checkScroll = () => {
            if (leftBtn && rightBtn) {
                leftBtn.style.display = brandContainer.scrollLeft > 10 ? 'flex' : 'none';
                rightBtn.style.display = (brandContainer.scrollWidth - brandContainer.clientWidth - brandContainer.scrollLeft) > 10 ? 'flex' : 'none';
            }
        };

        leftBtn?.addEventListener('click', () => {
            brandContainer.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        });
        rightBtn?.addEventListener('click', () => {
            brandContainer.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        });

        brandContainer.addEventListener('scroll', checkScroll);
        window.addEventListener('resize', checkScroll);
        setTimeout(checkScroll, 500); // Wait for potential layout shifts
    }
});
</script>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>