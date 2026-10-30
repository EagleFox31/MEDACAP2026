/**
 * MEDACAP Offline QCM Player v1
 * Vanilla JS — Chrome / Edge Android compatible
 */
(function () {
  'use strict';

  var FORMAT_VERSION = 'medacap-offline-qcm-v1';
  var DB_NAME = 'medacap-offline-qcm';
  var DB_STORE = 'sessions';
  var DB_VERSION = 1;

  /** @type {object|null} */
  var state = null;
  /** @type {number} */
  var currentIndex = 0;
  /** @type {boolean} */
  var embeddedMode = false;
  /** @type {object|null} */
  var pendingKitData = null;
  /** @type {object|null} */
  var pendingKitCache = null;
  /** @type {number|null} */
  var timerInterval = null;
  /** @type {number} */
  var remainingSeconds = 0;

  /* DOM refs */
  var el = {};

  function $(id) {
    return document.getElementById(id);
  }

  function initDom() {
    el.landing = $('screen-landing');
    el.welcome = $('screen-welcome');
    el.identify = $('screen-identify');
    el.quiz = $('screen-quiz');
    el.done = $('screen-done');
    el.dropZone = $('drop-zone');
    el.fileInput = $('file-input');
    el.btnPick = $('btn-pick-file');
    el.landingError = $('landing-error');
    el.resumeWrap = $('resume-list-wrap');
    el.resumeList = $('resume-list');
    el.displayUsername = $('display-username');
    el.displayMeta = $('display-meta');
    el.timerDisplay = $('timer-display');
    el.timerEl = $('timer');
    el.progressText = $('progress-text');
    el.progressFill = $('progress-fill');
    el.progressTrack = document.querySelector('.progress-track');
    el.statusBadge = $('status-badge');
    el.questionIndex = $('question-index');
    el.questionSpecialty = $('question-specialty');
    el.questionLabel = $('question-label');
    el.questionImageWrap = $('question-image-wrap');
    el.questionImage = $('question-image');
    el.proposalsList = $('proposals-list');
    el.btnPrev = $('btn-prev');
    el.btnNext = $('btn-next');
    el.btnGrid = $('btn-grid');
    el.btnSave = $('btn-save');
    el.btnFinalize = $('btn-finalize');
    el.saveStatus = $('save-status');
    el.gridModal = $('grid-modal');
    el.questionGrid = $('question-grid');
    el.btnCloseGrid = $('btn-close-grid');
    el.finalizeModal = $('finalize-modal');
    el.finalizeSummary = $('finalize-summary');
    el.btnCancelFinalize = $('btn-cancel-finalize');
    el.btnConfirmFinalize = $('btn-confirm-finalize');
    el.doneMessage = $('done-message');
    el.doneStats = $('done-stats');
    el.btnDownload = $('btn-download');
    el.btnNew = $('btn-new');
    el.welcomeInfoList = $('welcome-info-list');
    el.welcomeFooterMeta = $('welcome-footer-meta');
    el.btnWelcomeContinue = $('btn-welcome-continue');
    el.btnWelcomeResume = $('btn-welcome-resume');
    el.inputCandidateName = $('input-candidate-name');
    el.identifyHint = $('identify-hint');
    el.identifyError = $('identify-error');
    el.btnIdentifyBack = $('btn-identify-back');
    el.btnIdentifyStart = $('btn-identify-start');
    el.quizStepBadge = $('quiz-step-badge');
    el.screenTransition = $('screen-transition');
    el.btnStartDeclaratif = $('btn-start-declaratif');
    el.transitionTaskCount = $('transition-task-count');
    el.doneBreakdown = $('done-breakdown');
    el.doneFactuelBox = $('done-factuel-box');
    el.doneFactuelVal = $('done-factuel-val');
    el.doneDeclaBox = $('done-decla-box');
    el.doneDeclaVal = $('done-decla-val');
  }

  /* ── IndexedDB ── */

  function openDb() {
    return new Promise(function (resolve, reject) {
      var req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = function (e) {
        var db = e.target.result;
        if (!db.objectStoreNames.contains(DB_STORE)) {
          db.createObjectStore(DB_STORE, { keyPath: 'exportId' });
        }
      };
      req.onsuccess = function () { resolve(req.result); };
      req.onerror = function () { reject(req.error); };
    });
  }

  function idbGet(exportId) {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(DB_STORE, 'readonly');
        var store = tx.objectStore(DB_STORE);
        var req = store.get(exportId);
        req.onsuccess = function () { resolve(req.result || null); };
        req.onerror = function () { reject(req.error); };
      });
    });
  }

  function idbPut(record) {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(DB_STORE, 'readwrite');
        var store = tx.objectStore(DB_STORE);
        var req = store.put(record);
        req.onsuccess = function () { resolve(); };
        req.onerror = function () { reject(req.error); };
      });
    });
  }

  function idbGetAll() {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(DB_STORE, 'readonly');
        var store = tx.objectStore(DB_STORE);
        var req = store.getAll();
        req.onsuccess = function () { resolve(req.result || []); };
        req.onerror = function () { reject(req.error); };
      });
    });
  }

  /* ── Validation ── */

  function validatePayload(data) {
    if (!data || typeof data !== 'object') {
      throw new Error('Fichier JSON invalide.');
    }
    if (data.format !== FORMAT_VERSION && data.version !== FORMAT_VERSION) {
      throw new Error('Format non reconnu. Attendu : ' + FORMAT_VERSION);
    }
    if (!data.meta || !data.meta.exportId) {
      throw new Error('Champ meta.exportId manquant.');
    }
    var hasF = Array.isArray(data.questions) && data.questions.length > 0;
    var hasD = Array.isArray(data.declaratifQuestions) && data.declaratifQuestions.length > 0;
    if (!hasF && !hasD) {
      throw new Error('Aucune question trouvée dans le kit.');
    }
    var validStatus = ['pending', 'in_progress', 'completed'];
    if (validStatus.indexOf(data.status) === -1) {
      data.status = 'pending';
    }
    if (!data.session) {
      data.session = {};
    }
    if (!data.session.timer) {
      data.session.timer = { hours: 2, minutes: 0, seconds: 0 };
    }
    return data;
  }

  function ensureAnswersKeys(data) {
    if (data.questions && Array.isArray(data.questions)) {
      data.answers = data.answers || {};
      data.questions.forEach(function (q) {
        var key = String(q.index != null ? q.index : q.id);
        if (!(key in data.answers)) {
          data.answers[key] = null;
        }
      });
    }
    if (data.declaratifQuestions && Array.isArray(data.declaratifQuestions)) {
      data.declaratifAnswers = data.declaratifAnswers || {};
      data.declaratifQuestions.forEach(function (q) {
        var key = String(q.index != null ? q.index : q.id);
        if (!(key in data.declaratifAnswers)) {
          data.declaratifAnswers[key] = null;
        }
      });
    }
    data.session = data.session || {};
    if (!data.session.step) {
      data.session.step = (data.questions && data.questions.length > 0) ? 'factuel' : 'declaratif';
    }
    return data;
  }

  /* ── 2-en-1 Helpers ── */

  function hasFactuel() {
    return !!(state && state.questions && state.questions.length > 0);
  }

  function hasDeclaratif() {
    return !!(state && state.declaratifQuestions && state.declaratifQuestions.length > 0);
  }

  function isDeclaratifStep() {
    return !!(state && state.session && state.session.step === 'declaratif');
  }

  function getActiveQuestions() {
    if (isDeclaratifStep()) {
      return (state && state.declaratifQuestions) || [];
    }
    return (state && state.questions) || [];
  }

  function getActiveAnswers() {
    if (isDeclaratifStep()) {
      return (state && state.declaratifAnswers) || {};
    }
    return (state && state.answers) || {};
  }

  /* ── File I/O ── */

  function readFileAsText(file) {
    return new Promise(function (resolve, reject) {
      var reader = new FileReader();
      reader.onload = function () { resolve(reader.result); };
      reader.onerror = function () { reject(new Error('Lecture du fichier impossible.')); };
      reader.readAsText(file, 'UTF-8');
    });
  }

  function parseJson(text) {
    try {
      return JSON.parse(text);
    } catch (e) {
      throw new Error('JSON illisible : ' + e.message);
    }
  }

  function getEmbeddedData() {
    var el = document.getElementById('medacap-quiz-data');
    if (!el || !el.textContent) {
      return null;
    }
    return parseJson(el.textContent);
  }

  function downloadBlob(content, filename, mimeType) {
    var blob = new Blob([content], { type: mimeType });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = filename || 'epreuve.html';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
  }

  function downloadJson(data, filename) {
    downloadBlob(JSON.stringify(data, null, 2), filename || 'epreuve.medacap.json', 'application/json');
  }

  function syncEmbeddedDataInDom(data) {
    var node = document.getElementById('medacap-quiz-data');
    if (node) {
      node.textContent = JSON.stringify(data);
    }
  }

  function downloadCompletedHtml(data, filename) {
    syncEmbeddedDataInDom(data);
    var html = '<!DOCTYPE html>\n' + document.documentElement.outerHTML;
    downloadBlob(html, filename || suggestedHtmlFilename(), 'text/html;charset=utf-8');
  }

  function suggestedFilename() {
    if (!state || !state.meta) {
      return 'epreuve_REPONSES.medacap.json';
    }
    var slug = (state.meta.username || 'candidat').replace(/[^a-zA-Z0-9_-]/g, '_');
    var level = state.meta.level || 'QCM';
    return slug + '_' + level + '_REPONSES.medacap.json';
  }

  function suggestedHtmlFilename() {
    if (!state || !state.meta) {
      return 'epreuve_QCM.html';
    }
    var slug = (state.meta.username || 'candidat').replace(/[^a-zA-Z0-9_-]/g, '_');
    var level = state.meta.level || 'QCM';
    return slug + '_' + level + '_QCM.html';
  }

  function downloadResultFile() {
    if (embeddedMode) {
      downloadCompletedHtml(state, suggestedHtmlFilename());
    } else {
      downloadJson(state, suggestedFilename());
    }
  }

  /* ── State persistence ── */

  function buildPersistRecord() {
    return {
      exportId: state.meta.exportId,
      username: state.meta.username || '',
      level: state.meta.level || '',
      type: state.meta.type || '',
      status: state.status,
      updatedAt: new Date().toISOString(),
      currentIndex: currentIndex,
      remainingSeconds: remainingSeconds,
      data: state
    };
  }

  function saveAll(showFeedback) {
    if (!state) {
      return Promise.resolve();
    }

    state.session.remainingSeconds = remainingSeconds;
    state.session.lastSavedAt = new Date().toISOString();

    var record = buildPersistRecord();

    return idbPut(record).then(function () {
      if (showFeedback) {
        el.saveStatus.textContent = 'Sauvegardé';
        el.saveStatus.classList.add('saved');
        setTimeout(function () {
          el.saveStatus.textContent = '';
          el.saveStatus.classList.remove('saved');
        }, 2500);
      }
    }).catch(function (err) {
      console.error('Save error:', err);
      if (showFeedback) {
        el.saveStatus.textContent = 'Erreur sauvegarde';
      }
    });
  }

  function autosave() {
    saveAll(false);
  }

  /* ── Timer ── */

  function timerToSeconds(timer) {
    var h = parseInt(timer.hours, 10) || 0;
    var m = parseInt(timer.minutes, 10) || 0;
    var s = parseInt(timer.seconds, 10) || 0;
    return h * 3600 + m * 60 + s;
  }

  function formatTime(totalSec) {
    var sec = Math.max(0, totalSec);
    var h = Math.floor(sec / 3600);
    var m = Math.floor((sec % 3600) / 60);
    var s = sec % 60;
    function pad(n) { return n < 10 ? '0' + n : String(n); }
    if (h > 0) {
      return pad(h) + ':' + pad(m) + ':' + pad(s);
    }
    return pad(m) + ':' + pad(s);
  }

  function startTimer() {
    stopTimer();
    updateTimerDisplay();

    timerInterval = setInterval(function () {
      if (remainingSeconds <= 0) {
        stopTimer();
        onTimerExpired();
        return;
      }
      remainingSeconds -= 1;
      updateTimerDisplay();
      if (remainingSeconds % 30 === 0) {
        autosave();
      }
    }, 1000);
  }

  function stopTimer() {
    if (timerInterval) {
      clearInterval(timerInterval);
      timerInterval = null;
    }
  }

  function updateTimerDisplay() {
    el.timerDisplay.textContent = formatTime(remainingSeconds);
    el.timerEl.classList.remove('warning', 'danger');
    if (remainingSeconds <= 300 && remainingSeconds > 60) {
      el.timerEl.classList.add('warning');
    } else if (remainingSeconds <= 60) {
      el.timerEl.classList.add('danger');
    }
  }

  function onTimerExpired() {
    el.timerDisplay.textContent = '00:00';
    if (state && state.status !== 'completed') {
      alert('Temps écoulé ! Veuillez terminer et enregistrer votre épreuve.');
    }
  }

  function initTimerFromState() {
    if (state.session.remainingSeconds != null && state.session.remainingSeconds >= 0) {
      remainingSeconds = state.session.remainingSeconds;
    } else {
      remainingSeconds = timerToSeconds(state.session.timer);
      state.session.remainingSeconds = remainingSeconds;
    }
    if (state.status !== 'completed') {
      if (!state.session.startedAt) {
        state.session.startedAt = new Date().toISOString();
        state.status = 'in_progress';
      }
      startTimer();
    } else {
      updateTimerDisplay();
    }
  }

  /* ── UI helpers ── */

  function showScreen(name) {
    el.landing.classList.remove('screen-active');
    if (el.welcome) { el.welcome.classList.remove('screen-active'); }
    if (el.identify) { el.identify.classList.remove('screen-active'); }
    el.quiz.classList.remove('screen-active');
    if (el.screenTransition) { el.screenTransition.classList.remove('screen-active'); }
    el.done.classList.remove('screen-active');
    if (name === 'landing') { el.landing.classList.add('screen-active'); }
    if (name === 'welcome' && el.welcome) { el.welcome.classList.add('screen-active'); }
    if (name === 'identify' && el.identify) { el.identify.classList.add('screen-active'); }
    if (name === 'quiz') { el.quiz.classList.add('screen-active'); }
    if (name === 'transition' && el.screenTransition) { el.screenTransition.classList.add('screen-active'); }
    if (name === 'done') { el.done.classList.add('screen-active'); }
  }

  function formatTimerLabel(timer) {
    if (!timer) {
      return '—';
    }
    var h = parseInt(timer.hours, 10) || 0;
    var m = parseInt(timer.minutes, 10) || 0;
    var parts = [];
    if (h > 0) {
      parts.push(h + ' h');
    }
    if (m > 0 || h === 0) {
      parts.push(m + ' min');
    }
    return parts.join(' ');
  }

  function expectedCandidateName(meta) {
    if (!meta) {
      return '';
    }
    if (meta.firstName || meta.lastName) {
      return ((meta.firstName || '') + ' ' + (meta.lastName || '')).trim();
    }
    return meta.username || '';
  }

  function normalizeName(str) {
    return (str || '').toLowerCase().replace(/\s+/g, ' ').trim();
  }

  function namesRoughlyMatch(typed, expected) {
    var a = normalizeName(typed);
    var b = normalizeName(expected);
    if (!a || !b) {
      return true;
    }
    return a === b || a.indexOf(b) >= 0 || b.indexOf(a) >= 0;
  }

  function addWelcomeInfoRow(label, value) {
    if (!value) {
      return;
    }
    var li = document.createElement('li');
    li.innerHTML = '<span class="info-label">' + escapeHtml(label) + '</span>' +
      '<span class="info-value">' + escapeHtml(value) + '</span>';
    el.welcomeInfoList.appendChild(li);
  }

  function renderWelcomeScreen(data, cached) {
    var meta = data.meta || {};
    var expected = expectedCandidateName(meta);
    var qCount = meta.questionCount || (data.questions ? data.questions.length : 0);
    var declaCount = meta.declaratifQuestionCount || (data.declaratifQuestions ? data.declaratifQuestions.length : 0);
    var timerLabel = formatTimerLabel(data.session && data.session.timer);

    el.welcomeInfoList.innerHTML = '';
    addWelcomeInfoRow('Candidat attendu', expected || meta.username || '—');
    addWelcomeInfoRow('Agence', meta.agency);
    addWelcomeInfoRow('Filiale', meta.subsidiary);
    addWelcomeInfoRow('Pays', meta.country);
    addWelcomeInfoRow('Niveau', meta.level);
    addWelcomeInfoRow('Type d\'épreuve', meta.type || 'Factuel');

    if (qCount > 0 && declaCount > 0) {
      addWelcomeInfoRow('Partie 1 (Théorique)', qCount + ' questions QCM (' + timerLabel + ')');
      addWelcomeInfoRow('Partie 2 (Pratique)', declaCount + ' tâches (Chrono libre)');
    } else if (qCount > 0) {
      addWelcomeInfoRow('Questions', String(qCount));
      addWelcomeInfoRow('Durée', timerLabel);
    } else if (declaCount > 0) {
      addWelcomeInfoRow('Tâches à évaluer', String(declaCount));
      addWelcomeInfoRow('Durée', 'Chrono libre');
    }

    if (el.welcomeFooterMeta) {
      var footerParts = ['MEDACAP'];
      if (meta.agency) { footerParts.push(meta.agency); }
      if (meta.level) { footerParts.push(meta.level); }
      el.welcomeFooterMeta.textContent = footerParts.join(' · ');
    }

    var canResume = cached && cached.data && data.status !== 'completed' &&
      data.session && data.session.candidateName;
    if (canResume) {
      el.btnWelcomeResume.classList.remove('hidden');
      el.btnWelcomeContinue.textContent = 'Nouvelle identification';
      el.btnWelcomeContinue.classList.remove('btn-primary');
      el.btnWelcomeContinue.classList.add('btn-ghost');
    } else {
      el.btnWelcomeResume.classList.add('hidden');
      el.btnWelcomeContinue.textContent = 'Continuer';
      el.btnWelcomeContinue.classList.add('btn-primary');
      el.btnWelcomeContinue.classList.remove('btn-ghost');
    }
  }

  function showIdentifyScreen() {
    var meta = pendingKitData && pendingKitData.meta ? pendingKitData.meta : {};
    var expected = expectedCandidateName(meta);
    var prefill = (pendingKitData && pendingKitData.session && pendingKitData.session.candidateName) || '';

    el.inputCandidateName.value = prefill;
    el.identifyHint.textContent = expected
      ? 'Épreuve prévue pour : ' + expected
      : '';
    el.identifyError.classList.add('hidden');
    el.identifyError.textContent = '';
    showScreen('identify');
    setTimeout(function () {
      el.inputCandidateName.focus();
    }, 100);
  }

  function clearIdentifyError() {
    el.identifyError.classList.add('hidden');
    el.identifyError.textContent = '';
  }

  function showIdentifyError(msg) {
    el.identifyError.textContent = msg;
    el.identifyError.classList.remove('hidden');
  }

  function startExamWithName(name) {
    if (!pendingKitData) {
      return;
    }
    pendingKitData.session = pendingKitData.session || {};
    pendingKitData.session.candidateName = name.trim();
    pendingKitData.session.identifiedAt = new Date().toISOString();
    loadFromData(pendingKitData, pendingKitCache);
    pendingKitCache = null;
  }

  function submitIdentifyForm() {
    var name = (el.inputCandidateName.value || '').trim();
    if (name.length < 2) {
      showIdentifyError('Veuillez saisir votre nom complet (au moins 2 caractères).');
      return;
    }
    clearIdentifyError();

    var expected = expectedCandidateName(pendingKitData.meta);
    if (expected && !namesRoughlyMatch(name, expected)) {
      var ok = confirm(
        'Le nom saisi (« ' + name + ' ») ne correspond pas exactement au candidat prévu (« ' +
        expected + ' »).\n\nVoulez-vous quand même commencer cette épreuve ?'
      );
      if (!ok) {
        return;
      }
    }

    startExamWithName(name);
  }

  function showLandingError(msg) {
    el.landingError.textContent = msg;
    el.landingError.classList.remove('hidden');
  }

  function clearLandingError() {
    el.landingError.classList.add('hidden');
    el.landingError.textContent = '';
  }

  function answerKeyForQuestion(q) {
    return String(q.index != null ? q.index : q.id);
  }

  function countFactuelAnswered() {
    if (!hasFactuel()) return 0;
    var n = 0;
    state.questions.forEach(function (q) {
      var key = answerKeyForQuestion(q);
      if (state.answers && state.answers[key] != null && state.answers[key] !== '') {
        n += 1;
      }
    });
    return n;
  }

  function countDeclaratifAnswered() {
    if (!hasDeclaratif()) return 0;
    var n = 0;
    state.declaratifQuestions.forEach(function (q) {
      var key = answerKeyForQuestion(q);
      if (state.declaratifAnswers && state.declaratifAnswers[key] != null && state.declaratifAnswers[key] !== '') {
        n += 1;
      }
    });
    return n;
  }

  function countAnswered() {
    var questions = getActiveQuestions();
    var answers = getActiveAnswers();
    var n = 0;
    questions.forEach(function (q) {
      var key = answerKeyForQuestion(q);
      if (answers[key] != null && answers[key] !== '') {
        n += 1;
      }
    });
    return n;
  }

  function isLocked() {
    return state && state.status === 'completed';
  }

  function updateProgress() {
    var questions = getActiveQuestions();
    var total = questions.length;
    var answered = countAnswered();
    var noun = isDeclaratifStep() ? 'tâches évaluées' : 'répondues';
    el.progressText.textContent = answered + ' / ' + total + ' ' + noun;
    var pct = total > 0 ? Math.round((answered / total) * 100) : 0;
    el.progressFill.style.width = pct + '%';
    if (el.progressTrack) {
      el.progressTrack.setAttribute('aria-valuenow', String(pct));
    }
  }

  function updateStatusBadge() {
    var badge = el.statusBadge;
    badge.classList.remove('badge-progress', 'badge-done', 'badge-locked');
    if (state.status === 'completed') {
      badge.textContent = 'Terminé';
      badge.classList.add('badge-done');
    } else if (state.status === 'in_progress') {
      badge.textContent = 'En cours';
      badge.classList.add('badge-progress');
    } else {
      badge.textContent = 'En attente';
      badge.classList.add('badge-progress');
    }
  }

  function renderQuestion() {
    var questions = getActiveQuestions();
    var answers = getActiveAnswers();
    var q = questions[currentIndex];
    if (!q) {
      return;
    }

    var key = answerKeyForQuestion(q);
    var selected = answers[key];
    var isDecla = isDeclaratifStep();

    if (isDecla) {
      el.questionIndex.textContent = 'Tâche ' + (q.index != null ? q.index : currentIndex + 1);
      if (el.quizStepBadge) {
        el.quizStepBadge.textContent = 'Étape 2/2 · Pratique';
      }
      el.btnFinalize.textContent = 'Terminer l\'épreuve';
      el.timerDisplay.textContent = 'Auto-évaluation';
      el.timerEl.classList.remove('warning', 'danger');
    } else {
      el.questionIndex.textContent = 'Q ' + (q.index != null ? q.index : currentIndex + 1);
      if (el.quizStepBadge) {
        el.quizStepBadge.textContent = hasDeclaratif() ? 'Étape 1/2 · Théorique' : 'QCM Théorique';
      }
      el.btnFinalize.textContent = hasDeclaratif() ? 'Passer à la pratique →' : 'Terminer l\'épreuve';
    }

    el.questionSpecialty.textContent = q.specialty || q.ref || '';
    el.questionLabel.textContent = q.label || '—';

    if (q.image) {
      el.questionImage.src = q.image;
      el.questionImageWrap.classList.remove('hidden');
    } else {
      el.questionImage.src = '';
      el.questionImageWrap.classList.add('hidden');
    }

    el.proposalsList.innerHTML = '';
    var letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    var proposals = q.proposals || [];
    var locked = isLocked();

    proposals.forEach(function (text, i) {
      var li = document.createElement('li');
      li.className = 'proposal-item';
      if (isDecla) {
        li.classList.add('proposal-declaratif');
        if (i === 0) li.classList.add('choice-master');
        else if (i === 1) li.classList.add('choice-partial');
        else if (i === 2) li.classList.add('choice-never');
      }

      var inputId = 'prop-' + key + '-' + i;
      var input = document.createElement('input');
      input.type = 'radio';
      input.name = 'q-' + key;
      input.id = inputId;
      input.value = String(i);
      input.disabled = locked;

      if (selected != null && String(selected) === String(i)) {
        input.checked = true;
      }

      input.addEventListener('change', function () {
        answers[key] = i;
        updateProgress();
        autosave();
        renderGridCells();

        if (isDecla && currentIndex < questions.length - 1) {
          setTimeout(function () {
            if (currentIndex < questions.length - 1) {
              currentIndex += 1;
              renderQuestion();
            }
          }, 250);
        }
      });

      var label = document.createElement('label');
      label.setAttribute('for', inputId);

      var badgeContent = '';
      if (isDecla) {
        var icons = ['🟢', '🟡', '⚪'];
        badgeContent = '<span class="proposal-letter">' + (icons[i] || '•') + '</span>';
      } else {
        badgeContent = '<span class="proposal-letter">' + (letters[i] || (i + 1)) + '</span>';
      }

      label.innerHTML = badgeContent + '<span class="proposal-text">' + escapeHtml(text) + '</span>';

      li.appendChild(input);
      li.appendChild(label);
      el.proposalsList.appendChild(li);
    });

    el.btnPrev.disabled = currentIndex <= 0;
    el.btnNext.disabled = currentIndex >= questions.length - 1;

    if (locked) {
      el.quiz.classList.add('quiz-locked');
    } else {
      el.quiz.classList.remove('quiz-locked');
    }

    updateProgress();
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function renderHeader() {
    var displayName = (state.session && state.session.candidateName) ||
      state.meta.username || 'Candidat';
    el.displayUsername.textContent = displayName;
    var parts = [];
    if (state.meta.level) { parts.push(state.meta.level); }
    if (state.meta.type) { parts.push(state.meta.type); }
    el.displayMeta.textContent = parts.join(' · ') || state.meta.exportId;

    if (el.quizStepBadge) {
      if (isDeclaratifStep()) {
        el.quizStepBadge.textContent = 'Étape 2/2 · Pratique';
      } else {
        el.quizStepBadge.textContent = hasDeclaratif() ? 'Étape 1/2 · Théorique' : 'QCM Théorique';
      }
    }
    updateStatusBadge();
  }

  function renderGridCells() {
    var questions = getActiveQuestions();
    var answers = getActiveAnswers();
    el.questionGrid.innerHTML = '';
    questions.forEach(function (q, i) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'grid-cell';
      btn.setAttribute('role', 'listitem');
      btn.textContent = String(q.index != null ? q.index : i + 1);

      var key = answerKeyForQuestion(q);
      var ans = answers[key];
      if (ans != null && ans !== '') {
        btn.classList.add('answered');
      }
      if (i === currentIndex) {
        btn.classList.add('current');
      }

      btn.addEventListener('click', function () {
        currentIndex = i;
        renderQuestion();
        renderGridCells();
        closeGridModal();
      });

      el.questionGrid.appendChild(btn);
    });
  }

  function openGridModal() {
    renderGridCells();
    if (typeof el.gridModal.showModal === 'function') {
      el.gridModal.showModal();
    } else {
      el.gridModal.setAttribute('open', 'open');
    }
  }

  function closeGridModal() {
    if (typeof el.gridModal.close === 'function') {
      el.gridModal.close();
    } else {
      el.gridModal.removeAttribute('open');
    }
  }

  /* ── Load & start ── */

  function loadFromData(data, fromIdbRecord) {
    state = ensureAnswersKeys(validatePayload(data));
    currentIndex = 0;

    var activeQ = getActiveQuestions();
    if (fromIdbRecord && fromIdbRecord.currentIndex != null) {
      currentIndex = Math.min(fromIdbRecord.currentIndex, Math.max(0, activeQ.length - 1));
    }

    if (state.session && state.session.step === 'transition') {
      showTransitionScreen();
      return;
    }

    renderHeader();
    if (!isDeclaratifStep() && hasFactuel()) {
      initTimerFromState();
    }
    renderQuestion();
    showScreen('quiz');
    clearLandingError();
    saveAll(false);
  }

  function loadFromFile(file) {
    clearLandingError();
    return readFileAsText(file).then(function (text) {
      var data = parseJson(text);
      return idbGet(data.meta && data.meta.exportId).then(function (cached) {
        if (cached && cached.data && cached.status !== 'completed') {
          var useCache = confirm(
            'Une session en cours existe pour cette épreuve.\n' +
            'Voulez-vous reprendre où vous vous étiez arrêté ?'
          );
          if (useCache) {
            loadFromData(cached.data, cached);
            return;
          }
        }
        loadFromData(data, null);
      });
    }).catch(function (err) {
      showLandingError(err.message || 'Erreur de chargement.');
    });
  }

  function loadFromPicker() {
    clearLandingError();
    el.fileInput.click();
  }

  function refreshResumeList() {
    idbGetAll().then(function (records) {
      var active = records.filter(function (r) {
        return r.status !== 'completed';
      });
      if (active.length === 0) {
        el.resumeWrap.classList.add('hidden');
        return;
      }
      el.resumeWrap.classList.remove('hidden');
      el.resumeList.innerHTML = '';
      active.sort(function (a, b) {
        return (b.updatedAt || '').localeCompare(a.updatedAt || '');
      });
      active.forEach(function (rec) {
        var li = document.createElement('li');
        var btn = document.createElement('button');
        btn.type = 'button';
        var label = rec.username || 'Session';
        if (rec.level) { label += ' — ' + rec.level; }
        btn.textContent = label + ' (' + (rec.status || 'en cours') + ')';
        btn.addEventListener('click', function () {
          loadFromData(rec.data, rec);
        });
        li.appendChild(btn);
        el.resumeList.appendChild(li);
      });
    }).catch(function () {
      el.resumeWrap.classList.add('hidden');
    });
  }

  /* ── Finalize ── */

  function openFinalizeModal() {
    var isDecla = isDeclaratifStep();
    var questions = getActiveQuestions();
    var total = questions.length;
    var answered = countAnswered();
    var unanswered = total - answered;

    var modalTitle = el.finalizeModal.querySelector('h3');
    var modalDesc = el.finalizeModal.querySelector('p');

    if (!isDecla && hasDeclaratif()) {
      if (modalTitle) modalTitle.textContent = 'Passer à l\'épreuve pratique ?';
      if (modalDesc) modalDesc.textContent = 'Vos réponses théoriques seront enregistrées. Vous passerez ensuite à l\'auto-évaluation déclarative.';
      el.finalizeSummary.textContent =
        answered + ' question(s) répondue(s) sur ' + total +
        (unanswered > 0 ? ' — ' + unanswered + ' sans réponse.' : '.');
      el.btnConfirmFinalize.textContent = 'Valider et continuer →';
    } else {
      if (modalTitle) modalTitle.textContent = 'Terminer l\'épreuve ?';
      if (modalDesc) modalDesc.textContent = 'Vous ne pourrez plus modifier vos réponses après validation.';
      var fAns = countFactuelAnswered();
      var dAns = countDeclaratifAnswered();
      var summaryParts = [];
      if (hasFactuel()) {
        summaryParts.push('Théorique : ' + fAns + '/' + state.questions.length);
      }
      if (hasDeclaratif()) {
        summaryParts.push('Pratique : ' + dAns + '/' + state.declaratifQuestions.length);
      }
      el.finalizeSummary.textContent = summaryParts.join(' · ');
      el.btnConfirmFinalize.textContent = 'Confirmer et terminer';
    }

    if (typeof el.finalizeModal.showModal === 'function') {
      el.finalizeModal.showModal();
    } else {
      el.finalizeModal.setAttribute('open', 'open');
    }
  }

  function closeFinalizeModal() {
    if (typeof el.finalizeModal.close === 'function') {
      el.finalizeModal.close();
    } else {
      el.finalizeModal.removeAttribute('open');
    }
  }

  function showTransitionScreen() {
    if (el.transitionTaskCount && state.declaratifQuestions) {
      el.transitionTaskCount.textContent = state.declaratifQuestions.length + ' tâches';
    }
    showScreen('transition');
  }

  function startDeclaratifStep() {
    state.session.step = 'declaratif';
    state.session.declaratifStartedAt = new Date().toISOString();
    currentIndex = 0;
    saveAll(false);
    renderHeader();
    renderQuestion();
    showScreen('quiz');
  }

  function finalizeExam() {
    closeFinalizeModal();

    if (!isDeclaratifStep() && hasDeclaratif()) {
      stopTimer();
      state.session.step = 'transition';
      state.session.remainingSeconds = remainingSeconds;
      var rs = remainingSeconds;
      var th = Math.floor(rs / 3600);
      var tm = Math.floor((rs % 3600) / 60);
      var ts = rs % 60;
      function padT(n) { return n < 10 ? '0' + n : String(n); }
      state.session.timerFormatted = padT(th) + ':' + padT(tm) + ':' + padT(ts);

      saveAll(false).then(function () {
        showTransitionScreen();
      });
      return;
    }

    stopTimer();
    state.status = 'completed';
    state.session.completedAt = new Date().toISOString();
    if (hasFactuel()) {
      state.session.remainingSeconds = remainingSeconds;
      var rs = remainingSeconds;
      var th = Math.floor(rs / 3600);
      var tm = Math.floor((rs % 3600) / 60);
      var ts = rs % 60;
      function padT(n) { return n < 10 ? '0' + n : String(n); }
      state.session.timerFormatted = padT(th) + ':' + padT(tm) + ':' + padT(ts);
    }

    saveAll(false).then(function () {
      downloadResultFile();
      showDoneScreen();
    });
  }

  function showDoneScreen() {
    var fAns = countFactuelAnswered();
    var dAns = countDeclaratifAnswered();

    if (el.doneFactuelBox && hasFactuel()) {
      el.doneFactuelBox.classList.remove('hidden');
      el.doneFactuelVal.textContent = fAns + ' / ' + state.questions.length + ' répondues';
    } else if (el.doneFactuelBox) {
      el.doneFactuelBox.classList.add('hidden');
    }

    if (el.doneDeclaBox && hasDeclaratif()) {
      el.doneDeclaBox.classList.remove('hidden');
      el.doneDeclaVal.textContent = dAns + ' / ' + state.declaratifQuestions.length + ' tâches';
    } else if (el.doneDeclaBox) {
      el.doneDeclaBox.classList.add('hidden');
    }

    if (embeddedMode) {
      el.doneMessage.textContent =
        'Votre épreuve complétée a été téléchargée (fichier HTML). Renvoyez ce même fichier à votre responsable RH MEDACAP.';
      if (el.btnDownload) {
        el.btnDownload.textContent = 'Télécharger à nouveau le HTML complété';
      }
    } else {
      el.doneMessage.textContent =
        'Votre fichier de réponses a été téléchargé. Remettez-le à votre responsable RH MEDACAP.';
      if (el.btnDownload) {
        el.btnDownload.textContent = 'Télécharger le fichier';
      }
    }

    var parts = [];
    if (hasFactuel()) parts.push('Théorique : ' + fAns + '/' + state.questions.length);
    if (hasDeclaratif()) parts.push('Pratique : ' + dAns + '/' + state.declaratifQuestions.length);
    el.doneStats.textContent = parts.join(' · ') + ' · Statut : Terminé';

    showScreen('done');
  }

  /* ── Event bindings ── */

  function bindEvents() {
    el.btnPick.addEventListener('click', function (e) {
      e.stopPropagation();
      loadFromPicker();
    });

    el.dropZone.addEventListener('click', function () {
      loadFromPicker();
    });

    el.dropZone.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        loadFromPicker();
      }
    });

    el.fileInput.addEventListener('change', function () {
      var file = el.fileInput.files && el.fileInput.files[0];
      if (file) {
        loadFromFile(file);
      }
      el.fileInput.value = '';
    });

    ['dragenter', 'dragover'].forEach(function (evt) {
      el.dropZone.addEventListener(evt, function (e) {
        e.preventDefault();
        e.stopPropagation();
        el.dropZone.classList.add('drag-over');
      });
    });

    ['dragleave', 'drop'].forEach(function (evt) {
      el.dropZone.addEventListener(evt, function (e) {
        e.preventDefault();
        e.stopPropagation();
        el.dropZone.classList.remove('drag-over');
      });
    });

    el.dropZone.addEventListener('drop', function (e) {
      var file = e.dataTransfer.files && e.dataTransfer.files[0];
      if (file) {
        loadFromFile(file);
      }
    });

    el.btnPrev.addEventListener('click', function () {
      if (currentIndex > 0) {
        currentIndex -= 1;
        renderQuestion();
      }
    });

    el.btnNext.addEventListener('click', function () {
      var questions = getActiveQuestions();
      if (currentIndex < questions.length - 1) {
        currentIndex += 1;
        renderQuestion();
      }
    });

    if (el.btnStartDeclaratif) {
      el.btnStartDeclaratif.addEventListener('click', function () {
        startDeclaratifStep();
      });
    }

    el.btnGrid.addEventListener('click', openGridModal);
    el.btnCloseGrid.addEventListener('click', closeGridModal);

    el.btnSave.addEventListener('click', function () {
      saveAll(true);
    });

    el.btnFinalize.addEventListener('click', function () {
      if (isLocked()) {
        return;
      }
      openFinalizeModal();
    });

    el.btnCancelFinalize.addEventListener('click', closeFinalizeModal);
    el.btnConfirmFinalize.addEventListener('click', finalizeExam);

    el.btnDownload.addEventListener('click', function () {
      downloadResultFile();
    });

    el.btnNew.addEventListener('click', function () {
      if (embeddedMode) {
        return;
      }
      state = null;
      stopTimer();
      showScreen('landing');
      refreshResumeList();
    });

    if (el.btnWelcomeContinue) {
      el.btnWelcomeContinue.addEventListener('click', function () {
        showIdentifyScreen();
      });
    }

    if (el.btnWelcomeResume) {
      el.btnWelcomeResume.addEventListener('click', function () {
        if (pendingKitData && pendingKitCache) {
          loadFromData(pendingKitData, pendingKitCache);
          pendingKitCache = null;
        } else if (pendingKitData) {
          loadFromData(pendingKitData, null);
        }
      });
    }

    if (el.btnIdentifyBack) {
      el.btnIdentifyBack.addEventListener('click', function () {
        clearIdentifyError();
        showScreen('welcome');
      });
    }

    if (el.btnIdentifyStart) {
      el.btnIdentifyStart.addEventListener('click', submitIdentifyForm);
    }

    if (el.inputCandidateName) {
      el.inputCandidateName.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          submitIdentifyForm();
        }
      });
    }

    document.addEventListener('visibilitychange', function () {
      if (document.hidden && state && !isLocked()) {
        autosave();
      }
    });

    window.addEventListener('beforeunload', function () {
      if (state && !isLocked()) {
        state.session.remainingSeconds = remainingSeconds;
      }
    });
  }

  function applyEmbeddedMode() {
    document.body.classList.add('embedded-kit');
    if (el.btnNew) {
      el.btnNew.classList.add('hidden');
    }
  }

  function bootEmbedded(embedded) {
    embeddedMode = true;
    applyEmbeddedMode();
    clearLandingError();

    var baseData = ensureAnswersKeys(validatePayload(embedded));

    idbGet(embedded.meta.exportId).then(function (cached) {
      pendingKitCache = (cached && cached.data && cached.status !== 'completed') ? cached : null;
      pendingKitData = pendingKitCache ? ensureAnswersKeys(validatePayload(cached.data)) : baseData;

      if (pendingKitData.status === 'completed') {
        state = pendingKitData;
        currentIndex = 0;
        pendingKitData = null;
        showDoneScreen();
        return;
      }

      renderWelcomeScreen(pendingKitData, pendingKitCache);
      showScreen('welcome');
    }).catch(function () {
      pendingKitData = baseData;
      pendingKitCache = null;
      if (embedded.status === 'completed') {
        state = pendingKitData;
        currentIndex = 0;
        pendingKitData = null;
        showDoneScreen();
        return;
      }
      renderWelcomeScreen(pendingKitData, null);
      showScreen('welcome');
    });
  }

  /* ── Boot ── */

  function boot() {
    initDom();
    bindEvents();

    var embedded = getEmbeddedData();
    if (embedded) {
      bootEmbedded(embedded);
      return;
    }

    refreshResumeList();
    showScreen('landing');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
