<?php
function renderQuizComponent($allQuestions, $user, $params, $exam = null, $test_con = 'Test de Connaissances', $level_label = '', $quizType = 'factuel', $labels = []) {
    $totalQuestions = count($allQuestions);
    $isDeclaratif = $quizType === 'declaratif';
    $purposeText = $isDeclaratif ? sprintf('Ce quiz mesure la maîtrise pratique du niveau %s. Il comporte %d questions à choix multiple. Vous disposez de %d heure(s) et %d minute(s). Répondez à toutes les questions avant la fin du chronomètre.', htmlspecialchars($params['level']), $totalQuestions, $exam['hour'] ?? 1, $exam['minute'] ?? 30) : sprintf('Ce quiz mesure vos connaissances théorique niveau %s. Il comporte %d questions à choix multiple. Vous disposez de %d heure(s) et %d minute(s). Répondez à toutes les question avant la fin du chronomètre.', htmlspecialchars($params['level']), $totalQuestions, $exam['hour'] ?? 1, $exam['minute'] ?? 30);
    $quizPurpose = $purposeText;
    $timer_hr = $exam['hour'] ?? '01';
    $timer_mn = $exam['minute'] ?? '30';
    $timer_sc = $exam['second'] ?? '00';
    $hasSaveBtn = false;
    $debugMode = $params['debug'] ?? false;
    $optionCount = $isDeclaratif ? 3 : 4; // Max for factuel
    $tourChoicesText = $isDeclaratif ? '3 choix de réponse possible' : '2 à 4 choix de réponse possible';
    $keydownMax = $isDeclaratif ? 3 : 4;
    $defaultLabels = $isDeclaratif ? ['je_maitrise' => 'Je maîtrise', 'je_ne_maitrise_pas' => 'Je ne maîtrise pas', 'jai_jamais_fait' => 'J\'ai jamais fait'] : [];
    $labels = array_merge($defaultLabels, $labels);
    ?>
    <style>
        /* All original styles preserved */
        .notification-container{position:absolute;top:20px;right:20px;z-index:100;display:flex;flex-direction:column;gap:10px;max-width:400px;}
        .notification{min-width:300px;padding:15px 20px;border-radius:10px;box-shadow:0 5px 15px rgba(0,0,0,0.15);display:flex;align-items:center;gap:12px;transform:translateX(100%);opacity:0;transition:all 0.3s ease;background:white;}
        .notification.show{transform:translateX(0);opacity:1;}
        .notification.hide{transform:translateX(100%);opacity:0;}
        .notification-icon{font-size:1.4rem;flex-shrink:0;}
        .notification-content{flex:1;font-size:0.9rem;}
        .notification-close{background:none;border:none;font-size:1.2rem;cursor:pointer;opacity:0.7;transition:opacity 0.2s;padding:0;width:24px;height:24px;display:flex;align-items:center;justify-content:center;border-radius:50%;}
        .notification-close:hover{opacity:1;background:rgba(0,0,0,0.05);}
        .notification-success{border-left:4px solid #10b981;background:linear-gradient(90deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);}
        .notification-success .notification-icon{color:#10b981;}
        .notification-error{border-left:4px solid #ef4444;background:linear-gradient(90deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);}
        .notification-error .notification-icon{color:#ef4444;}
        .notification-warning{border-left:4px solid #f59e0b;background:linear-gradient(90deg, rgba(245, 158, 11, 0.1) 0%, rgba(245, 158, 11, 0.05) 100%);}
        .notification-warning .notification-icon{color:#f59e0b;}
        .notification-info{border-left:4px solid #005ba9;background:linear-gradient(90deg, rgba(0, 91, 169, 0.1) 0%, rgba(0, 91, 169, 0.05) 100%);}
        .notification-info .notification-icon{color:#005ba9;}
        .alert{display:none !important;}
        .timer-warning{animation:pulse 1s infinite;color:#ef4444 !important;}
        @keyframes pulse{0%{opacity:1;}50%{opacity:0.7;}100%{opacity:1;}}
        .quiz-main{position:relative;}
        .quiz-main.tour-mode {position: relative;}
        .quiz-main.tour-mode::before {content: '';position: absolute;top: 0;left: 0;width: 100%;height: 100%;background: rgba(0, 0, 0, 0.3);z-index: 998;pointer-events: none;}
        .quiz-main.tour-mode * {pointer-events: none;}
        .quiz-main.tour-mode .shepherd-target {pointer-events: auto;position: relative;z-index: 999;}
        .nav-grid {position: relative;padding: 2px 30px 2px 30px;margin-bottom: 5px;width: 100%;height: auto; overflow-x: auto; white-space: nowrap;}
        .nav-container {display: flex;align-items: center;justify-content: space-between;gap: 15px;width: 100%;max-width: 100%;}
        .page-nav-btn {width: 45px;height: 45px;border: none;background: #005ba9;color: white;cursor: pointer;border-radius: 50%;font-size: 1.2rem;transition: all 0.3s ease;display: flex;align-items: center;justify-content: center;box-shadow: 0 2px 8px rgba(0, 91, 169, 0.3);flex-shrink: 0;}
        .page-nav-btn:hover:not(:disabled) {transform: scale(1.1);box-shadow: 0 4px 12px rgba(0, 91, 169, 0.5);}
        .page-nav-btn:disabled {opacity: 0.3;cursor: not-allowed;transform: scale(1);}
        .nav-scroller {flex: 1;overflow: hidden;position: relative;min-width: 0;}
        .nav-pages {display: flex;transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);height: 100%;}
        .nav-page {flex: 0 0 100%;display: flex;gap: 4px;align-items: center;justify-content: center;padding: 0 10px;box-sizing: border-box;}
        .question-nav-button{width: 48px;height: 48px;border-radius: 50%;border: none;background: #f1f5f9;cursor: pointer;transition: all 0.3s ease;font-size: 0.9rem;font-weight: 700;color: #64748b;display: flex;align-items: center;justify-content: center;position: relative;flex-shrink: 0;}
        .question-nav-button:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,0.15);}
        .question-nav-button.answered{background:linear-gradient(135deg, #10b981, #059669);color:white;}
        .question-nav-button.unanswered{background:#f8fafc;color:#475569;}
        .question-nav-button.active{background:linear-gradient(135deg, #3b82f6, #1d4ed8);color:white;transform:scale(1.05);box-shadow:0 0 0 3px rgba(59,130,246,0.3);}
        .question-nav-button.answered:hover{transform:translateY(-1px) scale(1.05);}
        .question-nav-button.active:hover{transform:scale(1.1);}
        .question-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
        .question-number { width:40px;height:40px;border-radius:50%;border:2px solid #e2e8f0;background:white;cursor:pointer;transition:all 0.2s ease;font-size:0.8rem;font-weight:600;color:#64748b;box-shadow:0 1px 3px rgba(0,0,0,0.1);display:flex;align-items:center;justify-content:center; }
        .question-number:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,0.15);}
        .question-number.answered{background:linear-gradient(135deg, #10b981, #059669);color:white;border-color:#10b981;}
        .question-number.unanswered{background:#f8fafc;color:#475569;}
        .question-number.answered:hover{transform:translateY(-1px) scale(1.05);}
        .question-ref { font-size: 0.9rem; color: #64748b; }
        .question-with-image {display: flex;gap: 10px;align-items: flex-start;}
        .left-block {flex: 1;display: flex;flex-direction: column;}
        .right-block {flex: 1;display: flex;align-items: center;justify-content: center;}
        .question-image {width: 100%;height: 100%;}
        .question-image img {width: 100%;height: 100%;object-fit: contain;border-radius: 8px;box-shadow: 0 4px 12px rgba(0,0,0,0.1);}
        .question-image-placeholder {width: 100%;height: 100%;display: flex;align-items: center;justify-content: center;background: #f8fafc;border-radius: 8px;border: 2px dashed #e2e8f0;}
        .question-image-placeholder img {width: 100%;height: 100%;object-fit: cover;border-radius: 8px;}
        .placeholder-content {text-align: center;color: #64748b;}
        .placeholder-content i {font-size: 3rem;margin-bottom: 10px;display: block;}
        .placeholder-content p {font-size: 0.9rem;margin: 0;}
        .nav-button {background: #005ba9;color: white;border: none;padding: 10px 20px;border-radius: 5px;cursor: pointer;transition: all 0.3s ease;display: flex;align-items: center;gap: 5px;font-size: 0.9rem;font-weight: 500;width: 140px;justify-content: center;}
        .nav-button:hover:not(:disabled) {background: #003d73;transform: translateY(-1px);box-shadow: 0 4px 12px rgba(0, 91, 169, 0.3);}
        .nav-button:disabled {opacity: 0.5;cursor: not-allowed;transform: none;}
        .btn-submit {background: #10b981;}
        .btn-submit:hover:not(:disabled) {background: #059669;}
        .btn-save {background: #f59e0b;}
        .btn-save:hover:not(:disabled) {background: #d97706;}
        .btn-debug {background: #ef4444;width: auto;min-width: 140px;}
        .btn-debug:hover:not(:disabled) {background: #dc2626;}
        .action-buttons {display: flex;justify-content: space-between;align-items: center;gap: 10px;}
        .nav-controls {display: flex;justify-content: space-between;width: 100%;flex-wrap: wrap;gap: 5px;}
        .welcome-modal {position: absolute;top: 0;left: 0;width: 100%;height: 100%;background: rgba(0, 0, 0, 0.5);display: none;align-items: center;justify-content: center;z-index: 1000;}
        .content-container.modal-open {position: relative;}
        .welcome-content {background: white;padding: 30px;border-radius: 10px;max-width: 500px;text-align: center;box-shadow: 0 10px 30px rgba(0,0,0,0.2);}
        .welcome-title {font-size: 1.5rem;margin-bottom: 10px;color: #005ba9;}
        .welcome-text {font-size: 1rem;margin-bottom: 20px;color: #333;}
        .welcome-buttons {display: flex;gap: 10px;justify-content: center;}
        .btn-welcome {padding: 10px 20px;border: none;border-radius: 5px;cursor: pointer;font-weight: 500;transition: all 0.3s ease;}
        .btn-start-quiz {background: #10b981;color: white;}
        .btn-start-quiz:hover {background: #059669;}
        .btn-start-guide {background: #005ba9;color: white;}
        .btn-start-guide:hover {background: #003d73;}
        #guide-trigger {position: absolute;right: 20px;top: 50%;transform: translateY(-50%);background: #005ba9;color: white;padding: 8px 12px;border-radius: 20px;cursor: pointer;display: inline-flex;align-items: center;gap: 5px;box-shadow: 0 4px 12px rgba(0,91,169,0.3);white-space: nowrap;z-index: 10;}
        #guide-trigger:hover {background: #003d73;}
        .shepherd-theme-custom {border-radius: 12px;box-shadow: 0 5px 20px rgba(0,0,0,0.3);font-family: inherit;max-width: 400px;}
        .shepherd-theme-custom .shepherd-header {background: #005ba9;color: white;border-radius: 12px 12px 0 0;padding: 15px 20px;}
        .shepherd-theme-custom .shepherd-title {font-weight: 600;font-size: 1.1rem;}
        .shepherd-theme-custom .shepherd-text {color: #334155;padding: 20px;font-size: 0.95rem;line-height: 1.5;}
        .shepherd-theme-custom .shepherd-footer {padding: 0 20px 20px;display: flex;gap: 10px;justify-content: flex-end;}
        .shepherd-theme-custom .shepherd-button {border-radius: 6px;padding: 8px 16px;font-weight: 500;transition: all 0.3s ease;border: none;cursor: pointer;}
        .shepherd-theme-custom .shepherd-button:not(.shepherd-button-secondary) {background: #005ba9;color: white;}
        .shepherd-theme-custom .shepherd-button:not(.shepherd-button-secondary):hover {background: #003d73;transform: translateY(-1px);}
        .shepherd-theme-custom .shepherd-button-secondary {background: #f1f5f9;color: #64748b;}
        .shepherd-theme-custom .shepherd-button-secondary:hover {background: #e2e8f0;}
        .shepherd-theme-custom .shepherd-cancel-icon {color: white;font-size: 1.2rem;}
        .shepherd-modal-overlay-container {z-index: 9998;position: absolute !important;top: 0 !important;left: 0 !important;width: 100% !important;height: 100% !important;background: rgba(0, 0, 0, 0.5) !important;}
        .content-container.tour-open .shepherd-modal-overlay-container {position: absolute !important;top: 0 !important;left: 0 !important;width: 100% !important;height: 100% !important;}
        .shepherd-element {z-index: 9999;}
        .quiz-header {position: relative;display: flex;align-items: center;justify-content: center;margin-bottom: 0;padding: 0 100px;min-height: 60px;}
        .header-center-content {text-align: center;flex: 1;max-width: 600px;}
        .quiz-header h2 {margin: 0;font-size: 1.5rem;color: #005ba9;font-weight: 600;}
        .quiz-header p {margin: 5px 0 0;font-size: 0.9rem;color: #64748b;font-weight: 500;}
        .timer-section {display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; padding: 5px; background: #f8fafc; border-radius: 0;}
        .timer-box {display: flex; align-items: center; gap: 8px; font-weight: 500; color: #475569;}
        .specialty-text { font-size: 0.85rem; font-weight: 500; margin-bottom: 5px; color: #475569; }
        .question-title {margin: 0 0 10px; font-size: 1.1rem; color: #1e293b; line-height: 1.4;}
        .options-container {display: flex; flex-direction: column; gap: 4px;}
        .option {display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8fafc; border-radius: 8px; cursor: pointer; transition: all 0.2s ease; border: 2px solid transparent;}
        .option:hover {background: #e2e8f0; transform: translateX(4px);}
        .option.selected {background: #dbeafe; border-color: #3b82f6;}
        .option-radio {width: 20px; height: 20px; border: 2px solid #cbd5e1; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; flex-shrink: 0;}
        .option.selected .option-radio {background: #3b82f6; border-color: #3b82f6;}
        .option-radio::after {content: ''; width: 8px; height: 8px; background: white; border-radius: 50%; opacity: 0; transform: scale(0); transition: all 0.2s ease;}
        .option.selected .option-radio::after {opacity: 0; transform: scale(0);}
        .option-text {flex: 1; font-size: 1rem; color: #334155;}
        .navigation {padding: 10px; border-top: 1px solid #e2e8f0; background: white;}
        .confirm-modal {position: fixed;top: 0;left: 0;width: 100%;height: 100%;background: rgba(0, 0, 0, 0.5);display: none;align-items: center;justify-content: center;z-index: 2000;animation: fadeIn 0.3s ease;}
        .confirm-content {background: white;padding: 30px;border-radius: 12px;max-width: 500px;text-align: center;box-shadow: 0 10px 30px rgba(0,0,0,0.2);transform: scale(0.9);animation: scaleIn 0.3s ease forwards;}
        .confirm-title {font-size: 1.5rem;margin-bottom: 15px;color: #005ba9;font-weight: 600;}
        .confirm-text {font-size: 1rem;margin-bottom: 25px;color: #334155;line-height: 1.5;}
        .confirm-warning {color: #ef4444;font-weight: 500;margin-top: 10px;display: block;}
        .confirm-buttons {display: flex;gap: 15px;justify-content: center;}
        .btn-confirm {padding: 10px 25px;border: none;border-radius: 6px;cursor: pointer;font-weight: 500;transition: all 0.2s;font-size: 0.95rem;}
        .btn-cancel {background: #f1f5f9;color: #64748b;}
        .btn-cancel:hover {background: #e2e8f0;color: #475569;}
        .btn-ok {background: #10b981;color: white;}
        .btn-ok:hover {background: #059669;transform: translateY(-1px);}
        @keyframes fadeIn {from {opacity: 0;} to {opacity: 1;}}
        @keyframes scaleIn {from {transform: scale(0.9);} to {transform: scale(1);}}
    </style>
    <div class="content-container fs-6 d-flex flex-column flex-column-fluid p-10" id="kt_content">
        <div class="welcome-modal" id="welcomeModal" style="display: none;">
            <div class="welcome-content">
                <h2 class="welcome-title"><?php echo $test_con; ?></h2>
                <p class="welcome-text"><?php echo $quizPurpose; ?></p>
                <div class="welcome-buttons">
                    <button class="btn-welcome btn-start-guide" onclick="startTour()">Découvrir le Guide</button>
                    <button class="btn-welcome btn-start-quiz" onclick="beginQuiz()">Commencer le Test</button>
                </div>
            </div>
        </div>
        <div class="confirm-modal" id="confirmModal">
            <div class="confirm-content">
                <i class="fas fa-question-circle" style="font-size: 3rem; color: #005ba9; margin-bottom: 15px;"></i>
                <h3 class="confirm-title">Finalisation du Test</h3>
                <p class="confirm-text" id="confirmText">Confirmez-vous l'enregistrement définitif de vos réponses ?</p>
                <div class="confirm-buttons">
                    <button class="btn-confirm btn-cancel" onclick="closeConfirmModal()">Retour</button>
                    <button class="btn-confirm btn-ok" onclick="confirmFinalize()">Confirmer et Quitter</button>
                </div>
            </div>
        </div>
        <div class="background-overlay"></div>
        <div class="flex-fill overflow-hidden custom-main-panel" style="display:flex;flex-direction:row;min-width:0;width:100%;height:0;">
            <div id="trainings-table" class="glass-effect overflow-hidden" style="display:flex;flex-direction:column;min-height:0;max-width:85%;margin:0 auto;">
                <div class="quiz-main d-flex flex-column" style="flex:1;overflow:hidden;">
                    <div class="notification-container" id="notificationContainer"></div>
                    <div class="quiz-header">
                        <div class="header-center-content pt-5">
                            <h2><?php echo $test_con; ?></h2>
                            <p><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']) . ' - ' . htmlspecialchars($level_label) . ' ' . $params['level']; ?></p>
                        </div>
                        <div id="guide-trigger" onclick="startTour()" style="display:none;">
                            <i class="fas fa-info-circle"></i> Guide
                        </div>
                    </div>
                    <div class="timer-section" id="timer-section">
                        <div class="timer-box"><i class="fas fa-clock"></i><span id="timer"><?php echo sprintf('%02d:%02d:%02d', $exam['hour'] ?? 1, $exam['minute'] ?? 30, $exam['second'] ?? 0); ?></span></div>
                        <div class="timer-box"><i class="fas fa-question-circle"></i><span>Questions répondues: <strong id="completedCount">0</strong> / <?php echo $totalQuestions; ?></span></div>
                    </div>
                    <div class="nav-grid" id="nav-grid">
                        <?php for ($i = 1; $i <= $totalQuestions; $i++): $ans = $allQuestions[$i-1]['answer'] ?? 'null'; ?>
                            <button class="question-nav-button <?php echo $ans !== 'null' ? 'answered' : 'unanswered'; ?> <?php echo $i === 1 ? 'active' : ''; ?>" data-question="<?php echo $i; ?>"><?php echo $i; ?></button>
                        <?php endfor; ?>
                    </div>
                    <div class="alert" id="errorAlert"><i class="fas fa-exclamation-triangle"></i><span id="errorMessage"></span></div>
                    <div class="question-wrapper scrollable-vertical" style="flex:1;padding:15px;overflow-y:auto;" id="question-wrapper">
                        <?php foreach ($allQuestions as $i => $item): $q = $item['question']; $hasImg = !empty($q['image']); $ansd = isset($item['answer']) && $item['answer'] !== 'null'; $ansClass = $ansd ? 'answered' : 'unanswered'; $correctVal = $isDeclaratif ? sprintf("1-%s-%s-%s-1", $item['specialty'], $params['level'], $q['label']) : $q['answer']; ?>
                            <div class="question-step <?php echo $i === 0 ? 'active' : ''; ?>" data-question="<?php echo $i + 1; ?>">
                                <div class="question-header">
                                    <div class="question-number <?php echo $ansClass; ?>"><?php echo $i + 1; ?></div>
                                    <div class="question-ref">Réf: <?php echo htmlspecialchars($q['ref']); ?></div>
                                </div>
                                <div class="question-with-image">
                                    <div class="left-block">
                                        <div class="specialty-text"><?php echo htmlspecialchars($item['specialty']); ?></div>
                                        <?php if ($params['level'] === 'Expert' && !empty($q['title'])): ?><p class="question-title"><?php echo htmlspecialchars($q['title']); ?></p><?php endif; ?>
                                        <h3 class="question-title"><?php echo htmlspecialchars($q['label']); ?></h3>
                                        <div class="options-container">
                                            <?php if ($isDeclaratif):
                                                if (isset($labels['il_maitrise'])) {
                                                    $decProps = [
                                                        $labels['il_maitrise'] ?? 'Il maitrise (le technicien réalise cette tâche professionnelle)',
                                                        $labels['il_ne_maitrise_pas'] ?? 'Il ne maitrise pas (le technicien ne réalise pas cette tâche professionnelle)',
                                                        $labels['il_na_jamais_fait'] ?? 'Il n\'a jamais complété cette tâche professionnelle dans l\'atelier'
                                                    ];
                                                } else {
                                                    $decProps = [
                                                        $labels['je_maitrise'] ?? 'Je maitrise (je réalise cette tâche professionnelle seul)',
                                                        $labels['je_ne_maitrise_pas'] ?? 'Je ne maitrise pas (je ne réalise pas cette tâche professionnelle seul)',
                                                        $labels['jai_jamais_fait'] ?? 'Je n\'ai jamais complété cette tâche professionnelle dans l\'atelier'
                                                    ];
                                                }
                                                foreach ($decProps as $j => $prop): $isCorrect = $j === 0; // First is always correct for declaratif ?>
                                                    <div class="option" data-value="<?php echo htmlspecialchars($prop); ?>" <?php echo isset($item['answer']) && $item['answer'] === $prop ? 'data-selected="true"' : ''; ?> data-correct="<?php echo $isCorrect ? 'true' : 'false'; ?>">
                                                        <div class="option-radio"></div>
                                                        <div class="option-text"><?php echo htmlspecialchars($prop); ?></div>
                                                    </div>
                                                <?php endforeach; else: $props = [$q['proposal1'], $q['proposal2']]; if (!empty($q['proposal3'])) $props[] = $q['proposal3']; if (!empty($q['proposal4'])) $props[] = $q['proposal4']; foreach ($props as $j => $prop): ?>
                                                <div class="option" data-value="<?php echo htmlspecialchars($prop); ?>" <?php echo isset($item['answer']) && $item['answer'] === $prop ? 'data-selected="true"' : ''; ?> data-correct="<?php echo $prop === $correctVal ? 'true' : 'false'; ?>">
                                                    <div class="option-radio"></div>
                                                    <div class="option-text"><?php echo htmlspecialchars($prop); ?></div>
                                                </div>
                                            <?php endforeach; endif; ?>
                                        </div>
                                    </div>
                                    <div class="right-block">
                                        <?php if ($hasImg): ?>
                                            <div class="question-image"><img src="../../public/files/<?php echo htmlspecialchars($q['image']); ?>" alt="Image de la question" loading="lazy"></div>
                                        <?php else: ?>
                                            <div class="question-image-placeholder">
                                                <img alt="Logo" src="../../public/images/logo-cfao.png" loading="lazy" />
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="navigation" id="navigation">
                        <div class="nav-controls">
                            <button class="nav-button btn-prev" id="prevBtn" disabled><i class="fas fa-chevron-left"></i> Précédent</button>
                            <?php if ($hasSaveBtn): ?><button class="nav-button btn-save" id="saveBtn"><i class="fas fa-save"></i> Sauvegarder</button><?php endif; ?>
                            <button class="nav-button btn-next" id="nextBtn">Suivant <i class="fas fa-chevron-right"></i></button>
                            <button class="nav-button btn-submit" id="submitBtn" disabled><i class="fas fa-check"></i> Finaliser</button>
                            <?php if ($debugMode): ?>
                                <button class="nav-button btn-debug" id="randomFillBtn"><i class="fas fa-dice"></i> Remplissage Aléatoire</button>
                                <button class="nav-button btn-debug" id="correctFillBtn"><i class="fas fa-check-circle"></i> Remplissage Correct</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <input type="hidden" id="timer_hr" value="<?php echo htmlspecialchars($timer_hr); ?>">
    <input type="hidden" id="timer_mn" value="<?php echo htmlspecialchars($timer_mn); ?>">
    <input type="hidden" id="timer_sc" value="<?php echo htmlspecialchars($timer_sc); ?>">
    <input type="hidden" id="quiz_type" value="<?php echo $quizType; ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/shepherd.js@11.2.0/dist/js/shepherd.min.js" defer></script>
    <script defer>
        let tour; let tourActive = false; const totalQuestions = <?php echo $totalQuestions; ?>; const quizType = document.getElementById('quiz_type').value; const isDeclaratif = quizType === 'declaratif'; const hasSaveBtn = false; const keydownMax = isDeclaratif ? 3 : 4; const tourChoicesText = isDeclaratif ? '3 choix de réponse possible' : '2 à 4 choix de réponse possible'; const tourNavText = hasSaveBtn ? 'Les boutons "Précédent/Suivant" vous permettent de naviguer entre les questions. <br>Le bouton "Sauvegarder" enregistre vos réponses. <br>Le bouton "Finaliser" permet d\'enregistrer définitivement vos réponses.' : 'Les boutons "Précédent/Suivant" vous permettent de naviguer entre les questions. <br>Le bouton "Finaliser" permet d\'enregistrer définitivement vos réponses.'; const debugMode = <?php echo json_encode($debugMode); ?>;
        function isShepherdLoaded() { return typeof Shepherd !== 'undefined'; }
        function beginQuiz() { const ktContent = document.getElementById('kt_content'); document.getElementById('welcomeModal').style.display = 'none'; ktContent.classList.remove('modal-open'); document.getElementById('guide-trigger').style.display = 'inline-flex'; tourActive = false; document.querySelector('.quiz-main')?.classList.remove('tour-mode'); if (!window.quiz) window.quiz = new Quiz(); }
        function startTour() { if (!isShepherdLoaded()) { showNotification('Erreur: Le guide n\'est pas disponible. Rechargez la page.', 'error'); return; } const ktContent = document.getElementById('kt_content'); document.getElementById('welcomeModal').style.display = 'none'; ktContent.classList.remove('modal-open'); document.getElementById('guide-trigger').style.display = 'none'; if (!window.quiz) window.quiz = new Quiz(); const previousQ = window.quiz.currentQuestion; window.quiz.goToQuestion(1); window.previousQuestion = previousQ; document.querySelector('.quiz-main').classList.add('tour-mode'); ktContent.classList.add('tour-open'); tourActive = true; tour = new Shepherd.Tour({ useModalOverlay: true, defaultStepOptions: { classes: 'shepherd-theme-custom', scrollTo: true, scrollToHandler: el => el.scrollIntoView({behavior: 'smooth', block: 'center', inline: 'center'}), modalOverlayOpeningPadding: 10, cancelIcon: {enabled: true}, arrow: true } }); const steps = [ {title: 'Temps et progression', text: '<strong>Chronomètre à gauche :</strong> Indique le temps restant.<br><strong>Compteur à droite :</strong> Indique le nombre de questions traitées.<br><em>NB : Finalisation automatique à la fin du chronomètre.</em>', attachTo: {element: '#timer-section', on: 'top'}}, {title: 'Parcours rapide', text: '<strong>Accès direct :</strong> Chaque bouton numéroté permet d\'accéder à la question correspondante.<br><strong>Indicateurs de couleurs :</strong> Vert = question répondue | Bleu = Question actuelle | Blanc = Question sans réponse.<br><strong>Navigation :</strong> Utilisez les flèches < > pour voir plus de questions.', attachTo: {element: '#nav-grid', on: 'bottom'}}, {title: 'Questions', text: `<strong>Contenu :</strong> Pour chaque question, vous avez : sa spécialité, son énoncé, ${tourChoicesText} et une image à droite si nécessaire.<br><strong>Action :</strong> Cliquez pour choisir une réponse.`, attachTo: {element: '.question-step:first-child', on: 'right'}}, {title: 'Navigation et fin', text: tourNavText, attachTo: {element: '#navigation', on: 'top'}} ]; steps.forEach((step, idx) => { const el = document.querySelector(step.attachTo.element); if (!el) return; tour.addStep({ id: `step-${idx + 1}`, title: step.title, text: step.text, attachTo: step.attachTo, buttons: (() => { const btns = []; if (idx > 0) btns.push({text: 'Précédent', action: tour.back, secondary: true}); if (idx < steps.length - 1) { btns.push({text: 'Suivant', action: tour.next}); } else { btns.push({text: 'Commencer le Test', action: tour.complete}); } return btns; })() }); }); if (tour.steps.length === 0) throw new Error('No valid steps'); tour.on('complete', () => { closeTour(); if (window.previousQuestion && window.previousQuestion !== 1 && window.quiz) { window.quiz.goToQuestion(window.previousQuestion); delete window.previousQuestion; } }); tour.on('cancel', () => { closeTour(); if (window.previousQuestion && window.previousQuestion !== 1 && window.quiz) { window.quiz.goToQuestion(window.previousQuestion); delete window.previousQuestion; } }); tour.start(); }
        function closeTour() { tourActive = false; document.querySelector('.quiz-main')?.classList.remove('tour-mode'); const ktContent = document.getElementById('kt_content'); ktContent.classList.remove('tour-open'); document.getElementById('guide-trigger').style.display = 'inline-flex'; if (window.quiz) { const selectors = ['.option', '.question-nav-button', '#prevBtn', '#nextBtn', '#submitBtn']; if (hasSaveBtn) selectors.push('#saveBtn'); if (debugMode) selectors.push('#randomFillBtn', '#correctFillBtn'); document.querySelectorAll(selectors.join(',')).forEach(el => el.replaceWith(el.cloneNode(true))); window.quiz.bindEvents(); } if (window.quiz?.timerInterval) { clearInterval(window.quiz.timerInterval); window.quiz.startTimer(); } }
        function debounce(fn, wait) { let to; return (...args) => { clearTimeout(to); to = setTimeout(() => fn(...args), wait); }; }
        function showNotification(msg, type = 'info') { const cont = document.getElementById('notificationContainer'); const notif = document.createElement('div'); notif.className = `notification notification-${type}`; const icon = type === 'success' ? 'fas fa-check-circle' : type === 'error' ? 'fas fa-exclamation-circle' : type === 'warning' ? 'fas fa-exclamation-triangle' : 'fas fa-info-circle'; notif.innerHTML = `<i class="notification-icon ${icon}"></i><div class="notification-content">${msg}</div><button class="notification-close">&times;</button>`; cont.appendChild(notif); setTimeout(() => notif.classList.add('show'), 10); if (type === 'success' || type === 'info') setTimeout(() => hideNotification(notif), 3000); notif.querySelector('.notification-close').addEventListener('click', () => hideNotification(notif)); }
        function hideNotification(notif) { notif.classList.remove('show'); notif.classList.add('hide'); setTimeout(() => notif.parentNode?.removeChild(notif), 300); }
        function openConfirmModal(unansweredCount) {
            const modal = document.getElementById('confirmModal');
            const textEl = document.getElementById('confirmText');
            let msg = 'Confirmez-vous la finalisation de vos réponses ?<br>Attention, cette action est définitive et vous ne pourrez plus modifier vos réponses !';
            if (unansweredCount > 0) {
                msg += `<br><span class="confirm-warning"><i class="fas fa-exclamation-triangle"></i> Il vous reste ${unansweredCount} question(s) non répondue(s).</span>`;
            }
            textEl.innerHTML = msg;
            modal.style.display = 'flex';
        }
        function closeConfirmModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }
        function confirmFinalize() {
            closeConfirmModal();
            if (window.quiz) window.quiz.finalize();
        }
        function scrollToQuestion(num) { document.querySelector(`.question-step[data-question="${num}"]`)?.scrollIntoView({behavior: 'smooth', block: 'start'}); }
        function Quiz() { this.currentQuestion = 1; this.totalQuestions = totalQuestions; this.currentNavPage = 0; this.numPerPage = 0; this.numPages = 0; this.pagesContainer = null; this.prevPageBtn = null; this.nextPageBtn = null; this.answers = {}; this.timer = { hours: parseInt(document.getElementById('timer_hr').value), minutes: parseInt(document.getElementById('timer_mn').value), seconds: parseInt(document.getElementById('timer_sc').value) }; this.init(); }
        Quiz.prototype.init = function() { this.loadAnswers(); this.updateQuestionNumbers(); this.initPagination(); this.startTimer(); this.bindEvents(); this.updateUI(); this.enableAutoSave(); this.goToQuestion(1); };
        Quiz.prototype.initPagination = function() { const grid = document.querySelector('.nav-grid'); const btns = Array.from(grid.querySelectorAll('.question-nav-button')); if (!btns.length) return; const first = btns[0]; const style = window.getComputedStyle(first); const btnW = first.offsetWidth + parseFloat(style.marginLeft) + parseFloat(style.marginRight) + 4; const contW = grid.clientWidth; const navW = 40; const marg = 20; const effW = contW - 2 * navW - marg; let perPage = Math.floor(effW / btnW); this.numPerPage = Math.max(5, Math.floor(perPage / 5) * 5); this.numPages = Math.ceil(this.totalQuestions / this.numPerPage); const navCont = document.createElement('div'); navCont.className = 'nav-container'; this.prevPageBtn = document.createElement('button'); this.prevPageBtn.innerHTML = '<i class="fas fa-chevron-left"></i>'; this.prevPageBtn.className = 'page-nav-btn'; this.prevPageBtn.disabled = true; this.prevPageBtn.addEventListener('click', () => this.prevNavPage()); const scroller = document.createElement('div'); scroller.className = 'nav-scroller'; const pages = document.createElement('div'); pages.className = 'nav-pages'; btns.forEach((btn, idx) => { const pIdx = Math.floor(idx / this.numPerPage); let page = pages.children[pIdx]; if (!page) { page = document.createElement('div'); page.className = 'nav-page'; pages.appendChild(page); } page.appendChild(btn); }); scroller.appendChild(pages); navCont.appendChild(this.prevPageBtn); navCont.appendChild(scroller); this.nextPageBtn = document.createElement('button'); this.nextPageBtn.innerHTML = '<i class="fas fa-chevron-right"></i>'; this.nextPageBtn.className = 'page-nav-btn'; this.nextPageBtn.addEventListener('click', () => this.nextNavPage()); navCont.appendChild(this.nextPageBtn); grid.innerHTML = ''; grid.appendChild(navCont); this.pagesContainer = pages; this.updateNavPage(); };
        Quiz.prototype.recalculatePagination = function() { const oldPer = this.numPerPage; if (this.numPerPage === 0) return; this.initPagination(); if (oldPer !== this.numPerPage && this.currentNavPage >= this.numPages) { this.currentNavPage = this.numPages - 1; this.updateNavPage(); } };
        Quiz.prototype.updateNavPage = function() { if (!this.pagesContainer) return; this.pagesContainer.style.transform = `translateX(-${this.currentNavPage * 100}%)`; this.prevPageBtn.disabled = this.currentNavPage === 0; this.nextPageBtn.disabled = this.currentNavPage === this.numPages - 1; };
        Quiz.prototype.prevNavPage = function() { if (this.currentNavPage > 0) { this.currentNavPage--; this.updateNavPage(); } };
        Quiz.prototype.nextNavPage = function() { if (this.currentNavPage < this.numPages - 1) { this.currentNavPage++; this.updateNavPage(); } };
        Quiz.prototype.bindEvents = function() { if (tourActive) return; this.unbindEvents(); document.querySelectorAll('.option').forEach(opt => opt.addEventListener('click', e => this.selectOption(opt))); document.querySelectorAll('.question-nav-button').forEach(btn => btn.addEventListener('click', e => this.jumpToQuestion(btn))); document.getElementById('prevBtn').addEventListener('click', this.previousQuestion.bind(this)); document.getElementById('nextBtn').addEventListener('click', this.nextQuestion.bind(this)); document.getElementById('submitBtn').addEventListener('click', this.submit.bind(this)); if (hasSaveBtn) document.getElementById('saveBtn').addEventListener('click', this.save.bind(this)); <?php if ($debugMode): ?> if (debugMode) { document.getElementById('randomFillBtn').addEventListener('click', this.randomFill.bind(this)); document.getElementById('correctFillBtn').addEventListener('click', this.correctFill.bind(this)); } <?php endif; ?> document.addEventListener('keydown', e => { if (tourActive) return; if (e.key === 'ArrowLeft' && !e.ctrlKey && !e.altKey) { e.preventDefault(); this.previousQuestion(); } else if (e.key === 'ArrowRight' && !e.ctrlKey && !e.altKey) { e.preventDefault(); this.nextQuestion(); } else if (hasSaveBtn && e.key === 's' && e.ctrlKey) { e.preventDefault(); this.save(); } else if (e.key >= '1' && e.key <= keydownMax.toString()) { const step = document.querySelector('.question-step.active'); if (step) { const opts = step.querySelectorAll('.option'); const oIdx = parseInt(e.key) - 1; if (opts[oIdx]) this.selectOption(opts[oIdx]); } } }); window.addEventListener('resize', debounce(this.recalculatePagination.bind(this), 250)); };
        Quiz.prototype.unbindEvents = function() { const selectors = ['.option', '.question-nav-button', '#prevBtn', '#nextBtn', '#submitBtn']; if (hasSaveBtn) selectors.push('#saveBtn'); if (debugMode) selectors.push('#randomFillBtn', '#correctFillBtn'); selectors.forEach(sel => { document.querySelectorAll(sel).forEach(el => el.replaceWith(el.cloneNode(true))); }); };
        Quiz.prototype.loadAnswers = function() { document.querySelectorAll('.option[data-selected="true"]').forEach(opt => { const step = opt.closest('.question-step'); this.answers[step.dataset.question] = opt.dataset.value; opt.classList.add('selected'); }); this.updateNavButtons(); };
        Quiz.prototype.updateQuestionNumbers = function() { document.querySelectorAll('.question-step').forEach(step => { const num = step.dataset.question; const ans = !!this.answers[num]; const numEl = step.querySelector('.question-number'); if (numEl) { numEl.classList.toggle('answered', ans); numEl.classList.toggle('unanswered', !ans); } }); };
        Quiz.prototype.selectOption = function(opt) { const step = opt.closest('.question-step'); step.querySelectorAll('.option').forEach(o => o.classList.remove('selected')); opt.classList.add('selected'); const old = this.answers[step.dataset.question]; this.answers[step.dataset.question] = opt.dataset.value; this.updateNavButtons(); this.updateQuestionNumbers(); this.updateUI(); if (old !== opt.dataset.value) this.save(true); };
        Quiz.prototype.jumpToQuestion = function(btn) { const num = parseInt(btn.dataset.question); this.goToQuestion(num); };
        Quiz.prototype.goToQuestion = function(num) { if (num < 1 || num > this.totalQuestions) return; const bIdx = num - 1; const pIdx = Math.floor(bIdx / this.numPerPage); if (pIdx !== this.currentNavPage && this.numPages > 1) { this.currentNavPage = pIdx; this.updateNavPage(); } document.querySelector(`.question-step[data-question="${this.currentQuestion}"]`)?.classList.remove('active'); const newStep = document.querySelector(`.question-step[data-question="${num}"]`); newStep?.classList.add('active'); const curNav = document.querySelector(`.question-nav-button[data-question="${this.currentQuestion}"]`); curNav?.classList.remove('active'); const newNav = document.querySelector(`.question-nav-button[data-question="${num}"]`); newNav?.classList.add('active'); this.currentQuestion = num; this.updateUI(); if (newStep?.querySelector('.question-with-image')) { const cont = newStep.querySelector('.question-with-image'); const left = cont.querySelector('.left-block'); const right = cont.querySelector('.right-block'); left.style.height = 'auto'; const lh = left.scrollHeight; right.style.height = lh + 'px'; } scrollToQuestion(num); };
        Quiz.prototype.previousQuestion = function() { if (this.currentQuestion > 1) this.goToQuestion(this.currentQuestion - 1); };
        Quiz.prototype.nextQuestion = function() { if (this.currentQuestion < this.totalQuestions) this.goToQuestion(this.currentQuestion + 1); };
        Quiz.prototype.updateUI = function() { const ans = Object.keys(this.answers).length; document.getElementById('completedCount').textContent = ans; document.getElementById('prevBtn').disabled = this.currentQuestion === 1; document.getElementById('nextBtn').disabled = this.currentQuestion === this.totalQuestions; document.getElementById('submitBtn').disabled = ans <= 1; this.updateNavButtons(); };
        Quiz.prototype.updateNavButtons = function() { document.querySelectorAll('.question-nav-button').forEach(btn => { const num = btn.dataset.question; btn.classList.toggle('answered', !!this.answers[num]); btn.classList.toggle('unanswered', !this.answers[num]); }); };
        Quiz.prototype.startTimer = function() { const timerEl = document.getElementById('timer'); this.timerInterval = setInterval(() => { this.timer.seconds--; if (this.timer.seconds < 0) { this.timer.seconds = 59; this.timer.minutes--; if (this.timer.minutes < 0) { this.timer.minutes = 59; this.timer.hours--; if (this.timer.hours < 0) { clearInterval(this.timerInterval); showNotification('Temps écoulé ! Soumission automatique du test...', 'warning'); setTimeout(() => this.submit(), 2000); return; } } } timerEl.textContent = `${String(this.timer.hours).padStart(2, '0')}:${String(this.timer.minutes).padStart(2, '0')}:${String(this.timer.seconds).padStart(2, '0')}`; if (this.timer.hours === 0 && this.timer.minutes === 5 && this.timer.seconds === 0) { showNotification('Attention : Il ne vous reste que 5 minutes !', 'warning'); } if (this.timer.hours === 0 && this.timer.minutes === 1 && this.timer.seconds === 0) { showNotification('URGENT : Il ne vous reste qu\'une minute !', 'error'); timerEl.classList.add('timer-warning'); } }, 1000); };
        Quiz.prototype.save = function(silent = false) { if (!silent) showNotification('Enregistrement de vos réponses en cours...', 'info'); const fd = new FormData(); fd.append('action', 'save'); fd.append('answers', JSON.stringify(this.answers)); fd.append('hr', this.timer.hours); fd.append('mn', this.timer.minutes); fd.append('sc', this.timer.seconds); fetch(window.location.href.replace('/quiz', '/quiz.php'), {method: 'POST', body: fd, headers: {'Accept': 'application/json'}}) .then(r => r.ok ? r.json() : r.text().then(t => Promise.reject(new Error('Server error: ' + t)))) .then(d => { if (d.success) { if (!silent) showNotification('Vos réponses ont été enregistrées avec succès !', 'success'); } else { showNotification(d.message, 'error'); } }) .catch(e => { showNotification('Erreur de connexion lors de l\'enregistrement : ' + e.message, 'error'); console.error('Save error:', e); }); };
        Quiz.prototype.submit = function() { const unans = this.totalQuestions - Object.keys(this.answers).length; openConfirmModal(unans); };
        Quiz.prototype.finalize = function() { showNotification('Finalisation du test en cours...', 'info'); const fd = new FormData(); fd.append('action', 'submit'); fd.append('answers', JSON.stringify(this.answers)); fd.append('timer', `${String(this.timer.hours).padStart(2, '0')}:${String(this.timer.minutes).padStart(2, '0')}:${String(this.timer.seconds).padStart(2, '0')}`); fetch(window.location.href.replace('/quiz', '/quiz.php'), {method: 'POST', body: fd, headers: {'Accept': 'application/json'}}) .then(r => r.ok ? r.json() : r.text().then(t => Promise.reject(new Error('Server error: ' + t)))) .then(d => { if (d.success) { clearInterval(this.timerInterval); showNotification('Test finalisé avec succès !', 'success'); if (d.redirect) setTimeout(() => window.location.href = d.redirect, 2000); } else { showNotification('Erreur lors de la finalisation : ' + d.message, 'error'); } }) .catch(e => { showNotification('Erreur de connexion lors de la finalisation : ' + e.message, 'error'); console.error('Submit error:', e); }); };
        Quiz.prototype.randomFill = function() { if (!confirm('Remplir toutes les questions avec des réponses aléatoires? (Mode debug uniquement)')) return; this.answers = {}; document.querySelectorAll('.question-step').forEach(q => { const opts = q.querySelectorAll('.option'); const rand = opts[Math.floor(Math.random() * opts.length)]; if (rand) { q.querySelectorAll('.option').forEach(o => o.classList.remove('selected')); rand.classList.add('selected'); this.answers[q.dataset.question] = rand.dataset.value; } }); this.updateQuestionNumbers(); this.updateUI(); showNotification('Questions remplies aléatoirement (mode debug)', 'info'); };
        Quiz.prototype.correctFill = function() { if (!confirm('Remplir toutes les questions avec les bonnes réponses? (Mode debug uniquement)')) return; this.answers = {}; document.querySelectorAll('.question-step').forEach(q => { const cor = q.querySelector('.option[data-correct="true"]'); if (cor) { q.querySelectorAll('.option').forEach(o => o.classList.remove('selected')); cor.classList.add('selected'); this.answers[q.dataset.question] = cor.dataset.value; } }); this.updateQuestionNumbers(); this.updateUI(); showNotification('Questions remplies avec les bonnes réponses (mode debug)', 'info'); };
        Quiz.prototype.enableAutoSave = function() { setInterval(() => { if (Object.keys(this.answers).length > 0) this.save(true); }, 300000); };
        document.addEventListener('DOMContentLoaded', () => { const urlP = new URLSearchParams(window.location.search); const ktContent = document.getElementById('kt_content'); if (urlP.get('guide') !== 'false') { ktContent.classList.add('modal-open'); document.getElementById('welcomeModal').style.display = 'flex'; } else { beginQuiz(); } <?php if ($debugMode): ?> if (debugMode) { document.getElementById('randomFillBtn').addEventListener('click', () => window.quiz?.randomFill()); document.getElementById('correctFillBtn').addEventListener('click', () => window.quiz?.correctFill()); } <?php endif; ?> });
    </script>
    <?php
}
?>