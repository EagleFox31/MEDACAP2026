<?php
session_start();
require_once "../vendor/autoload.php";
require_once "../views/sendMail.php";

use MongoDB\Client;
use MongoDB\BSON\ObjectId;

$allowedProfiles = ['Super Admin', 'Admin', 'Admin Commercial'];
$isLoggedIn = isset($_SESSION['id']) && in_array($_SESSION['profile'] ?? '', $allowedProfiles);

if (!$isLoggedIn) {
    header("Location: responses.php");
    exit();
}

if (strtolower($_SESSION['email'] ?? '') !== strtolower(SUPER_ADMIN_EMAIL)) {
    http_response_code(403);
    exit('<p style="font-family:sans-serif;text-align:center;margin-top:4rem;color:#b91c1c;">Accès refusé.</p>');
}

$client  = new Client("mongodb://localhost:27017");
$db      = $client->academy;
$admColl = $db->commercial_admins;

$message = '';

// ── Actions POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $firstName = trim($_POST['firstName'] ?? '');
        $lastName  = trim($_POST['lastName']  ?? '');
        $email     = strtolower(trim($_POST['email'] ?? ''));

        if ($firstName && $lastName && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $existing = $admColl->findOne(['email' => $email]);
            if (!$existing) {
                $admColl->insertOne([
                    'firstName'  => $firstName,
                    'lastName'   => $lastName,
                    'email'      => $email,
                    'active'     => true,
                    'created_at' => date('d-m-Y H:i:s'),
                ]);
                $message = 'success:Correcteur ajouté avec succès.';
            } else {
                $message = 'error:Cet email est déjà enregistré.';
            }
        } else {
            $message = 'error:Veuillez remplir tous les champs correctement.';
        }
    }

    if ($action === 'toggle') {
        $id = trim($_POST['id'] ?? '');
        try {
            $oid  = new ObjectId($id);
            $corr = $admColl->findOne(['_id' => $oid]);
            if ($corr) {
                $newStatus = !((bool)($corr['active'] ?? false));
                $admColl->updateOne(['_id' => $oid], ['$set' => ['active' => $newStatus]]);
                $message = 'success:Statut mis à jour.';
            }
        } catch (Exception $e) {
            $message = 'error:ID invalide.';
        }
    }

    if ($action === 'delete') {
        $id = trim($_POST['id'] ?? '');
        try {
            $oid = new ObjectId($id);
            $admColl->deleteOne(['_id' => $oid]);
            $message = 'success:Correcteur supprimé.';
        } catch (Exception $e) {
            $message = 'error:Erreur lors de la suppression.';
        }
    }
}

$correctors = iterator_to_array($admColl->find([], ['sort' => ['lastName' => 1, 'firstName' => 1]]));
$activeCount   = 0;
$inactiveCount = 0;
foreach ($correctors as $c) {
    if ((bool)($c['active'] ?? false)) $activeCount++;
    else $inactiveCount++;
}
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../public/images/logos/cfao_academy.ico">
    <title>Gestion des correcteurs — CFAO Academy</title>
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

        /* ── Topbar ── */
        .topbar {
            background: rgba(255,255,255,0.55);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255,255,255,0.6);
            padding: .85rem 2rem;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 2px 16px rgba(0,0,0,.08); flex-shrink: 0;
        }
        .topbar-left { display: flex; align-items: center; gap: 1rem; }
        .topbar-logo { background: rgba(255,255,255,0.8); border-radius: 6px; padding: 3px 8px; display: flex; align-items: center; }
        .topbar-logo img { height: 26px; width: auto; display: block; }
        .topbar-sep { width: 1px; height: 22px; background: rgba(0,0,0,.12); flex-shrink: 0; }
        .topbar-title { color: #0b1a2e; font-size: .88rem; font-weight: 700; }
        .topbar-sub { color: rgba(0,0,0,.4); font-size: .68rem; margin-top: 1px; }
        .topbar-right { display: flex; align-items: center; gap: .6rem; }
        .topbar-btn {
            color: rgba(0,0,0,.65); font-size: .75rem; font-weight: 500;
            text-decoration: none; display: inline-flex; align-items: center; gap: .35rem;
            padding: .32rem .75rem; border-radius: 6px;
            border: 1px solid rgba(0,0,0,.12); transition: all .15s;
            background: rgba(255,255,255,0.5); cursor: pointer; font-family: inherit;
        }
        .topbar-btn:hover { background: rgba(255,255,255,.85); color: #0b1a2e; border-color: rgba(0,0,0,.2); }

        /* ── Layout ── */
        .container { max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem; flex: 1; width: 100%; }

        /* ── Alerts ── */
        .alert { padding: .9rem 1.2rem; border-radius: 10px; margin-bottom: 1.5rem; font-size: .875rem; font-weight: 500; }
        .alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
        .alert-error   { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }

        /* ── Title card ── */
        .title-card {
            background: rgba(255,255,255,.93); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border-radius: 16px; padding: 1.4rem 1.8rem; box-shadow: 0 4px 20px rgba(0,0,0,.12);
            margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        }
        .title-card-h1 { font-size: 1.4rem; font-weight: 800; color: #0b3c6e; margin-bottom: .2rem; }
        .title-card-p { color: #94a3b8; font-size: .82rem; }
        .title-card-icon {
            width: 50px; height: 50px; background: rgba(11,60,110,.08); border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; color: #0b3c6e; flex-shrink: 0;
        }

        /* ── Stats row ── */
        .stats-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card {
            flex: 1; background: rgba(255,255,255,.9); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
            border-radius: 14px; padding: 1rem 1.4rem; box-shadow: 0 2px 12px rgba(0,0,0,.07);
            display: flex; align-items: center; gap: .9rem;
        }
        .stat-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .stat-icon.green  { background: rgba(25,135,84,.1);   color: #198754; }
        .stat-icon.orange { background: rgba(255,140,0,.1);   color: #b06a00; }
        .stat-icon.blue   { background: rgba(11,60,110,.08);  color: #0b3c6e; }
        .stat-val { font-size: 1.6rem; font-weight: 800; color: #222; line-height: 1; }
        .stat-lbl { font-size: .75rem; color: #888; margin-top: .1rem; }

        /* ── Add form card ── */
        .add-card {
            background: rgba(255,255,255,.93); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border-radius: 14px; box-shadow: 0 4px 20px rgba(0,0,0,.1); margin-bottom: 1.5rem; overflow: hidden;
        }
        .add-card-header {
            padding: 1rem 1.4rem; border-bottom: 1px solid #f1f5f9;
            display: flex; align-items: center; gap: .6rem;
            font-size: .875rem; font-weight: 700; color: #0b3c6e;
        }
        .add-card-body { padding: 1.25rem 1.4rem; }
        .form-row { display: flex; gap: .75rem; flex-wrap: wrap; }
        .form-group { flex: 1 1 160px; display: flex; flex-direction: column; gap: .35rem; }
        .form-group label { font-size: .75rem; font-weight: 700; color: #555; text-transform: uppercase; letter-spacing: .04em; }
        .form-control {
            border: 1.5px solid #dde3ec; border-radius: 8px; padding: .55rem .8rem;
            font-size: .875rem; color: #0f172a; background: #f8fafc; font-family: inherit;
            transition: border-color .15s, box-shadow .15s;
        }
        .form-control:focus { outline: none; border-color: #0b3c6e; background: #fff; box-shadow: 0 0 0 3px rgba(11,60,110,.08); }
        .btn-add {
            background: #0b3c6e; color: #fff; border: none; border-radius: 8px;
            padding: .55rem 1.4rem; font-size: .875rem; font-weight: 700; cursor: pointer;
            font-family: inherit; display: inline-flex; align-items: center; gap: .45rem;
            align-self: flex-end; transition: background .15s; white-space: nowrap;
        }
        .btn-add:hover { background: #083060; }

        /* ── List card ── */
        .list-card {
            background: rgba(255,255,255,.93); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border-radius: 14px; box-shadow: 0 4px 20px rgba(0,0,0,.1); overflow: hidden;
        }
        .list-card-header {
            padding: 1rem 1.4rem; border-bottom: 1px solid #f1f5f9;
            display: flex; align-items: center; justify-content: space-between;
            font-size: .875rem; font-weight: 700; color: #0b3c6e;
        }
        .list-card-header-left { display: flex; align-items: center; gap: .6rem; }

        /* ── Corrector rows ── */
        .corr-row {
            display: flex; align-items: center; gap: 1rem; padding: 1rem 1.4rem;
            border-bottom: 1px solid #f1f5f9; transition: background .1s;
        }
        .corr-row:last-child { border-bottom: none; }
        .corr-row:hover { background: #fafbfd; }

        .corr-avatar {
            width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: .95rem; font-weight: 800; color: #fff;
        }
        .avatar-active   { background: linear-gradient(135deg, #0b3c6e, #2563eb); }
        .avatar-inactive { background: #c4c4c4; }

        .corr-info { flex: 1; min-width: 0; }
        .corr-name  { font-size: .9rem; font-weight: 700; color: #0f172a; }
        .corr-email { font-size: .78rem; color: #94a3b8; margin-top: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .corr-date  { font-size: .7rem; color: #c4c4c4; margin-top: 2px; }

        .badge-active   { display: inline-block; padding: .2rem .65rem; border-radius: 20px; font-size: .68rem; font-weight: 700; background: rgba(25,135,84,.1); color: #198754; }
        .badge-inactive { display: inline-block; padding: .2rem .65rem; border-radius: 20px; font-size: .68rem; font-weight: 700; background: rgba(148,163,184,.12); color: #94a3b8; }

        .corr-actions { display: flex; gap: .5rem; flex-shrink: 0; }
        .btn-toggle {
            padding: .38rem .85rem; border-radius: 7px; font-size: .78rem; font-weight: 700;
            cursor: pointer; font-family: inherit; border: 1.5px solid; transition: all .15s;
            display: inline-flex; align-items: center; gap: .35rem; white-space: nowrap;
        }
        .btn-activate   { border-color: #198754; color: #198754; background: rgba(25,135,84,.06); }
        .btn-activate:hover { background: #198754; color: #fff; }
        .btn-deactivate { border-color: #dde3ec; color: #94a3b8; background: #f8fafc; }
        .btn-deactivate:hover { border-color: #dc3545; color: #dc3545; background: rgba(220,53,69,.06); }
        .btn-delete {
            padding: .38rem .6rem; border-radius: 7px; font-size: .78rem; cursor: pointer;
            font-family: inherit; border: 1.5px solid #dde3ec; color: #ccc; background: #f8fafc;
            transition: all .15s; display: inline-flex; align-items: center;
        }
        .btn-delete:hover { border-color: #dc3545; color: #dc3545; background: rgba(220,53,69,.06); }

        .empty-state { text-align: center; padding: 3rem; color: #bbb; }
        .empty-state i { font-size: 2.5rem; margin-bottom: .75rem; }

        /* ── Delete modal ── */
        #deleteModal {
            display: none; position: fixed; inset: 0; z-index: 1000;
            align-items: center; justify-content: center;
            background: rgba(15,23,42,.55); backdrop-filter: blur(4px);
        }
        .modal-box {
            background: #fff; border-radius: 18px; padding: 2rem 2rem 1.75rem;
            max-width: 380px; width: 90%; box-shadow: 0 24px 64px rgba(0,0,0,.3); text-align: center;
        }
        .modal-icon { font-size: 2rem; color: #dc3545; margin-bottom: .6rem; }
        .modal-title { font-size: 1rem; font-weight: 800; color: #0f172a; margin-bottom: .4rem; }
        .modal-sub { font-size: .875rem; color: #64748b; line-height: 1.55; margin-bottom: 1.5rem; }
        .modal-actions { display: flex; gap: .75rem; justify-content: center; }
        .btn-cancel {
            padding: .6rem 1.4rem; border-radius: 8px; border: 1.5px solid #dde3ec;
            background: #fff; color: #555; font-size: .875rem; font-weight: 600;
            cursor: pointer; font-family: inherit;
        }
        .btn-confirm-delete {
            padding: .6rem 1.4rem; border-radius: 8px; border: none;
            background: #dc3545; color: #fff; font-size: .875rem; font-weight: 700;
            cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: .4rem;
        }
        .btn-confirm-delete:hover { background: #b91c1c; }

        /* ── Footer ── */
        .page-footer {
            background: rgba(255,255,255,0.55);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border-top: 1px solid rgba(255,255,255,0.6);
            box-shadow: 0 -2px 16px rgba(0,0,0,.06);
            padding: .8rem 2rem; text-align: center; color: rgba(0,0,0,.4);
            font-size: .72rem; flex-shrink: 0; margin-top: auto;
        }
    </style>
</head>
<body>

<div class="topbar">
    <div class="topbar-left">
        <div class="topbar-logo"><img src="../public/images/logo-cfao.png" alt="CFAO"></div>
        <div class="topbar-sep"></div>
        <div>
            <div class="topbar-title">Gestion des correcteurs</div>
            <div class="topbar-sub">CFAO Academy &mdash; <?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></div>
        </div>
    </div>
    <div class="topbar-right">
        <a href="responses.php" class="topbar-btn">&#8592; Réponses</a>
    </div>
</div>

<div class="container">

    <?php if ($message):
        $msgType = strpos($message, 'success:') === 0 ? 'success' : 'error';
        $msgText = substr($message, strpos($message, ':') + 1);
    ?>
    <div class="alert alert-<?php echo $msgType; ?>">
        <i class="fas fa-<?php echo $msgType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>" style="margin-right:.5rem;"></i>
        <?php echo htmlspecialchars($msgText); ?>
    </div>
    <?php endif; ?>

    <!-- Title card -->
    <div class="title-card">
        <div>
            <div class="title-card-h1"><i class="fas fa-users-cog" style="color:#0b3c6e;margin-right:.5rem;"></i>Correcteurs</div>
            <div class="title-card-p">Gérez les personnes autorisées à corriger les questionnaires commerciaux.</div>
        </div>
        <div class="title-card-icon"><i class="fas fa-shield-alt"></i></div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-users"></i></div>
            <div>
                <div class="stat-val"><?php echo count($correctors); ?></div>
                <div class="stat-lbl">Total</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="stat-val"><?php echo $activeCount; ?></div>
                <div class="stat-lbl">Actifs</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-pause-circle"></i></div>
            <div>
                <div class="stat-val"><?php echo $inactiveCount; ?></div>
                <div class="stat-lbl">Désactivés</div>
            </div>
        </div>
    </div>

    <!-- Add form -->
    <div class="add-card">
        <div class="add-card-header">
            <i class="fas fa-user-plus" style="color:#0b3c6e;"></i> Ajouter un correcteur
        </div>
        <div class="add-card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName">Prénom</label>
                        <input type="text" id="firstName" name="firstName" class="form-control" placeholder="Jean" required>
                    </div>
                    <div class="form-group">
                        <label for="lastName">Nom</label>
                        <input type="text" id="lastName" name="lastName" class="form-control" placeholder="Dupont" required>
                    </div>
                    <div class="form-group" style="flex:2 1 220px;">
                        <label for="email">Adresse email</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="jean.dupont@cfao.com" required>
                    </div>
                    <div class="form-group" style="flex:0 0 auto;">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn-add"><i class="fas fa-plus"></i> Ajouter</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Correctors list -->
    <div class="list-card">
        <div class="list-card-header">
            <div class="list-card-header-left">
                <i class="fas fa-list"></i> Liste des correcteurs
            </div>
            <span style="font-size:.75rem;color:#94a3b8;font-weight:500;"><?php echo count($correctors); ?> correcteur(s)</span>
        </div>

        <?php if (empty($correctors)): ?>
        <div class="empty-state">
            <div><i class="fas fa-user-slash"></i></div>
            <div style="font-size:.9rem;font-weight:600;">Aucun correcteur enregistré</div>
            <div style="font-size:.8rem;margin-top:.3rem;">Utilisez le formulaire ci-dessus pour en ajouter un.</div>
        </div>
        <?php else: ?>
        <?php foreach ($correctors as $c):
            $cId       = (string)$c['_id'];
            $cFirst    = (string)($c['firstName'] ?? '');
            $cLast     = (string)($c['lastName']  ?? '');
            $cEmail    = (string)($c['email']      ?? '');
            $cActive   = (bool)($c['active']       ?? false);
            $cCreated  = (string)($c['created_at'] ?? '');
            $cInitials = strtoupper(mb_substr($cFirst, 0, 1, 'UTF-8') . mb_substr($cLast, 0, 1, 'UTF-8'));
        ?>
        <div class="corr-row">
            <div class="corr-avatar <?php echo $cActive ? 'avatar-active' : 'avatar-inactive'; ?>">
                <?php echo htmlspecialchars($cInitials ?: '?', ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div class="corr-info">
                <div class="corr-name">
                    <?php echo htmlspecialchars(trim($cFirst . ' ' . $cLast) ?: '—', ENT_QUOTES, 'UTF-8'); ?>
                    <span class="badge-<?php echo $cActive ? 'active' : 'inactive'; ?>" style="margin-left:.5rem;">
                        <?php echo $cActive ? 'Actif' : 'Désactivé'; ?>
                    </span>
                </div>
                <div class="corr-email"><?php echo htmlspecialchars($cEmail, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if ($cCreated): ?>
                <div class="corr-date">Ajouté le <?php echo htmlspecialchars($cCreated, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>
            <div class="corr-actions">
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($cId, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="btn-toggle <?php echo $cActive ? 'btn-deactivate' : 'btn-activate'; ?>">
                        <?php if ($cActive): ?>
                            <i class="fas fa-pause"></i> Désactiver
                        <?php else: ?>
                            <i class="fas fa-play"></i> Activer
                        <?php endif; ?>
                    </button>
                </form>
                <button type="button" class="btn-delete"
                        onclick="openDeleteModal('<?php echo htmlspecialchars($cId, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars(addslashes(trim($cFirst . ' ' . $cLast)), ENT_QUOTES, 'UTF-8'); ?>')"
                        title="Supprimer">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- Delete confirmation modal -->
<div id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon"><i class="fas fa-trash-alt"></i></div>
        <div class="modal-title">Supprimer ce correcteur ?</div>
        <div class="modal-sub" id="deleteModalSub">Cette action est irréversible.</div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeDeleteModal()">Annuler</button>
            <form method="POST" id="deleteForm" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteModalId" value="">
                <button type="submit" class="btn-confirm-delete"><i class="fas fa-trash-alt"></i> Supprimer</button>
            </form>
        </div>
    </div>
</div>

<footer class="page-footer">
    <?php echo date('Y'); ?>&copy; CFAO Mobility Academy &mdash; All rights reserved
</footer>

<script>
function openDeleteModal(id, name) {
    document.getElementById('deleteModalId').value = id;
    document.getElementById('deleteModalSub').textContent =
        'Vous allez supprimer ' + (name || 'ce correcteur') + '. Cette action est irréversible.';
    document.getElementById('deleteModal').style.display = 'flex';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
</script>
</body>
</html>
