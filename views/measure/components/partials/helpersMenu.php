<?php
//helpers.php
/* ========= Helpers ========= */
function groupLabel(array $items, string $lang): string
{
    $first = reset($items);

    // on utilise d’abord la langue courante
    if ($lang === 'EN' && !empty($first['group_EN'])) {
        return $first['group_EN'];
    }
    if (!empty($first['group'])) {
        return $first['group'];
    }

    // repli : la clé du tableau (qu’on reçoit dans foreach)
    return key($items);   // ou une valeur par défaut
}


function menuLabel(array $item, string $lang): string
{
    return ($lang === 'EN' && !empty($item['name_EN']))
           ? $item['name_EN']
           : $item['name'];
}

/**
 * Construit l’URL d’un QCM (ou renvoie l’URL brute si aucun paramètre
 * supplémentaire n’est requis).  
 * On se base sur la clé de la fonctionnalité et sur les paramètres GET
 * déjà présents dans l’URL courante.
 */
function buildQuizLink(array $item): string
{
    $url = $item['url'];
    $key = $item['key'] ?? '';

    /* Cas “QCM technicien” : on doit propager test / level / id */
    if (in_array($key, ['qcm_tache_pro', 'qcm_connaissances'], true)
        && isset($_GET['test'], $_GET['level'], $_GET['id'])) {

        $url .= '?test='  . urlencode($_GET['test'])
             .  '&level=' . urlencode($_GET['level'])
             .  '&id='    . urlencode($_GET['id']);
    }

    /* Cas “user_evaluation” (manager qui évalue un technicien) */
    if ($key === 'user_evaluation'
        && isset($_GET['test'], $_GET['level'], $_GET['id'], $_GET['user'])) {

        $url .= '?test='  . urlencode($_GET['test'])
             .  '&level=' . urlencode($_GET['level'])
             .  '&id='    . urlencode($_GET['id'])
             .  '&user='  . urlencode($_GET['user']);
    }

    return $url;
}

/**
 * Dit si l’on doit afficher le groupe “Évaluer vos collaborateurs”.
 * On reprend exactement la **longue** condition que tu avais,
 * mais emballée dans une fonction pour plus de lisibilité.
 */
function conditionPourAfficherEvalCollab(): bool
{
    $u = $_SERVER['REQUEST_URI'] ?? '';

    /* Pages où l’on NE veut PAS afficher le groupe -------------------- */
    $blacklistExacte = [    
        '/medacap/views/measure/testSavoir',
        '/medacap/views/measure/testSavoirFaire',
        '/medacap/views/measure/profile',
    ];

    /* Pages avec paramètres GET que l’on doit aussi bloquer ------------ */
    $patterns = [
        '#^/medacap/views/measure/profile\?id=#',
        '#^/medacap/views/measure/userQuizDeclaratif\?test=#',
        '#^/medacap/views/measure/userQuizFactuel\?test=#',
    ];

    /* Test exactes */
    if (in_array($u, $blacklistExacte, true)) {
        return false;
    }

    /* Test pattern (preg_match) */
    foreach ($patterns as $p) {
        if (preg_match($p, $u)) {
            return false;
        }
    }

    return true;   // si rien n’a matché → on affiche le groupe
}
function echoItem(array $item, string $lang, string $extraHref = ''): void
{
    $iconType = $item['icon_type'] ?? 'font_awesome';
    echo '<div class="menu-item"><a class="menu-link" href="'
         . htmlspecialchars($item['url'] . $extraHref) . '"><span class="menu-icon">';
    if ($iconType === 'ki_duotone') {
        echo '<i class="' . htmlspecialchars($item['icon']) . ' fs-2"><span class="path1"></span><span class="path2"></span></i>';
    } else {
        echo '<i class="' . htmlspecialchars($item['icon']) . ' fs-2"></i>';
    }
    echo '</span><span class="menu-title">'
         . htmlspecialchars(menuLabel($item, $lang))
         . '</span></a></div>';
}

?>