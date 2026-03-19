<?php
session_start();
include_once "language.php";

require_once __DIR__ . "/../vendor/autoload.php";

// Create connection
$conn = new MongoDB\Client("mongodb://localhost:27017");

// Connecting in database
$academy = $conn->academy;

// Connecting in collections
$users = $academy->users;
$connections = $academy->connections;

// Priorité : ?lang=  →  session  →  cookie  →  fr
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en','fr'])) {
    $_SESSION['lang'] = $_GET['lang'];
    setcookie('lang', $_GET['lang'], time() + 31536000, '/');
}

$lang = $_SESSION['lang'] ?? ($_COOKIE['lang'] ?? 'fr');

if ($lang === 'en') {
    include_once "language_en.php";
} else {
    include_once "language.php";
}

$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userName = trim($_POST["username"]);
    $password = trim($_POST["password"]); // No sha1, assuming plain text in DB
//    $selectedProfile = isset($_POST["selected_profile"]) ? $_POST["selected_profile"] : null;

    $data = [
        "username" => $userName,
        "password" => sha1($_POST["password"]),
    ];

    $login = $users->findOne($data);

    if (empty($login)) {
        $error_msg = $error_username_pass;
    } elseif ($login->active == false) {
        $error_msg = $error_authentication;
    } else {
        if (!empty($selectedProfile)) {
            $profileData = [
                "username" => $userName,
                "password" => $password,
                "firstName" => $login["firstName"],
                "lastName" => $login["lastName"],
                "profile" => $selectedProfile,
                "active" => true
            ];

            $profileLogin = $users->findOne($profileData);
            if ($profileLogin) {
                $login = $profileLogin;
            }
        }
        $_SESSION["username"] = $login["username"];
        $_SESSION["profile"] = $login["profile"];
        $_SESSION["lastName"] = $login["lastName"];
        $_SESSION["firstName"] = $login["firstName"];
        $_SESSION["email"] = $login["email"];
        $_SESSION["test"] = $login["test"];
        $_SESSION["id"] = $login["_id"];
        $_SESSION["subsidiary"] = $login["subsidiary"];
        $_SESSION["agency"] = $login["agency"];
        $_SESSION["department"] = $login["department"];
        $_SESSION["country"] = $login["country"];

        $userConnected = $connections->findOne([
            '$and' => [
                [
                    "user" => new MongoDB\BSON\ObjectId($_SESSION["id"]),
                    "active" => true,
                ],
            ],
        ]);
        if ($userConnected) {
            $userConnected->status = "Online";
            $userConnected->start = date("d-m-Y H:i:s");
            $connections->updateOne(
                ["_id" => new MongoDB\BSON\ObjectId($userConnected->_id)],
                ['$set' => $userConnected]
            );
        } else {
            $connection = [
                "user" => new MongoDB\BSON\ObjectId($_SESSION["id"]),
                "status" => "Online",
                "start" => date("d-m-Y H:i:s"),
                "end" => "",
                "active" => true
            ];

            $connections->insertOne($connection);
        }

        if (!isset($_SESSION['lang'])) {
            $_SESSION['lang'] = 'fr';
        }
        setcookie('lang', $_SESSION['lang'], time() + 31536000, '/');
        header("Location: views/portal");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CFAO Academy - <?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, rgba(36, 35, 92, 1), rgba(230, 34, 68, 1));
            --secondary-gradient: linear-gradient(135deg, rgba(229, 85, 79, 1), rgba(54, 60, 90, 1));
            --button-gradient: linear-gradient(135deg, rgba(230, 34, 68, 1), rgba(229, 85, 79, 1));
            --button-alt-gradient: linear-gradient(135deg, rgba(54, 60, 90, 1), rgba(36, 35, 92, 1));
            --button-shadow: 0 10px 20px -8px rgba(230, 34, 68, 0.45);
            --button-alt-shadow: 0 10px 20px -8px rgba(54, 60, 90, 0.4);
            --color-0: rgb(36, 35, 92);
            --color-1: rgb(230, 34, 68);
            --color-2: rgb(229, 85, 79);
            --color-3: rgb(54, 60, 90);
            --bg-overlay: linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
            --panel-width: 32rem;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transparency-bg: rgba(255, 255, 255, 0.2);
            --transparency-border: rgba(255, 255, 255, 0.18);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-overlay);
            color: white; overflow-x: hidden; min-height: 100vh;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 110%;
            height: 110%;
            background: url('public/images/backgrounds/home.png') center/cover no-repeat fixed;
            filter: blur(8px);
            z-index: -10;
            transform: translate(-5%, -5%);
        }

        @keyframes parallaxFloat1 {
            0% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(60px, -80px) rotate(45deg); }
            50% { transform: translate(-40px, 120px) rotate(-30deg); }
            75% { transform: translate(80px, -60px) rotate(60deg); }
            100% { transform: translate(0, 0) rotate(0deg); }
        }

        @keyframes parallaxFloat2 {
            0% { transform: translate(0, 0) scale(1) rotate(0deg); }
            33% { transform: translate(100px, -70px) scale(1.1) rotate(120deg); }
            66% { transform: translate(-80px, 90px) scale(0.9) rotate(-90deg); }
            100% { transform: translate(0, 0) scale(1) rotate(0deg); }
        }

        @keyframes parallaxFloat3 {
            0% { transform: translate(0, 0) rotate(0deg); }
            20% { transform: translate(-60px, -100px) rotate(-60deg); }
            40% { transform: translate(90px, 40px) rotate(90deg); }
            60% { transform: translate(-30px, 140px) rotate(-120deg); }
            80% { transform: translate(70px, -90px) rotate(150deg); }
            100% { transform: translate(0, 0) rotate(0deg); }
        }

        .parallax-shapes { position: fixed; inset: 0; pointer-events: none; z-index: -1; }
        .parallax-shape {
            position: absolute; animation: var(--anim-name) var(--duration) ease-in-out infinite; animation-delay: var(--delay); opacity: var(--opacity); filter: var(--filter);
        }

        header {
            position: sticky; top: 0; z-index: 50; padding: 0.75rem 1rem;
            backdrop-filter: blur(10px);
            background: var(--transparency-bg);
            border-bottom: 1px solid var(--transparency-border);
        }
        nav { max-width: 1400px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; }
        .logo-section { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; transition: var(--transition); }
        .logo-section:hover { transform: scale(1.05); }
        .logo { width: 3.5rem; height: 3.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .logo img { width: 100%; height: 100%; object-fit: contain; }
        .brand-name { font-size: 1.25rem; font-weight: 700; color: white; }

        .language-toggle {
            display: flex; gap: 0;
            background: var(--transparency-bg);
            border-radius: 999px; overflow: hidden;
        }
        .lang-btn {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            border: none;
            background: transparent;
            color: rgba(255,255,255,0.8);
            cursor: pointer;
            transition: var(--transition);
        }
        .lang-btn.active, .lang-btn:hover { background: var(--transparency-bg); color: white; }

        .main-layout {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 5rem);
            padding: 2rem 1rem;
        }
        .right-panel {
            width: 100%;
            max-width: var(--panel-width);
        }

        @media (max-width: 768px) { .main-layout { padding: 1rem; } }

        .login-card {
            padding: 3.5rem;
            min-height: 550px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.7);
            box-shadow: 0 15px 30px -10px rgba(36, 35, 92, 0.35);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-card .logo-section { justify-content: center; margin-bottom: 1rem; }
        .login-card .logo { width: 5rem; height: 5rem; border-radius: 0.75rem; }
        .login-card .brand-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
        }
        .greeting { color: white; font-weight: 500; text-align: center; margin-bottom: 2rem; opacity: 0.9; }
        .login-card form { display: flex; flex-direction: column; gap: 2rem; margin-top: 2rem; }
        .form-group { position: relative; margin-bottom: 1rem; }
        .login-card input {
            appearance: none;
            width: 100%;
            padding: 0.75rem 3rem 0.75rem 1rem;
            border: none;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 0.5rem;
            color: #374151;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        .login-card input::placeholder { color: rgba(107, 114, 128, 0.8); }
        .login-card input:focus { outline: none; box-shadow: 0 0 0 2px rgba(230, 34, 68, 0.2); border-color: rgba(230, 34, 68, 0.3); }
        .login-card label {
            position: absolute;
            left: -10000px;
            top: auto;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }
        .error {
            padding: 0.75rem;
            border-radius: 0.5rem;
            background: rgba(230, 34, 68, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(230, 34, 68, 0.3);
            margin-bottom: 1rem;
        }
        .error p { font-size: 0.875rem; font-weight: 500; text-align: center; color: rgb(230, 34, 68); margin: 0; }
        .submit-btn {
            padding: 1rem;
            font-size: 1rem;
            font-weight: bold;
            background: var(--button-gradient);
            border: none;
            border-radius: 9999px;
            color: white;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            margin-top: 1rem;
            box-shadow: var(--button-shadow);
        }
        .submit-btn:hover:not(:disabled) { transform: scale(1.05); }
        .submit-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .password-viewer {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: rgba(0, 0, 0, 0.7);
            z-index: 10;
            font-size: 1rem;
        }

        footer {
            text-align: center; padding: 1rem;
            background: var(--transparency-bg);
            backdrop-filter: blur(10px);
            color: rgba(255,255,255,0.7);
            font-size: 0.875rem;
            border-top: 1px solid var(--transparency-border);
        }
    </style>
</head>
<body>
<div class="parallax-shapes" id="parallax-shapes"></div>

<header>
    <nav>
        <div class="logo-section" onclick="location.reload()">
            <div class="logo"><img src="public/images/logos/cfao_academy.jpg" alt="CFAO Academy"></div>
            <span class="brand-name"><?php echo htmlspecialchars(mb_strtoupper($title)); ?></span>
        </div>
        <div class="language-toggle">
            <button class="lang-btn <?php echo ($lang === 'en') ? 'active' : ''; ?>" onclick="window.location.href='?lang=en'">EN</button>
            <button class="lang-btn <?php echo ($lang === 'fr') ? 'active' : ''; ?>" onclick="window.location.href='?lang=fr'">FR</button>
        </div>
    </nav>
</header>

<main class="main-layout">
    <aside class="right-panel">
        <div class="login-card">
            <div class="logo-section" onclick="location.reload()">
                <div class="logo"><img src="public/images/logos/cfao_academy.jpg" alt="CFAO Academy"></div>
                <span class="brand-name"><?php echo strtoupper(htmlspecialchars($title)); ?></span>
            </div>
            <p class="greeting"><?php echo htmlspecialchars($greeting); ?></p>
            <?php if ($error_msg): ?>
                <div class="error">
                    <p><?php echo htmlspecialchars($error_msg); ?></p>
                </div>
            <?php endif; ?>
            <form method="POST" id="loginForm">
                <div class="form-group">
                    <input id="username" name="username" type="text" autocomplete="username" required placeholder="<?php echo htmlspecialchars($usernamePlaceholder); ?>">
                </div>
                <div class="form-group">
                    <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="<?php echo htmlspecialchars($passwordPlaceholder); ?>">
                    <span class="password-viewer" onclick="togglePasswordVisibility()" title="<?php echo htmlspecialchars($show_password); ?>">
                        <i class="bi bi-eye"></i>
                    </span>
                </div>
                <button type="submit" name="login" class="submit-btn" id="submitBtn"><?php echo htmlspecialchars($acceder); ?></button>
            </form>
        </div>
    </aside>
</main>

<footer><?php echo htmlspecialchars($footer); ?></footer>

<script>
    const shapesContainer = document.getElementById('parallax-shapes');
    const animations = ['parallaxFloat1', 'parallaxFloat2', 'parallaxFloat3'];
    const colors = [
        {r: 36, g: 35, b: 92}, {r: 230, g: 34, b: 68},
        {r: 229, g: 85, b: 79}, {r: 54, g: 60, b: 90}
    ];

    for (let i = 0; i < 30; i++) {
        const size = 20 + Math.random() * 80;
        const animName = animations[Math.floor(Math.random() * animations.length)];
        const duration = (15 + Math.random() * 20) + 's';
        const opacity = 0.12 + Math.random() * 0.25;
        const blur = Math.random() > 0.4 ? Math.random() * 2 + 'px' : '0px';
        const left = Math.random() * 100 + '%';
        const top = Math.random() * 100 + '%';
        const baseColor = colors[i % colors.length];
        const nextColor = colors[(i + 1) % colors.length];
        const bgGradient = `linear-gradient(135deg, rgba(${baseColor.r},${baseColor.g},${baseColor.b},0.65), rgba(${nextColor.r},${nextColor.g},${nextColor.b},0.65))`;
        const filter = `blur(${blur}) drop-shadow(0 0 15px rgba(${baseColor.r},${baseColor.g},${baseColor.b},0.25))`;
        const borderRadius = i % 2 === 0 ? '50%' : '10%';
        const delay = (i * 0.4) + 's';

        const shape = document.createElement('div');
        shape.className = 'parallax-shape';
        shape.style.width = size + 'px';
        shape.style.height = size + 'px';
        shape.style.left = left;
        shape.style.top = top;
        shape.style.background = bgGradient;
        shape.style.borderRadius = borderRadius;
        shape.style.animation = `${animName} ${duration} ease-in-out infinite`;
        shape.style.animationDelay = delay;
        shape.style.opacity = opacity;
        shape.style.filter = filter;
        shape.style.setProperty('--anim-name', animName);
        shape.style.setProperty('--duration', duration);
        shape.style.setProperty('--delay', delay);
        shape.style.setProperty('--opacity', opacity);
        shape.style.setProperty('--filter', filter);
        shape.style.pointerEvents = 'none';

        shapesContainer.appendChild(shape);
    }

    function togglePasswordVisibility() {
        const passwordInput = document.getElementById("password");
        const eyeIcon = document.querySelector(".password-viewer i");
        const viewerSpan = document.querySelector(".password-viewer");
        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            eyeIcon.classList.remove("bi-eye");
            eyeIcon.classList.add("bi-eye-slash");
            viewerSpan.title = "<?php echo htmlspecialchars($hide_password ?? 'Masquer le mot de passe'); ?>";
        } else {
            passwordInput.type = "password";
            eyeIcon.classList.remove("bi-eye-slash");
            eyeIcon.classList.add("bi-eye");
            viewerSpan.title = "<?php echo htmlspecialchars($show_password ?? 'Afficher le mot de passe'); ?>";
        }
    }

    document.getElementById('loginForm').addEventListener('submit', () => {
        const btn = document.getElementById('submitBtn');
        btn.textContent = '<?php echo htmlspecialchars($loadingButton); ?>';
        btn.disabled = true;
    });
</script>
</body>
</html>