<?php
session_start();
require_once "../vendor/autoload.php";
require_once "../views/sendMail.php";

use MongoDB\Client;

$allowedProfiles = ['Super Admin', 'Admin', 'Admin Commercial'];
$isLoggedIn = isset($_SESSION['id']) && in_array($_SESSION['profile'] ?? '', $allowedProfiles);

if (!$isLoggedIn) {
    header("Location: responses.php");
    exit();
}

$client = new Client("mongodb://localhost:27017");
$db     = $client->academy;

// Seuil "en ligne" : last_seen dans les 3 dernières minutes (heartbeat toutes les 60s)
$now       = new DateTime();
$threshold = 180;

$liveCursor = $db->commercial_live->find([], ['sort' => ['started_at' => -1]]);
$onlineUsers = [];

foreach ($liveCursor as $row) {
    $lastSeenStr = (string)($row['last_seen'] ?? '');
    $lastSeenDt  = $lastSeenStr ? DateTime::createFromFormat('d-m-Y H:i:s', $lastSeenStr) : false;

    $secsAgo  = $lastSeenDt ? ($now->getTimestamp() - $lastSeenDt->getTimestamp()) : 99999;
    $submitted = (string)($row['status'] ?? '') === 'submitted';
    $isOnline  = !$submitted && $secsAgo <= $threshold;

    // Durée (depuis started_at)
    $startStr = (string)($row['started_at'] ?? '');
    $startDt  = $startStr ? DateTime::createFromFormat('d-m-Y H:i:s', $startStr) : false;
    $duration = '—';
    $durationSecs = 0;
    if ($startDt) {
        $ref  = $lastSeenDt ? $lastSeenDt : $now;
        $diff = $ref->diff($startDt);
        $durationSecs = ($diff->days * 86400) + ($diff->h * 3600) + ($diff->i * 60) + $diff->s;
        if ($diff->days > 0) {
            $duration = $diff->days . 'j ' . $diff->h . 'h ' . $diff->i . 'min';
        } elseif ($diff->h > 0) {
            $duration = $diff->h . 'h ' . $diff->i . 'min';
        } else {
            $duration = $diff->i . 'min ' . $diff->s . 's';
        }
    }

    $onlineUsers[] = [
        'firstName'    => (string)($row['firstName'] ?? ''),
        'lastName'     => (string)($row['lastName']  ?? ''),
        'email'        => (string)($row['email']     ?? ''),
        'brand'        => (string)($row['brand']     ?? '—'),
        'isOnline'     => $isOnline,
        'submitted'    => $submitted,
        'startedAt'    => $startStr,
        'lastSeen'     => $lastSeenStr,
        'duration'     => $duration,
        'durationSecs' => $durationSecs,
    ];
}

// En ligne en premier, puis soumis, puis inactifs — tri par durée desc dans chaque groupe
usort($onlineUsers, function($a, $b) {
    $rankA = $a['isOnline'] ? 0 : ($a['submitted'] ? 1 : 2);
    $rankB = $b['isOnline'] ? 0 : ($b['submitted'] ? 1 : 2);
    if ($rankA !== $rankB) return $rankA - $rankB;
    return $b['durationSecs'] - $a['durationSecs'];
});

$totalOnline = count(array_filter($onlineUsers, function($u) { return $u['isOnline']; }));

$totalOnline = count($onlineUsers);
$_isSuperAdmin = strtolower($_SESSION['email'] ?? '') === strtolower(SUPER_ADMIN_EMAIL);
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="60">
    <link rel="shortcut icon" type="image/x-icon" href="../public/images/logos/cfao_academy.ico">
    <title>Utilisateurs en ligne — CFAO Academy</title>
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
        .container { max-width: 960px; margin: 0 auto; padding: 2rem 1.5rem 5rem; flex: 1; width: 100%; }

        /* ── Title card ── */
        .title-card {
            background: rgba(255,255,255,.93);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border-radius: 16px; padding: 1.2rem 1.6rem;
            box-shadow: 0 4px 20px rgba(0,0,0,.1); margin-bottom: 1.5rem;
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        }
        .title-card-left h1 { font-size: 1.25rem; font-weight: 800; color: #0b3c6e; }
        .title-card-left p  { font-size: .8rem; color: #94a3b8; margin-top: .2rem; }
        .badge-count {
            background: #0b3c6e; color: #fff;
            font-size: .8rem; font-weight: 700;
            padding: .35rem .85rem; border-radius: 20px;
            display: flex; align-items: center; gap: .4rem;
        }
        .dot-online {
            width: 8px; height: 8px; border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 0 2px rgba(34,197,94,.3);
            display: inline-block;
            animation: pulse-dot 1.5s infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { box-shadow: 0 0 0 2px rgba(34,197,94,.3); }
            50%       { box-shadow: 0 0 0 5px rgba(34,197,94,.1); }
        }

        /* ── Table card ── */
        .table-card {
            background: rgba(255,255,255,.93);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 220px);
        }
        .table-scroll {
            overflow-y: auto;
            flex: 1;
        }
        .table-scroll::-webkit-scrollbar { width: 6px; }
        .table-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
        .table-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .online-table { width: 100%; border-collapse: collapse; }
        .online-table thead tr { background: #f8fafc; }
        .online-table thead { position: sticky; top: 0; z-index: 2; }
        .online-table th {
            padding: .75rem 1rem; text-align: left;
            font-size: .7rem; font-weight: 700;
            color: #64748b; text-transform: uppercase; letter-spacing: .4px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .online-table th.sortable { cursor: pointer; user-select: none; white-space: nowrap; }
        .online-table th.sortable:hover { color: #0b3c6e; }
        .online-table th.sortable::after { content: ' ⇅'; opacity: .35; font-size: .65rem; }
        .online-table th.sortable.asc::after  { content: ' ↑'; opacity: 1; color: #0b3c6e; }
        .online-table th.sortable.desc::after { content: ' ↓'; opacity: 1; color: #0b3c6e; }
        .online-table td {
            padding: .85rem 1rem;
            font-size: .82rem; color: #1e293b;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .online-table tbody tr:last-child td { border-bottom: none; }
        .online-table tbody tr:hover td { background: #f8fafc; }

        .user-name { font-weight: 700; color: #0b3c6e; }
        .user-sub  { font-size: .72rem; color: #94a3b8; margin-top: .1rem; }
        .badge-online    { display:inline-flex;align-items:center;background:#f0fdf4;color:#16a34a;border:1px solid #86efac;border-radius:12px;padding:.2rem .6rem;font-size:.72rem;font-weight:700; }
        .badge-submitted { display:inline-flex;align-items:center;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:12px;padding:.2rem .6rem;font-size:.72rem;font-weight:700; }
        .badge-offline   { display:inline-flex;align-items:center;background:#f8fafc;color:#94a3b8;border:1px solid #e2e8f0;border-radius:12px;padding:.2rem .6rem;font-size:.72rem;font-weight:600; }

        .badge-profile {
            display: inline-block; padding: .2rem .6rem; border-radius: 12px;
            font-size: .7rem; font-weight: 600;
            background: #eff6ff; color: #1d4ed8;
        }

        .duration-val { font-weight: 700; color: #0f172a; }
        .duration-long { color: #dc3545; }

        .login-time { font-size: .75rem; color: #64748b; }

        .empty-state {
            text-align: center; padding: 3rem 1rem;
            color: #94a3b8;
        }
        .empty-state i { font-size: 2.5rem; margin-bottom: .75rem; display: block; color: #cbd5e1; }

        .refresh-note {
            text-align: right; font-size: .7rem; color: rgba(0,0,0,.35);
            margin-top: .6rem; padding-right: .25rem;
        }

        /* ── Footer ── */
        .page-footer {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 10;
            background: rgba(255,255,255,0.55);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border-top: 1px solid rgba(255,255,255,0.6);
            box-shadow: 0 -2px 16px rgba(0,0,0,.06);
            padding: .6rem 2rem; text-align: center; color: rgba(0,0,0,.4); font-size: .72rem;
        }
    </style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
    <div class="topbar-left">
        <div class="topbar-logo">
            <img src="../public/images/logos/cfao_academy.jpg" alt="CFAO Academy">
        </div>
        <div class="topbar-sep"></div>
        <div>
            <div class="topbar-title">Utilisateurs en ligne</div>
            <div class="topbar-sub">CFAO Mobility Academy</div>
        </div>
    </div>
    <div class="topbar-right">
        <a href="responses.php" class="topbar-btn">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>
</div>

<div class="container">
    <!-- Title -->
    <div class="title-card">
        <div class="title-card-left">
            <h1><span class="dot-online" style="margin-right:.5rem;"></span>Utilisateurs en ligne</h1>
            <p>Actualisé automatiquement toutes les 60 secondes · <?php echo date('H:i:s'); ?></p>
        </div>
        <div class="badge-count">
            <span class="dot-online"></span>
            <?php echo $totalOnline; ?> en ligne
        </div>
    </div>

    <!-- Table -->
    <div class="table-card">
        <?php if (empty($onlineUsers)): ?>
        <div class="empty-state">
            <i class="fas fa-user-slash"></i>
            Aucun correcteur commercial enregistré.
        </div>
        <?php else: ?>
        <div class="table-scroll">
        <table class="online-table" id="onlineTable">
            <thead>
                <tr>
                    <th class="sortable" data-col="0">Candidat</th>
                    <th class="sortable" data-col="1">Email</th>
                    <th class="sortable" data-col="2">Marque</th>
                    <th class="sortable" data-col="3">Statut</th>
                    <th class="sortable" data-col="4">Démarré à</th>
                    <th class="sortable" data-col="5">Temps passé</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($onlineUsers as $u): ?>
                <?php $durationClass = $u['durationSecs'] > 3600 ? 'duration-val duration-long' : 'duration-val'; ?>
                <tr>
                    <td>
                        <div class="user-name"><?php echo htmlspecialchars(trim($u['firstName'] . ' ' . $u['lastName']), ENT_QUOTES, 'UTF-8'); ?></div>
                    </td>
                    <td class="login-time"><?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <?php if ($u['brand'] === 'SINOTRUK'): ?>
                        <img src="../public/images/sinotruk.png" alt="SINOTRUK" style="height:18px;">
                        <?php else: ?>
                        <img src="../public/images/king-long-seeklogo.png" alt="KING LONG" style="height:18px;">
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['isOnline']): ?>
                        <span class="badge-online"><span class="dot-online" style="margin-right:.35rem;"></span>En cours</span>
                        <?php elseif ($u['submitted']): ?>
                        <span class="badge-submitted"><i class="fas fa-check-circle" style="margin-right:.35rem;"></i>Soumis</span>
                        <?php else: ?>
                        <span class="badge-offline"><i class="fas fa-circle" style="font-size:.45rem;margin-right:.35rem;color:#94a3b8;"></i>Inactif</span>
                        <?php endif; ?>
                    </td>
                    <td class="login-time"><?php echo htmlspecialchars($u['startedAt'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><span class="<?php echo $durationClass; ?>"><?php echo htmlspecialchars($u['duration'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
    <div class="refresh-note"><i class="fas fa-sync-alt" style="margin-right:.3rem;"></i>Actualisation auto dans 60s</div>
</div>

<footer class="page-footer">
    <?php echo date('Y'); ?> &copy; CFAO Mobility Academy &mdash; All rights reserved
</footer>

<script>
(function() {
    var table = document.getElementById('onlineTable');
    if (!table) return;
    var tbody = table.querySelector('tbody');
    var headers = table.querySelectorAll('th.sortable');
    var currentCol = 4, currentDir = -1; // par défaut : durée décroissante

    function getCellVal(row, col) {
        var cell = row.querySelectorAll('td')[col];
        return cell ? cell.innerText.trim() : '';
    }

    function parseVal(str, col) {
        // Col 4 : durée — convertir en secondes
        if (col === 4) {
            var s = 0;
            var j = str.match(/(\d+)j/);   if (j)  s += parseInt(j[1])  * 86400;
            var h = str.match(/(\d+)h/);   if (h)  s += parseInt(h[1])  * 3600;
            var m = str.match(/(\d+)min/); if (m)  s += parseInt(m[1])  * 60;
            var sc= str.match(/(\d+)s/);   if (sc) s += parseInt(sc[1]);
            return s;
        }
        // Col 3 : statut — ordre En cours > Soumis > Inactif
        if (col === 3) {
            if (str.indexOf('en cours') >= 0) return 0;
            if (str.indexOf('soumis')   >= 0) return 1;
            return 2;
        }
        return str.toLowerCase();
    }

    function sortTable(col, dir) {
        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        rows.sort(function(a, b) {
            var va = parseVal(getCellVal(a, col), col);
            var vb = parseVal(getCellVal(b, col), col);
            if (va < vb) return -1 * dir;
            if (va > vb) return  1 * dir;
            return 0;
        });
        rows.forEach(function(r) { tbody.appendChild(r); });
    }

    headers.forEach(function(th) {
        th.addEventListener('click', function() {
            var col = parseInt(th.getAttribute('data-col'));
            if (currentCol === col) {
                currentDir = currentDir === 1 ? -1 : 1;
            } else {
                currentCol = col;
                currentDir = 1;
            }
            headers.forEach(function(h) { h.classList.remove('asc', 'desc'); });
            th.classList.add(currentDir === 1 ? 'asc' : 'desc');
            sortTable(col, currentDir);
        });
    });

    // Appliquer le tri initial : statut (En cours d'abord)
    headers[3].classList.add('asc');
    sortTable(3, 1);
})();
</script>

</body>
</html>
