<?php
session_start();
require_once "../vendor/autoload.php";
require_once "../views/sendMail.php";
require_once "sendCommercialMail.php";

use MongoDB\Client;
use MongoDB\BSON\ObjectId;

$allowedProfiles = ['Super Admin', 'Admin', 'Admin Commercial'];
$isLoggedIn = isset($_SESSION['id']) && in_array($_SESSION['profile'] ?? '', $allowedProfiles);

if (!$isLoggedIn) {
    header("Location: responses.php");
    exit();
}

// Réservé à SUPER_ADMIN uniquement
if (strtolower($_SESSION['email'] ?? '') !== strtolower(SUPER_ADMIN_EMAIL)) {
    http_response_code(403);
    exit('<p style="font-family:sans-serif;text-align:center;margin-top:4rem;color:#b91c1c;">Accès refusé.</p>');
}

$client = new Client("mongodb://localhost:27017");
$db     = $client->academy;

$message = '';

// ── Action : envoyer / renvoyer le mail ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['response_id'])) {
    try {
        $rid      = new ObjectId(trim($_POST['response_id']));
        $response = $db->commercial_responses->findOne(['_id' => $rid]);

        if ($response) {
            // Générer le token de téléchargement si absent
            $dlToken = isset($response['download_token']) ? (string)$response['download_token'] : null;
            if (!$dlToken) {
                $dlToken = bin2hex(random_bytes(24));
                $db->commercial_responses->updateOne(
                    ['_id' => $rid],
                    ['$set' => ['download_token' => $dlToken]]
                );
            }

            $qType      = (string)($response['questionnaire_type'] ?? '');
            $brandNotif = $qType === 'sinotruk' ? 'SINOTRUK' : 'KING LONG';
            $lastName   = (string)($response['lastName'] ?? '—');
            
            // Build dynamic URLs using medacapLinkForCountry
            $country = $response['country'] ?? '';
            $baseUrl = medacapLinkForCountry($country);
            if (substr($baseUrl, -1) !== '/') {
                $baseUrl .= '/';
            }
            $dlUrl = $baseUrl . 'Commercial/download-response.php?token=' . $dlToken;
            $platformLink = $baseUrl . 'Commercial/correction.php?id=' . $rid;

            $subject    = $lastName . ' — Questionnaire ' . $brandNotif . ' — ' . date('d/m/Y');

            // Chercher le score dans commercial_corrections
            $corr = $db->commercial_corrections->findOne(['response_id' => (string)$rid]);
            $scoreDetails = [];
            if ($corr && ($corr['status'] ?? '') === 'completed') {
                $scoreArr = (array)($corr['score'] ?? []);
                $correct  = (int)($scoreArr['correct']  ?? 0);
                $total    = (int)($scoreArr['total']    ?? 0);
                if ($total > 0) {
                    $scoreDetails[] = ['label' => 'Résultat', 'value' => $correct . ' / ' . $total . ' correct(s)', 'highlight' => true];
                }
            }

            $emailData = [
                'companyName'    => 'CFAO Academy',
                'companyTagline' => 'Questionnaires Commerciaux',
                'userName'       => 'Équipe ' . $brandNotif,
                'title'          => $lastName . ' — ' . $brandNotif,
                'message'        => 'Questionnaire complété. Utilisez les boutons ci-dessous pour corriger en ligne ou télécharger les réponses.',
                'ctaText'        => 'Corriger en ligne',
                'ctaLink'        => $platformLink,
                'ctaText2'       => 'Télécharger en Excel',
                'ctaLink2'       => $dlUrl,
                'details'        => array_merge([
                    ['label' => 'Nom',    'value' => $lastName,   'highlight' => true],
                    ['label' => 'Marque', 'value' => $brandNotif, 'highlight' => true],
                ], $scoreDetails),
                'supportEmail' => SUPER_ADMIN_EMAIL,
            ];

            // Admins commerciaux en CC
            $ccEmails = [];
            $admins = $db->commercial_admins->find(['active' => true]);
            foreach ($admins as $a) {
                $ae = isset($a['email']) ? strtolower(trim((string)$a['email'])) : '';
                if ($ae && strtolower($ae) !== strtolower(SUPER_ADMIN_EMAIL)) $ccEmails[] = $ae;
            }

            $sent = sendCommercialEmail(SUPER_ADMIN_EMAIL, $subject, $emailData, $ccEmails);

            if ($sent) {
                // Enregistrer la date d'envoi
                $db->commercial_responses->updateOne(
                    ['_id' => $rid],
                    ['$set' => ['mail_sent_at' => date('d-m-Y H:i:s'), 'mail_sent_count' => (int)($response['mail_sent_count'] ?? 0) + 1]]
                );
                $message = 'success:Mail envoyé avec succès.';
            } else {
                $message = 'error:Le serveur n\'a pas pu envoyer le mail. Le mail reste dans l\'état "à envoyer".';
            }
        }
    } catch (Exception $e) {
        $message = 'error:Erreur : ' . htmlspecialchars($e->getMessage());
    }
}

// ── Récupérer toutes les réponses ────────────────────────────────────────────
$rows = iterator_to_array($db->commercial_responses->find([], ['sort' => ['submitted_at' => -1]]));

// Bulk-fetch corrections status and scores
$responseIds = [];
foreach ($rows as $row) {
    $responseIds[] = (string)$row['_id'];
}
$corrMap = [];
if (!empty($responseIds)) {
    $corrCursor = $db->commercial_corrections->find(['response_id' => ['$in' => $responseIds]]);
    foreach ($corrCursor as $c) {
        $rid = $c['response_id'] ?? '';
        if ($rid !== '') {
            $scoreArr = (array)($c['score'] ?? []);
            $corrMap[$rid] = [
                'status'  => $c['status'] ?? 'pending',
                'correct' => (int)($scoreArr['correct']  ?? 0),
                'partiel' => (int)($scoreArr['partiel']  ?? 0),
                'total'   => (int)($scoreArr['total']    ?? 0),
            ];
        }
    }
}
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../public/images/logos/cfao_academy.ico">
    <title>Gestionnaire de mails — CFAO Academy</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body { font-family: 'Inter', sans-serif; min-height: 100vh; color: #0f172a; display: flex; flex-direction: column; }
        body::before {
            content: '';
            position: fixed; inset: 0; z-index: -1;
            background: url('../public/images/cfao_academy.jpg') center/cover no-repeat;
        }

        .topbar {
            background: rgba(255,255,255,0.55);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255,255,255,0.6);
            padding: .85rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 16px rgba(0,0,0,.08);
            flex-shrink: 0;
        }
        .topbar-left { display: flex; align-items: center; gap: 1rem; }
        .topbar-logo { background: rgba(255,255,255,0.8); border-radius: 6px; padding: 3px 8px; display: flex; align-items: center; }
        .topbar-logo img { height: 26px; width: auto; display: block; }
        .topbar-sep { width: 1px; height: 22px; background: rgba(0,0,0,.12); flex-shrink: 0; }
        .topbar-title { color: #0b1a2e; font-size: 1rem; font-weight: 700; }
        .topbar-sub   { color: rgba(0,0,0,0.4); font-size: 0.75rem; margin-top: 1px; }
        .topbar-right { display: flex; align-items: center; gap: 1rem; }
        .back-link {
            color: rgba(0,0,0,0.65);
            font-size: 0.8rem;
            text-decoration: none;
            display: flex; align-items: center; gap: 0.4rem;
            padding: .32rem .75rem;
            border-radius: 6px;
            border: 1px solid rgba(255,255,255,.15);
            transition: all .15s;
        }
        .back-link:hover { color: #0b1a2e; background: rgba(255,255,255,.85); border-color: rgba(0,0,0,.2); }

        .container { max-width: 1100px; margin: 2rem auto; padding: 0 1.5rem; flex: 1; width: 100%; }

        .alert {
            padding: 0.9rem 1.2rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
        .alert-error   { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }

        .card {
            background: rgba(255,255,255,.93);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0,0,0,.12);
            overflow: hidden;
        }
        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-header h2 { font-size: 1rem; font-weight: 700; }
        .badge-count {
            background: #D70006;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.2rem 0.55rem;
            border-radius: 20px;
        }

        .table-responsive {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 520px;
        }
        .table-responsive::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        .table-responsive::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
        }
        .table-responsive::-webkit-scrollbar-thumb {
            background: rgba(215, 0, 6, 0.2);
            border-radius: 4px;
        }
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: rgba(215, 0, 6, 0.4);
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #f8fafc !important;
            padding: 0.75rem 1rem;
            text-align: left;
            font-size: 0.72rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: inset 0 -1px 0 #e2e8f0;
        }
        tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .1s; }
        tbody tr:hover { background: #fafafa; }
        tbody td { padding: 0.85rem 1rem; font-size: 0.875rem; }

        .name-cell  { font-weight: 600; }
        .email-cell { color: #64748b; font-size: 0.8rem; }

        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-sino  { background: #fef2f2; color: #D70006; }
        .badge-king  { background: #eff6ff; color: #1d4ed8; }
        .badge-sent  { background: #f0fdf4; color: #166534; }
        .badge-unsent{ background: #fef9c3; color: #854d0e; }

        .btn-send {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 1rem;
            background: #D70006;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s;
            font-family: inherit;
        }
        .btn-send:hover { background: #b50005; }
        .btn-resend {
            background: #0f172a;
        }
        .btn-resend:hover { background: #1e293b; }

        .empty { text-align: center; padding: 3rem; color: #94a3b8; font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="topbar">
    <div class="topbar-left">
        <div class="topbar-logo"><img src="../public/images/logo-cfao.png" alt="CFAO"></div>
        <div class="topbar-sep"></div>
        <div>
            <div class="topbar-title">Gestionnaire de mails commerciaux</div>
            <div class="topbar-sub">CFAO Academy &mdash; <?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></div>
        </div>
    </div>
    <div class="topbar-right">
        <a href="responses.php" class="back-link">&#8592; Réponses</a>
    </div>
</div>

<div class="container">

    <?php if ($message):
        $type = strpos($message, 'success:') === 0 ? 'success' : 'error';
        $text = substr($message, strpos($message, ':') + 1);
    ?>
        <div class="alert alert-<?php echo $type; ?>"><?php echo htmlspecialchars($text); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h2>Réponses soumises</h2>
            <span class="badge-count"><?php echo count($rows); ?></span>
        </div>

        <?php if (empty($rows)): ?>
            <div class="empty">Aucune réponse enregistrée.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Marque</th>
                        <th>Soumis le</th>
                        <th>Résultat</th>
                        <th>Statut mail</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row):
                    $rid       = (string)$row['_id'];
                    $nom       = trim((string)($row['firstName'] ?? '') . ' ' . (string)($row['lastName'] ?? ''));
                    $email     = (string)($row['email'] ?? '—');
                    $brand     = (string)($row['brand'] ?? '—');
                    $date      = (string)($row['submitted_at'] ?? '—');
                    $sentAt    = isset($row['mail_sent_at']) ? (string)$row['mail_sent_at'] : null;
                    $sentCount = (int)($row['mail_sent_count'] ?? 0);
                    $isSino    = $brand === 'SINOTRUK';

                    $corrInfo  = isset($corrMap[$rid]) ? $corrMap[$rid] : ['status' => 'pending', 'correct' => 0, 'partiel' => 0, 'total' => 0];
                    $cStatus   = $corrInfo['status'];
                ?>
                    <tr>
                        <td>
                            <div class="name-cell"><?php echo htmlspecialchars($nom ?: '—'); ?></div>
                            <div class="email-cell"><?php echo htmlspecialchars($email); ?></div>
                        </td>
                        <td><span class="badge <?php echo $isSino ? 'badge-sino' : 'badge-king'; ?>"><?php echo htmlspecialchars($brand); ?></span></td>
                        <td><?php echo htmlspecialchars($date); ?></td>
                        <td>
                            <?php if ($cStatus === 'completed' && $corrInfo['total'] > 0): ?>
                                <strong style="color: #198754; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fas fa-check-circle" style="color:#198754;"></i><?php echo $corrInfo['correct']; ?> / <?php echo $corrInfo['total']; ?>
                                </strong>
                            <?php else: ?>
                                <span style="color:#888;font-size:0.8rem;">— En attente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($sentAt): ?>
                                <span class="badge badge-sent">Envoyé</span>
                                <div style="font-size:0.7rem;color:#94a3b8;margin-top:2px;"><?php echo htmlspecialchars($sentAt); ?> (<?php echo $sentCount; ?>x)</div>
                            <?php else: ?>
                                <span class="badge badge-unsent">Non envoyé</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="response_id" value="<?php echo htmlspecialchars($rid); ?>">
                                <button type="submit" class="btn-send <?php echo $sentAt ? 'btn-resend' : ''; ?>">
                                    <?php echo $sentAt ? '&#8635; Renvoyer' : '&#9993; Envoyer'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<footer style="background:rgba(255,255,255,0.55);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border-top:1px solid rgba(255,255,255,0.6);box-shadow:0 -2px 16px rgba(0,0,0,.06);padding:.8rem 2rem;text-align:center;color:rgba(0,0,0,.4);font-size:.72rem;font-family:'Inter',sans-serif;flex-shrink:0;margin-top:auto;">
    <?php echo date('Y'); ?>&copy; CFAO Mobility Academy &mdash; All rights reserved
</footer>
</body>
</html>
