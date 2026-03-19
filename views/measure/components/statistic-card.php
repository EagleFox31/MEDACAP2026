<?php
/**
 * Génère un conteneur de cartes statistiques à partir d'un tableau de données.
 *
 * @param array $cards Tableau de cartes avec clés : id, value, label, color
 * @return void
 */
function renderStatisticCards(array $cards)
{
    // Définition des styles de couleurs
    $styles = [
        'yellow' => [
            'bg' => 'rgba(224, 224, 72, 0.74)',
            'border' => 'rgba(255, 255, 150, 0.3)',
            'shadow' => 'rgba(255, 200, 0, 0.2)',
        ],
        'blue' => [
            'bg' => 'rgba(109, 180, 235, 0.7)',
            'border' => 'rgba(150, 200, 255, 0.3)',
            'shadow' => 'rgba(0, 100, 255, 0.2)',
        ],
        'none' => [
            'bg' => 'rgba(255, 255, 255, 0.7)',
            'border' => 'rgba(150, 200, 255, 0.3)',
            'shadow' => 'rgba(0, 100, 255, 0.2)',
        ],
    ];
    ?>
    <div class="d-flex flex-wrap justify-content-center gap-3" style="margin: 15px 0">
        <?php foreach ($cards as $card): ?>
            <?php
            $style = $styles[$card['color'] ?? 'none'] ?? $styles['none'];
            ?>
            <div style="flex: 1 1 calc(25% - 1rem); min-width: 220px;" class="d-flex">
                <div class='card glass-effect depth-effect h-70 w-100'
                     style="background: <?= $style['bg'] ?> !important;
                             border: 1px solid <?= $style['border'] ?> !important;
                             box-shadow: 0 8px 32px 0 <?= $style['shadow'] ?> !important;">
                    <div class='card-body d-flex flex-column justify-content-center align-items-center py-2'>
                        <div class="fs-lg-3hx fs-1x fw-bolder text-gray-800 d-flex justify-content-center text-center">
                            <div id="<?= $card['id'] ?>" data-value="<?= $card['value'] ?>" style="opacity: 0;">0</div>
                        </div>
                        <div class="fs-4 fw-bold mb-2"><?= htmlspecialchars($card['label']) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}
?>
