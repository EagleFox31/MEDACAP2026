<?php
/**
 * Génère une carte de titre stylisée avec une icône et un titre centré.
 *
 * @param string $icon  Classe(s) CSS de l’icône (par ex. 'bi bi-gear' ou 'fas fa-cogs')
 * @param string $title Le titre à afficher dans la carte
 * @param string $size  Niveau du titre HTML (ex: 'h1', 'h2', 'h3', etc.) — par défaut 'h2'
 *
 * @return void
 *
 * Exemple :
 * renderTitleCard('bi bi-gear', 'Paramètres du Système');
 */
function renderTitleCard($icon, $title, $size = 'h2') {
    ?>
    <!-- Titre principal en pleine largeur -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card p-3 text-center" style="background-color: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                <<?php echo $size; ?> class="card-title mb-0 text-dark fw-bold fs-2">
                <i class="<?php echo $icon; ?> fs-2 me-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i> <?php echo $title; ?>
            </<?php echo $size; ?>>
        </div>
        </div>
    </div>
    <?php
}
?>