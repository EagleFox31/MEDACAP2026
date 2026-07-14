# Concours Suzuki — CFAO Academy

Interfaces **HTML / CSS / JS** (statique) prêtes à être **dynamisées en PHP** dans
votre LMS existant. Conçues pour des techniciens CFAO Mobility, peu habitués à
l'ordinateur : navigation simple, grandes cibles cliquables, parcours guidé.

Inspiration design : *Academy Assist Portail* et *TaskOverflow*
(police **DM Sans**, glassmorphism, fond animé) — couleurs **bleu Suzuki + rouge CFAO**.

## Parcours utilisateur
1. **`login.html`** — Connexion personnalisée (logos CFAO + Suzuki) avec **choix de
   la langue** (FR/EN) présenté juste avant le bouton *Se connecter*.
2. **`accueil.html`** — Écran de démarrage : bienvenue nominative, règlement clair
   en cartes, repères (100 questions / QCM / bilingue) et bouton *Commencer*.
   Diaporama de fond discret (visuels CFAO × Suzuki).

La langue est mémorisée (`localStorage["suzuki_lang"]`) et appliquée partout via
les attributs `data-i18n` / `data-bi` (voir `assets/js/i18n.js`). Un sélecteur
**FR | EN** discret reste accessible dans l'en-tête de l'accueil.

## Arborescence
```
login.html · accueil.html
assets/
  css/style.css          → système de design complet
  js/i18n.js             → textes d'interface FR/EN (data-i18n / data-bi)
  js/background.js       → formes flottantes de l'arrière-plan
  js/login.js            → logique connexion (démo → à brancher sur PHP)
  js/accueil.js          → écran de démarrage + diaporama + choix de langue
  img/suzuki.svg · cfao-mobility.png → logos officiels Suzuki & CFAO Mobility
  img/bg/concours-1..9.jpg           → visuels du diaporama d'accueil
```

## Brancher sur PHP (points d'entrée)
- **Connexion** — `assets/js/login.js` → remplacez le bloc *DÉMO* par un
  `fetch('api/login.php', …)`, ou laissez le `<form method="post">` poster côté
  serveur. Affichez l'erreur via `#form-error` (classe `show`).
- **Nom du technicien** — injectez-le dans `accueil.html`
  (`#user-name`, `#hello-name`) au lieu du `sessionStorage` de démo.

## Logos
Logos officiels intégrés : `assets/img/suzuki.svg` (logotype Suzuki) et
`assets/img/cfao-mobility.png` (repris du portail Academy Assist). Pour les
changer, remplacez les fichiers (même nom) ou mettez à jour les `src` dans les
pages HTML.

## Aperçu local
Ouvrez `login.html` dans un navigateur. Choisissez la langue, saisissez un
identifiant + mot de passe non vides → accueil.
