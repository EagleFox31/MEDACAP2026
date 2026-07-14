/* ============================================================================
   background.js — Génère les formes flottantes de l'arrière-plan (parallaxe)
   Inspiré du fond de la page de connexion d'Academy Assist.
   Usage : <div class="app-bg"></div><div class="shapes" id="shapes"></div>
   ============================================================================ */
(function () {
  'use strict';

  // --- Gestion de la direction des transitions de page ---
  const navDir = sessionStorage.getItem('suzuki_nav_dir');
  if (navDir === 'back') {
    document.documentElement.classList.add('nav-back');
    sessionStorage.removeItem('suzuki_nav_dir');
  }

  // Intercepte les clics sur les boutons/liens de déconnexion pour définir la transition de retour
  document.addEventListener('click', function (e) {
    const link = e.target.closest('a');
    if (link && (link.classList.contains('btn-logout') || link.getAttribute('href') === 'login.html')) {
      sessionStorage.setItem('suzuki_nav_dir', 'back');
    }
  });

  // Si le navigateur prend en charge les transitions de vue natives (cross-document) sur serveur, 
  // on désactive les classes d'animation d'entrée de secours pour éviter les conflits d'animation.
  const supportsCrossDocVT = ('onpagereveal' in window) && (window.location.protocol !== 'file:');
  if (supportsCrossDocVT) {
    const enterPage = document.querySelector('.page-enter');
    if (enterPage) {
      enterPage.classList.remove('page-enter');
    }
  }

  const host = document.getElementById('shapes');
  if (!host) return;

  const COUNT = window.matchMedia('(max-width: 760px)').matches ? 14 : 26;
  const frag = document.createDocumentFragment();

  for (let i = 0; i < COUNT; i++) {
    const el = document.createElement('div');
    el.className = 'shape';
    const size = 24 + Math.random() * 90;
    el.style.width = size + 'px';
    el.style.height = size + 'px';
    el.style.left = (Math.random() * 100) + '%';
    el.style.top = (Math.random() * 100) + '%';
    el.style.opacity = (0.08 + Math.random() * 0.18).toFixed(2);
    el.style.borderRadius = i % 2 === 0 ? '50%' : '22%';
    el.style.animationDuration = (16 + Math.random() * 22) + 's';
    el.style.animationDelay = (-Math.random() * 20) + 's';
    frag.appendChild(el);
  }
  host.appendChild(frag);
})();
