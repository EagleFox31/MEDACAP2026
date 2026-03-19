<?php
/**
 * Import candidats (stage) — Version Design Premium
 */

session_start();
include_once "../language.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
}

require_once "../../vendor/autoload.php";
include_once "../sendMail.php";
include_once "../partials/background-manager.php";
setPageBackground("bg-dashboard");

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/* ----------------------------------------------------------
 * DB & Config
 * -------------------------------------------------------- */
$client = new Client("mongodb://localhost:27017");
$db     = $client->academy;
$users  = $db->users;
$papers = $db->papers;

const FIXED_SUBSIDIARY = "CAMEROON MOTORS INDUSTRIES";
const FIXED_DEPARTMENT = "Equipment & Motors";
const FIXED_COUNTRY    = "Cameroun";
const CAMPAIGN_ROLE    = "Recrutement Technicien Campagne FNE-CFAO 2025";

/* ----------------------------------------------------------
 * Modèle Excel
 * -------------------------------------------------------- */
if (isset($_GET['template'])) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['firstName','lastName'], ['Awa','Diop'], ['Jean','Ndola']], NULL, 'A1');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="template_candidats.xlsx"');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

/* ----------------------------------------------------------
 * Logique d'Import (avec Résolution de Conflits)
 * -------------------------------------------------------- */
$result = null; $errors = []; $createdRows = []; $conflicts = [];

// Traitement d'import initial
if (isset($_POST["submit"])) {
    try {
        $rhId = $_POST['rh_id'] ?? '';
        if (!$rhId || !preg_match('/^[a-f0-9]{24}$/i', $rhId)) throw new RuntimeException("Veuillez sélectionner un RH.");
        
        $paperId = $_POST['paper_id'] ?? '';
        $campaignRole = CAMPAIGN_ROLE;
        if ($paperId && preg_match('/^[a-f0-9]{24}$/i', $paperId)) {
            $pDoc = $papers->findOne(['_id' => new ObjectId($paperId)]);
            if ($pDoc) $campaignRole = (string)$pDoc['label'];
        }

        if (!isset($_FILES['excel']) || $_FILES['excel']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException("Fichier invalide.");

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['excel']['tmp_name']);
        $data = $spreadsheet->getActiveSheet()->toArray();
        if (!$data || count($data) < 2) throw new RuntimeException("Le fichier est vide.");

        $header = array_map('trim', array_map('strtolower', $data[0]));
        $idx = array_flip($header);
        unset($data[0]);

        $created = 0; $updated = 0; $skipped = 0;
        foreach ($data as $rowNum => $row) {
            $fNameInput = trim((string)($row[$idx['firstname']] ?? ''));
            $lNameInput = trim((string)($row[$idx['lastname']]  ?? ''));
            if ($fNameInput === '' || $lNameInput === '') {
                $errors[] = "Ligne ".($rowNum+1).": prénom/nom manquant — ignorée.";
                $skipped++; continue;
            }

            $fName = ucfirst(strtolower($fNameInput));
            $lName = strtoupper($lNameInput);

            // 1. Recherche Exacte
            $exact = $users->findOne(['firstName'=>$fName, 'lastName'=>$lName, 'active'=>true]);

            // 2. Recherche Floue (Prénom/Nom inversés)
            $fuzzy = null;
            if (!$exact) {
                // On cherche si un candidat existe avec Nom = InputPrénom et Prénom = InputNom
                $fuzzy = $users->findOne(['firstName'=>ucfirst(strtolower($lNameInput)), 'lastName'=>strtoupper($fNameInput), 'active'=>true]);
            }

            if ($exact) {
                $existingRoles = ($exact['roles'] instanceof \MongoDB\Model\BSONArray) ? $exact['roles']->getArrayCopy() : (array)($exact['roles'] ?? []);
                if (!in_array($campaignRole, $existingRoles)) {
                    $users->updateOne(['_id' => $exact['_id']], [
                        '$set' => ['role' => $campaignRole, 'hr' => new ObjectId($rhId), 'status' => 'New'],
                        '$addToSet' => ['roles' => $campaignRole]
                    ]);
                    $updated++;
                    $createdRows[] = ['firstName'=>$exact['firstName'], 'lastName'=>$exact['lastName'], 'username'=>$exact['username'], 'password'=>$exact['visiblePassword']??'0000'];
                } else {
                    $skipped++;
                    $errors[] = "Ligne ".($rowNum+1).": {$fNameInput} {$lNameInput} est déjà inscrit.";
                }
            } elseif ($fuzzy) {
                // CONFLIT : On demande à l'utilisateur
                $conflicts[] = [
                    'input' => ['firstName' => $fName, 'lastName' => $lName],
                    'match' => ['_id' => (string)$fuzzy['_id'], 'firstName' => $fuzzy['firstName'], 'lastName' => $fuzzy['lastName']],
                    'rh_id' => $rhId,
                    'campaign' => $campaignRole
                ];
            } else {
                // Création Nouvelle
                $base = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $lName));
                $visible = "0000";
                $doc = [
                    "matricule" => '999999', "username" => $base, "firstName" => $fName, "lastName" => $lName,
                    "profile" => "Candidat", "country" => FIXED_COUNTRY, "subsidiary" => FIXED_SUBSIDIARY, "department" => FIXED_DEPARTMENT,
                    "role" => $campaignRole, "roles" => [$campaignRole], "password" => sha1($visible), "visiblePassword" => $visible,
                    "hr" => new ObjectId($rhId), "active" => true, "status" => "New", "created" => date("d-m-Y H:i:s")
                ];
                $users->insertOne($doc);
                $created++;
                $createdRows[] = ['firstName'=>$doc['firstName'], 'lastName'=>$doc['lastName'], 'username'=>$doc['username'], 'password'=>$visible];
            }
        }

        // Notification Mail (si pas de conflits bloquants ou déjà traités)
        if (($created + $updated) > 0 && empty($conflicts)) {
            $rowsHtml = '';
            foreach ($createdRows as $r) $rowsHtml .= "<tr><td>{$r['lastName']}</td><td>{$r['firstName']}</td><td>{$r['username']}</td><td>{$r['password']}</td></tr>";
            $link = FIXED_COUNTRY === 'Cameroun' ? 'http://10.68.0.7/medacap/' : 'http://129.0.64.34/medacap';
            sendMailRegisterUser((string)$rh['email'], "MEDACAP — Import (".($created+$updated).")", "<p>Bonjour,</p><p>Import fini :</p><table border='1'>{$rowsHtml}</table><p><a href='{$link}'>{$link}</a></p>");
        }
        $result = ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors, 'conflicts' => $conflicts];
    } catch (Throwable $e) { $errors[] = $e->getMessage(); $result = ['created'=>0, 'updated'=>0, 'skipped'=>0, 'errors'=>$errors]; }
}

// Traitement des résolutions de conflits
if (isset($_POST["submit_resolution"])) {
    $resolutions = $_POST['res'] ?? [];
    $rhId = $_POST['rh_id_res'] ?? '';
    $camp = $_POST['campaign_res'] ?? '';
    $created = 0; $updated = 0;

    foreach ($resolutions as $idx => $action) {
        $fName = $_POST['fName_res'][$idx];
        $lName = $_POST['lName_res'][$idx];
        $matchId = $_POST['matchId_res'][$idx];

        if ($action === 'merge') {
            $users->updateOne(['_id' => new ObjectId($matchId)], [
                '$set' => ['role' => $camp, 'hr' => new ObjectId($rhId), 'status' => 'New'],
                '$addToSet' => ['roles' => $camp]
            ]);
            $updated++;
        } else {
            $base = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $lName));
            $visible = "0000";
            $users->insertOne([
                "matricule" => '999999', "username" => $base, "firstName" => $fName, "lastName" => $lName,
                "profile" => "Candidat", "country" => FIXED_COUNTRY, "subsidiary" => FIXED_SUBSIDIARY, "department" => FIXED_DEPARTMENT,
                "role" => $camp, "roles" => [$camp], "password" => sha1($visible), "visiblePassword" => $visible,
                "hr" => new ObjectId($rhId), "active" => true, "status" => "New", "created" => date("d-m-Y H:i:s")
            ]);
            $created++;
        }
    }
    $result = ['created' => $created, 'updated' => $updated, 'skipped' => 0, 'errors' => ["Conflits résolus : $created créés, $updated fusionnés."]];
}

// Nouvelle Logique : Assignation forcée aux comptes DEMO (Indépendante)
if (isset($_POST["assign_demo"]) && !empty($_POST['demo_accounts'])) {
    try {
        $demoUsernames = (array)$_POST['demo_accounts'];
        $paperId = $_POST['paper_id'] ?? '';
        $campRole = CAMPAIGN_ROLE;
        if ($paperId && preg_match('/^[a-f0-9]{24}$/i', $paperId)) {
            $p = $papers->findOne(['_id' => new ObjectId($paperId)]);
            if ($p) $campRole = (string)$p['label'];
        }

        $rhId = $_POST['rh_id'] ?? '';
        $updateData = ['$set' => ['role' => $campRole, 'status' => 'New'], '$addToSet' => ['roles' => $campRole]];
        
        // On n'ajoute le RH que s'il est sélectionné (facultatif pour les DEMO)
        if ($rhId && preg_match('/^[a-f0-9]{24}$/i', $rhId)) {
            $updateData['$set']['hr'] = new ObjectId($rhId);
        }

        $count = 0;
        foreach ($demoUsernames as $un) {
            $users->updateOne(['username' => $un, 'active' => true], $updateData);
            $count++;
        }
        $result = ['created' => 0, 'updated' => $count, 'skipped' => 0, 'errors' => ["Succès : $count compte(s) DEMO assigné(s) à la campagne '$campRole'."]];
    } catch (Throwable $e) { $errors[] = $e->getMessage(); $result = ['created'=>0, 'updated'=>0, 'skipped'=>0, 'errors'=>$errors]; }
}

include_once "partials/header.php";
openBackgroundContainer();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Importation Candidats — CFAO Mobility Academy</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-black: #1a1a1a;
            --primary-navy: #1e3a8a;
            --secondary-navy: #3b82f6;
            --light-gray: #f8fafc;
            --medium-gray: #64748b;
            --dark-gray: #334155;
            --white: #ffffff;
            --border-color: #e2e8f0;
            --shadow-medium: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-large: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--primary-black);
            line-height: 1.6;
            padding: 40px 20px 100px;
        }

        .shell {
            max-width: 900px;
            margin: 0 auto;
        }

        /* Effet de profondeur pour les cartes */
        .depth-effect {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06), 0 12px 20px -2px rgba(0, 0, 0, 0.05) !important;
            transition: all 0.3s ease-in-out !important;
        }
        .depth-effect:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05), 0 20px 25px -5px rgba(0, 0, 0, 0.03) !important;
        }

        /* Glassmorphism */
        .glass-effect {
            background: rgba(255, 255, 255, 0.75) !important;
            backdrop-filter: blur(12px) !important;
            -webkit-backdrop-filter: blur(12px) !important;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
        }

        /* Header Title Card (Exact Match to Image 2) */
        .title-card {
            background-color: #fff;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            margin-bottom: 15px;
        }
        .title-card h1 {
            font-size: 1.2rem;
            font-weight: 800;
            color: #001f3f; /* Navy direct */
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        .title-card h1 svg {
            width: 24px;
            height: 24px;
            color: #333;
        }

        /* Main Form Card */
        .card {
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 25px;
        }
        .card-header-bg {
            background: linear-gradient(135deg, var(--primary-black) 0%, var(--primary-navy) 100%);
            color: #fff !important;
            padding: 14px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .card-header-bg h5 {
            color: #fff !important;
            margin: 0;
            font-size: 0.9rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .card-header-bg svg {
            color: #fff !important;
        }
        .card-body {
            padding: 30px;
        }

        /* Form Controls */
        label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--medium-gray);
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin-bottom: 8px;
        }
        select, input[type="file"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.9rem;
            color: var(--primary-black);
            background: #fff;
            transition: var(--transition);
        }
        select:focus {
            border-color: var(--secondary-navy);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .field { margin-bottom: 25px; }

        /* Action Bar (Sticky Footer Style) */
        .sbar {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            padding: 20px 30px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .sbar p { font-size: 0.8rem; color: var(--medium-gray); margin: 0; max-width: 60%; }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-navy) 0%, var(--secondary-navy) 100%);
            color: #fff;
            padding: 12px 28px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: var(--shadow-medium);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-large);
        }

        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            border: 1px solid transparent;
        }
        .a-ok { background: #d1fae5; border-color: #10b981; color: #065f46; }
        .a-err { background: #fee2e2; border-color: #ef4444; color: #991b1b; }

        /* Template link */
        .dl-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--primary-navy);
            text-decoration: none;
            font-weight: 800;
            font-size: 0.65rem;
            text-transform: uppercase;
            background: #fff;
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid #fff;
            transition: var(--transition);
        }
        .dl-link:hover { background: var(--light-gray); transform: scale(1.05); }

        .demo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .demo-item {
            background: #fff;
            border: 1px solid var(--border-color);
            padding: 8px 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: var(--transition);
        }
        .demo-item:hover { border-color: var(--secondary-navy); background: var(--light-gray); }
        .demo-item input { width: auto; }
        .demo-tag { font-size: 0.65rem; background: var(--acc-lt); color: var(--accent); padding: 2px 6px; border-radius: 4px; font-weight: 700; }
    </style>
</head>
<body>
<div class="shell">

    <!-- Header Title (Corrected to match Image 2) -->
    <div class="title-card depth-effect">
        <h1>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            IMPORTATION DE CANDIDATS
        </h1>
    </div>

    <?php if($result): ?>
        <div class="alert <?= (!empty($result['errors']) && $result['created'] == 0 && $result['updated'] == 0)?'a-err':'a-ok' ?> depth-effect">
            <strong><?= ($result['created'] > 0 || $result['updated'] > 0)?"IMPORTATION RÉUSSIE":"ERREUR D'IMPORTATION" ?></strong>
            <p style="margin-top:5px;">
                <?= $result['created'] ?> nouveau(x) compte(s), 
                <?= $result['updated'] ?> candidat(s) ajouté(s) à cette campagne, 
                <?= $result['skipped'] ?> ignoré(s).
            </p>
            <?php if (!empty($result['errors'])): ?>
                <ul style="margin:10px 0 0 20px; font-size:0.85em;">
                    <?php foreach ($result['errors'] as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if($result && !empty($result['conflicts'])): ?>
        <div class="card glass-effect depth-effect" style="border-left: 4px solid #f59e0b !important;">
            <div class="card-header-bg" style="background: linear-gradient(135deg, #92400e 0%, #f59e0b 100%);">
                <div style="display:flex; align-items:center; gap:12px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <h5>CONFLITS DE NOMS DÉTECTÉS</h5>
                </div>
            </div>
            <div class="card-body">
                <p style="font-size:0.85rem; color:var(--medium-gray); margin-bottom:20px;">
                    Certains noms ressemblent à des comptes existants (ex: nom/prénom inversés). Que souhaitez-vous faire ?
                </p>
                <form method="post">
                    <input type="hidden" name="rh_id_res" value="<?= htmlspecialchars($result['conflicts'][0]['rh_id']) ?>">
                    <input type="hidden" name="campaign_res" value="<?= htmlspecialchars($result['conflicts'][0]['campaign']) ?>">
                    
                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse: collapse; font-size:0.85rem;">
                            <thead>
                                <tr style="text-align:left; border-bottom:1px solid var(--border-color);">
                                    <th style="padding:10px;">Import (Excel)</th>
                                    <th style="padding:10px;">Match Trouvé</th>
                                    <th style="padding:10px; text-align:right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($result['conflicts'] as $i => $c): ?>
                                    <tr style="border-bottom:1px solid #f1f5f9;">
                                        <td style="padding:12px;">
                                            <span style="font-weight:700; color:var(--primary-navy);"><?= $c['input']['lastName'] ?></span> <?= $c['input']['firstName'] ?>
                                            <input type="hidden" name="fName_res[<?= $i ?>]" value="<?= $c['input']['firstName'] ?>">
                                            <input type="hidden" name="lName_res[<?= $i ?>]" value="<?= $c['input']['lastName'] ?>">
                                            <input type="hidden" name="matchId_res[<?= $i ?>]" value="<?= $c['match']['_id'] ?>">
                                        </td>
                                        <td style="padding:12px; color:var(--medium-gray);">
                                            Compte : <strong><?= $c['match']['lastName'] ?></strong> <?= $c['match']['firstName'] ?>
                                        </td>
                                        <td style="padding:12px; text-align:right;">
                                            <select name="res[<?= $i ?>]" style="width:auto; padding:5px; font-size:0.75rem; font-weight:700;">
                                                <option value="merge">Fusionner (Utiliser ce compte)</option>
                                                <option value="new">Créer un nouveau compte</option>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="margin-top:25px; display:flex; justify-content: flex-end;">
                        <button type="submit" name="submit_resolution" class="btn-primary" style="background: linear-gradient(135deg, #b45309 0%, #f59e0b 100%);">
                            VALIDER LES RÉSOLUTIONS
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <div class="card glass-effect depth-effect">
            <div class="card-header-bg">
                <div style="display:flex; align-items:center; gap:12px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    <h5>PARAMÈTRES DE SESSION D'IMPORT</h5>
                </div>
                <a href="?template=1" class="dl-link">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    TÉLÉCHARGER LE MODÈLE (.XLSX)
                </a>
            </div>
            <div class="card-body">
                <div class="field">
                    <label>Responsable RH Référent (Emailing)</label>
                    <select name="rh_id" required>
                        <option value="">— Veuillez choisir un RH —</option>
                        <?php
                        $cursor = $users->find(['active'=>true, 'profile'=>'Ressource Humaine'], ['sort'=>['lastName'=>1]]);
                        foreach ($cursor as $u) {
                            echo '<option value="'.(string)$u->_id.'">'.strtoupper((string)$u->lastName).' '.ucfirst((string)$u->firstName).'</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="field">
                    <label>Épreuve cible / Campagne de recrutement</label>
                    <select name="paper_id">
                        <option value="">— Utiliser le rôle par défaut (Campagne 2025) —</option>
                        <?php
                        $pList = $papers->find(['active'=>true], ['sort'=>['created'=>-1]]);
                        foreach ($pList as $p) {
                            echo '<option value="'.(string)$p->_id.'">'.htmlspecialchars((string)$p['label']).'</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="field" style="margin-bottom:0;">
                    <label>Fichier source (Excel)</label>
                    <input type="file" name="excel" accept=".xlsx" required>
                </div>
            </div>
        </div>

        <div class="card glass-effect depth-effect">
            <div class="card-header-bg" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%);">
                <div style="display:flex; align-items:center; gap:12px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                    <h5>COMPTES DE TEST (DEMO)</h5>
                </div>
            </div>
            <div class="card-body">
                <p style="font-size:0.75rem; color:var(--medium-gray); margin-bottom:15px;">
                    Sélectionnez les comptes Demo ci-dessous pour leur assigner cette campagne :
                </p>
                <div class="demo-grid">
                    <?php
                    $demoList = $users->find(['username' => new \MongoDB\BSON\Regex('demo', 'i'), 'active' => true]);
                    foreach ($demoList as $d) {
                        $un = (string)$d['username'];
                        echo '<label class="demo-item">
                                <input type="checkbox" name="demo_accounts[]" value="'.$un.'">
                                <div>
                                    <div style="font-weight:700;">'.$un.'</div>
                                    <div class="demo-tag">COMPTE TEST</div>
                                </div>
                              </label>';
                    }
                    ?>
                </div>
                
                <div style="margin-top:20px; display:flex; justify-content: flex-end; border-top:1px solid var(--border-color); padding-top:15px;">
                    <button type="submit" name="assign_demo" class="btn-primary" style="background: linear-gradient(135deg, #334155 0%, #475569 100%); padding: 10px 20px; font-size: 0.75rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" style="margin-right:5px;"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
                        ASSIGNER À LA SÉLECTION DEMO
                    </button>
                </div>
            </div>
        </div>

        <div class="sbar depth-effect">
            <p><strong>Note :</strong> Pour l'import de fichier, joindre un Excel valide. Pour les comptes DEMO, utilisez le bouton dédié ci-dessus.</p>
            <button type="submit" name="submit" class="btn-primary" onclick="this.form.excel.required=true;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg>
                IMPORTER LE FICHIER EXCEL
            </button>
        </div>
        
        <script>
            // Petite astuce pour que le 'required' ne bloque pas le bouton Demo
            document.querySelector('button[name="assign_demo"]').addEventListener('click', function(e) {
                document.querySelector('input[name="excel"]').required = false;
                document.querySelector('select[name="rh_id"]').required = false;
            });
        </script>
    </form>

</div>
</body>
</html>
<?php 
closeBackgroundContainer();
include_once "partials/footer.php"; 
?>
