<?php
/** Shell minimal Define — sans sidebar, header MEDACAP ni footer (?embed=1). */
$pageTitle = $GLOBALS['pageTitle'] ?? 'Plan de formation';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link href="/MEDACAP/public/assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
    <link href="/MEDACAP/public/assets/css/style.bundle.css" rel="stylesheet" type="text/css" />
    <link href="/MEDACAP/public/assets/css/ki-duotone-icons.css" rel="stylesheet" type="text/css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        html, body {
            margin: 0;
            padding: 0;
        }
        body.define-embed {
            min-height: 100vh;
            background: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.1)), url('/MEDACAP/public/images/welcome_tech.png') no-repeat center center fixed !important;
            background-size: cover !important;
        }
        body.define-embed #kt_content,
        body.define-embed .wrapper {
            background: transparent !important;
        }
        body.define-embed .container-fluid {
            max-width: 1280px;
        }
    </style>
</head>
<body class="define-embed">
