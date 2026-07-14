<?php
// views/define/components/widgets/histogram-row.php
/**
 * ---------------------------------------------------------------------------
 *  Rendu d’une rangée de cartes « Histogramme » avec séparateurs + / =
 * ---------------------------------------------------------------------------
 *
 *  @param array $cards   Liste des options à passer à renderHistogramCard()
 *                        → 3 cartes  👉  Junior / Senior / Expert
 *                        → 4 cartes  👉  Junior / Senior / Expert / Total
 *
 *  La 4ᵉ carte est détectée comme carte “Total” ⇒ on place le symbole “=”
 *  devant elle ; sinon on ne met que des “+”.
 *
 *  Exemple d’appel :
 *
 *  renderHistogramRow([
 *      [ 'id'=>'levelJ', 'label'=>'JUNIOR',  'done'=>12, 'todo'=>5 ],
 *      [ 'id'=>'levelS', 'label'=>'SENIOR',  'done'=>20, 'todo'=>8 ],
 *      [ 'id'=>'levelE', 'label'=>'EXPERT',  'done'=>6,  'todo'=>1 ],
 *  ]);
 */

require_once __DIR__ . '/card-histogram.php';  // ← renderHistogramCard()

function renderHistogramRow(array $cards): void
{
    $totalCards = count($cards);

    echo '<div class="define-histogram-row d-flex flex-wrap flex-lg-nowrap align-items-stretch justify-content-center mb-8 gap-3 gap-lg-4 p-2 w-100">';

    foreach ($cards as $idx => $opts) {

        /* ---------------- Separateur “+” ou “=” ---------------- */
        if ($idx > 0) {
            // Si on a 4 cartes ET qu’on est sur la dernière ⇒ “=”
            $isTotal = ($totalCards === 4 && $idx === $totalCards - 1);
            $symbol  = $isTotal ? '=' : '+';

            echo '<div class="d-flex align-items-center justify-content-center mx-1 flex-shrink-0 define-level-sep">';
            echo '  <div class="card shadow-sm border-0 d-flex align-items-center justify-content-center" 
                         style="width:32px; height:32px; border-radius:10px; background: rgba(255,255,255,0.9); backdrop-filter: blur(4px);">';
            echo '    <span style="color:#1a1a2e; font-size:18px; font-weight:800; line-height:1;">'
                 . htmlspecialchars($symbol, ENT_QUOTES, 'UTF-8')
                 . '</span>';
            echo '  </div>';
            echo '</div>';
        }

        /* ---------------- La carte histogramme elle‑même -------- */
        renderHistogramCard($opts);
    }

    echo '</div>';
}
