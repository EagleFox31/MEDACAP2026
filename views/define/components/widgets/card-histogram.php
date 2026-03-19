<?php
// views/define/components/widgets/histogram-card.php
require_once __DIR__.'/../helpers/ki-icon.php';

function renderHistogramCard(array $opts): void
{
    $o = $opts + [
        'badge'    => '#8b5cf6',
        'icon'     => 'ki-duotone ki-chart',
        'paths'    => 3,
        'iconHtml' => null,
        'showPct'  => true
    ];
    $id    = htmlspecialchars($o['id'],    ENT_QUOTES, 'UTF-8');
    $label = htmlspecialchars($o['label'], ENT_QUOTES, 'UTF-8');
    $color = htmlspecialchars($o['badge'], ENT_QUOTES, 'UTF-8');

    $done = (array) $o['done'];
    $todo = (array) $o['todo'];
    while (count($done) < 3) $done[] = 0;
    while (count($todo) < 3) $todo[] = 0;

    $total0 = $done[0] + $todo[0];
    $pct    = $total0 > 0 ? round($done[0] / $total0 * 100) : 0;
    $iconHtml = $o['iconHtml'] ?? kiIcon($o['icon'], $o['paths']);
?>
<div class="flex-grow-1" style="min-width:280px; max-width:340px; flex: 1 1 0;">
  <div class="card glass-effect depth-effect h-100 histogram-card shadow-lg border-0"
       style="border-radius: 30px !important;"
       data-done='<?= json_encode($done) ?>'
       data-todo='<?= json_encode($todo) ?>'
       data-color='<?= $color ?>'
       id="<?= $id ?>-wrapper">

    <!-- Header Style "Google-like" avec arrondis prononcés (30px) -->
    <div class="card-header border-0 d-flex align-items-center bg-transparent"
         style="border-radius: 30px 30px 0 0;
                padding: 20px 25px;
                position: relative;
                min-height: auto;">
      <!-- Accent coloré vertical discret -->
      <div style="position:absolute; left:0; top:30%; bottom:30%; width:5px; background:<?= $color ?>; border-radius:0 5px 5px 0;"></div>
      
      <span style="flex:1; text-align:center; font-weight:800; font-size:15px; color:#1a1a2e; letter-spacing:.5px; text-transform:uppercase;">
        <?= $iconHtml ?><?= $label ?>
      </span>
      
    </div>

    <div class="card-body" style="padding: 1rem; padding-top: 0;">
      <div style="background:#fff; border-radius:25px; padding:10px; border: 1px solid rgba(0,0,0,0.03);">
        <div id="<?= $id ?>" class="histogram" style="height:320px; width:100%;"></div>
      </div>
    </div>
  </div>
</div>
<?php } ?>
