<?php
require_once __DIR__ . '/../../core/medacap_config.php';
/**
 * SCRIPT DE RATTRAPAGE - Renvoi email import candidats
 * Usage : accéder à cette page via le navigateur WAMP
 * À SUPPRIMER après utilisation !
 */
session_start();

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
}

require_once "../../vendor/autoload.php";
include_once "../sendMail.php";

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

$client = new Client(medacapMongoUri());
$db     = $client->academy;
$users  = $db->users;

// Campagne concernée
$CAMPAIGN_ROLE = "Recrutement Techniciens Senior Chariots Elevateurs";
$FIXED_COUNTRY = "Cameroun";

// Lien portail
$link = medacapAppBaseUrl('Cameroun');

$log = [];
$sent = false;

// ──────────────────────────────────────────────
// ÉTAPE 1 : Récupérer les candidats créés aujourd'hui avec ce rôle
// ──────────────────────────────────────────────
$today = date('d-m-Y');

$cursor = $users->find([
    'profile' => 'Candidat',
    'active'  => true,
    'roles'   => $CAMPAIGN_ROLE,
    'created' => ['$regex' => '^' . $today], // créés aujourd'hui
]);

$candidates = iterator_to_array($cursor);
$log[] = count($candidates) . " candidat(s) trouvé(s) créés ce jour avec la campagne « {$CAMPAIGN_ROLE} ».";

// ──────────────────────────────────────────────
// ÉTAPE 2 : Récupérer tous les RH (pour la liste déroulante)
// ──────────────────────────────────────────────
$rhList = iterator_to_array($users->find(['active' => true, 'profile' => 'Ressource Humaine'], ['sort' => ['lastName' => 1]]));

// ──────────────────────────────────────────────
// ÉTAPE 3 : Envoi sur soumission du formulaire
// ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rh_id'])) {
    $rhId = $_POST['rh_id'];
    $rh   = $users->findOne(['_id' => new ObjectId($rhId)]);

    if (!$rh) {
        $log[] = "❌ RH introuvable.";
    } elseif (empty($rh['email'])) {
        $log[] = "❌ Ce RH n'a pas d'email en base.";
    } else {
        $rhEmail = (string)$rh['email'];
        $total = count($candidates);
        $rows = [];
        foreach ($candidates as $c) {
            $rows[] = [
                'lastName'  => (string)($c['lastName'] ?? ''),
                'firstName' => (string)($c['firstName'] ?? ''),
                'username'  => (string)($c['username'] ?? ''),
                'password'  => (string)($c['visiblePassword'] ?? '0000'),
            ];
        }
        $rhDisplayName = trim((string)($rh['firstName'] ?? ''));

        $result = @sendCandidateImportSummaryMail(
            $rhEmail,
            $rhDisplayName,
            $CAMPAIGN_ROLE,
            $total,
            $rows,
            $FIXED_COUNTRY
        );

        if ($result) {
            $log[] = "✅ Email envoyé avec succès à {$rhEmail} ({$total} candidats).";
            $sent = true;
        } else {
            $log[] = "⚠️ La fonction mail() a échoué. Vérifiez la configuration SMTP de WAMP (php.ini : SMTP, smtp_port).";
            $log[] = "→ Destinataire visé : {$rhEmail}";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rattrapage Email Import Candidats</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; padding: 40px 20px; color: #1e293b; }
        .box { max-width: 700px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 36px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        h1 { font-size: 1.2rem; font-weight: 800; color: #1e3a8a; text-transform: uppercase; margin-bottom: 6px; }
        p.sub { font-size: 0.85rem; color: #64748b; margin-bottom: 24px; }
        label { display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 8px; }
        select { width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.9rem; margin-bottom: 20px; }
        button { background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: #fff; padding: 12px 28px; border: none; border-radius: 10px; font-weight: 700; font-size: 0.85rem; cursor: pointer; text-transform: uppercase; }
        button:hover { opacity: .9; }
        .log { margin-top: 24px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; font-size: 0.85rem; }
        .log p { margin: 4px 0; }
        .ok { color: #065f46; }
        .warn { color: #92400e; }
        .candidates { margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.82rem; margin-top: 10px; }
        th { background: #1e3a8a; color: #fff; padding: 8px; text-align: left; }
        td { padding: 7px 8px; border-bottom: 1px solid #f1f5f9; }
        tr:nth-child(even) td { background: #f8fafc; }
        .badge-del { display:inline-block; background:#fee2e2; color:#991b1b; padding:3px 10px; border-radius:20px; font-size:0.75rem; font-weight:700; }
    </style>
</head>
<body>
<div class="box">
    <h1>📨 Rattrapage — Email Import Candidats</h1>
    <p class="sub">Ce script renvoie l'email récapitulatif au RH sélectionné pour les candidats importés <strong>aujourd'hui</strong>.</p>

    <?php if (!empty($candidates)): ?>
    <div class="candidates">
        <label><?= count($candidates) ?> candidat(s) trouvé(s) importé(s) aujourd'hui</label>
        <table>
            <thead><tr><th>Nom</th><th>Prénom</th><th>Identifiant</th><th>Mot de passe</th></tr></thead>
            <tbody>
            <?php foreach ($candidates as $c): ?>
                <tr>
                    <td><?= htmlspecialchars((string)($c['lastName'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string)($c['firstName'] ?? '')) ?></td>
                    <td><strong><?= htmlspecialchars((string)($c['username'] ?? '')) ?></strong></td>
                    <td><?= htmlspecialchars((string)($c['visiblePassword'] ?? '0000')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <form method="post" style="margin-top:28px;">
        <label>Choisir le RH destinataire</label>
        <select name="rh_id" required>
            <option value="">— Sélectionner un RH —</option>
            <?php foreach ($rhList as $r): ?>
                <option value="<?= (string)$r->_id ?>">
                    <?= strtoupper((string)($r['lastName'] ?? '')) ?> <?= ucfirst((string)($r['firstName'] ?? '')) ?>
                    (<?= htmlspecialchars((string)($r['email'] ?? 'pas d\'email')) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Envoyer l'email de rattrapage</button>
    </form>

    <?php else: ?>
        <div class="log">
            <p class="warn">⚠️ Aucun candidat trouvé avec la date d'aujourd'hui (<?= date('d-m-Y') ?>) et la campagne « <?= htmlspecialchars($CAMPAIGN_ROLE) ?> ».</p>
            <p>Les candidats ont peut-être été créés un autre jour, ou le champ <code>created</code> n'est pas au format attendu.</p>
        </div>
    <?php endif; ?>

    <?php if (!empty($log)): ?>
    <div class="log">
        <?php foreach ($log as $l): ?>
            <p class="<?= (strpos($l, '✅') === 0) ? 'ok' : 'warn' ?>"><?= htmlspecialchars($l) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($sent): ?>
    <div style="margin-top:20px; padding:16px; background:#d1fae5; border-radius:10px; border:1px solid #10b981;">
        <strong style="color:#065f46;">✅ Email envoyé ! Vous pouvez maintenant supprimer ce fichier.</strong>
    </div>
    <?php endif; ?>

    <p style="margin-top:28px; font-size:0.75rem; color:#94a3b8;">
        ⚠️ <strong>Supprimer ce fichier</strong> (<code>resend_import_mail.php</code>) après utilisation.
    </p>
</div>
</body>
</html>
