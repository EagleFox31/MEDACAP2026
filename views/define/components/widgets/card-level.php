<?php
/**
 * ------------------------------------------------------------------
 *  Widget « card‑level » réutilisable
 * ------------------------------------------------------------------
 *  › Usage minimal  :
 *        require_once __DIR__ . '/components/widgets/card-level.php';
 *        renderCardLevel('junior',  42, 'Techniciens Junior');
 *
 *  › Usage groupé   :
 *        renderLevelRow([
 *            ['id'=>'junior', 'count'=>42, 'label'=>'Junior',  'sep'=>'+'],
 *            ['id'=>'senior', 'count'=>10, 'label'=>'Senior',  'sep'=>'+'],
 *            ['id'=>'expert', 'count'=>3,  'label'=>'Expert',  'sep'=>'='],
 *            ['id'=>'total',  'count'=>55, 'label'=>'Tous Niveaux']
 *        ]);
 * ------------------------------------------------------------------ */

/**
 * Rend UNE carte niveau.
 *
 * @param string $id    id HTML (sert aussi à la cible CountUp)
 * @param int    $count valeur affichée
 * @param string $label libellé sous la valeur
 * @param array  $attrs attributs HTML supplémentaires [key => value]
 */
function renderCardLevel(string $id, int $count, string $label, array $attrs = []): void
{
    // attributs HTML optionnels (class, style, data-*, …)
    $extra = '';
    foreach ($attrs as $k => $v) {
        $extra .= sprintf(' %s="%s"', $k, htmlspecialchars($v, ENT_QUOTES));
    }

    ?>
    <div class="flex-grow-1" style="min-width:180px; max-width:340px; flex: 1 1 0;">
        <div class="card stats-card glass-effect depth-effect h-100 shadow-sm border-0">
            <div class="card-body text-center p-6 p-md-8 d-flex flex-column justify-content-center">
                <div class="big-number mb-3 fs-2hx fw-bolder text-dark"
                     id="<?= htmlspecialchars($id) ?>"
                     data-value="<?= (int)$count ?>"
                     <?= $extra ?>
                     style="line-height: 1; letter-spacing: -1px;">
                    <?= number_format($count) ?>
                </div>
                <div class="card-title-text fs-6 fw-bold text-muted text-uppercase ls-1"><?= htmlspecialchars($label) ?></div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Rend une rangée complète avec séparateurs + ou =.
 *
 * @param array $cards  tableau d’items :
 *        [
 *          ['id'=>'junior','count'=>42,'label'=>'Junior','sep'=>'+'],
 *          ...,
 *          ['id'=>'total','count'=>55,'label'=>'Total']
 *        ]
 */
function renderLevelRow(array $cards): void
{
    echo '<div class="define-level-row d-flex flex-wrap flex-lg-nowrap align-items-stretch justify-content-center mb-8 gap-3 gap-lg-4 p-2 w-100">';

    foreach ($cards as $item) {
        // 1) la carte elle‑même
        renderCardLevel($item['id'], (int)$item['count'], $item['label'] ?? '', $item['attrs'] ?? []);

        // 2) séparateur éventuel
        if (!empty($item['sep'])) {
            $sym = htmlspecialchars($item['sep']);
            echo <<<HTML
            <div class="d-flex align-items-center justify-content-center mx-1 flex-shrink-0 define-level-sep">
                <div class="card shadow-sm border-0 d-flex align-items-center justify-content-center" 
                     style="width:32px; height:32px; border-radius:10px; background: rgba(255,255,255,0.9); backdrop-filter: blur(4px);">
                    <div style="color:#1a1a2e; font-size:18px; font-weight:800; line-height:1;">{$sym}</div>
                </div>
            </div>
            HTML;
        }
    }

    echo '</div>';
}
