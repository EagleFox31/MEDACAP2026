// define/helpers/smartScale.js
/* ------------------------------------------------------------------
 * SmartScale – calcule un coefficient d’UI responsive et l’expose
 *              dans la variable CSS  --ui-scale  (0.60 – 1.00).
 * ------------------------------------------------------------------ */
(function () {
  const DESIGN_WIDTH = 1400;   // largeur de référence (desktop)
  const MIN_SCALE    = 0.60;
  const MAX_SCALE    = 1.00;

  function updateScale () {
    const cssW         = window.innerWidth;               // viewport CSS
    const ratio        = window.devicePixelRatio || 1;    // densité
    const physicalW    = Math.min(screen.width, screen.height);
    const zoomFactor   = physicalW ? cssW / physicalW : 1;
    const logicalW     = cssW / zoomFactor;

    let scale = logicalW / DESIGN_WIDTH;
    scale     = Math.max(MIN_SCALE, Math.min(MAX_SCALE, scale));

    document.documentElement
            .style.setProperty('--ui-scale', scale.toFixed(3));

    window.dispatchEvent(
      new CustomEvent('uiScaleChange', { detail: { scale } })
    );
  }

  updateScale();                        // première exécution
  window.addEventListener('resize',            updateScale);
  window.addEventListener('orientationchange', updateScale);
})();
