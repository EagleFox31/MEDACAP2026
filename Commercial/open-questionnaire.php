<?php
session_start();
require_once "../vendor/autoload.php";
require_once "../views/sendMail.php";
require_once "sendCommercialMail.php";

use MongoDB\Client as MongoClient;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

// Guard : session commerciale obligatoire (ou admin MEDACAP pour prévisualisation)
$isCommercialSession = isset($_SESSION['commercial_session']) && $_SESSION['commercial_session'] === true;
$isAdminSession = isset($_SESSION['id']) && in_array($_SESSION['profile'] ?? '', ['Super Admin', 'Admin']);

if (!$isCommercialSession && !$isAdminSession) {
    $redirectType = isset($_GET['type']) ? '?type=' . urlencode($_GET['type']) : '';
    header("Location: register.php" . $redirectType);
    exit();
}

// Configuration de base pour éviter les erreurs dans les partials
if (!isset($_SESSION['lang']))
    $_SESSION['lang'] = 'FR';
if (!isset($_SESSION['country']))
    $_SESSION['country'] = 'CFAO';
if (!isset($_SESSION['profile']))
    $_SESSION['profile'] = 'Commercial';
if (!isset($_SESSION['firstName']))
    $_SESSION['firstName'] = 'Visiteur';
if (!isset($_SESSION['lastName']))
    $_SESSION['lastName'] = '';
if (!isset($_SESSION['id']))
    $_SESSION['id'] = '0';
if (!isset($_SESSION['email']))
    $_SESSION['email'] = '';

// Langue
$lang = $_SESSION['lang'];
if ($lang === 'EN') {
    include_once "language_EN.php";
} else {
    include_once "language.php";
}

// Surcharge du titre du module pour le header
$currentLang = isset($_GET['lang']) && strtoupper($_GET['lang']) === 'EN' ? 'EN' : 'FR';
$module_measure = $currentLang === 'FR' ? "QUESTIONNAIRE COMMERCIAL : SINOTRUK, BUS ET CHARIOTS" : "SALES ASSESSMENT: SINOTRUK, BUS & FORKLIFTS";

$i18n = [
    'FR' => [
        'title' => "Questionnaire Commercial CFAO Equipment",
        'welcome_instructions' => "Consignes du Questionnaire",
        'welcome_subtitle' => "Vous allez débuter votre questionnaire commercial pour la gamme <strong>%s</strong>. Veuillez lire attentivement les consignes de sécurité suivantes :",
        'rule_chrono' => "Chrono par Question :",
        'rule_chrono_desc' => "Vous avez 2 minutes pour répondre. Passé ce délai, le système passe à la question suivante.",
        'rule_fullscreen' => "Plein Écran Automatique :",
        'rule_fullscreen_desc' => "Le questionnaire démarre automatiquement en mode plein écran. À chaque fois que vous quittez ce mode, vous recevez une pénalité.",
        'rule_tab' => "Sorties Interdites :",
        'rule_tab_desc' => "Assurez-vous de ne pas utiliser une autre application ou un autre onglet. Restez dans le questionnaire car chaque sortie compte comme une pénalité.",
        'rule_copy' => "Pas de Copier-Coller :",
        'rule_copy_desc' => "Vous devez taper vos réponses vous-même. Le clic droit et le copier-coller sont bloqués.",
        'rule_ban_warning' => "Au bout de trois pénalités, vos réponses sont enregistrées automatiquement et le formulaire se ferme.",
        'btn_accept' => "J'AI LU ET J'ACCEPTE",
        'admin_portal' => "Espace de Questionnaires Commerciaux",
        'admin_desc' => "Bienvenue sur la plateforme de certification CFAO. Ce système sécurisé vous permet de valider vos connaissances techniques et commerciales. Sélectionnez un module ci-dessous pour démarrer votre session.",
        'placeholder' => "Saisissez votre réponse ici...",
        'min_chars' => "caractères minimum",
        'btn_prev' => "Précédent",
        'btn_next' => "Suivant",
        'btn_submit' => "Terminer le questionnaire",
        'sec_fullscreen_title' => "MODE PLEIN ÉCRAN REQUIS",
        'sec_fullscreen_desc' => "Pour garantir l'équité et la sécurité de ce questionnaire, le mode plein écran doit être activé en permanence.",
        'sec_btn_reactivate' => "RÉACTIVER LE PLEIN ÉCRAN",
        'sec_penalty_label' => "Pénalité dans :",
        'sec_banned_title' => "ACCÈS BLOQUÉ",
        'sec_banned_desc' => "Cette session a été interrompue suite à des violations répétées des consignes de sécurité. Vos réponses actuelles ont été enregistrées.",
        'sec_banned_reason' => "Motif : Seuil de tolérance dépassé (3 pénalités).",
        'timer_global' => "Global :",
        'timer_question' => "Question :",
        'stats_alerts' => "Pénalités :",
        'stats_q_count' => "Question",
        'alert_focus_title' => "PERTE DE FOCUS DÉTECTÉE",
        'alert_focus_desc' => "Vous avez quitté la fenêtre du questionnaire. Cela peut être interprété comme une tentative de recherche externe.",
        'alert_focus_reason' => "Changement d'application / Fenêtre",
        'alert_tab_title' => "CHANGEMENT D'ONGLET",
        'alert_tab_desc' => "Le changement d'onglet est strictement interdit pendant le questionnaire.",
        'alert_tab_reason' => "Changement d'onglet",
        'alert_fs_title' => "MODE PLEIN ÉCRAN REQUIS",
        'alert_fs_desc' => "Le mode plein écran a été désactivé. Veuillez le réactiver immédiatement pour poursuivre.",
        'alert_fs_reason' => "Sortie du plein écran",
        'q_header_title' => "QUESTIONNAIRE",
        'q_main_title' => "Questionnaire à Réponse Ouverte",
        'q_main_desc' => "Veuillez répondre avec précision à chaque question.",
        'success_title' => "Soumission Réussie",
        'success_desc' => "Merci pour vos réponses ! Votre participation a bien été enregistrée.",
        'btn_return' => "Revenir au portail"
    ],
    'EN' => [
        'title' => "CFAO Equipment Sales Assessment",
        'welcome_instructions' => "Assessment Instructions",
        'welcome_subtitle' => "You are about to begin your sales assessment for the <strong>%s</strong> range. Please read the following rules carefully before proceeding:",
        'rule_chrono' => "Time Limit Per Question:",
        'rule_chrono_desc' => "You have 2 minutes to answer each question. Once the time is up, the system automatically moves to the next question.",
        'rule_fullscreen' => "Full-Screen Mode:",
        'rule_fullscreen_desc' => "The assessment launches automatically in full-screen mode. Each time you exit this mode, a penalty is recorded.",
        'rule_tab' => "No Tab or Window Switching:",
        'rule_tab_desc' => "Do not switch to another application or browser tab during the session. Every exit from the assessment window is recorded as a penalty.",
        'rule_copy' => "No Copy-Pasting:",
        'rule_copy_desc' => "You must type all answers yourself. Right-clicking and copy-pasting are disabled.",
        'rule_ban_warning' => "After three penalties, your answers are automatically saved and the session is closed.",
        'btn_accept' => "I HAVE READ AND I ACCEPT",
        'admin_portal' => "Sales Assessment Hub",
        'admin_desc' => "Welcome to the CFAO Certification Platform. This secure system allows you to demonstrate your product and sales knowledge. Select a module below to begin your session.",
        'placeholder' => "Type your answer here...",
        'min_chars' => "minimum characters",
        'btn_prev' => "Previous",
        'btn_next' => "Next",
        'btn_submit' => "Submit Assessment",
        'sec_fullscreen_title' => "FULL-SCREEN MODE REQUIRED",
        'sec_fullscreen_desc' => "To ensure fairness and integrity, full-screen mode must remain active throughout the session.",
        'sec_btn_reactivate' => "REACTIVATE FULL SCREEN",
        'sec_penalty_label' => "Penalty in:",
        'sec_banned_title' => "SESSION TERMINATED",
        'sec_banned_desc' => "This session has been closed due to repeated security violations. Your responses have been saved.",
        'sec_banned_reason' => "Reason: Penalty limit reached (3 penalties).",
        'timer_global' => "Total:",
        'timer_question' => "Question:",
        'stats_alerts' => "Penalties:",
        'stats_q_count' => "Question",
        'alert_focus_title' => "FOCUS LOSS DETECTED",
        'alert_focus_desc' => "You have left the assessment window. This may be considered an attempt to seek outside assistance.",
        'alert_focus_reason' => "Application / Window Switch",
        'alert_tab_title' => "TAB SWITCH DETECTED",
        'alert_tab_desc' => "Switching browser tabs is strictly prohibited during the assessment.",
        'alert_tab_reason' => "Tab Switch",
        'alert_fs_title' => "FULL-SCREEN MODE REQUIRED",
        'alert_fs_desc' => "Full-screen mode has been disabled. Please reactivate it immediately to continue.",
        'alert_fs_reason' => "Full-Screen Exit",
        'q_header_title' => "ASSESSMENT",
        'q_main_title' => "Open-Ended Assessment",
        'q_main_desc' => "Please answer each question as accurately and completely as possible.",
        'success_title' => "Submission Successful",
        'success_desc' => "Thank you for completing the assessment. Your responses have been successfully recorded.",
        'btn_return' => "Return to Portal"
    ]
];

$isSubmitted = false;
$isBannedSubmit = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answers'])) {
    $mongoClient = new MongoClient("mongodb://localhost:27017");
    $responsesCollection = $mongoClient->academy->commercial_responses;

    $answers = isset($_POST['answers']) ? $_POST['answers'] : [];
    $violations = isset($_POST['violations']) ? (int) $_POST['violations'] : 0;
    $totalTime = isset($_POST['total_time']) ? (int) $_POST['total_time'] : 0;
    $qType = isset($_POST['questionnaire_type']) ? trim($_POST['questionnaire_type']) : '';
    $isBannedSubmit = isset($_POST['is_banned']) && $_POST['is_banned'] === '1';

    $brand = $qType === 'sinotruk' ? 'SINOTRUK' : 'KING LONG';

    $doc = [
        "firstName" => $_SESSION['firstName'],
        "lastName" => $_SESSION['lastName'],
        "email" => $_SESSION['email'],
        "country" => $_SESSION['country'] ?? '',
        "questionnaire_type" => $qType,
        "brand" => $brand,
        "answers" => $answers,
        "violations" => $violations,
        "total_time_seconds" => $totalTime,
        "lang" => $currentLang,
        "is_banned" => $isBannedSubmit,
        "submitted_at" => date("d-m-Y H:i:s"),
    ];

    if (isset($_SESSION['id']) && $_SESSION['id'] !== '0') {
        try {
            $doc["user_id"] = new ObjectId($_SESSION['id']);
        } catch (Exception $e) {
            // ObjectId invalide, on ignore
        }
    }

    $downloadToken = bin2hex(random_bytes(24));
    $doc['download_token'] = $downloadToken;
    $responsesCollection->insertOne($doc);

    // Marquer la session live comme soumise
    $mongoClient->academy->commercial_live->updateOne(
        ['session_id' => session_id()],
        ['$set' => ['status' => 'submitted', 'last_seen' => date('d-m-Y H:i:s')]]
    );

    $brandNotif = $qType === 'sinotruk' ? 'SINOTRUK' : 'KING LONG';
    $mailSubject = $doc['lastName'] . ' — Questionnaire ' . $brandNotif . ' — ' . date('d/m/Y');
    $downloadUrl = 'http://129.0.64.34/MEDACAP/Commercial/download-response.php?token=' . $downloadToken;

    $emailData = [
        'companyName' => 'CFAO Academy',
        'companyTagline' => 'Questionnaires Commerciaux',
        'userName' => 'Équipe ' . $brandNotif,
        'title' => $doc['lastName'] . ' — ' . $brandNotif,
        'message' => 'Questionnaire complété. Cliquez sur le bouton pour télécharger les réponses en Excel.',
        'details' => [
            ['label' => 'Nom', 'value' => $doc['lastName'], 'highlight' => true],
            ['label' => 'Marque', 'value' => $brandNotif, 'highlight' => true],
        ],
        'ctaText' => 'Télécharger en Excel',
        'ctaLink' => $downloadUrl,
        'ctaText2' => 'Corriger en ligne',
        'ctaLink2' => 'http://129.0.64.34/MEDACAP/Commercial/responses.php',
        'supportEmail' => SUPER_ADMIN_EMAIL,
    ];

    // Admins commerciaux en CC (hors SUPER_ADMIN qui est le destinataire principal)
    $ccEmails = [];
    $commAdmins = $mongoClient->academy->commercial_admins->find(['active' => true]);
    foreach ($commAdmins as $admin) {
        $adminEmail = isset($admin['email']) ? strtolower(trim((string) $admin['email'])) : '';
        if ($adminEmail !== '' && strtolower($adminEmail) !== strtolower(SUPER_ADMIN_EMAIL)) {
            $ccEmails[] = $adminEmail;
        }
    }

    sendCommercialEmail(SUPER_ADMIN_EMAIL, $mailSubject, $emailData, $ccEmails);

    // Invalider la session commerciale après soumission (empêche le retake)
    if ($isCommercialSession) {
        unset($_SESSION['commercial_session'], $_SESSION['commercial_type']);
    }

    $isSubmitted = true;
}

$txt = $i18n[$currentLang];

// Tracker la session live (candidat en train de remplir le questionnaire)
if ($isCommercialSession && !$isSubmitted) {
    try {
        $liveClient = new MongoClient("mongodb://localhost:27017");
        $liveDb     = $liveClient->academy;
        $sid        = session_id();
        $existing   = $liveDb->commercial_live->findOne(['session_id' => $sid]);
        $nowStr     = date('d-m-Y H:i:s');
        $qTypeStr   = isset($_GET['type']) ? (strtolower(trim($_GET['type'])) === 'bus_et_chariot' ? 'KING LONG' : 'SINOTRUK') : 'SINOTRUK';
        if (!$existing) {
            $liveDb->commercial_live->insertOne([
                'session_id' => $sid,
                'firstName'  => (string)($_SESSION['firstName'] ?? ''),
                'lastName'   => (string)($_SESSION['lastName']  ?? ''),
                'email'      => (string)($_SESSION['email']     ?? ''),
                'brand'      => $qTypeStr,
                'started_at' => $nowStr,
                'last_seen'  => $nowStr,
                'status'     => 'active',
            ]);
        } else {
            $liveDb->commercial_live->updateOne(
                ['session_id' => $sid],
                ['$set' => ['last_seen' => $nowStr, 'status' => 'active']]
            );
        }
    } catch (Exception $e) { /* non bloquant */ }
}

include_once "background-manager.php";
setPageBackground("bg-dashboard");

// Chargement des questionnaires depuis les fichiers JSON (source unique)
function loadQuestionnaireFromJson($jsonFile, $id, $logo)
{
    $raw = json_decode(file_get_contents(__DIR__ . '/' . $jsonFile), true);
    $sections = [];
    foreach ($raw['sections'] as $section) {
        $sections[] = [
            'title_FR' => $section['section_FR'],
            'title_EN' => $section['section_EN'],
            'questions' => $section['questions'],
        ];
    }
    return [
        'id' => $id,
        'title' => $raw['questionnaire_FR'],
        'title_EN' => $raw['questionnaire_EN'],
        'brand' => isset($raw['brand_FR']) ? $raw['brand_FR'] : $raw['brand'],
        'brand_EN' => isset($raw['brand_EN']) ? $raw['brand_EN'] : $raw['brand'],
        'logo' => $logo,
        'sections' => $sections,
    ];
}

$questionnairesData = [
    'sinotruk' => loadQuestionnaireFromJson('questionnaire_sinotruk.json', 'sinotruk', '../public/images/sinotruk.png'),
    'bus_chariot' => loadQuestionnaireFromJson('questionnaire_bus_et_chariot.json', 'bus_chariot', '../public/images/king-long-seeklogo.png'),
];

$title = $txt['title'];

// Détection du type de questionnaire via l'URL (ex: ?type=sinotruk)
$preSelectedType = isset($_GET['type']) && isset($questionnairesData[$_GET['type']]) ? $_GET['type'] : null;
?>

<?php include_once "partials/header.php"; ?>
<?php openBackgroundContainer(); ?>

<style>
    /* Empêcher le scroll de la page entière */
    html, body { 
        height: 100vh !important; 
        overflow: hidden !important; 
        background: #f8fafc;
    }

    /* Supprimer l'arrondi du conteneur de fond */
    .page-background-container, 
    .page-background-overlay { 
        border-radius: 0 !important; 
    }

    /* Masquer la sidebar et étendre le contenu au maximum */
    #kt_aside, .aside, .aside-backdrop,
    #kt_aside_logo, #kt_aside_footer,
    #kt_aside_menu, #kt_aside_toggle,
    [data-kt-drawer-name="aside"] { display: none !important; }

    #kt_wrapper, .wrapper {
        padding: 0 !important;
        margin: 0 !important;
        margin-left: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        height: 100vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    /* Supprimer le décalage du wrapper dû à l'aside */
    body:not(.aside-minimize) #kt_wrapper {
        padding-left: 0 !important;
        margin-left: 0 !important;
    }

    #kt_root, .flex-root, .page {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    /* Header centré et aligné */
    /* Masquer le bouton de retour et le toggler du header */
    #kt_header {
        padding: 0.75rem 0 !important;
        display: flex !important;
        align-items: center !important;
        min-height: 80px !important;
    }

    /* Masquer le bouton de retour et le toggler du header */
    #kt_header .btn-icon[data-kt-toggle-name="aside-minimize"],
    #kt_header .rotate-180,
    #kt_header .ki-black-left-line { 
        display: none !important; 
    }

    #kt_header .container-fluid {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        width: 100% !important;
    }

    #kt_header .justify-content-center {
        justify-content: center !important; 
        align-items: center !important;    
        padding-left: 0 !important;
        margin: 0 !important;
        display: flex !important;
        flex: 1 !important;
    }

    /* Header pleine largeur — override l'offset de l'aside (left: 265px) */
    #kt_header .title-banner {
        margin: 0 !important;
        padding: 5px 16px !important;
    }

    /* Dropdown profil : masquer infos perso, lien profil et lien portail */
    #kt_header_user_menu_toggle [data-kt-menu="true"] > .menu-item:first-child,
    #kt_header_user_menu_toggle [data-kt-menu="true"] > .separator:first-of-type,
    #kt_header_user_menu_toggle [data-kt-menu="true"] > .menu-item:has(a.menu-link[href*="profile.php"]),
    #kt_header_user_menu_toggle [data-kt-menu="true"] > .separator:nth-of-type(2),
    #kt_header_user_menu_toggle [data-kt-menu="true"] > .menu-item:has(a[href*="portal.php"]) {
        display: none !important;
    }

    /* Masquer la barre logo vide à gauche du header */
    #kt_header .d-flex.align-items-center.flex-grow-1.flex-lg-grow-0 {
        display: none !important;
    }

    /* Supprimer le padding-left: 355px hérité de la mise en page avec aside */
    #kt_header [style*="padding-left"] {
        padding-left: 0 !important;
    }

    /* Éliminer le gap entre le bas du header et le contenu */
    #kt_wrapper .content,
    .page-background-container {
        padding-top: 0 !important;
    }

    /* background-manager.css met padding:20px sur page-content-container */
    .page-content-container {
        padding: 0 !important;
    }

    #kt_header h1 {
        font-size: 1.1rem !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Forcer l'alignement horizontal et vertical des boutons de langue et toolbar */
    #kt_header .flex-shrink-0,
    #kt_header .align-items-stretch {
        align-items: center !important;
    }

    #kt_header form.d-flex {
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        display: flex !important;
    }

    #kt_header .ms-2.ms-lg-3 {
        display: flex !important;
        align-items: center !important;
    }

    /* Ajouter une barre verticale avant le sélecteur de langue */
    #kt_header .d-flex.align-items-center.ms-2.ms-lg-3:first-child {
        border-left: 2px solid rgba(0, 0, 0, 0.1);
        height: 30px;
        margin-left: 1.5rem !important;
        padding-left: 1.5rem !important;
        display: flex !important;
        align-items: center !important;
    }

    #kt_header .btn-sm {
        display: inline-flex !important;
        align-items: center !important;
        flex-direction: row !important;
    }

    /* Désactivation de la sélection de texte pour tout le document */
    body {
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }

    /* Autoriser la sélection seulement dans les textareas pour la saisie */
    .premium-textarea {
        -webkit-user-select: text;
        -moz-user-select: text;
        -ms-user-select: text;
        user-select: text;
    }

    #kt_header {
        left: 0 !important;
        right: 0 !important;
        height: 60px !important;
        display: flex !important;
        align-items: center !important;
        padding: 0 20px !important;
        background-color: #ffffff !important;
        border-bottom: 1px solid #eff2f5 !important;
    }

    /* Fixer le footer en bas */
    #kt_footer {
        position: fixed !important;
        bottom: 0 !important;
        left: 0 !important;
        right: 0 !important;
        width: 100% !important;
        z-index: 1000 !important;
        background: rgba(255, 255, 255, 0.9) !important;
        backdrop-filter: blur(10px);
        border-top: 1px solid rgba(0, 0, 0, 0.05) !important;
        padding: 1rem 2rem !important;
        height: auto !important;
    }

    /* Ajuster le wrapper pour ne pas être caché par le footer */
    #kt_wrapper {
        padding-bottom: 60px !important;
    }

    :root {
        --cfao-red: #D70006;
        --cfao-blue: #24235c;
        --glass-bg: rgba(255, 255, 255, 0.9);
        --glass-border: rgba(226, 232, 240, 0.8);
        --shadow-premium: 0 10px 25px rgba(0, 0, 0, 0.05);
    }

    .open-q-container {
        width: 100%;
        max-width: 1200px;
        height: calc(100vh - 120px); /* header 60px + footer ~60px */
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        padding: 0.4rem 1rem 1rem;
    }

    /* Styles pour l'écran d'accueil avec effet Glassmorphism */
    .welcome-screen {
        background: rgba(255, 255, 255, 0.82);
        backdrop-filter: blur(20px) saturate(180%);
        -webkit-backdrop-filter: blur(20px) saturate(180%);
        border-radius: 24px;
        padding: 1.8rem 3rem 1.5rem;
        text-align: center;
        max-width: 1000px;
        width: 100%;
        margin: auto;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-top: 8px solid var(--cfao-red);
        max-height: calc(100vh - 140px);
        overflow-y: auto;
    }

    .welcome-title {
        color: var(--cfao-blue);
        font-weight: 850;
        font-size: 1.7rem;
        margin-bottom: 0.4rem;
    }

    .welcome-text {
        font-size: 0.92rem;
        color: #4a5568;
        line-height: 1.5;
        margin-bottom: 1rem;
        max-width: 650px;
        margin-left: auto;
        margin-right: auto;
    }

    .questionnaire-previews {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .q-preview-card {
        background: rgba(255, 255, 255, 0.6);
        backdrop-filter: blur(15px);
        padding: 2rem;
        border-radius: 25px;
        border: 1px solid rgba(255, 255, 255, 0.4);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
    }

    .q-preview-card:hover {
        transform: translateY(-8px);
        border-color: var(--cfao-red);
        background: rgba(255, 255, 255, 0.8);
        box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    }

    .q-preview-card i {
        font-size: 3rem;
        color: var(--cfao-red);
        margin-bottom: 1.2rem;
        filter: drop-shadow(0 4px 8px rgba(215, 0, 6, 0.2));
    }

    .q-preview-card h3 {
        font-weight: 800;
        color: var(--cfao-blue);
        font-size: 1.4rem;
        margin-bottom: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .q-preview-card .btn-outline-primary {
        border: 2px solid var(--cfao-red);
        color: var(--cfao-red);
        font-weight: 700;
        border-radius: 30px;
        padding: 0.6rem 1.5rem;
        transition: all 0.3s;
    }

    .q-preview-card .btn-outline-primary:hover {
        background: var(--cfao-red);
        color: white;
        box-shadow: 0 5px 15px rgba(215, 0, 6, 0.3);
    }

    .section-tag {
        display: inline-block;
        background: #edf2f7;
        padding: 0.2rem 0.8rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        margin: 0.2rem;
    }

    .btn-start-all {
        background: var(--cfao-red);
        color: white;
        padding: 1rem 3rem;
        font-size: 1.2rem;
        font-weight: 800;
        border-radius: 50px;
        border: none;
        box-shadow: 0 10px 25px rgba(215, 0, 6, 0.3);
        transition: all 0.3s;
        cursor: pointer;
    }

    .btn-start-all:hover {
        transform: scale(1.05);
        background: #b50005;
        box-shadow: 0 15px 35px rgba(215, 0, 6, 0.4);
    }

    /* Transition de section */
    .section-header-display {
        background: #f1f5f9;
        padding: 0.8rem 1.5rem;
        border-left: 4px solid var(--cfao-red);
        margin-bottom: 2rem;
        font-weight: 800;
        color: var(--cfao-blue);
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Nouvelle Entête Spécifique - Restructurée avec Logos */
    .page-main-header {
        background: white;
        border-radius: 12px;
        padding: 1.2rem 2.5rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: row; /* Horizontal */
        justify-content: space-between; /* Espace entre les logos et le texte */
        align-items: center;
        border-top: 6px solid var(--cfao-red);
        flex-shrink: 0;
    }

    .header-logo {
        max-height: 60px;
        width: auto;
        object-fit: contain;
    }

    .header-info {
        flex: 1;
        text-align: center;
        padding: 0 2rem;
    }

    .header-info h2 {
        color: var(--cfao-blue);
        font-weight: 800;
        font-size: 1.8rem;
        margin: 0 0 0.5rem 0;
        text-transform: uppercase;
    }

    .header-badge {
        background: rgba(215, 0, 6, 0.08);
        color: var(--cfao-red);
        padding: 0.4rem 1.2rem;
        border-radius: 30px;
        font-weight: 700;
        font-size: 0.8rem;
        border: 1px solid rgba(215, 0, 6, 0.15);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .premium-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        box-shadow: var(--shadow-premium);
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .q-header {
        background: var(--cfao-blue);
        color: #ffffff !important;
        padding: 1.5rem 2.5rem;
        flex-shrink: 0;
        text-align: center;
    }

    .q-header h1 {
        font-weight: 700;
        font-size: 1.4rem;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #ffffff !important;
    }

    .q-header p {
        color: rgba(255, 255, 255, 0.8) !important;
        margin-top: 5px;
    }

    .q-content {
        padding: 2rem 3rem;
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow-y: auto; /* Permet le scroll interne si besoin */
    }

    .question-box {
        flex: 1;
        display: none;
        flex-direction: column;
    }

    .question-box.active {
        display: flex;
    }

    .premium-textarea {
        flex: 1; /* Prend tout l'espace restant */
        width: 100%;
        background: white;
        border: 2px solid #edf2f7;
        border-radius: 12px;
        padding: 1.5rem;
        font-size: 1.1rem;
        color: #2d3748;
        resize: none;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
    }

    .nav-buttons {
        min-height: 90px;
        padding: 0 3rem !important; /* Un peu plus de marge à droite */
        background: #f8fafc;
        border-top: 1px solid #edf2f7;
        display: flex !important;
        justify-content: flex-end !important; /* Alignement à droite */
        align-items: center !important;
        gap: 1.5rem;
        flex-shrink: 0;
        width: 100%;
        margin: 0 !important;
    }

    form {
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        flex: 1;
    }

    .progress-wrapper {
        margin-top: 0.8rem;
        color: #ffffff !important;
    }

    .custom-progress {
        height: 6px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 3px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: var(--cfao-red);
        width: 0%;
        transition: width 0.6s ease;
    }

    .q-content {
        padding: 3rem;
        min-height: 400px;
    }

    .question-box {
        display: none;
        animation: fadeInSlide 0.5s ease-out forwards;
    }

    .question-box.active {
        display: block;
    }

    @keyframes fadeInSlide {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .question-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #24235c;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .question-title .number {
        background: var(--cfao-red);
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .question-text {
        font-size: 1.15rem;
        color: #4a5568;
        line-height: 1.6;
        margin-bottom: 2rem;
    }

    .premium-textarea {
        width: 100%;
        min-height: 200px;
        background: rgba(255, 255, 255, 0.8);
        border: 2px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.5rem;
        font-size: 1.1rem;
        color: #2d3748;
        transition: all 0.3s ease;
        resize: vertical;
    }

    .premium-textarea:focus {
        outline: none;
        border-color: var(--cfao-red);
        box-shadow: 0 0 0 4px rgba(215, 0, 6, 0.1);
        background: white;
    }

    .char-counter {
        text-align: right;
        margin-top: 0.75rem;
        font-size: 0.9rem;
        color: #718096;
        font-weight: 500;
    }

    .char-counter.valid { color: #38a169; }
    .char-counter.invalid { color: #e53e3e; }

    .nav-buttons {
        display: flex;
        justify-content: space-between;
        margin-top: 3rem;
        padding: 0 3rem 3rem;
    }

    .btn-premium {
        padding: 0.8rem 2rem;
        border-radius: 12px;
        font-weight: 700;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: none;
        margin: 0 !important; /* Pas de marge parasite */
    }

    .btn-prev {
        background: #edf2f7;
        color: #4a5568;
    }

    .btn-prev:hover {
        background: #e2e8f0;
        transform: translateX(-5px);
    }

    .btn-next {
        background: var(--cfao-red);
        color: white;
        box-shadow: 0 4px 12px rgba(215, 0, 6, 0.2);
    }

    .btn-next:hover:not(:disabled) {
        background: #b50005;
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(215, 0, 6, 0.3);
    }

    .btn-next:disabled {
        background: #e2e8f0;
        color: #94a3b8 !important;
        cursor: not-allowed;
        box-shadow: none;
        opacity: 1;
    }

    .btn-submit {
        background: #38a169;
        color: white;
        box-shadow: 0 10px 20px rgba(56, 161, 105, 0.2);
    }

    .btn-submit:hover {
        background: #2f855a;
        transform: scale(1.05);
    }

    /* Sidebar dots adaptation */
    .dots-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        padding: 1.5rem;
    }

    .dot-item {
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        font-weight: 700;
        color: #718096;
    }

    .dot-item.active {
        border-color: var(--cfao-red);
        background: rgba(215, 0, 6, 0.05);
        color: var(--cfao-red);
        transform: scale(1.05);
    }

    .dot-item.completed {
        background: #38a169;
        border-color: #38a169;
        color: white;
    }

    /* Overlay de sécurité pour le plein écran - Thème CFAO */
    #fullscreenRequiredOverlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(36, 35, 92, 0.98); /* var(--cfao-blue) avec opacité */
        z-index: 9999;
        display: none;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        color: white;
        text-align: center;
        backdrop-filter: blur(15px);
    }

    #fullscreenRequiredOverlay h2 {
        font-size: 2.8rem;
        margin-bottom: 1.5rem;
        font-weight: 800;
        color: #ffffff;
        text-transform: uppercase;
        letter-spacing: 2px;
    }

    #fullscreenRequiredOverlay p {
        font-size: 1.3rem;
        max-width: 700px;
        margin-bottom: 3rem;
        opacity: 0.9;
        line-height: 1.6;
    }

    #fullscreenRequiredOverlay i {
        color: var(--cfao-red);
        filter: drop-shadow(0 0 15px rgba(215, 0, 6, 0.4));
    }

    /* Overlay d'exclusion définitive */
    #bannedOverlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: #d70006;
        z-index: 10000;
        display: none;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        color: white;
        text-align: center;
        padding: 2rem;
    }

    .security-stats {
        background: rgba(255, 255, 255, 0.1);
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.9rem;
        display: flex;
        gap: 15px;
    }

    .q-timer-alert {
        color: #ffeb3b;
        font-weight: 800;
        animation: blink 1s infinite;
    }

    @keyframes blink {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }

    @media (max-width: 768px) {
        .q-content { padding: 2rem; }
        .nav-buttons { padding: 0 2rem 2rem; flex-direction: column; gap: 1rem; }
        .btn-premium { width: 100%; justify-content: center; }
    }
    /* Custom Nav Grid for questions */
    .nav-grid {
        position: relative;
        padding: 10px;
        margin-bottom: 20px;
        width: 100%;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: center;
        background: rgba(255, 255, 255, 0.5);
        border-radius: 12px;
        border: 1px solid rgba(226, 232, 240, 0.8);
    }
    
    .question-nav-button {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        border: 2px solid transparent;
        background: #f1f5f9;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.95rem;
        font-weight: 700;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        flex-shrink: 0;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    
    .question-nav-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .question-nav-button.answered {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        border-color: #059669;
    }
    
    .question-nav-button.active {
        background: linear-gradient(135deg, var(--cfao-red), #b30005);
        color: white;
        transform: scale(1.15);
        box-shadow: 0 0 0 3px rgba(215, 0, 6, 0.2);
        border-color: transparent;
        z-index: 2;
    }
</style>

<div class="open-q-container" id="mainContainer">
    <?php if ($isSubmitted): ?>
            <div class="container-fluid d-flex align-items-center justify-content-center" style="height: 100vh; position: fixed; z-index: 100000; width: 100vw; top: 0; left: 0; background: rgba(0,0,0,0.55); backdrop-filter: blur(6px);">
                <div class="premium-card text-center p-5 animate__animated animate__zoomIn" style="max-width: 600px; background: rgba(255,255,255,0.9); border-radius: 30px; box-shadow: 0 20px 60px rgba(0,0,0,0.15);">
                    <div class="mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                    </div>
                    <h2 class="font-weight-bold text-dark mb-3" data-i18n="success_title"><?php echo $txt['success_title']; ?></h2>
                    <p class="text-muted mb-4" data-i18n="success_desc"><?php echo $txt['success_desc']; ?></p>
                    <?php if ($isAdminSession): ?>
                            <a href="responses.php" class="btn-start-all mx-auto mt-4" style="text-decoration: none; display: inline-block;">
                                <i class="fas fa-list ms-2 me-2"></i> <?php echo $currentLang === 'FR' ? 'Voir les réponses' : 'View Responses'; ?>
                            </a>
                            <a href="../views/portal.php" class="btn btn-light mt-3 d-block mx-auto" style="max-width:300px;">
                                <?php echo $currentLang === 'FR' ? 'Retour au portail MEDACAP' : 'Back to MEDACAP Portal'; ?>
                            </a>
                    <?php else: ?>
                            <a href="register.php?type=<?php echo urlencode($qType); ?>" class="btn-start-all mx-auto mt-4" style="text-decoration: none; display: inline-block;">
                                <i class="fas fa-home ms-2 me-2"></i> <?php echo $currentLang === 'FR' ? 'Terminer' : 'Close'; ?>
                            </a>
                    <?php endif; ?>
                </div>
            </div>
    <?php endif; ?>

    <!-- OVERLAY EXCLUSION -->
    <div id="bannedOverlay">
        <i class="fas fa-user-slash mb-4" style="font-size: 6rem;"></i>
        <h1 style="font-size: 3.5rem; font-weight: 900;" data-i18n="sec_banned_title"><?php echo $txt['sec_banned_title']; ?></h1>
        <p style="font-size: 1.5rem; max-width: 800px;" data-i18n="sec_banned_desc"><?php echo $txt['sec_banned_desc']; ?></p>
        <div class="mt-4 p-3 border rounded" data-i18n="sec_banned_reason">
            <?php echo $txt['sec_banned_reason']; ?>
        </div>
    </div>

    <!-- OVERLAY SECURITE PLEIN ECRAN -->
    <div id="fullscreenRequiredOverlay">
        <i id="securityIcon" class="fas fa-expand-arrows-alt mb-4" style="font-size: 5rem; color: var(--cfao-red);"></i>
        <h2 id="securityTitle"><?php echo $txt['sec_fullscreen_title']; ?></h2>
        <p id="securityMessage"><?php echo $txt['sec_fullscreen_desc']; ?></p>
        
        <div id="penaltyTimerContainer" class="mb-4" style="display: none; background: rgba(255,255,255,0.1); padding: 15px 30px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.2);">
            <i class="fas fa-clock me-2 text-warning"></i> 
            <span data-i18n="sec_penalty_label"><?php echo $txt['sec_penalty_label']; ?></span> <span id="penaltyTimerText" style="font-weight: 800; font-size: 1.5rem;">10</span>s
        </div>

        <button class="btn-start-all" onclick="enterFullscreen()">
            <span data-i18n="sec_btn_reactivate"><?php echo $txt['sec_btn_reactivate']; ?></span> <i class="fas fa-check ms-2"></i>
        </button>
    </div>

    <!-- ECRAN D'ACCUEIL -->
    <div id="welcomeScreen" class="welcome-screen" <?php if ($isSubmitted)
        echo 'style="display:none;"'; ?>>
        <?php if ($preSelectedType): ?>
                <i class="fas fa-shield-alt mb-4" style="font-size: 4rem; color: var(--cfao-red);"></i>
                <h1 class="welcome-title" data-i18n="welcome_instructions"><?php echo $txt['welcome_instructions']; ?></h1>
                <p class="welcome-text" id="welcomeSubtitleContainer">
                    <?php
                    $brandKey = ($currentLang === 'EN' && isset($questionnairesData[$preSelectedType]['brand_EN'])) ? 'brand_EN' : 'brand';
                    echo sprintf($txt['welcome_subtitle'], $questionnairesData[$preSelectedType][$brandKey]);
                    ?>
                </p>

                <div class="row justify-content-center mb-5">
                    <div class="col-md-10">
                        <div class="q-preview-card text-start" style="background: rgba(36, 35, 92, 0.03); border: 1px solid rgba(36, 35, 92, 0.1);">
                            <ul class="list-unstyled" style="font-size: 1.05rem; line-height: 1.8;">
                                <li class="mb-3">
                                    <i class="fas fa-hourglass-half me-3 text-warning"></i> 
                                    <strong data-i18n="rule_chrono"><?php echo $txt['rule_chrono']; ?></strong> <span data-i18n="rule_chrono_desc"><?php echo $txt['rule_chrono_desc']; ?></span>
                                </li>
                                <li class="mb-3">
                                    <i class="fas fa-expand me-3 text-primary"></i> 
                                    <strong data-i18n="rule_fullscreen"><?php echo $txt['rule_fullscreen']; ?></strong> <span data-i18n="rule_fullscreen_desc"><?php echo $txt['rule_fullscreen_desc']; ?></span>
                                </li>
                                <li class="mb-3">
                                    <i class="fas fa-exclamation-triangle me-3 text-danger"></i> 
                                    <strong data-i18n="rule_tab"><?php echo $txt['rule_tab']; ?></strong> <span data-i18n="rule_tab_desc"><?php echo $txt['rule_tab_desc']; ?></span>
                                </li>
                                <li class="mb-3">
                                    <i class="fas fa-ban me-3 text-danger"></i> 
                                    <strong data-i18n="rule_copy"><?php echo $txt['rule_copy']; ?></strong> <span data-i18n="rule_copy_desc"><?php echo $txt['rule_copy_desc']; ?></span>
                                </li>
                            </ul>
                            <div class="alert alert-danger mt-3 mb-0" style="background: rgba(215, 0, 6, 0.1); border: none; color: var(--cfao-red); font-weight: 700;">
                                <i class="fas fa-exclamation-circle me-2"></i> <span data-i18n="rule_ban_warning"><?php echo $txt['rule_ban_warning']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn-start-all" onclick="startEvaluation()">
                    <span data-i18n="btn_accept"><?php echo $txt['btn_accept']; ?></span> <i class="fas fa-check-circle ms-2"></i>
                </button>

        <?php else: ?>
                <i class="fas fa-clipboard-check mb-4" style="font-size: 4rem; color: var(--cfao-red);"></i>
                <h1 class="welcome-title" data-i18n="admin_portal"><?php echo $txt['admin_portal']; ?></h1>
                <p class="welcome-text" data-i18n="admin_desc">
                    <?php echo $txt['admin_desc']; ?>
                </p>

                <div class="questionnaire-previews">
                    <div class="q-preview-card">
                        <img src="<?php echo $questionnairesData['sinotruk']['logo']; ?>" alt="Sinotruk" class="brand-logo-preview mb-3" style="max-height: 80px; width: auto; object-fit: contain;">
                        <h3>Sinotruk</h3>
                        <p class="small text-muted"><?php echo $currentLang === 'FR' ? 'Gamme Poids Lourds' : 'Heavy Duty Range'; ?></p>
                        <a href="?lang=<?php echo $currentLang; ?>&type=sinotruk" class="btn btn-sm btn-outline-primary mt-3" data-q-lang="sinotruk"><?php echo $currentLang === 'FR' ? 'Ouvrir le lien' : 'Open link'; ?></a>
                    </div>
                    <div class="q-preview-card">
                        <img src="<?php echo $questionnairesData['bus_chariot']['logo']; ?>" alt="King Long" class="brand-logo-preview mb-3" style="max-height: 80px; width: auto; object-fit: contain;">
                        <h3>Bus & Chariots</h3>
                        <p class="small text-muted"><?php echo $currentLang === 'FR' ? 'Manutention et Transport' : 'Material Handling & Transport'; ?></p>
                        <a href="?lang=<?php echo $currentLang; ?>&type=bus_chariot" class="btn btn-sm btn-outline-primary mt-3" data-q-lang="bus_chariot"><?php echo $currentLang === 'FR' ? 'Ouvrir le lien' : 'Open link'; ?></a>
                    </div>
                </div>
        <?php endif; ?>
    </div>

    <!-- CONTENU DU QUESTIONNAIRE (Masqué au début) -->
    <div id="questionnaireContent" style="display: none; height: 100%; flex-direction: column;">
        <!-- Header Spécifique de la Page - Avec Logos -->
        <div class="page-main-header">
            <!-- Logo Gauche: CFAO -->
            <img src="../public/images/logo-cfao.png" alt="CFAO" class="header-logo">

            <!-- Infos Centrales -->
            <div class="header-info">
                <h2 id="currentQTitle" data-i18n="q_header_title"><?php echo $txt['q_header_title']; ?></h2>
                <p class="mb-0" id="currentQBrand">BRAND</p>
            </div>

            <!-- Logo Droite: Marque -->
            <img src="" alt="Brand" class="header-logo" id="currentQLogo">
        </div>

        <div class="premium-card">
            <!-- Header Questionnaire -->
            <div class="q-header">
                <h1 data-i18n="q_main_title"><?php echo $txt['q_main_title']; ?></h1>
                <p data-i18n="q_main_desc"><?php echo $txt['q_main_desc']; ?></p>
                
                <div class="progress-wrapper">
                    <div class="d-flex justify-content-between align-items-center small font-weight-bold" style="color: #ffffff;">
                        <div id="timer-display">
                            <i class="fas fa-stopwatch me-1"></i> <span data-i18n="timer_global"><?php echo $txt['timer_global']; ?></span> <span id="timer-text">00:00</span>
                        </div>
                        
                        <div id="question-timer-container" class="q-timer-alert">
                            <i class="fas fa-hourglass-start me-1"></i> <span data-i18n="timer_question"><?php echo $txt['timer_question']; ?></span> <span id="q-timer-text">02:00</span>
                        </div>

                        <div class="security-stats">
                            <span><i class="fas fa-shield-alt me-1"></i> <span data-i18n="stats_alerts"><?php echo $txt['stats_alerts']; ?></span> <span id="alert-count">0</span>/3</span>
                            <span><span data-i18n="stats_q_count"><?php echo $txt['stats_q_count']; ?></span> <span id="current-q-index">1</span>/<span id="total-q-count">0</span></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigator -->
            <div class="nav-grid" id="navGrid" style="display: none;">
                <!-- Rempli dynamiquement -->
            </div>

            <!-- Content -->
            <form id="questionnaireForm" method="POST" action="">
                <input type="hidden" name="questionnaire_type" id="hiddenQType" value="<?php echo htmlspecialchars($preSelectedType ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="violations"         id="hiddenViolations" value="0">
                <input type="hidden" name="total_time"         id="hiddenTotalTime"  value="0">
                <input type="hidden" name="is_banned"          id="hiddenIsBanned"   value="0">
                <div class="q-content" id="questionsWrapper">
                    <?php
                    $allQuestions = [];
                    $targetData = $questionnairesData;

                    // Si un type est pré-sélectionné, on ne garde que celui-là
                    if ($preSelectedType) {
                        $targetData = [$preSelectedType => $questionnairesData[$preSelectedType]];
                    }

                    foreach ($targetData as $qKey => $qData) {
                        foreach ($qData['sections'] as $section) {
                            foreach ($section['questions'] as $question) {
                                $question['qKey'] = $qKey;
                                $question['sectionTitle_FR'] = $section['title_FR'];
                                $question['sectionTitle_EN'] = $section['title_EN'];
                                $allQuestions[] = $question;
                            }
                        }
                    }
                    ?>

                    <?php foreach ($allQuestions as $index => $q): ?>
                            <div class="question-box" data-index="<?php echo $index; ?>" data-qkey="<?php echo $q['qKey']; ?>" id="q-box-<?php echo $index; ?>">
                                <div class="section-header-display">
                                    <i class="fas fa-layer-group me-2"></i> <span><?php echo $q['sectionTitle_' . $currentLang]; ?></span>
                                </div>
                            
                                <div class="question-title">
                                    <span class="number"><?php echo $index + 1; ?></span>
                                    <span><?php echo $q['text_' . $currentLang]; ?></span>
                                </div>
                            
                                <textarea 
                                    name="answers[<?php echo $q['id']; ?>]" 
                                    class="premium-textarea" 
                                    placeholder="<?php echo $txt['placeholder']; ?>"
                                    data-min="<?php echo $q['minChars']; ?>"
                                    oninput="updateCounter(this, <?php echo $index; ?>)"
                                    onpaste="return false;" 
                                    oncopy="return false;" 
                                    oncut="return false;"
                                    oncontextmenu="return false;"
                                ></textarea>
                            
                                <div class="char-counter" id="counter-<?php echo $index; ?>">
                                    0 / <?php echo $q['minChars']; ?>     <?php echo $txt['min_chars']; ?>
                                </div>
                            </div>
                    <?php endforeach; ?>
                </div>

                <!-- Footer / Navigation -->
                <div class="nav-buttons">
                    <button type="button" class="btn-premium btn-prev" id="prevBtn" onclick="changeQuestion(-1)">
                        <i class="fas fa-arrow-left"></i> <span data-i18n="btn_prev"><?php echo $txt['btn_prev']; ?></span>
                    </button>
                    
                    <button type="button" class="btn-premium btn-next" id="nextBtn" onclick="changeQuestion(1)">
                        <span data-i18n="btn_next"><?php echo $txt['btn_next']; ?></span> <i class="fas fa-arrow-right"></i>
                    </button>
                    
                    <button type="submit" class="btn-premium btn-submit" id="submitBtn" style="display: none;">
                        <i class="fas fa-check-circle"></i> <span data-i18n="btn_submit"><?php echo $txt['btn_submit']; ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const isSubmitted = <?php echo $isSubmitted ? 'true' : 'false'; ?>;
    const i18n = <?php echo json_encode($i18n); ?>;
    let currentLang = '<?php echo $currentLang; ?>';
    const questionnairesData = <?php echo json_encode($questionnairesData); ?>;
    const currentType = '<?php echo $preSelectedType; ?>';

    // Initialiser le titre du header dès le chargement (sans attendre un changement de langue)
    document.addEventListener('DOMContentLoaded', function() {
        var headerTitle = document.querySelector('.title-banner h1');
        if (headerTitle) {
            headerTitle.innerText = currentLang === 'FR'
                ? "QUESTIONNAIRE COMMERCIAL : SINOTRUK, BUS ET CHARIOTS"
                : "SALES ASSESSMENT: SINOTRUK, BUS & FORKLIFTS";
        }
    });

    function setLanguage(lang) {
        lang = lang.toUpperCase();
        if (!i18n[lang]) return;
        currentLang = lang;
        const txt = i18n[lang];

        // Mettre à jour les textes data-i18n
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            if (txt[key]) el.innerHTML = txt[key];
        });

        // Mettre à jour le sous-titre (avec sprintf)
        if (currentType && document.getElementById('welcomeSubtitleContainer')) {
            const brand = (lang === 'EN' && questionnairesData[currentType].brand_EN)
                ? questionnairesData[currentType].brand_EN
                : questionnairesData[currentType].brand;
            document.getElementById('welcomeSubtitleContainer').innerHTML = txt.welcome_subtitle.replace('%s', brand);
        }

        // Mettre à jour le titre du header (module_measure)
        const headerTitle = document.querySelector('.title-banner h1');
        if (headerTitle) {
            headerTitle.innerText = lang === 'FR' ? 
                "QUESTIONNAIRE COMMERCIAL : SINOTRUK, BUS ET CHARIOTS" : 
                "SALES ASSESSMENT: SINOTRUK, BUS & FORKLIFTS";
        }

        // Mettre à jour les questions
        document.querySelectorAll('.question-box').forEach(box => {
            const index = parseInt(box.getAttribute('data-index'));
            const qkey = box.getAttribute('data-qkey');
            
            let found = false;
            let qIndex = 0;
            const data = questionnairesData[qkey];
            if (data) {
                for (let s in data.sections) {
                    for (let q of data.sections[s].questions) {
                        if (qIndex === index) {
                            const titleEl = box.querySelector('.question-title span:last-child');
                            if (titleEl) titleEl.innerText = q['text_' + lang];
                            
                            const textarea = box.querySelector('.premium-textarea');
                            if (textarea) textarea.placeholder = txt.placeholder;
                            
                            const counter = box.querySelector('.char-counter');
                            if (counter) counter.innerHTML = `0 / ${q.minChars} ${txt.min_chars}`;
                            
                            const sectionHeader = box.querySelector('.section-header-display span');
                            if (sectionHeader) sectionHeader.innerText = data.sections[s]['title_' + lang];
                            
                            found = true; break;
                        }
                        qIndex++;
                    }
                    if (found) break;
                }
            }
        });

        // Mettre à jour les liens admin (Ouvrir le lien)
        document.querySelectorAll('[data-q-lang]').forEach(link => {
            link.innerText = lang === 'FR' ? 'Ouvrir le lien' : 'Open link';
            const url = new URL(link.href, window.location.origin);
            url.searchParams.set('lang', lang);
            link.href = url.search + url.hash;
        });

        // Mettre à jour l'URL sans rafraîchir
        const url = new URL(window.location);
        url.searchParams.set('lang', lang);
        window.history.pushState({}, '', url);
    }

    function selectQuestionnaire(type) {
        const data = questionnairesData[type];
        if (!data) return;

        const txt = i18n[currentLang];
        
        // Mettre à jour les logos et éléments spécifiques si besoin
        // (L'écran d'accueil change d'état : de portail à consignes)
        const welcomeScreen = document.getElementById('welcomeScreen');
        welcomeScreen.innerHTML = `
            <i class="fas fa-shield-alt mb-4" style="font-size: 4rem; color: var(--cfao-red);"></i>
            <h1 class="welcome-title" data-i18n="welcome_instructions">${txt.welcome_instructions}</h1>
            <p class="welcome-text" id="welcomeSubtitleContainer">
                ${txt.welcome_subtitle.replace('%s', (currentLang === 'EN' && data.brand_EN) ? data.brand_EN : data.brand)}
            </p>

            <div class="row justify-content-center mb-5">
                <div class="col-md-10">
                    <div class="q-preview-card text-start" style="background: rgba(36, 35, 92, 0.03); border: 1px solid rgba(36, 35, 92, 0.1);">
                        <ul class="list-unstyled" style="font-size: 1.05rem; line-height: 1.8;">
                            <li class="mb-3">
                                <i class="fas fa-hourglass-half me-3 text-warning"></i> 
                                <strong data-i18n="rule_chrono">${txt.rule_chrono}</strong> <span data-i18n="rule_chrono_desc">${txt.rule_chrono_desc}</span>
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-expand me-3 text-primary"></i> 
                                <strong data-i18n="rule_fullscreen">${txt.rule_fullscreen}</strong> <span data-i18n="rule_fullscreen_desc">${txt.rule_fullscreen_desc}</span>
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-exclamation-triangle me-3 text-danger"></i> 
                                <strong data-i18n="rule_tab">${txt.rule_tab}</strong> <span data-i18n="rule_tab_desc">${txt.rule_tab_desc}</span>
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-ban me-3 text-danger"></i> 
                                <strong data-i18n="rule_copy">${txt.rule_copy}</strong> <span data-i18n="rule_copy_desc">${txt.rule_copy_desc}</span>
                            </li>
                        </ul>
                        <div class="alert alert-danger mt-3 mb-0" style="background: rgba(215, 0, 6, 0.1); border: none; color: var(--cfao-red); font-weight: 700;">
                            <i class="fas fa-exclamation-circle me-2"></i> <span data-i18n="rule_ban_warning">${txt.rule_ban_warning}</span>
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" class="btn-start-all" onclick="startEvaluation()">
                <span data-i18n="btn_accept">${txt.btn_accept}</span> <i class="fas fa-check-circle ms-2"></i>
            </button>
        `;

        // Mettre à jour l'URL sans rafraîchir
        const url = new URL(window.location);
        url.searchParams.set('type', type);
        window.history.pushState({type: type}, '', url);
        
        // Filtrer les questions visibles
        document.querySelectorAll('.question-box').forEach(box => {
            if (box.getAttribute('data-qkey') === type) {
                box.style.display = 'none'; // Sera géré par changeQuestion
            } else {
                box.remove(); // On retire les questions des autres types pour cette session
            }
        });
        
        // Re-calculer le total
        totalQuestions = document.querySelectorAll('.question-box').length;
        totalQCountDisplay.innerText = totalQuestions;
    }

    // Intercepter les clics
    document.addEventListener('click', (e) => {
        // 1. Boutons de changement de langue (formulaire dans le header)
        const langBtn = e.target.closest('button[name="lang"]');
        if (langBtn) {
            e.preventDefault();
            setLanguage(langBtn.value);
            
            // Mettre à jour visuellement les boutons actifs du header
            document.querySelectorAll('button[name="lang"]').forEach(btn => {
                if (btn.value === langBtn.value) {
                    btn.classList.replace('btn-light', 'btn-primary');
                } else {
                    btn.classList.replace('btn-primary', 'btn-light');
                }
            });
            return;
        }

        // 2. Liens de sélection de questionnaire
        const link = e.target.closest('a');
        if (!link) return;

        const url = new URL(link.href, window.location.origin);
        
        if (url.searchParams.has('type') && !url.searchParams.has('lang')) {
            e.preventDefault();
            selectQuestionnaire(url.searchParams.get('type'));
        } else if (url.searchParams.has('type') && url.searchParams.has('lang')) {
            // Cas où le lien a les deux, on empêche le rechargement
            e.preventDefault();
            selectQuestionnaire(url.searchParams.get('type'));
        }
    });

    // Prévenir le rafraîchissement accidentel
    let formIsSubmitting = false;
    if (!isSubmitted) {
        window.addEventListener('beforeunload', (e) => {
            if (!formIsSubmitting && questionnaireContent.style.display === 'flex' && bannedOverlay.style.display !== 'flex') {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    let currentIndex = 0;
    let timerInterval = null;
    let qTimerInterval = null;
    let secondsElapsed = 0;
    let qSecondsRemaining = 120;
    let alertCount = 0;
    const maxAlerts = 3;

    let penaltyInterval = null;
    let penaltySeconds = 10;

    const overlay = document.getElementById('fullscreenRequiredOverlay');
    const securityTitle = document.getElementById('securityTitle');
    const securityMessage = document.getElementById('securityMessage');
    const securityIcon = document.getElementById('securityIcon');
    const penaltyContainer = document.getElementById('penaltyTimerContainer');
    const penaltyText = document.getElementById('penaltyTimerText');

    const boxes = document.querySelectorAll('.question-box');
    let totalQuestions = boxes.length;
    
    const welcomeScreen = document.getElementById('welcomeScreen');
    const questionnaireContent = document.getElementById('questionnaireContent');
    const bannedOverlay = document.getElementById('bannedOverlay');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    
    const timerText = document.getElementById('timer-text');
    const qTimerText = document.getElementById('q-timer-text');
    const alertCountDisplay = document.getElementById('alert-count');
    const currentQIndexDisplay = document.getElementById('current-q-index');
    const totalQCountDisplay = document.getElementById('total-q-count');

    const qTitle = document.getElementById('currentQTitle');
    const qBrand = document.getElementById('currentQBrand');
    const qLogo = document.getElementById('currentQLogo');

    function startEvaluation() {
        // Demande d'accès Webcam (Surveillance active)
        initWebcam();

        // Sécurité : Mode plein écran
        enterFullscreen();

        welcomeScreen.style.display = 'none';
        questionnaireContent.style.display = 'flex';
        
        totalQCountDisplay.innerText = totalQuestions;
        
        generateNavGrid();
        
        startGlobalTimer();
        startQuestionTimer();
        updateUI();
    }

    function generateNavGrid() {
        const navGrid = document.getElementById('navGrid');
        navGrid.innerHTML = '';
        navGrid.style.display = 'flex';
        
        for (let i = 0; i < totalQuestions; i++) {
            const btn = document.createElement('button');
            btn.className = 'question-nav-button';
            btn.innerText = i + 1;
            btn.type = 'button';
            btn.onclick = () => goToQuestion(i);
            btn.id = 'nav-btn-' + i;
            navGrid.appendChild(btn);
        }
    }

    function goToQuestion(index) {
        if (index === currentIndex) return;

        const currentBox = boxes[currentIndex];
        currentBox.style.opacity = '0';
        currentBox.style.transform = index > currentIndex ? 'translateX(-20px)' : 'translateX(20px)';

        setTimeout(() => {
            currentIndex = Math.max(0, Math.min(totalQuestions - 1, index));
            boxes.forEach(b => {
                b.style.opacity = '1';
                b.style.transform = 'none';
            });
            
            updateUI();
            startQuestionTimer(); // Reset timer pour la nouvelle question
            window.scrollTo(0, 0);
        }, 150);
    }

    async function initWebcam() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            // Le flux est actif, on pourrait l'afficher dans un petit coin ou juste le garder pour détection
            console.log("Webcam activée pour surveillance.");
        } catch (err) {
            console.warn("Accès webcam refusé ou non disponible.");
        }
    }

    function startGlobalTimer() {
        if (timerInterval) clearInterval(timerInterval);
        secondsElapsed = 0;
        timerInterval = setInterval(() => {
            secondsElapsed++;
            const mins = Math.floor(secondsElapsed / 60).toString().padStart(2, '0');
            const secs = (secondsElapsed % 60).toString().padStart(2, '0');
            timerText.innerText = `${mins}:${secs}`;
        }, 1000);
    }

    function startQuestionTimer() {
        if (qTimerInterval) clearInterval(qTimerInterval);
        qSecondsRemaining = 120;
        updateQTimerDisplay();
        
        qTimerInterval = setInterval(() => {
            qSecondsRemaining--;
            updateQTimerDisplay();
            
            if (qSecondsRemaining <= 0) {
                clearInterval(qTimerInterval);
                handleTimeOut();
            }
        }, 1000);
    }

    function updateQTimerDisplay() {
        const mins = Math.floor(qSecondsRemaining / 60).toString().padStart(2, '0');
        const secs = (qSecondsRemaining % 60).toString().padStart(2, '0');
        qTimerText.innerText = `${mins}:${secs}`;
        
        // Alerte visuelle si moins de 30s
        const container = document.getElementById('question-timer-container');
        if (qSecondsRemaining < 30) {
            container.style.color = '#ff5252';
            container.classList.add('q-timer-alert');
        } else {
            container.style.color = '#ffffff';
            container.classList.remove('q-timer-alert');
        }
    }

    function handleTimeOut() {
        if (currentIndex < totalQuestions - 1) {
            changeQuestion(1);
        } else {
            formIsSubmitting = true;
            document.getElementById('questionnaireForm').submit();
        }
    }

    function triggerSecurityOverlay(type) {
        if (questionnaireContent.style.display !== 'flex' || bannedOverlay.style.display === 'flex') return;

        overlay.style.display = 'flex';
        document.querySelectorAll('textarea').forEach(t => t.blur());

        // Configurer le message en fonction du type
        const txt = i18n[currentLang];
        switch(type) {
            case 'focus':
                securityTitle.innerText = txt.alert_focus_title;
                securityMessage.innerText = txt.alert_focus_desc;
                securityIcon.className = "fas fa-clone mb-4";
                startPenaltyTimer(txt.alert_focus_reason);
                break;
            case 'visibility':
                securityTitle.innerText = txt.alert_tab_title;
                securityMessage.innerText = txt.alert_tab_desc;
                securityIcon.className = "fas fa-external-link-alt mb-4";
                startPenaltyTimer(txt.alert_tab_reason);
                break;
            case 'fullscreen':
                securityTitle.innerText = txt.alert_fs_title;
                securityMessage.innerText = txt.alert_fs_desc;
                securityIcon.className = "fas fa-expand-arrows-alt mb-4";
                startPenaltyTimer(txt.alert_fs_reason);
                break;
        }
    }

    function startPenaltyTimer(reason) {
        if (penaltyInterval) clearInterval(penaltyInterval);
        
        penaltySeconds = 10;
        penaltyText.innerText = penaltySeconds;
        penaltyContainer.style.display = 'block';

        penaltyInterval = setInterval(() => {
            penaltySeconds--;
            penaltyText.innerText = penaltySeconds;

            if (penaltySeconds <= 0) {
                clearInterval(penaltyInterval);
                registerSecurityViolation(reason);
                penaltyContainer.style.display = 'none';
            }
        }, 1000);
    }

    function registerSecurityViolation(type) {
        if (questionnaireContent.style.display !== 'flex' || bannedOverlay.style.display === 'flex') return;
        
        alertCount++;
        alertCountDisplay.innerText = alertCount;
        
        if (alertCount >= maxAlerts) {
            terminateSession();
        } else {
            // Notification visuelle sur le compteur
            const alertContainer = document.getElementById('alert-count').parentElement;
            alertContainer.style.color = '#ff5252';
            alertContainer.style.transform = 'scale(1.2)';
            setTimeout(() => {
                alertContainer.style.color = 'inherit';
                alertContainer.style.transform = 'scale(1)';
            }, 2000);
        }
    }

    function terminateSession() {
        if (penaltyInterval) clearInterval(penaltyInterval);
        clearInterval(timerInterval);
        clearInterval(qTimerInterval);
        // Mise à jour des champs cachés avant soumission automatique
        document.getElementById('hiddenIsBanned').value   = '1';
        document.getElementById('hiddenViolations').value = alertCount;
        document.getElementById('hiddenTotalTime').value  = secondsElapsed;
        // Remplir le type si non encore défini (cas multi-questionnaire)
        if (!document.getElementById('hiddenQType').value && boxes.length > 0) {
            document.getElementById('hiddenQType').value = boxes[0].dataset.qkey || '';
        }
        formIsSubmitting = true;
        document.getElementById('questionnaireForm').submit();
    }

    function enterFullscreen() {
        const docEl = document.documentElement;
        if (docEl.requestFullscreen) docEl.requestFullscreen();
        else if (docEl.mozRequestFullScreen) docEl.mozRequestFullScreen();
        else if (docEl.webkitRequestFullscreen) docEl.webkitRequestFullscreen();
        else if (docEl.msRequestFullscreen) docEl.msRequestFullscreen();
        
        // Cacher l'overlay et stopper le timer de pénalité
        if (penaltyInterval) {
            clearInterval(penaltyInterval);
            penaltyInterval = null;
        }
        penaltyContainer.style.display = 'none';
        overlay.style.display = 'none';
    }

    function updateUI() {
        if (totalQuestions === 0) return;

        const currentBox = boxes[currentIndex];
        const qKey = currentBox.dataset.qkey;
        const qData = questionnairesData[qKey];

        const displayTitle = (currentLang === 'EN' && qData.title_EN) ? qData.title_EN : qData.title;
        const displayBrand = (currentLang === 'EN' && qData.brand_EN) ? qData.brand_EN : qData.brand;
        qTitle.innerText = displayTitle;
        qBrand.innerText = displayBrand;
        qLogo.src = qData.logo;
        qLogo.alt = displayBrand;

        boxes.forEach((box, i) => {
            box.style.display = i === currentIndex ? 'block' : 'none';
        });

        currentQIndexDisplay.innerText = currentIndex + 1;
        prevBtn.style.display = currentIndex === 0 ? 'none' : 'flex';
        
        if (currentIndex === totalQuestions - 1) {
            nextBtn.style.display = 'none';
            submitBtn.style.display = 'flex';
        } else {
            nextBtn.style.display = 'flex';
            submitBtn.style.display = 'none';
        }

        updateNavGridClasses();
        checkValidity();
        const currentTextarea = currentBox.querySelector('textarea');
        if (currentTextarea) setTimeout(() => currentTextarea.focus(), 100);
    }

    function updateNavGridClasses() {
        for (let i = 0; i < totalQuestions; i++) {
            const btn = document.getElementById('nav-btn-' + i);
            if (!btn) continue;
            
            const box = boxes[i];
            const textarea = box.querySelector('textarea');
            const min = parseInt(textarea.dataset.min);
            const isAnswered = textarea.value.length >= min;
            
            btn.className = 'question-nav-button';
            if (i === currentIndex) {
                btn.classList.add('active');
            } else if (isAnswered) {
                btn.classList.add('answered');
            }
        }
    }

    function changeQuestion(delta) {
        const currentBox = boxes[currentIndex];
        currentBox.style.opacity = '0';
        currentBox.style.transform = delta > 0 ? 'translateX(-20px)' : 'translateX(20px)';

        setTimeout(() => {
            currentIndex = Math.max(0, Math.min(totalQuestions - 1, currentIndex + delta));
            boxes.forEach(b => {
                b.style.opacity = '1';
                b.style.transform = 'none';
            });
            
            updateUI();
            startQuestionTimer(); // Reset timer pour la nouvelle question
            window.scrollTo(0, 0);
        }, 150);
    }

    function updateCounter(textarea, index) {
        const min = parseInt(textarea.dataset.min);
        const count = textarea.value.length;
        const counter = document.getElementById('counter-' + index);
        
        if (count >= min) {
            counter.classList.remove('invalid');
            counter.classList.add('valid');
            counter.innerHTML = `<i class="fas fa-check"></i> Requis atteint (${count})`;
        } else {
            counter.classList.remove('valid');
            counter.classList.add('invalid');
            counter.innerText = `${count} / ${min} caractères minimum`;
        }
        checkValidity();
    }

    function checkValidity() {
        if (totalQuestions === 0) return;
        const currentTextarea = boxes[currentIndex].querySelector('textarea');
        const min = parseInt(currentTextarea.dataset.min);
        nextBtn.disabled = currentTextarea.value.length < min;
        
        // Mettre à jour la couleur du bouton nav actuel si on tape
        updateNavGridClasses();
        
        let allValid = true;
        for (let i = 0; i < totalQuestions; i++) {
            const box = boxes[i];
            if (box.style.display === 'none' && box.parentElement === null) continue; // Si supprimé
            const t = box.querySelector('textarea');
            if (t && t.value.length < parseInt(t.dataset.min)) {
                allValid = false;
                break;
            }
        }
        submitBtn.disabled = !allValid;
    }

    // Mettre à jour violations et temps avant toute soumission normale
    document.getElementById('questionnaireForm').addEventListener('submit', function() {
        formIsSubmitting = true;
        document.getElementById('hiddenViolations').value = alertCount;
        document.getElementById('hiddenTotalTime').value  = secondsElapsed;
    });

    document.addEventListener('DOMContentLoaded', () => {
        const logoImg = document.querySelector('#kt_header img[alt="Logo"]');
        if (logoImg) {
            logoImg.src = "../public/images/logo-cfao.png";
            logoImg.parentElement.removeAttribute('href');
        }

        if (isSubmitted) return; // Ne pas appliquer la sécurité si soumis

        // KEYBOARD SECURITY
        document.addEventListener('keydown', (e) => {
            if (e.key === 'F12' || e.key === 'F11' || (e.ctrlKey && e.shiftKey && e.key === 'I') || (e.ctrlKey && e.key === 'u') || (e.ctrlKey && e.key === 'p') || (e.ctrlKey && e.key === 'c') || (e.ctrlKey && e.key === 'v') || (e.ctrlKey && e.key === 'x') || (e.key === 'Escape' && document.fullscreenElement)) {
                e.preventDefault();
                return false;
            }
            if (e.key === 'Enter' && e.ctrlKey) {
                if (currentIndex < totalQuestions - 1 && !nextBtn.disabled) changeQuestion(1);
                else if (currentIndex === totalQuestions - 1 && !submitBtn.disabled) { formIsSubmitting = true; document.getElementById('questionnaireForm').submit(); }
            }
        });

        // CLIPBOARD & CONTEXT MENU
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('copy', e => e.preventDefault());
        document.addEventListener('cut', e => e.preventDefault());
        document.addEventListener('paste', e => e.preventDefault());

        // TAB & FOCUS SECURITY
        window.addEventListener('blur', () => triggerSecurityOverlay('focus'));
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) triggerSecurityOverlay('visibility');
        });

        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement && questionnaireContent.style.display === 'flex') {
                triggerSecurityOverlay('fullscreen');
            } else if (document.fullscreenElement) {
                overlay.style.display = 'none';
                if (questionnaireContent.style.display === 'flex') {
                    const currentBox = boxes[currentIndex];
                    const textarea = currentBox.querySelector('textarea');
                    if (textarea) textarea.focus();
                }
            }
        });
    });
</script>




<?php if ($isCommercialSession && !$isSubmitted): ?>
<script>
(function() {
    function livePing() { fetch('live-ping.php', { method: 'POST' }).catch(function() {}); }
    setInterval(livePing, 60000);
})();
</script>
<?php endif; ?>
<?php include_once "partials/footer.php"; ?>
