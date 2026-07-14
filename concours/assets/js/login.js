/* ============================================================================
   login.js â€” Logique de la page de connexion
   En statique : dÃ©mo (n'importe quel identifiant non vide redirige vers
   accueil.html). En PHP : remplacez handleSubmit() par un POST rÃ©el,
   ou laissez le <form> poster cÃ´tÃ© serveur et utilisez ce JS uniquement
   pour l'UX (langue, afficher/masquer mot de passe).
   ============================================================================ */
(function () {
  'use strict';

  /* Traductions + annÃ©e */
  I18N.apply();
  document.getElementById('year').textContent = new Date().getFullYear();

  /* Choix de la langue (cartes FR / EN) + Ã©ventuel sÃ©lecteur compact */
  const langOpts = document.querySelectorAll('.lang-opt');
  function reflectLang() {
    langOpts.forEach((o) => o.classList.toggle('selected', o.dataset.lang === I18N.lang));
  }
  reflectLang();

  document.querySelectorAll('.lang-switch button').forEach((btn) => {
    btn.addEventListener('click', () => I18N.setLang(btn.dataset.lang, reflectLang));
  });
  langOpts.forEach((opt) => {
    opt.addEventListener('click', () => I18N.setLang(opt.dataset.lang, reflectLang));
  });

  /* Afficher / masquer le mot de passe */
  const pwd = document.getElementById('password');
  const toggle = document.getElementById('toggle-pwd');
  const eyeOpen = document.getElementById('eye-open');
  const eyeClosed = document.getElementById('eye-closed');
  toggle.addEventListener('click', () => {
    const show = pwd.type === 'password';
    pwd.type = show ? 'text' : 'password';
    eyeOpen.style.display = show ? 'none' : '';
    eyeClosed.style.display = show ? '' : 'none';
    toggle.setAttribute('aria-label', I18N.t(show ? 'login.hide' : 'login.show'));
  });

  /* Soumission */
  const form = document.getElementById('login-form');
  const errorBox = document.getElementById('form-error');
  const errorText = document.getElementById('form-error-text');


  function showError(msgKey) {
    errorText.textContent = I18N.t(msgKey || 'login.error');
    errorBox.classList.add('show');
  }

  form.addEventListener('submit', function (e) {
    errorBox.classList.remove('show');

    const username = document.getElementById('username').value.trim();
    const password = pwd.value.trim();
    if (!username || !password) { 
      e.preventDefault();
      showError('login.error'); 
      return; 
    }

    /* État « chargement » */
    if (document.getElementById('lang-input')) {
      document.getElementById('lang-input').value = I18N.lang;
    }
    const submitBtn = document.getElementById('submit-btn');
    const submitLabel = document.getElementById('submit-label');
    submitBtn.disabled = true;
    submitLabel.textContent = I18N.t('login.loading');
    
    // Le formulaire va se soumettre naturellement Ã  index.php via la mÃ©thode POST.
  });

  function resetBtn() {
    submitBtn.disabled = false;
    I18N.apply();   // restaure le libellÃ© bilingue du bouton (et le reste)
  }
  window.addEventListener('pageshow', resetBtn);
})();

