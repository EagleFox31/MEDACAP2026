<?php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page non trouvée | CFAO Mobility Academy</title>
    <link rel="icon" type="image/png" href="../public/images/logo-cfao.png" />
    <style>
        :root { --ink: #1f2a44; --muted: #5b6478; --accent: #c8102e; --bg: #f4f6fa; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; flex-direction: column; align-items: center;
               justify-content: center; padding: 24px 16px; background: var(--bg); color: var(--ink);
               font-family: "Segoe UI", Roboto, Arial, sans-serif; text-align: center; }
        img { max-width: 160px; height: auto; margin-bottom: 32px; }
        .code { font-size: clamp(64px, 18vw, 140px); font-weight: 800; line-height: 1; color: var(--accent); }
        h1 { margin: 12px 0 8px; font-size: clamp(20px, 4vw, 28px); letter-spacing: .04em; }
        p { margin: 0 auto 32px; max-width: 460px; color: var(--muted); line-height: 1.5; }
        a.btn { display: inline-block; padding: 12px 28px; border-radius: 26px; background: var(--accent);
                color: #fff; text-decoration: none; font-weight: 600; }
        a.btn:hover, a.btn:focus { background: #a00d25; }
        footer { margin-top: 48px; font-size: 13px; color: var(--muted); }
    </style>
</head>

<body>
    <img src="../public/images/logo-cfao.png" alt="CFAO Mobility Academy" />
    <div class="code">404</div>
    <h1>PAGE NON TROUVÉE</h1>
    <p>Oups ! La page que vous recherchez n'existe pas encore. Elle a peut-être été déplacée ou supprimée.</p>
    <a class="btn" href="./portal">RETOUR À LA PAGE MEDACAP</a>
    <footer>© <?php echo date('Y'); ?> CFAO Mobility Academy</footer>
</body>

</html>
