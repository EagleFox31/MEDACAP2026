<?php
session_start();

require_once __DIR__ . '/core/bootstrap.php';

$users = $databaseConcours->users;
$connections = $databaseConcours->connections;

if (isset($_GET['lang']) && in_array($_GET['lang'], ['en','fr'])) {
    $_SESSION['concours_lang'] = $_GET['lang'];
    setcookie('concours_lang', $_GET['lang'], time() + 31536000, '/');
}
$lang = $_SESSION['concours_lang'] ?? ($_COOKIE['concours_lang'] ?? 'fr');

if ($lang === 'en') {
    include_once "../views/language_EN.php";
} else {
    include_once "../views/language.php";
}

$error_msg = '';
$hasError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userName = trim($_POST["username"]);
    $password = trim($_POST["password"]); 

    $data = [
        "username" => $userName,
        "password" => sha1($_POST["password"]),
    ];

    $login = $users->findOne($data);

    if (empty($login) || (isset($login->active) && $login->active == false)) {
        $hasError = true;
    } else {
        $_SESSION["concours_username"] = $login["username"];
        $_SESSION["concours_profile"] = $login["profile"];
        $_SESSION["concours_lastName"] = $login["lastName"];
        $_SESSION["concours_firstName"] = $login["firstName"];
        $_SESSION["concours_email"] = $login["email"];
        $_SESSION["concours_id"] = (string)$login["_id"];
        
        $newToken = bin2hex(random_bytes(16));
        $_SESSION["concours_session_token"] = $newToken;

        $userConnected = $connections->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($_SESSION["concours_id"]),
                    "active" => true,
                ],
            ],
        ]);
        if ($userConnected) {
            $userConnected->status = "Online";
            $userConnected->start = date("d-m-Y H:i:s");
            $userConnected->session_token = $newToken;
            $connections->updateOne(
                ["_id" => new MongoDB\BSON\ObjectId($userConnected->_id)],
                ['$set' => $userConnected]
            );
        } else {
            $connection = [
                "user" => new MongoDB\BSON\ObjectId($_SESSION["concours_id"]),
                "status" => "Online",
                "start" => date("d-m-Y H:i:s"),
                "end" => "",
                "active" => true,
                "session_token" => $newToken
            ];
            $connections->insertOne($connection);
        }

        if (isset($_POST['lang']) && in_array($_POST['lang'], ['en', 'fr'])) {
            $_SESSION['concours_lang'] = $_POST['lang'];
        } elseif (!isset($_SESSION['concours_lang'])) {
            $_SESSION['concours_lang'] = 'fr';
        }
        setcookie('concours_lang', $_SESSION['concours_lang'], time() + 31536000, '/');
        header("Location: portal.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Connexion · Concours Suzuki — CFAO Academy</title>
  <link rel="icon" type="image/png" href="../public/images/logo-cfao.png" />
  <script>
    (function() {
      const navDir = sessionStorage.getItem('suzuki_nav_dir');
      if (navDir === 'back') {
        document.documentElement.classList.add('nav-back');
      }
    })();
  </script>
  <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>
  <div class="app-bg"></div>

  <div class="auth">
    <aside class="auth__brand">
      <div class="shapes" id="shapes"></div>
      <div class="speedlines"><span></span><span></span><span></span><span></span></div>
      <div class="checker checker--tl"></div>
      <div class="checker checker--br"></div>
    </aside>

    <main class="auth__form">
      <div class="auth__form-head">
        <div class="auth__brand-logos">
          <img src="assets/img/cfao-mobility.png" alt="CFAO Mobility" />
  
          <div class="hero-visual">
            <div class="rays"></div>
          <div class="halo"></div>
          <svg class="trophy" viewBox="0 0 120 120" aria-hidden="true">
            <defs>
              <linearGradient id="gold" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#FFEFB8"/>
                <stop offset="0.5" stop-color="#F6B73C"/>
                <stop offset="1" stop-color="#C77B1B"/>
              </linearGradient>
              <linearGradient id="goldDark" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#E8A02E"/>
                <stop offset="1" stop-color="#9A5B12"/>
              </linearGradient>
            </defs>
            <path d="M35 28 C15 26 15 52 38 53" fill="none" stroke="url(#goldDark)" stroke-width="6" stroke-linecap="round"/>
            <path d="M85 28 C105 26 105 52 82 53" fill="none" stroke="url(#goldDark)" stroke-width="6" stroke-linecap="round"/>
            <path d="M34 24 L86 24 C86 50 73 64 60 64 C47 64 34 50 34 24 Z" fill="url(#gold)"/>
            <ellipse cx="60" cy="24" rx="26" ry="5" fill="#FFF3CE"/>
            <rect x="55" y="64" width="10" height="9" fill="url(#goldDark)"/>
            <rect x="45" y="73" width="30" height="6" rx="2" fill="url(#gold)"/>
            <rect x="38" y="84" width="44" height="11" rx="3" fill="url(#goldDark)"/>
            <polygon points="60,29 62.65,36.36 70.46,36.6 64.28,41.39 66.47,48.9 60,44.5 53.53,48.9 55.72,41.39 49.54,36.6 57.35,36.36" fill="#de0039"/>
          </svg>
          <svg class="spark" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0l2.5 9.5L24 12l-9.5 2.5L12 24l-2.5-9.5L0 12l9.5-2.5z"/></svg>
          <svg class="spark" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0l2.5 9.5L24 12l-9.5 2.5L12 24l-2.5-9.5L0 12l9.5-2.5z"/></svg>
          <svg class="spark" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0l2.5 9.5L24 12l-9.5 2.5L12 24l-2.5-9.5L0 12l9.5-2.5z"/></svg>
          <svg class="spark" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0l2.5 9.5L24 12l-9.5 2.5L12 24l-2.5-9.5L0 12l9.5-2.5z"/></svg>
          </div>
  
          <img src="assets/img/suzuki.svg" alt="Suzuki" />
        </div>
      </div>

      <div class="auth__form-body">
        <div class="auth__intro" style="text-align: center;">
          <h2 class="auth__title" data-bi="login.welcome">
            <span class="bi-primary">Bienvenue au <span>Concours Suzuki</span></span>
            <span class="bi-secondary">Welcome to the <span>Suzuki Contest</span></span>
          </h2>
          <p class="auth__sub bi--inline" data-bi="login.challenge">
            <span class="bi-primary">Prêt à relever le défi ?</span>
            <span class="bi-secondary">Ready to take on the challenge?</span>
          </p>
        </div>

        <div class="form-error <?php echo $hasError ? 'show' : ''; ?>" id="form-error" role="alert">
          <svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span id="form-error-text" class="bi--inline" data-bi="login.error">Identifiant ou mot de passe incorrect.</span>
        </div>

        <form id="login-form" method="post" action="#" novalidate>
          <div class="field">
            <label class="field__label bi--inline" for="username" data-bi="login.id_label">Identifiant</label>
            <div class="field__control">
              <input class="input" id="username" name="username" type="text"
                     autocomplete="username" required
                     data-bi-ph="login.id_ph" placeholder="Votre identifiant technicien" />
            </div>
          </div>

          <div class="field">
            <label class="field__label bi--inline" for="password" data-bi="login.pwd_label">Mot de passe</label>
            <div class="field__control">
              <input class="input" id="password" name="password" type="password"
                     autocomplete="current-password" required
                     data-bi-ph="login.pwd_ph" placeholder="Votre mot de passe" />
              <button type="button" class="input-icon" id="toggle-pwd"
                      data-i18n-aria="login.show" aria-label="Afficher le mot de passe">
                <svg class="icon" id="eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="icon" id="eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-7-10-7a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>
              </button>
            </div>
          </div>

                    <input type="hidden" name="lang" id="lang-input" value="fr">
          
          <button type="submit" class="btn btn--primary btn--block btn--lg" id="submit-btn" style="margin-top: 0.5rem;">
            <span id="submit-label" class="bi--inline" data-bi="login.submit">
              <span class="bi-primary">Se connecter</span>
              <span class="bi-secondary">Log in</span>
            </span>
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </button>
        </form>

        <p class="login-help" data-bi="login.help">Un souci pour vous connecter ? Contactez votre formateur CFAO.</p>
      </div>

      <footer class="auth__foot">
        © <span id="year"></span> CFAO Academy — CFAO Mobility Cameroun
      </footer>
    </main>
  </div>

  <footer class="login-footer">
    © <span class="login-footer__year">2026</span> CFAO Academy — CFAO Mobility Cameroun
  </footer>

  <script src="assets/js/i18n.js"></script>
  <script src="assets/js/background.js"></script>
  <script src="assets/js/login.js"></script>
</body>
</html>




