<?php
/**
 * Construit le markup d’une icône ki‑duotone.
 *
 * @param string       $classes  classes CSS du tag <i>
 * @param int|int[]    $paths    nbre de paths (ex : 3) OU tableau (ex : [1,3])
 * @return string      HTML complet prêt à être inclus
 */
function kiIcon($classes, $paths = 0)
{
    // Si on reçoit déjà un <span class="path…"> on considère que c’est du HTML fini
    if (strpos($classes, '<span') !== false) {
        return $classes;
    }

    // Normalisation du paramètre $paths
    $spans = '';
    if (is_int($paths) && $paths > 0) {
        $paths = range(1, $paths);
    }
    if (is_array($paths)) {
        foreach ($paths as $p) {
            $spans .= '<span class="path' . (int)$p . '"></span>';
        }
    }

    return '<i class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '">' . $spans . '</i>';
}
