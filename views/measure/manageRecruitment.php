<?php
session_start();

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
}

$profile = $_SESSION["profile"] ?? '';
$allowed = ['Super Admin', 'Admin', 'Ressource Humaine', 'Directeur Groupe', 'Directeur Général'];

if (!in_array($profile, $allowed)) {
    header("Location: ../portal.php");
    exit();
}

require_once "../../vendor/autoload.php";
include_once "includes/language.php"; 
include_once "partials/background-manager.php";
setPageBackground("bg-dashboard");

$title = "Gestion de Campagne de Recrutement";
include_once "partials/header.php";
openBackgroundContainer();
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
:root {
  --primary: #d70006;
  --primary-light: #fef2f2;
  --card-bg: rgba(255, 255, 255, 0.85);
  --card-border: rgba(255, 255, 255, 0.4);
  --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  --text: #1a1f36;
  --sub: #4b5563;
  --r: 16px;
}
.recruit-page {
    font-family: 'Inter', system-ui, sans-serif;
    padding: 30px 20px 80px;
}
.shell {
    max-width: 1000px;
    margin: 0 auto;
}
.page-title {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 30px;
    padding: 20px;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: var(--r);
    box-shadow: var(--shadow);
}
.page-title .icon {
    width: 50px;
    height: 50px;
    background: var(--primary-light);
    color: var(--primary);
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 1.5rem;
}
.page-title h1 {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text);
    margin: 0;
}
.page-title p {
    font-size: 0.85rem;
    color: var(--sub);
    margin: 4px 0 0;
}
.timeline {
    position: relative;
    padding-left: 30px;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 20px;
    bottom: 20px;
    width: 2px;
    background: rgba(215, 0, 6, 0.3);
}
.step-card {
    position: relative;
    background: var(--card-bg);
    backdrop-filter: blur(15px);
    border: 1px solid var(--card-border);
    border-radius: var(--r);
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: var(--shadow);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s;
}
.step-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
}
.step-card::before {
    content: attr(data-step);
    position: absolute;
    left: -46px;
    top: 25px;
    width: 34px;
    height: 34px;
    background: var(--primary);
    color: #fff;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-weight: 700;
    font-size: 0.9rem;
    box-shadow: 0 0 0 4px rgba(255,255,255,0.5);
    z-index: 1;
}
.step-card h2 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text);
    margin: 0 0 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.step-card h2 i {
    color: var(--primary);
}
.step-card p {
    font-size: 0.9rem;
    color: var(--sub);
    line-height: 1.5;
    margin-bottom: 20px;
}
.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: var(--primary);
    color: #fff;
    font-weight: 600;
    font-size: 0.85rem;
    border-radius: 8px;
    text-decoration: none;
    transition: background 0.2s, transform 0.1s;
    border: none;
    cursor: pointer;
}
.btn-action:hover {
    background: #b00005;
    transform: translateY(-2px);
    color: #fff;
}
.btn-secondary {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #d1d5db;
}
.btn-secondary:hover {
    background: #e5e7eb;
    color: #111827;
}
.code-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 10px 15px;
    border-radius: 8px;
    font-family: monospace;
    font-size: 0.8rem;
    color: #334155;
    margin-top: 10px;
}
</style>

<div class="recruit-page">
    <div class="shell">
        
        <div class="page-title">
            <div class="icon"><i class="fas fa-users-cog"></i></div>
            <div>
                <h1>Gestionnaire de Campagne de Recrutement</h1>
                <p>Suivez ces étapes pour organiser, générer et lier facilement une épreuve aux candidats d'une campagne.</p>
            </div>
        </div>

        <div class="timeline">
            
            <!-- ETAPE 1 -->
            <div class="step-card" data-step="1">
                <h2><i class="fas fa-file-excel"></i> 1. Préparation des données (RH)</h2>
                <p>Demandez aux Ressources Humaines de remplir le modèle Excel avec la liste des candidats à évaluer. Le fichier est très simple : il ne nécessite que les colonnes <strong>firstName</strong> et <strong>lastName</strong>.</p>
                <a href="../explore/importCandidat.php?template=1" class="btn-action btn-secondary" target="_blank">
                    <i class="fas fa-download"></i> Télécharger le modèle Excel
                </a>
            </div>

            <!-- ETAPE 2 -->
            <div class="step-card" data-step="2">
                <h2><i class="fas fa-cogs"></i> 2. Création de l'épreuve figée</h2>
                <p>Générez un test unique qui sera identique pour tous les candidats de cette vague. Configurez le nombre de questions, les marques, et la répartition (Junior/Senior) selon les exigences des RH. Gardez en tête que c'est la dernière épreuve de ce type créée qui sera automatiquement assignée.</p>
                <a href="create_fixed_paper.php" class="btn-action">
                    <i class="fas fa-magic"></i> Créer l'épreuve figée
                </a>
            </div>

            <!-- ETAPE 3 -->
            <div class="step-card" data-step="3">
                <h2><i class="fas fa-user-plus"></i> 3. Importation des Candidats</h2>
                <p>Importez le fichier Excel rempli à l'étape 1. Lors de l'import, vous devrez <strong>sélectionner un compte RH</strong>. Tous les candidats importés y seront rattachés, et ce RH recevra par email le tableau récapitulatif des identifiants (le mot de passe de tous les candidats sera défini sur '0000').</p>
                <a href="../explore/importCandidat.php" class="btn-action">
                    <i class="fas fa-upload"></i> Importer le batch
                </a>
            </div>

            <!-- ETAPE 4 -->
            <div class="step-card" data-step="4">
                <h2><i class="fas fa-link"></i> 4. Assignation et Finalisation</h2>
                <p>Une fois les candidats importés, il faut les lier à l'épreuve générée à l'étape 2. Actuellement, cela se fait via un script automatisé en ligne de commande qui assignera le "Rôle" de la dernière épreuve active aux comptes "démo" ou à tous les comptes ciblés du batch.</p>
                
                <div style="background: rgba(230, 34, 68, 0.05); border: 1px solid rgba(230, 34, 68, 0.2); padding: 15px; border-radius: 8px; margin-top: 15px;">
                    <h4 style="margin: 0 0 10px; font-size: 0.9rem; color: var(--text);">Mode d'emploi (Serveur) :</h4>
                    <p style="margin-bottom: 5px; font-size: 0.85rem;">Exécutez cette commande sur le serveur pour assigner le test au batch importé :</p>
                    <div class="code-box">
                        php scripts/assign_demo_recrutement_pl.php --all
                    </div>
                    <p style="margin-top: 10px; font-size: 0.8rem; color: #64748b;">* Tous les comptes ciblés recevront alors l'accès exact au test généré à l'étape 2 et la campagne sera prête à être passée par les candidats.</p>
                </div>
            </div>

        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="../portal.php" class="btn-action btn-secondary" style="border-radius: 50px;">
                <i class="fas fa-arrow-left"></i> Retour au portail
            </a>
        </div>

    </div>
</div>

<?php 
closeBackgroundContainer();
include_once "partials/footer.php"; 
?>
