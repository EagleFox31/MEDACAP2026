<?php
session_start();
require_once __DIR__ . '/core/bootstrap.php';

if (!isset($_SESSION["concours_profile"])) {
    header("Location: index.php");
    exit();
}

// Check if user has already completed the exam
$connections = $databaseConcours->connections;
$userId = new MongoDB\BSON\ObjectId($_SESSION["concours_id"]);
$completedConnection = $connections->findOne(["user" => $userId, "status" => "Completed"]);

if ($completedConnection) {
    header("Location: congrat.php");
    exit();
}

$firstName = $_SESSION["concours_firstName"] ?? '';
$lastName = $_SESSION["concours_lastName"] ?? '';
$fullName = trim($firstName . ' ' . $lastName);
$initial = mb_substr($firstName, 0, 1) ?: 'T';
?>
<!DOCTYPE html>
<!--
  ============================================================================
  PAGE D'ACCUEIL / DÉMARRAGE — Concours Suzuki · CFAO Academy
  Affichée après connexion. Présente les consignes, les repères clés et
  LE CHOIX DE LANGUE (moment subtil, sans page supplémentaire) avant de lancer
  le questionnaire.

  POUR PHP :
   · Injectez le nom du technicien dans #user-name et #hello-name.
   · Injectez le nombre total de questions dans #total-questions (data-total).
   · Le bouton « Commencer » mène à quiz.html (ou quiz.php).
  ============================================================================
-->
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Accueil · Concours Suzuki — CFAO Academy</title>
  <link rel="icon" type="image/png" href="../public/images/logo-cfao.png" />
  <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body class="theme-light">
  <div class="app-bg"></div>
  <!-- Diaporama de fond discret (visuels CFAO x Suzuki) -->
  <div class="bg-slideshow" id="bg-slideshow" aria-hidden="true">
    <div class="bg-slide is-active" style="background-image:url('assets/img/bg/concours-1.jpg')"></div>
    <div class="bg-slide" style="background-image:url('assets/img/bg/concours-2.jpg')"></div>
    <div class="bg-slide" style="background-image:url('assets/img/bg/concours-3.jpg')"></div>
    <div class="bg-slide" style="background-image:url('assets/img/bg/concours-4.jpg')"></div>
    <div class="bg-slide" style="background-image:url('assets/img/bg/concours-5.jpg')"></div>
    <div class="bg-slide" style="background-image:url('assets/img/bg/concours-6.jpg')"></div>
    <div class="bg-slide" style="background-image:url('assets/img/bg/concours-7.jpg')"></div>
    <div class="bg-slide" style="background-image:url('assets/img/bg/concours-8.jpg')"></div>
    <div class="bg-slide" style="background-image:url('assets/img/bg/concours-9.jpg')"></div>
  </div>
  <div class="shapes" id="shapes"></div>

  <div class="page">
    <!-- En-tête -->
    <header class="topbar on-light">
      <div class="brand">
        <div class="brand__logos">
          <img class="brand__logo" src="assets/img/cfao-mobility.png" alt="CFAO Mobility" />
          <img class="brand__logo" src="assets/img/suzuki.svg" alt="Suzuki" />
        </div>
        <div class="brand__text">
          <div class="brand__title" data-i18n="app.title">Concours Suzuki</div>
          <div class="brand__sub" data-i18n="app.sub">CFAO Academy</div>
        </div>
      </div>

      <div style="display:flex;align-items:center;gap:1rem;">
        <div class="lang-switch" role="group" aria-label="Language">
          <button type="button" data-lang="fr">FR</button>
          <button type="button" data-lang="en">EN</button>
        </div>
        <div class="user-chip">
          <div class="user-chip__avatar" id="user-avatar"><?php echo htmlspecialchars($initial); ?></div>
          <div>
            <div class="user-chip__name" id="user-name"><?php echo htmlspecialchars($fullName); ?></div>
            <div class="user-chip__role">CFAO Mobility</div>
          </div>
        </div>
        <a class="btn-logout" href="logout.php">
          <svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          <span data-i18n="common.logout">Déconnexion</span>
        </a>
      </div>
    </header>

    <!-- Carte de démarrage -->
    <main class="welcome">
      <section class="glass-card welcome-card">
        <p class="welcome-hello">
          <span class="welcome-hello__kicker" data-i18n="home.hello">Bienvenue</span>
          <span class="welcome-hello__name" id="hello-name"><?php echo htmlspecialchars($fullName); ?></span>
          <span class="welcome-hello__wave">👋</span>
        </p>
        <h1 class="welcome-title" data-i18n="home.title">Prêt pour le concours&nbsp;<span>Suzuki</span>&nbsp;?</h1>
        <p class="welcome-lead" data-i18n="home.lead">Ce concours évalue vos compétences techniques. Prenez votre temps, lisez bien chaque question et répondez avec calme. Bonne chance&nbsp;!</p>

        <!-- 3 repères clairs -->
        <div class="info-row">
          <div class="info-pill">
            <div class="info-pill__icon">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </div>
            <div>
              <div class="info-pill__value"><span id="total-questions" data-total="100">100</span></div>
              <div class="info-pill__label" data-i18n="home.info_questions">questions</div>
            </div>
          </div>
          <div class="info-pill">
            <div class="info-pill__icon">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12l2.5 2.5L16 9"/></svg>
            </div>
            <div>
              <div class="info-pill__value">QCM</div>
              <div class="info-pill__label" data-i18n="home.info_choice">réponses au choix</div>
            </div>
          </div>
          <div class="info-pill">
            <div class="info-pill__icon">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            </div>
            <div>
              <div class="info-pill__value" data-i18n="home.info_lang_v">FR / EN</div>
              <div class="info-pill__label" data-i18n="home.info_lang">bilingue</div>
            </div>
          </div>
        </div>

        <!-- Règlement -->
        <div class="guidelines">
          <h3 data-i18n="home.guidelines_title">Avant de commencer</h3>
          <div class="rules-grid">
            <div class="rule-card">
              <div class="rule-card__icon">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3l7.07 16.97 2.51-7.39 7.39-2.51L3 3z"/><path d="M13 13l6 6"/></svg>
              </div>
              <div class="rule-card__content">
                <h4 data-i18n="home.r1_t">Répondez</h4>
                <p data-i18n="home.g1">Répondez à chaque question en touchant la réponse de votre choix.</p>
              </div>
            </div>
            <div class="rule-card">
              <div class="rule-card__icon">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
              </div>
              <div class="rule-card__content">
                <h4 data-i18n="home.r2_t">Modifiez librement</h4>
                <p data-i18n="home.g2">Vous pouvez revenir en arrière pour modifier une réponse.</p>
              </div>
            </div>
            <div class="rule-card">
              <div class="rule-card__icon">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
              </div>
              <div class="rule-card__content">
                <h4 data-i18n="home.r3_t">Suivez l’avancement</h4>
                <p data-i18n="home.g3">Une barre en haut vous indique votre progression.</p>
              </div>
            </div>
            <div class="rule-card">
              <div class="rule-card__icon">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              </div>
              <div class="rule-card__content">
                <h4 data-i18n="home.r4_t">Validez à la fin</h4>
                <p data-i18n="home.g4">À la fin, validez pour envoyer vos réponses.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Démarrer -->
        <div class="welcome-cta">
          <button type="button" class="btn btn--primary btn--block btn--lg" id="start-btn">
            <span data-i18n="home.start">Commencer le concours</span>
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </button>
        </div>
      </section>
    </main>
  </div>

  <script src="assets/js/i18n.js"></script>
  <script src="assets/js/background.js"></script>
  <script src="assets/js/accueil.js"></script>
</body>
</html>

