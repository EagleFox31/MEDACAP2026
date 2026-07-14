<?php
session_start();
require_once __DIR__ . '/core/bootstrap.php';

if (!isset($_SESSION["concours_profile"])) {
    header("Location: index.php");
    exit();
}

$userId = new MongoDB\BSON\ObjectId($_SESSION["concours_id"]);
$connections = $databaseConcours->connections;

// Check if user has already completed the exam
$completedConnection = $connections->findOne(["user" => $userId, "status" => "Completed"]);
if ($completedConnection) {
    header("Location: congrat.php");
    exit();
}

if (isset($_GET['lang']) && in_array($_GET['lang'], ['en','fr'])) {
    $_SESSION['concours_lang'] = $_GET['lang'];
    setcookie('concours_lang', $_GET['lang'], time() + 31536000, '/');
}

// Rotate Session Token for Anti-Double-Login
$newToken = bin2hex(random_bytes(16));
$_SESSION["concours_session_token"] = $newToken;

$connections->updateOne(
    ["user" => $userId, "active" => true],
    ['$set' => ["session_token" => $newToken, "last_heartbeat" => time()]]
);

$questionsCol = $databaseConcours->questions;
$questionsCursor = $questionsCol->find([]);
$questions = [];
foreach ($questionsCursor as $doc) {
    $questions[] = $doc;
}
$totalQuestions = count($questions);

// Initialize Absolute Timer (e.g., 60 minutes)
if (!isset($_SESSION["concours_deadline"])) {
    $_SESSION["concours_deadline"] = time() + (60 * 60);
}
$deadline = $_SESSION["concours_deadline"];

$firstName = $_SESSION["concours_firstName"] ?? '';
$lastName = $_SESSION["concours_lastName"] ?? '';
$fullName = trim($firstName . ' ' . $lastName);

$lang = $_SESSION['concours_lang'] ?? ($_COOKIE['concours_lang'] ?? 'fr');
if ($lang === 'en') {
    $txtAuthReq = "Authorization Required";
    $txtAuthDesc = "To ensure exam integrity, you must grant camera access and enter Full Screen mode.";
    $txtAuthBtn = "Authorize and Start Exam";
    $txtCamError = "Camera access denied. (";
    $txtQuizTitle = "Suzuki Contest Questionnaire";
    $txtQuizWait = "Waiting for questions...";
} else {
    $txtAuthReq = "Autorisation Requise";
    $txtAuthDesc = "Pour garantir l'intégrité de l'examen, vous devez autoriser l'accès à votre caméra et passer en mode Plein Écran.";
    $txtAuthBtn = "Autoriser et Démarrer l'Épreuve";
    $txtCamError = "Accès à la caméra refusé. (";
    $txtQuizTitle = "Questionnaire Concours Suzuki";
    $txtQuizWait = "En attente de questions...";
}
$initial = mb_substr($firstName, 0, 1) ?: 'T';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Épreuve · Concours Suzuki — CFAO Academy</title>
  <link rel="icon" type="image/png" href="../public/images/logo-cfao.png" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <style>
    /* Exam Layout Improvements */
    body.theme-light { background-color: #f5f7fa; }
    .app-bg { display: none; } /* Hide the colorful bg for exams */
    
    .topbar.exam-header {
        position: fixed; top: 0; left: 0; width: 100%;
        background: #ffffff;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        z-index: 1000;
        padding: 0 2rem;
        height: 70px;
        display: flex; justify-content: space-between; align-items: center;
    }
    .exam-title {
        font-size: 1.25rem; font-weight: 700; color: #1a2332;
        position: absolute; left: 50%; transform: translateX(-50%);
    }
    
    @media (max-width: 768px) {
        .exam-title { display: none; }
    }
    
    .exam-main {
        margin-top: 100px;
        max-width: 900px;
        margin-left: auto; margin-right: auto;
        padding: 0 1rem;
    }
    .exam-card {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e1e4e8;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        min-height: 500px;
        display: flex; flex-direction: column;
        overflow: hidden;
    }
    .exam-card-header {
        background: #f8f9fa;
        border-bottom: 1px solid #e1e4e8;
        padding: 1rem 2rem;
        font-size: 0.9rem; font-weight: 600; color: #6c757d;
    }
    .exam-card-body {
        flex: 1;
        padding: 3rem 2rem;
        display: flex; flex-direction: column; justify-content: center; align-items: center;
        text-align: center;
    }
    .exam-card-footer {
        border-top: 1px solid #e1e4e8;
        padding: 1rem 2rem;
        background: #fafbfa;
        display: flex; justify-content: space-between;
    }
    
    .timer-badge {
        font-weight: bold; font-size: 1.1rem; 
        background: #eef2f5; color: #1a2332;
        padding: 0.4rem 1rem; border-radius: 20px;
        display: flex; align-items: center; gap: 0.5rem;
        border: 1px solid #d1d9e0;
        transition: all 0.3s ease;
    }
    .timer-badge.warning { background: #fff3cd; color: #856404; border-color: #ffeeba; }
    .timer-badge.danger { background: #f8d7da; color: #721c24; border-color: #f5c6cb; animation: pulse-danger 1.5s infinite; }
    
    @keyframes pulse-danger {
        0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }
    
    .spinner {
        width: 40px; height: 40px;
        border: 4px solid #f3f3f3; border-top: 4px solid #E4002B;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 1.5rem;
    }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    
    .q-layout { display: grid; grid-template-columns: 48% 52%; gap: 18px; align-items: stretch; flex: 1; margin-top: 1rem; }
    .q-left { min-width: 0; overflow: hidden; }
    .q-right { min-width: 0; min-height: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; }
    
    .contImage { display: flex; justify-content: center; align-items: center; width: 100%; height: 100%; border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0; }
    .img-fallback { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; color: #64748b; }
    .img-fallback img { width: 150px; max-width: 70%; height: auto; opacity: 0.9; }
    
    .questions-panel { background: #ffffff; padding: 1rem; border-bottom: 1px solid #e2e8f0; margin-bottom: 1.5rem; border-radius: 8px; }
    .questions-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .questions-title { font-weight: 700; color: #0f172a; }
    .q-nav-wrap { display: grid; grid-template-columns: 36px minmax(0, 1fr) 36px; gap: 10px; align-items: center; }
    .q-grid { display: flex; justify-content: flex-start; gap: 8px; overflow-x: auto; padding: 4px 1px; scrollbar-width: thin; }
    .q-dot { display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; border: 1.5px solid #cbd5e1; color: #64748b; font-weight: 700; cursor: pointer; background: #fff; transition: all 0.2s; }
    .q-dot:hover { transform: translateY(-2px); }
    .q-dot.done { background: #2ecc71; border-color: #2ecc71; color: #fff; }
    .q-dot.current { background: #E4002B; border-color: #E4002B; color: #fff; }
    .q-nav-btn { min-width: 36px; height: 36px; border-radius: 50%; border: 1px solid #cbd5e1; background: #fff; font-weight: bold; cursor: pointer; }
    
    @media (max-width: 992px) {
        .q-layout { grid-template-columns: 1fr; }
        .q-right { order: -1; min-height: 200px; }
    }
    
    .quiz-form__ans {
        display: flex; align-items: center; padding: 1rem 1.5rem; 
        border: 1px solid #d1d9e0; border-radius: 8px; cursor: pointer; 
        transition: all 0.2s ease;
        background: white;
    }
    .quiz-form__ans:hover { background: #f8f9fa; border-color: #aeb8c2; }
    .quiz-form__ans input:checked + span { font-weight: bold; color: #E4002B; }
    
    /* Freeze Screen Overlay */
    #freeze-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100vw; height: 100vh;
        background: rgba(0, 0, 0, 0.95);
        z-index: 9999;
        color: white;
        text-align: center;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        backdrop-filter: blur(10px);
    }
    #freeze-overlay.active {
        display: flex;
    }
    .freeze-card {
        background: #111;
        padding: 3rem;
        border-radius: 12px;
        border: 2px solid #E4002B;
        max-width: 500px;
    }
    .freeze-title { color: #E4002B; font-size: 2rem; margin-bottom: 1rem; }
    .freeze-buttons { display: flex; gap: 1rem; justify-content: center; margin-top: 2rem; }
    
    /* Camera Preview */
    #cam-preview {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 150px;
        height: 110px;
        background: #000;
        border: 2px solid rgba(255,255,255,0.2);
        border-radius: 8px;
        z-index: 100;
        object-fit: cover;
        box-shadow: 0 4px 12px rgba(0,0,0,0.5);
    }
    #cam-indicator {
        position: fixed;
        bottom: 140px;
        right: 20px;
        background: rgba(0,0,0,0.7);
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        z-index: 100;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .dot {
        width: 8px; height: 8px;
        background: red;
        border-radius: 50%;
        animation: pulse 1.5s infinite;
    }
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.3; }
        100% { opacity: 1; }
    }

    /* Start Exam Gate */
    #start-gate {
        position: fixed;
        top: 0; left: 0; width: 100vw; height: 100vh;
        background: rgba(10, 42, 102, 0.6); /* Semi-transparent blue */
        backdrop-filter: blur(8px);
        z-index: 9000;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        color: white;
    }
    .start-gate-content { 
        text-align: center; 
        max-width: 600px; 
        padding: 3rem; 
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    }
  </style>
</head>
<body class="theme-light">
  <div class="app-bg"></div>

  <!-- START GATE -->
  <div id="start-gate">
      <div class="start-gate-content">
          <h2><?php echo $txtAuthReq; ?></h2>
          <p><?php echo $txtAuthDesc; ?></p>
          <button id="btn-grant-access" class="btn btn--primary btn--lg" style="margin-top: 2rem;">
              <?php echo $txtAuthBtn; ?>
          </button>
          <p id="cam-error" style="color: #ff4444; margin-top: 1rem; display: none;">
          </p>
      </div>
  </div>

  <!-- FREEZE OVERLAY -->
  <div id="freeze-overlay">
      <div class="freeze-card">
          <h2 class="freeze-title">⚠️ <?php echo $lang === 'en' ? 'EXAM SUSPENDED' : 'EXAMEN SUSPENDU'; ?></h2>
          <p><?php echo $lang === 'en' ? 'You have exited full screen mode or switched windows.' : 'Vous avez quitté le mode plein écran ou changé de fenêtre.'; ?></p>
          <p><?php echo $lang === 'en' ? 'This is considered a violation. The incident has been recorded.' : 'Ceci est considéré comme une infraction. L\'incident a été enregistré.'; ?></p>
          <div class="freeze-buttons">
              <button class="btn" style="background: transparent; border: 1px solid rgba(255,255,255,0.5); color: white;" onclick="alert('<?php echo $lang === 'en' ? 'Please raise your hand to call the proctor.' : 'Veuillez lever la main pour appeler le surveillant.'; ?>');">
                  <?php echo $lang === 'en' ? 'Contact Proctor' : 'Contacter Surveillant'; ?>
              </button>
              <button class="btn btn--primary" id="btn-resume">
                  <?php echo $lang === 'en' ? 'Resume Exam' : 'Reprendre l\'épreuve'; ?>
              </button>
          </div>
      </div>
  </div>

  <!-- CAMERA PREVIEW -->
  <video id="cam-preview" autoplay playsinline muted></video>
  <div id="cam-indicator"><div class="dot"></div> Surveillance active</div>

  <!-- HEADER -->
  <div class="page" style="padding:0;">
    <header class="topbar exam-header">
      <div class="brand">
        <div class="brand__logos" style="margin-bottom: 0;">
          <img class="brand__logo" src="assets/img/cfao-mobility.png" alt="CFAO Mobility" style="height: 30px;" />
          <img class="brand__logo" src="assets/img/suzuki.svg" alt="Suzuki" style="height: 24px;" />
        </div>
      </div>
      
      <div class="exam-title"><?php echo $txtQuizTitle; ?></div>

      <div style="display:flex;align-items:center;gap:1.5rem;">
        <div class="timer-badge" id="timer-badge">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            <span id="timer-display">00:00</span>
        </div>
        <div class="user-chip" style="background: transparent; padding: 0;">
          <div class="user-chip__avatar" title="<?php echo htmlspecialchars($fullName); ?>"><?php echo htmlspecialchars($initial); ?></div>
        </div>
      </div>
    </header>
    
    <main class="exam-main">
        <div id="quiz-container" class="exam-card">
            <div class="exam-card-header">
                Progression : <span id="current-q">1</span> / <?php echo $totalQuestions > 0 ? $totalQuestions : 100; ?>
            </div>
            
            <?php if ($totalQuestions > 0): ?>
            <div class="questions-panel">
                <div class="questions-head">
                    <div class="questions-title">QUESTIONS</div>
                </div>
                <div class="q-nav-wrap">
                    <button type="button" class="q-nav-btn" onclick="navPrev()">‹</button>
                    <div class="q-grid" id="qGrid">
                        <?php for($i = 0; $i < $totalQuestions; $i++): ?>
                            <div class="q-dot" id="dot-<?php echo $i; ?>" onclick="showQuestion(<?php echo $i; ?>)"><?php echo $i + 1; ?></div>
                        <?php endfor; ?>
                    </div>
                    <button type="button" class="q-nav-btn" onclick="navNext()">›</button>
                </div>
            </div>
            <?php endif; ?>

            <div class="exam-card-body" id="exam-card-body">
                <?php if ($totalQuestions > 0): ?>
                    <?php foreach ($questions as $index => $q): 
                        $qId = (string)$q['_id'];
                        $label = $q["label_{$lang}"] ?? $q['label_fr'];
                        $props = [
                            $q["proposal1_{$lang}"] ?? $q['proposal1_fr'],
                            $q["proposal2_{$lang}"] ?? $q['proposal2_fr'],
                            $q["proposal3_{$lang}"] ?? $q['proposal3_fr'],
                            $q["proposal4_{$lang}"] ?? $q['proposal4_fr']
                        ];
                        $img = $q["image"] ?? "";
                        $display = $index === 0 ? 'block' : 'none';
                    ?>
                        <div class='q-item' id='q-item-<?php echo $index; ?>' style='display: <?php echo $display; ?>; width: 100%; text-align: left;'>
                            <h3 style='margin-bottom: 1rem; font-size: 1.3rem; color: #1a2332;'><?php echo ($index + 1) . ". " . htmlspecialchars($label); ?></h3>
                            
                            <div class="q-layout">
                                <div class="q-left">
                                    <div style='display: flex; flex-direction: column; gap: 1rem;'>
                                        <?php foreach ($props as $pIndex => $prop): if (!empty($prop)): ?>
                                            <label class='quiz-form__ans'>
                                                <input type='radio' name='q_<?php echo $qId; ?>' value='<?php echo $pIndex; ?>' style='margin-right: 1rem; width: 20px; height: 20px;' onchange='saveAnswer(<?php echo $index; ?>)'>
                                                <span style='font-size: 1.1rem; color: #495057;'><?php echo htmlspecialchars($prop); ?></span>
                                            </label>
                                        <?php endif; endforeach; ?>
                                    </div>
                                </div>
                                <div class="q-right">
                                    <?php if ($img !== ""): ?>
                                        <div class="contImage"><img src="../public/files/<?php echo htmlspecialchars($img); ?>" alt="Illustration"></div>
                                    <?php else: ?>
                                        <div class="contImage">
                                            <div class="img-fallback">
                                                <img alt="CFAO Mobility" src="../public/images/logo-cfao.png">
                                                <span style="margin-top: 10px; font-weight: 500; font-size: 0.9rem;">Illustration non disponible</span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="spinner"></div>
                    <p style="color: #6c757d; font-size: 1.1rem;"><?php echo $txtQuizWait; ?></p>
                <?php endif; ?>
            </div>
            <div class="exam-card-footer" style="justify-content: space-between;">
                <button class="btn" id="btn-prev" disabled style="opacity: 0.5; min-width: 120px;">Précédent</button>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-outline-secondary" id="btn-save" style="background: white; border: 1px solid #d1d9e0;" onclick="manualSave()">Sauvegarder</button>
                    <button class="btn btn--primary" id="btn-next" style="min-width: 120px;" <?php echo $totalQuestions > 0 ? '' : 'disabled style="opacity: 0.5;"'; ?>>Suivant</button>
                </div>
            </div>
        </div>
    </main>
  </div>

  <script>
    // --- TIMER LOGIC ---
    const deadline = <?php echo $deadline; ?> * 1000; // PHP timestamp to JS ms
    const totalQuestions = <?php echo $totalQuestions; ?>;
    let currentQ = 0;
    
    function showQuestion(index) {
        if (totalQuestions === 0) return;
        currentQ = index;
        
        // Update dots
        for (let i = 0; i < totalQuestions; i++) {
            const dot = document.getElementById('dot-' + i);
            if (dot) {
                if (i === index) dot.classList.add('current');
                else dot.classList.remove('current');
            }
        }
        
        // Update items
        for (let i = 0; i < totalQuestions; i++) {
            const el = document.getElementById('q-item-' + i);
            if (el) el.style.display = (i === index) ? 'block' : 'none';
        }
        document.getElementById('current-q').textContent = index + 1;
        
        const btnPrev = document.getElementById('btn-prev');
        const btnNext = document.getElementById('btn-next');
        
        btnPrev.disabled = (index === 0);
        btnPrev.style.opacity = (index === 0) ? '0.5' : '1';
        
        if (index === totalQuestions - 1) {
            btnNext.textContent = "<?php echo $lang === 'en' ? 'Finish Exam' : 'Terminer l\'épreuve'; ?>";
        } else {
            btnNext.textContent = "<?php echo $lang === 'en' ? 'Next' : 'Suivant'; ?>";
        }
    }
    
    function navPrev() {
        if (currentQ > 0) showQuestion(currentQ - 1);
    }
    
    function navNext() {
        if (currentQ < totalQuestions - 1) showQuestion(currentQ + 1);
    }
    
    document.getElementById('btn-prev')?.addEventListener('click', navPrev);
    
    document.getElementById('btn-next')?.addEventListener('click', () => {
        if (currentQ < totalQuestions - 1) {
            navNext();
        } else {
            window.location.href = 'congrat.php';
        }
    });
    
    function saveAnswer(index) {
        // Enregistrement de la réponse (AJAX simulé)
        const dot = document.getElementById('dot-' + index);
        if (dot) dot.classList.add('done');
    }
    
    function manualSave() {
        const dot = document.getElementById('dot-' + currentQ);
        if (dot) {
            // Fake animation or alert if wanted, for now just ensures green state
            dot.classList.add('done');
            alert("<?php echo $lang === 'en' ? 'Answer saved.' : 'Réponse sauvegardée.'; ?>");
        }
    }
    
    // Init first question highlight
    if (totalQuestions > 0) showQuestion(0);

    function updateTimer() {
        const now = Date.now();
        const diff = deadline - now;
        if (diff <= 0) {
            document.getElementById('timer-display').textContent = "Terminé";
            document.getElementById('timer-badge').className = "timer-badge danger";
            // TODO: Auto-submit
            return;
        }
        const m = Math.floor(diff / 1000 / 60);
        const s = Math.floor((diff / 1000) % 60);
        
        const badge = document.getElementById('timer-badge');
        if (m < 5) badge.className = "timer-badge danger";
        else if (m < 15) badge.className = "timer-badge warning";
        else badge.className = "timer-badge";

        document.getElementById('timer-display').textContent = 
            String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }
    setInterval(updateTimer, 1000);
    updateTimer();

    // --- ANTI-CHEAT LOGIC ---
    const btnGrantAccess = document.getElementById('btn-grant-access');
    const startGate = document.getElementById('start-gate');
    const videoElem = document.getElementById('cam-preview');
    const freezeOverlay = document.getElementById('freeze-overlay');
    const btnResume = document.getElementById('btn-resume');
    
    let isExamActive = false;
    
    btnGrantAccess.addEventListener('click', async () => {
        let camSuccess = false;
        try {
            // 1. Request Camera
            const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            videoElem.srcObject = stream;
            camSuccess = true;
        } catch (err) {
            console.error("Camera access denied", err);
            document.getElementById('cam-error').style.display = 'block';
            document.getElementById('cam-error').textContent = "<?php echo $txtCamError; ?>" + err.message + ")";
        }
        
        if (camSuccess) {
            try {
                // 2. Request Fullscreen
                if (document.documentElement.requestFullscreen) {
                    await document.documentElement.requestFullscreen();
                }
            } catch (err) {
                console.warn("Fullscreen failed", err);
            }
            
            // 3. Start Exam
            startGate.style.display = 'none';
            isExamActive = true;
        }
    });
    
    // Resume button
    btnResume.addEventListener('click', async () => {
        if (document.documentElement.requestFullscreen) {
            await document.documentElement.requestFullscreen();
        }
        freezeOverlay.classList.remove('active');
    });

    // Detect Fullscreen Exit
    document.addEventListener('fullscreenchange', () => {
        if (isExamActive && !document.fullscreenElement) {
            triggerFreeze("Sortie du mode plein écran détectée.");
        }
    });
    
    // Detect Tab Switch / Loss of Focus
    document.addEventListener('visibilitychange', () => {
        if (isExamActive && document.visibilityState === 'hidden') {
            triggerFreeze("Changement d'onglet ou perte de focus détectée.");
        }
    });

    function triggerFreeze(reason) {
        console.warn("INFRACTION:", reason);
        // Log to server
        fetch('api/log_infraction.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ reason: reason })
        }).catch(err => console.error("Failed to log infraction", err));
        freezeOverlay.classList.add('active');
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(()=>{});
        }
    }

    // --- SNAPSHOT LOGIC ---
    // Take a snapshot every random interval (2 to 5 minutes)
    function scheduleNextSnapshot() {
        const minMs = 2 * 60 * 1000;
        const maxMs = 5 * 60 * 1000;
        const delay = Math.floor(Math.random() * (maxMs - minMs + 1)) + minMs;
        
        setTimeout(() => {
            takeAndSendSnapshot();
            scheduleNextSnapshot();
        }, delay);
    }

    function takeAndSendSnapshot() {
        if (!isExamActive || !videoElem.srcObject) return;
        
        const canvas = document.createElement('canvas');
        canvas.width = videoElem.videoWidth;
        canvas.height = videoElem.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(videoElem, 0, 0, canvas.width, canvas.height);
        
        const base64Image = canvas.toDataURL('image/jpeg', 0.5); // Quality 0.5 to save bandwidth
        
        // TODO: fetch('api/snapshot.php', { method: 'POST', body: base64Image })
        console.log("Snapshot taken and 'sent' to server.");
    }
    
    scheduleNextSnapshot();

  </script>
</body>
</html>

