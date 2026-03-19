<?php
// (../ = on remonte du dossier widgets/ vers components/)
require_once dirname(__DIR__) . '/helpers/ki-icon.php';


/**
 * Grand titre principal (fond blanc)
 */
function renderTitleMain(string $iconClasses, $paths, string $text): void
{ ?>
  <div class="row mb-5">
    <div class="col-12">
      <div class="card depth-effect" style="background-color:#fff;">
        <div class="card-body text-center py-4">
          <h1 class="card-title mb-0">
            <?= kiIcon($iconClasses, $paths) . $text ?>
          </h1>
        </div>
      </div>
    </div>
  </div>
<?php }

/**
 * Variante avec sous‑titre
 */
function renderTitleMainWithSubtitle(
    string $iconClasses,
    $paths,
    string $text,
    string $subtitle
): void { ?>
  <div class="row mb-5">
    <div class="col-12">
      <div class="card depth-effect" style="background-color:#fff;">
        <div class="card-body text-center py-4">
          <h1 class="card-title mb-0">
            <?= kiIcon($iconClasses, $paths) . $text ?>
          </h1>
          <h2 class="card-subtitle text-muted mt-2">
            <?= htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8'); ?>
          </h2>
        </div>
      </div>
    </div>
  </div>
<?php }
