<?php
function renderTitleContainer(
    string $iconClass,
    ?string $title,
    string $tileSize,
    string $contentComponent,
    string $heightSize = "100%",
    string $flexSize = "flex: 1 1 25%; padding: 10px"
): string {
    ob_start();
    ?>
<div class="glassmorphism overflow-hidden"
     style="display: flex; flex-direction: column; min-height: 0; <?= htmlspecialchars($flexSize) ?>; height: <?= htmlspecialchars($heightSize) ?>;">

    <?php if (!empty($title)): ?>
        <div class="card-header-bg filter-header text-start" style="margin: -10px -10px 0 -10px; border-radius: 0;">
        <i class="<?= htmlspecialchars($iconClass) ?>">
            <span class="path1"></span><span class="path2"></span>
        </i>
        <<?= $tileSize ?> class="card-title d-inline" style="margin: 0;">
        <?= htmlspecialchars(mb_strtoupper($title)) ?>
        </<?= $tileSize ?>>
        </div>
    <?php endif; ?>

    <!-- Bloc contenu scrollable -->
    <div style="flex: 1; display: flex; flex-direction: column; margin-top: 10px; overflow-y: auto; min-height: 0;">
        <?= $contentComponent ?>
    </div>
    </div>
    <?php
    return ob_get_clean();
}


/**
 * Génère un conteneur flexible transparent (glass-effect) pouvant afficher
 * un tableau d’éléments enfants et permettre le scroll si nécessaire.
 *
 * @param array $children          Tableau de contenus HTML déjà générés
 * @param string $heightSize       Hauteur CSS du conteneur (ex: '100%', '600px') — par défaut : '100%'
 * @param string $flexSize         Style flex par défaut pour le conteneur (ex: 'flex: 1 1 25%; padding: 10px')
 *
 * @return string                  HTML du conteneur complet
 *
 * @example
 * echo renderChildrenContainer([$component1, $component2, $component3], '400px', 'flex: 1 1 30%; padding: 10px');
 */
function renderScrollableContainer(
    array $children,
    string $heightSize = "100%",
    string $flexSize = "flex: 1 1 25%; padding: 10px"
): string {
    ob_start();
    ?>
    <div class="overflow-hidden"
         style="display: flex; flex-direction: column; min-height: 0; <?= htmlspecialchars($flexSize) ?>; height: <?= htmlspecialchars($heightSize) ?>;">

        <div style="flex: 1; display: flex; flex-direction: column; overflow-y: auto; gap: 10px; padding: 5px;">
            <?php foreach ($children as $child): ?>
                <?= $child ?>
            <?php endforeach; ?>
        </div>

    </div>
    <?php
    return ob_get_clean();
}
?>
