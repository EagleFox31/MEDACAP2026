<?php
//helpers.php
/* ========= Helpers ========= */
function groupLabel(array $items, string $lang, string $fallback = 'Autres'): string
{
    $first = reset($items) ?: [];
    if ($lang === 'EN' && !empty($first['group_EN'])) return (string)$first['group_EN'];
    if (!empty($first['group'])) return (string)$first['group'];
    return $fallback; // <= utilise $groupName si fourni à l’appel
}



function menuLabel(array $item, string $lang): string
{
    if (menuItemBasePage($item) === 'ListeDesQcm') {
        return ($lang === 'EN') ? 'Tests to Take' : 'Tests à passer';
    }
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

    /* Cas “candidats” pour Admin : pré-filtre par pays de la filiale */
    if ($key === 'candidats'
        && isset($_SESSION['profile'], $_SESSION['country'])
        && $_SESSION['profile'] === 'Admin') {
        $url .= '?country=' . urlencode($_SESSION['country']);
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

    return true;   // si rien n'a matché → on affiche le groupe
}

function menuItemBasePage(array $item): string
{
    return pathinfo(parse_url($item['url'] ?? '', PHP_URL_PATH) ?: '', PATHINFO_FILENAME);
}

/** Liens contextuels (pendant un QCM) — jamais dans la barre laterale. */
function menuIsSidebarExcludedKey($key): bool
{
    return in_array($key, array(
        'qcm_connaissances',
        'qcm_tache_pro',
        'connect_app',
        'user_evaluation',
    ), true);
}

function menuCurrentUserHasCollaborators($usersCollection, MongoDB\BSON\ObjectId $userId): bool
{
    $u = $usersCollection->findOne(array('_id' => $userId), array('projection' => array('users' => 1)));
    if (!$u) {
        return false;
    }
    $list = $u['users'] ?? array();
    if ($list instanceof MongoDB\Model\BSONArray) {
        $list = $list->getArrayCopy();
    }
    return count((array) $list) > 0;
}

/**
 * Mesure des competences : un seul lien ListeDesQcm, pas de quiz contextuels.
 */
function menuMesureSidebarItems(array $items): array
{
    $candidate = null;

    foreach ($items as $item) {
        $key = $item['key'] ?? '';
        if (menuIsSidebarExcludedKey($key)) {
            continue;
        }
        $base = menuItemBasePage($item);
        if ($base !== 'ListeDesQcm' && !in_array($key, array('list_qcm_connaissances', 'list_qcm_tache_pro'), true)) {
            continue;
        }
        if ($candidate === null || $key === 'list_qcm_connaissances') {
            $candidate = $item;
        }
        if ($key === 'list_qcm_connaissances') {
            break;
        }
    }

    if ($candidate === null) {
        return array();
    }

    return array($candidate);
}

function menuIsBilanTestsGroup(array $items): bool
{
    $first = reset($items) ?: array();
    $groupFR = $first['group'] ?? '';
    $groupEN = $first['group_EN'] ?? '';
    return strcasecmp($groupFR, 'Bilan des Tests') === 0
        || strcasecmp($groupEN, 'Assessment Status') === 0;
}

function echoMenuGroupHeader(array $items, string $lang): void
{
    echo '<div class="menu-content"><span class="fw-bold text-black text-uppercase fs-70">'
        . htmlspecialchars(groupLabel($items, $lang))
        . '</span></div>';
}

function echoMesureCompetencesGroup(array $items, string $lang, string $extraHref = ''): void
{
    $filtered = menuMesureSidebarItems($items);
    if (empty($filtered)) {
        return;
    }
    echoMenuGroupHeader($items, $lang);
    foreach ($filtered as $item) {
        echoItem($item, $lang, $extraHref);
    }
    echo '<div class="menu-item"><div class="menu-content"></div></div>';
}

function echoItem(array $item, string $lang, string $extraHref = ''): void
{
    $iconType    = $item['icon_type'] ?? 'font_awesome';
    // Sous-pages sans entrée de menu : on surligne l'entrée parente
    $parentPages = array(
        'bilanTestNotes'     => 'bilanTest',
        'detailTechnicien'   => 'bilanTest',
        'userEvaluation'     => 'userManagerList',
        'userQuizFactuel'    => 'ListeDesQcm',
        'userQuizDeclaratif' => 'ListeDesQcm',
    );
    $currentPage = pathinfo(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), PATHINFO_FILENAME);
    if (isset($parentPages[$currentPage])) {
        $currentPage = $parentPages[$currentPage];
    }
    $itemPage    = pathinfo(parse_url($item['url'], PHP_URL_PATH), PATHINFO_FILENAME);
    $isActive    = ($currentPage !== '' && $currentPage === $itemPage);

    $itemClass = $isActive ? 'menu-item here' : 'menu-item';
    $linkClass = $isActive ? 'menu-link active' : 'menu-link';

    echo '<div class="' . $itemClass . '"><a class="' . $linkClass . '" href="'
         . htmlspecialchars(buildQuizLink($item) . $extraHref) . '"><span class="menu-icon">';
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