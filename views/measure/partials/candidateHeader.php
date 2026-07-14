<?php
/**
 * Navbar candidat — hiérarchie UX : marque → plateforme → section | langue → utilisateur.
 * Inclus depuis header.php quand profile === Candidat.
 */
$candLang = $lang ?? ($_SESSION['lang'] ?? 'FR');
$candPlatform = isset($cand_nav_platform)
    ? (string)$cand_nav_platform
    : 'Plateforme de Test des Candidats';
$candSection = isset($candidate_nav_title)
    ? (string)$candidate_nav_title
    : (isset($cand_nav_quiz) ? (string)$cand_nav_quiz : 'Tests théoriques');
$candFirst = (string)($_SESSION['firstName'] ?? '');
$candLast = (string)($_SESSION['lastName'] ?? '');
$candName = trim($candFirst . ' ' . $candLast);
if ($candName === '') {
    $candName = (string)($_SESSION['username'] ?? '');
}
$candInitials = '';
if ($candName !== '') {
    $parts = preg_split('/\s+/', $candName);
    foreach (array_slice($parts, 0, 2) as $part) {
        $candInitials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
}
if ($candInitials === '') {
    $candInitials = 'U';
}
$candLogoutLabel = isset($deconnexion) ? (string)$deconnexion : 'Se déconnecter';
$reqUri = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '', ENT_QUOTES, 'UTF-8');
$pageTitle = isset($title)
    ? htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8')
    : htmlspecialchars($candSection, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo strtolower($candLang) === 'en' ? 'en' : 'fr'; ?>">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo $pageTitle; ?> | CFAO Mobility Academy</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../public/images/logos/cfao_academy.ico" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
    <link rel="stylesheet" href="../../public/css/background-manager.css" />
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            min-height: 100%;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: #0f172a;
            background: #eef2f7;
        }
        body.candidate-portal {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .cand-topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.25rem;
            padding: 0.55rem 1.5rem;
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 2px 18px rgba(15, 23, 42, 0.06);
        }
        .cand-topbar-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: 0;
            flex: 1;
        }
        .cand-topbar-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            border-radius: 10px;
            padding: 7px 16px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            flex-shrink: 0;
            text-decoration: none;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04);
        }
        .cand-topbar-logo img {
            height: 52px;
            width: auto;
            display: block;
        }
        .cand-topbar-platform {
            font-size: 0.95rem;
            font-weight: 700;
            color: #0b1a2e;
            letter-spacing: -0.02em;
            white-space: nowrap;
            line-height: 1.2;
        }
        .cand-topbar-sep {
            width: 1px;
            height: 34px;
            background: rgba(15, 23, 42, 0.14);
            flex-shrink: 0;
        }
        .cand-topbar-section {
            font-size: 0.9rem;
            font-weight: 600;
            color: #334155;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
        }
        .cand-topbar-actions {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            flex-shrink: 0;
        }
        .cand-lang-form {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            margin: 0;
            height: 40px;
        }
        .cand-lang-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            height: 40px;
            min-width: 52px;
            padding: 0 0.65rem;
            border-radius: 10px;
            border: 1px solid rgba(15, 23, 42, 0.1);
            background: rgba(255, 255, 255, 0.8);
            font-size: 0.72rem;
            font-weight: 700;
            color: #334155;
            cursor: pointer;
            font-family: inherit;
            line-height: 1;
            margin: 0;
            box-sizing: border-box;
            transition: background 0.15s, border-color 0.15s;
        }
        .cand-lang-btn:hover {
            background: #fff;
            border-color: rgba(15, 23, 42, 0.18);
        }
        .cand-lang-btn.is-active {
            background: #0b3c6e;
            border-color: #0b3c6e;
            color: #fff;
        }
        .cand-lang-btn img { width: 16px; height: 16px; border-radius: 2px; }
        .cand-user-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            height: 40px;
            padding: 0 0.75rem 0 0.25rem;
            border-radius: 999px;
            border: 1px solid rgba(15, 23, 42, 0.1);
            background: rgba(255, 255, 255, 0.85);
            max-width: 240px;
            box-sizing: border-box;
        }
        .cand-user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0b3c6e 0%, #1273ea 100%);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 800;
            flex-shrink: 0;
            letter-spacing: 0.02em;
            line-height: 1;
        }
        .cand-user-avatar i { font-size: 0.85rem; }
        .cand-user-name {
            font-size: 0.8rem;
            font-weight: 600;
            color: #0b1a2e;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
            line-height: 1;
        }
        .cand-logout-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid rgba(215, 0, 6, 0.2);
            background: rgba(215, 0, 6, 0.08);
            color: #D70006;
            text-decoration: none;
            flex-shrink: 0;
            box-sizing: border-box;
            line-height: 1;
            transition: background 0.15s, color 0.15s;
        }
        .cand-logout-btn:hover {
            background: #D70006;
            color: #fff;
            border-color: #D70006;
        }
        .cand-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding-top: 82px;
            min-height: 100vh;
            width: 100%;
        }
        .cand-main .page-background-container {
            flex: 1;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            border-radius: 0 !important;
        }
        .cand-main .page-content-container {
            width: 100%;
            max-width: 100%;
            padding: 1.5rem 1.25rem 1.75rem;
            box-sizing: border-box;
        }
        @media (max-width: 992px) {
            .cand-topbar-platform { font-size: 0.82rem; }
            .cand-topbar-section { font-size: 0.8rem; }
            .cand-topbar-logo img { height: 44px; }
        }
        @media (max-width: 768px) {
            .cand-topbar { padding: 0.5rem 0.85rem; gap: 0.6rem; }
            .cand-topbar-brand { gap: 0.65rem; }
            .cand-topbar-platform,
            .cand-topbar-section,
            .cand-topbar-brand .cand-topbar-sep { display: none; }
            .cand-topbar-logo img { height: 40px; }
            .cand-user-name { display: none; }
            .cand-user-chip { padding: 0.2rem; border: none; background: transparent; }
        }
    </style>
</head>
<body class="candidate-portal">
<header class="cand-topbar" role="banner">
    <div class="cand-topbar-brand">
        <a href="./candidatePaperHome.php" class="cand-topbar-logo" title="CFAO Mobility Academy">
            <img src="../../public/images/logos/cfao_academy.jpg" alt="CFAO Mobility Academy" />
        </a>
        <span class="cand-topbar-platform"><?php echo htmlspecialchars($candPlatform, ENT_QUOTES, 'UTF-8'); ?></span>
        <div class="cand-topbar-sep" aria-hidden="true"></div>
        <span class="cand-topbar-section"><?php echo htmlspecialchars($candSection, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <div class="cand-topbar-actions">
        <form method="get" action="<?php echo $reqUri; ?>" class="cand-lang-form" aria-label="Langue">
            <button type="submit" name="lang" value="FR" class="cand-lang-btn<?php echo $candLang === 'FR' ? ' is-active' : ''; ?>" title="Français">
                <img src="../../public/assets/media/flags/france.svg" alt="" /> FR
            </button>
            <button type="submit" name="lang" value="EN" class="cand-lang-btn<?php echo $candLang === 'EN' ? ' is-active' : ''; ?>" title="English">
                <img src="../../public/assets/media/flags/united-states.svg" alt="" /> EN
            </button>
        </form>
        <?php if ($candName !== ''): ?>
            <div class="cand-user-chip" title="<?php echo htmlspecialchars($candName, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="cand-user-avatar" aria-hidden="true">
                    <?php if (preg_match('/^[A-Z]{1,2}$/u', $candInitials)): ?>
                        <?php echo htmlspecialchars($candInitials, ENT_QUOTES, 'UTF-8'); ?>
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                </span>
                <span class="cand-user-name"><?php echo htmlspecialchars($candName, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>
        <a href="./logout.php" class="cand-logout-btn" title="<?php echo htmlspecialchars($candLogoutLabel, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($candLogoutLabel, ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
        </a>
    </div>
</header>
<main class="cand-main" id="candMain">
