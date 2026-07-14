<?php
require_once dirname(__DIR__) . '/helpers/ki-icon.php';


/**
 * Sous‑titre simple (carte glass‑effect)
 */
function renderTitleSection(
    string $iconClasses,
    $paths,
    string $text,
    string $padding = 'py-3',
    string $maxWidth = '1550px',
    string $htmlSuffix = ''
): void { ?>
  <div class="row mb-5 justify-content-center">
    <div class="col-12" style="max-width: <?= htmlspecialchars($maxWidth) ?>; margin: 0 auto;">
      <div class="card glass-effect depth-effect">
        <div class="card-body text-center <?= htmlspecialchars($padding, ENT_QUOTES, 'UTF-8'); ?>">
          <h1 class="text-contrast mb-0">
            <?= kiIcon($iconClasses, $paths) . $text . $htmlSuffix ?>
          </h1>
        </div>
      </div>
    </div>
  </div>
<?php }

/**
 * Sous‑titre contenant un lien
 */
function renderTitleSectionLink(
    string $iconClasses,
    $paths,
    string $text,
    string $url,
    string $padding = 'py-3',
    string $maxWidth = '1550px'
): void { ?>
  <div class="row mb-5 justify-content-center">
    <div class="col-12" style="max-width: <?= htmlspecialchars($maxWidth) ?>; margin: 0 auto;">
      <div class="card glass-effect depth-effect">
        <div class="card-body text-center <?= htmlspecialchars($padding, ENT_QUOTES, 'UTF-8'); ?>">
          <h1 class="text-contrast mb-0">
            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none">
              <?= kiIcon($iconClasses, $paths) . $text ?>
            </a>
          </h1>
        </div>
      </div>
    </div>
  </div>
<?php }
