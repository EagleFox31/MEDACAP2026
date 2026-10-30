<?php
function renderLevelBadge(int $level, ?string $label = null, ?string $type = null): string
{
    // Définir les valeurs par défaut selon le niveau : Junior (Bronze), Senior (Argent), Expert (Or)
    $defaults = [
        1 => ['label' => 'Junior', 'type' => 'bronze badge-level-bronze'],
        2 => ['label' => 'Senior', 'type' => 'silver badge-level-silver'],
        3 => ['label' => 'Expert', 'type' => 'gold badge-level-gold'],
    ];

    // Fallback pour les niveaux inconnus
    $fallback = ['label' => 'Inconnu', 'type' => 'secondary badge-level-unknown'];

    // Obtenir les infos par défaut ou fallback
    $info = isset($defaults[$level]) ? $defaults[$level] : $fallback;

    // Remplacer si on fournit un label ou un type personnalisés
    $finalLabel = $label !== null ? $label : $info['label'];
    $finalType  = $type  !== null ? $type  : $info['type'];

    // Construction du badge
    return '<span class="badge badge-' . htmlspecialchars($finalType, ENT_QUOTES, 'UTF-8') . ' fs-7 m-1">'
        . htmlspecialchars($finalLabel, ENT_QUOTES, 'UTF-8') . '</span>';
}
