<?php
function renderLevelBadge(int $level, ?string $label = null, ?string $type = null): string
{

    // Définir les valeurs par défaut selon le niveau
    $defaults = [
        1 => ['label' => 'Junior', 'type' => 'light-success'],
        2 => ['label' => 'Senior', 'type' => 'light-danger'],
        3 => ['label' => 'Expert', 'type' => 'light-warning'],
    ];

    // Fallback pour les niveaux inconnus
    $fallback = ['label' => 'Inconnu', 'type' => 'light-secondary'];

    // Obtenir les infos par défaut ou fallback
    $info = $defaults[$level] ?? $fallback;

    // Remplacer si on fournit un label ou un type personnalisés
    $finalLabel = $label ?? $info['label'];
    $finalType  = $type  ?? $info['type'];

    // Construction du badge
    return '<span class="badge badge-' . htmlspecialchars($finalType) . ' fs-7 m-1">'
        . htmlspecialchars($finalLabel) . '</span>';
}
