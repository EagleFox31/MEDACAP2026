# Contexte du projet — Concours Suzuki · CFAO Academy

Document de synthèse récapitulant le contexte, les décisions et l'état d'avancement
des interfaces. Sert de mémoire / passation.

---

## 1. Objectif

Créer les **interfaces HTML / CSS / JS** (statiques, à **dynamiser ensuite en PHP**
dans un LMS existant) pour un **concours technique Suzuki** :

- Organisé par **CFAO Academy** (département de **CFAO Mobility Cameroun**).
- Se tient en **juillet 2026**.
- Destiné aux **techniciens CFAO**.
- **100 questions** type QCM.
- **Bilingue Français / Anglais**.
- Public peu habitué à l'ordinateur → navigation **simple, intuitive, cohérente**,
  sans perdre l'utilisateur.

## 2. Inspiration design

Repris de deux projets existants :
- `C:\Users\IBRAH\Documents\Projets\Academy-Assist-Portail`
- `C:\Users\IBRAH\Documents\Projets\taskoverflow`

Langage retenu : police **DM Sans**, **glassmorphism**, fond animé, cartes nettes,
libellés typographiques. Couleurs : **bleu Suzuki `#0a2a66`** + **rouge CFAO `#E4002B`**.

## 3. Arborescence livrée

```
login.html · accueil.html
assets/
  css/style.css          → système de design complet
  js/i18n.js             → moteur de traduction FR/EN + affichage bilingue (data-bi)
  js/background.js        → formes flottantes (confettis) de l'arrière-plan
  js/login.js             → logique connexion + choix de langue (démo → PHP)
  js/accueil.js           → écran de démarrage + diaporama de fond
  img/suzuki.svg          → logo officiel Suzuki (logotype bleu/rouge)
  img/cfao-mobility.png   → logo officiel CFAO Mobility (repris d'Academy Assist, rogné)
  img/bg/concours-1..9.jpg → visuels CFAO × Suzuki (diaporama d'accueil)
README.md · CONTEXTE.md
```

## 4. Les pages

1. **login.html** — Connexion personnalisée, layout immersif en **deux colonnes** :
   panneau de marque animé (gauche) + formulaire épuré (droite). Le **choix de langue
   FR/EN** (cartes) est présenté dans le formulaire, juste avant *Se connecter*.
2. **accueil.html** — Écran de démarrage : accueil nominatif, **règlement en cartes**
   (icône + titre + description), 3 repères (100 questions / QCM / bilingue) et bouton
   *Commencer*. **Diaporama de fond discret** (visuels CFAO × Suzuki, flou + voile clair).

> La page **quiz** (questionnaire) a été retirée du livrable statique ; à reconstruire
> côté PHP le moment venu.

## 5. Gestion bilingue

- Langue mémorisée dans `localStorage["suzuki_lang"]`, appliquée via attributs
  `data-i18n` (mono-langue) et `data-bi` (bilingue simultané).
- **Page de connexion = bilingue en simultané**, façon **document officiel** (le
  Cameroun étant officiellement bilingue) : langue choisie en **principal**, l'autre
  en **italique secondaire**.
  - Deux rendus : **empilé** (panneau gauche) et **en ligne** `Principal · secondaire`
    (formulaire : badge, titre, libellés, placeholders, bouton).
  - **Dédoublonnage** : si les deux langues sont identiques, le texte n'apparaît
    qu'une fois.
- **Accueil = mono-langue** (selon le choix mémorisé à la connexion).

## 6. Logos

- **Suzuki** : logotype officiel SVG.
- **CFAO Mobility** : récupéré du portail Academy Assist (`public/cfao_academy.png`),
  **rogné** avec PIL (460×290 → 321×118) pour supprimer le blanc autour.
- Affichés dans des **pastilles blanches identiques** (même largeur ET hauteur,
  `object-fit: contain` → chaque logo garde son ratio).
- Ordre : **CFAO (gauche) × Suzuki (droite)** — l'organisateur ouvre le lockup.

## 7. Page de connexion — état actuel du panneau de marque (gauche)

Évolution au fil des échanges, état final :

- **Contenu centré** dans le panneau.
- **Lockup de logos** : CFAO à gauche, **trophée animé au centre**, Suzuki à droite
  (`justify-content: space-between` sur une colonne de largeur `--brand-w`).
- **Les logos définissent la bordure** : la variable **`--brand-w: 600px`** fixe la
  largeur commune. **Tous les textes** (titre, accroche, points forts) suivent cette
  même bordure gauche/droite.
- **Visuel concours** : trophée doré SVG (étoile rouge), rayons tournants, halo
  pulsant, flottement, étincelles ; + drapeaux à damier, lignes de vitesse, confettis.
- **Titre** réduit à `clamp(1.6rem, 2.3vw, 2rem)` + `nowrap` pour tenir sur **une
  seule ligne** dans la bordure des logos (cf. décision ouverte ci-dessous).
- Badge, kicker, accroche, points forts (pastilles `flex:1`), pied « Propulsé par
  CFAO Academy ».

### Formulaire (droite)
- Fond blanc, lisibilité maximale.
- Sélecteur de langue FR|EN discret en haut.
- Ordre de l'intro : **Connexion** (titre) → badge **Concours Technique 2026** →
  sous-titre **Prêt à relever le défi ?**.
- Champs identifiant / mot de passe (œil afficher/masquer), bouton rouge à **reflet
  animé**, aide séparée par un léger filet.
- **Contenu centré verticalement**, rythme d'espacement équilibré (intro · champs ·
  bouton · aide).
- Badge sur **une seule ligne** (taille réduite + `nowrap`).

## 8. Réglages centralisés (faciles à ajuster)

| Variable / sélecteur | Rôle |
|---|---|
| `--brand-w` (`.auth__brand`) | Largeur commune logos + textes (bordure). Actuel : **600px**. |
| `.auth__brand-logos img` | Taille des pastilles logo (largeur/hauteur identiques). |
| `.auth__brand-logos .hero-visual` | Taille du trophée central. |
| `.brand-title` font-size | Taille du titre du panneau. |
| `--suzuki-blue` / `--cfao-red` | Couleurs de marque. |

## 9. Points d'intégration PHP (rappels)

- **Connexion** : `login.js` → remplacer le bloc démo par `fetch('api/login.php')`
  (ou laisser le `<form method="post">` poster). Erreurs via `#form-error`.
- **Nom du technicien** : injecter dans `#user-name` / `#hello-name`.
- **Questionnaire** : page quiz à reconstruire côté PHP (la version statique a été
  retirée). Prévoir une banque `window.QUESTIONS` au format
  `{ id, q:{fr,en}, options:[{fr,en}, …] }`, **bonnes réponses jamais envoyées au
  client** (correction serveur), et une soumission POST des réponses + `lang` + durée.

## 10. Décision ouverte (à trancher)

Le titre du panneau gauche a été **réduit** pour tenir sur une ligne dans la bordure
des logos (600px). Deux options :
- **A (en place)** : bordure 600px → titre plus compact, une ligne, tout aligné.
- **B** : élargir `--brand-w` (~720–760px) → les logos s'écartent un peu plus mais le
  titre retrouve une **grande taille** sur une ligne.

## 11. Environnement de travail

- Aperçu via **Live Server** (ne pas relancer le navigateur manuellement).
- Logos officiels intégrés ; placeholders supprimés.
- Stubs PHP (`api/login.php`, `api/submit.php`, page `quiz`) **non encore créés**.
