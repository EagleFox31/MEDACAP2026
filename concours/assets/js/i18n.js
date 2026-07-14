/* ============================================================================
   i18n — Textes d'interface FR / EN (Concours Suzuki · CFAO Academy)
   La langue est mémorisée dans localStorage ("suzuki_lang").
   Les QUESTIONS du concours sont fournies séparément (par PHP), déjà bilingues.
   Tout élément portant data-i18n="cle" est traduit automatiquement.
   ============================================================================ */
(function (global) {
  'use strict';

  const STRINGS = {
    fr: {
      'app.title': 'Concours Suzuki',
      'app.sub': 'CFAO Academy',

      /* Connexion */
      'login.badge': 'Concours Technique 2026',
      'login.title': 'Concours Suzuki',
      'login.sub': 'Réservé aux techniciens CFAO Mobility',
      'login.signin': 'Connexion',
      'login.signin_sub': 'Prêt à relever le défi ?',
      'login.welcome': 'Bienvenue au <span>Concours Suzuki</span>',
      'login.challenge': 'Prêt à relever le défi&nbsp;?',
      /* Panneau de marque (colonne gauche) */
      'login.brand_kicker': 'Concours Technique · Juillet 2026',
      'login.brand_title': 'Montrez votre expertise&nbsp;<span>Suzuki</span>',
      'login.brand_lead': 'Le grand rendez-vous technique des meilleurs techniciens CFAO Mobility. Connectez-vous et prenez le départ.',
      'login.feat_q': '100 questions',
      'login.feat_q_d': 'QCM techniques',
      'login.feat_bi': 'Bilingue',
      'login.feat_bi_d': 'Français / English',
      'login.feat_tech': 'Niveau expert',
      'login.feat_tech_d': 'Techniciens CFAO',
      'login.powered': 'Propulsé par CFAO Academy',
      'login.id_label': 'Identifiant',
      'login.id_ph': 'Votre identifiant technicien',
      'login.pwd_label': 'Mot de passe',
      'login.pwd_ph': 'Votre mot de passe',
      'login.submit': 'Se connecter',
      'login.loading': 'Connexion…',
      'login.error': 'Identifiant ou mot de passe incorrect.',
      'login.help': 'Un souci pour vous connecter ? Contactez votre formateur CFAO.',
      'login.show': 'Afficher le mot de passe',
      'login.hide': 'Masquer le mot de passe',

      /* Accueil */
      'home.hello': 'Bienvenue',
      'home.title': 'Prêt pour le concours&nbsp;<span>Suzuki</span>&nbsp;?',
      'home.lead': 'Ce concours évalue vos compétences techniques. Prenez votre temps, lisez bien chaque question et répondez avec calme. Bonne chance&nbsp;!',
      'home.info_questions': 'questions',
      'home.info_choice': 'réponses au choix',
      'home.info_lang': 'bilingue',
      'home.info_lang_v': 'FR / EN',
      'home.guidelines_title': 'Avant de commencer',
      'home.g1': 'Répondez à chaque question en touchant la réponse de votre choix.',
      'home.g2': 'Vous pouvez revenir en arrière pour modifier une réponse.',
      'home.g3': 'Une barre en haut vous indique votre progression.',
      'home.g4': 'À la fin, validez pour envoyer vos réponses.',
      'home.r1_t': 'Répondez',
      'home.r2_t': 'Modifiez librement',
      'home.r3_t': 'Suivez l’avancement',
      'home.r4_t': 'Validez à la fin',
      'home.lang_q': 'Dans quelle langue souhaitez-vous passer le concours&nbsp;?',
      'home.lang_fr': 'Français',
      'home.lang_fr_hint': 'Passer le concours en français',
      'home.lang_en': 'English',
      'home.lang_en_hint': 'Take the test in English',
      'home.start': 'Commencer le concours',

      'common.logout': 'Déconnexion',
    },

    en: {
      'app.title': 'Suzuki Contest',
      'app.sub': 'CFAO Academy',

      'login.badge': 'Technical Contest 2026',
      'login.title': 'Suzuki Contest',
      'login.sub': 'For CFAO Mobility technicians only',
      'login.signin': 'Sign in',
      'login.signin_sub': 'Ready for the challenge?',
      'login.welcome': 'Welcome to the <span>Suzuki Contest</span>',
      'login.challenge': 'Ready to take on the challenge?',
      'login.brand_kicker': 'Technical Contest · July 2026',
      'login.brand_title': 'Show your&nbsp;<span>Suzuki</span>&nbsp;expertise',
      'login.brand_lead': 'The big technical challenge for the best CFAO Mobility technicians. Sign in and get on the starting line.',
      'login.feat_q': '100 questions',
      'login.feat_q_d': 'Technical MCQ',
      'login.feat_bi': 'Bilingual',
      'login.feat_bi_d': 'Français / English',
      'login.feat_tech': 'Expert level',
      'login.feat_tech_d': 'CFAO technicians',
      'login.powered': 'Powered by CFAO Academy',
      'login.id_label': 'Username',
      'login.id_ph': 'Your technician ID',
      'login.pwd_label': 'Password',
      'login.pwd_ph': 'Your password',
      'login.submit': 'Sign in',
      'login.loading': 'Signing in…',
      'login.error': 'Incorrect username or password.',
      'login.help': 'Trouble signing in? Contact your CFAO trainer.',
      'login.show': 'Show password',
      'login.hide': 'Hide password',

      'home.hello': 'Welcome',
      'home.title': 'Ready for the&nbsp;<span>Suzuki</span>&nbsp;contest?',
      'home.lead': 'This contest assesses your technical skills. Take your time, read each question carefully and answer calmly. Good luck!',
      'home.info_questions': 'questions',
      'home.info_choice': 'multiple choice',
      'home.info_lang': 'bilingual',
      'home.info_lang_v': 'FR / EN',
      'home.guidelines_title': 'Before you start',
      'home.g1': 'Answer each question by tapping the option of your choice.',
      'home.g2': 'You can go back to change an answer.',
      'home.g3': 'A bar at the top shows your progress.',
      'home.g4': 'At the end, confirm to submit your answers.',
      'home.r1_t': 'Answer',
      'home.r2_t': 'Change freely',
      'home.r3_t': 'Track progress',
      'home.r4_t': 'Submit at the end',
      'home.lang_q': 'In which language would you like to take the contest?',
      'home.lang_fr': 'Français',
      'home.lang_fr_hint': 'Passer le concours en français',
      'home.lang_en': 'English',
      'home.lang_en_hint': 'Take the test in English',
      'home.start': 'Start the contest',

      'common.logout': 'Sign out',
    },
  };

  const I18N = {
    get lang() { return localStorage.getItem('suzuki_lang') || 'fr'; },
    set lang(v) { localStorage.setItem('suzuki_lang', v === 'en' ? 'en' : 'fr'); },

    /** Langue secondaire (l'autre que la courante). */
    get other() { return this.lang === 'en' ? 'fr' : 'en'; },

    /** Traduit une clé dans une langue donnée. */
    tin(key, lang) {
      const dict = STRINGS[lang] || STRINGS.fr;
      return dict[key] != null ? dict[key] : key;
    },

    /** Traduit une clé, avec interpolation {var} optionnelle. */
    t(key, vars) {
      let s = this.tin(key, this.lang);
      if (vars) for (const k in vars) s = s.replace(new RegExp('\\{' + k + '\\}', 'g'), vars[k]);
      return s;
    },

    /** Applique les traductions sur la page (éléments [data-i18n] et [data-bi]). */
    apply(root) {
      const r = root || document;
      const L = this.lang, O = this.other;

      /* Mono-langue (suit la langue courante) */
      r.querySelectorAll('[data-i18n]').forEach((el) => {
        el.innerHTML = this.t(el.getAttribute('data-i18n'));
      });
      r.querySelectorAll('[data-i18n-ph]').forEach((el) => {
        el.setAttribute('placeholder', this.t(el.getAttribute('data-i18n-ph')));
      });
      r.querySelectorAll('[data-i18n-aria]').forEach((el) => {
        el.setAttribute('aria-label', this.t(el.getAttribute('data-i18n-aria')));
      });

      /* Bilingue simultané (style document officiel) :
         langue choisie en évidence, l'autre en italique secondaire.
         Si les deux textes sont identiques, on n'affiche qu'une fois. */
      r.querySelectorAll('[data-bi]').forEach((el) => {
        const k = el.getAttribute('data-bi');
        const p = this.tin(k, L), s = this.tin(k, O);
        el.innerHTML = (p === s)
          ? '<span class="bi-primary">' + p + '</span>'
          : '<span class="bi-primary">' + p + '</span><span class="bi-secondary">' + s + '</span>';
      });
      r.querySelectorAll('[data-bi-ph]').forEach((el) => {
        const k = el.getAttribute('data-bi-ph');
        const p = this.tin(k, L), s = this.tin(k, O);
        el.setAttribute('placeholder', p === s ? p : p + ' · ' + s);   // même pattern « · » que le reste
      });

      document.documentElement.lang = this.lang;
      // état visuel des sélecteurs de langue
      document.querySelectorAll('.lang-switch button').forEach((b) => {
        b.classList.toggle('active', b.dataset.lang === this.lang);
      });
    },

    /** Bascule de langue + ré-application. onChange permet de re-render le contenu. */
    setLang(lang, onChange) {
      this.lang = lang;
      this.apply();
      if (typeof onChange === 'function') onChange(this.lang);
    },
  };

  global.I18N = I18N;
})(window);
