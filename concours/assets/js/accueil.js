/* ============================================================================
   accueil.js — Page de démarrage
   · Affiche le technicien connecté
   · Met en évidence le choix de langue (les 2 boutons + le sélecteur d'en-tête
     restent synchronisés)
   · Lance le questionnaire
   ============================================================================ */
(function () {
  'use strict';

  I18N.apply();

  /* --- Nom du technicien (démo : sessionStorage ; en PHP : injecté côté serveur) --- */
  /* --- Nom du technicien (démo : sessionStorage ; en PHP : injecté côté serveur) --- */
  /*
  const raw = sessionStorage.getItem('suzuki_user') || 'Technicien';
  function prettyName(v) {
    let s = String(v).split('@')[0].replace(/[._-]+/g, ' ').trim();
    if (!s) return 'Technicien';
    return s.replace(/\b\p{L}/gu, (c) => c.toUpperCase());
  }
  const pretty = prettyName(raw);
  document.getElementById('user-name').textContent = pretty;
  document.getElementById('hello-name').textContent = pretty;
  document.getElementById('user-avatar').textContent = pretty.charAt(0).toUpperCase();
  */

  /* --- Synchronise l'état sélectionné des grandes cartes de langue --- */
  const langOpts = document.querySelectorAll('.lang-opt');
  function reflectLang() {
    langOpts.forEach((o) => o.classList.toggle('selected', o.dataset.lang === I18N.lang));
  }
  reflectLang();

  /* Sélecteur d'en-tête (FR/EN) */
  document.querySelectorAll('.lang-switch button').forEach((btn) => {
    btn.addEventListener('click', () => I18N.setLang(btn.dataset.lang, reflectLang));
  });

  /* Grandes cartes de langue */
  langOpts.forEach((opt) => {
    opt.addEventListener('click', () => I18N.setLang(opt.dataset.lang, reflectLang));
  });

  /* --- Démarrer le concours --- */
  document.getElementById('start-btn').addEventListener('click', () => {
    /* La langue choisie est déjà mémorisée (localStorage) et sera lue par quiz.js */
    sessionStorage.setItem('suzuki_started', '1');
    window.location.href = 'quiz.php?lang=' + I18N.lang;
  });

  /* --- Diaporama de fond (fondu enchaîné discret) --- */
  const slides = document.querySelectorAll('.bg-slide');
  if (slides.length > 1) {
    let cur = 0;
    setInterval(() => {
      slides[cur].classList.remove('is-active');
      cur = (cur + 1) % slides.length;
      slides[cur].classList.add('is-active');
    }, 13000);
  }
})();
