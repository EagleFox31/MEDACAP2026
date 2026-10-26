# MEDACAP — instructions agents

> **IMPORTANT — contexte de cette branche (26/10/2026)** : `fix/quiz-factuel-countdocuments` est la copie de migration **MEDACAP 8.4 / version 2.9.4**, avec PHP 8.4, MongoDB 8.0 et `mongodb/mongodb` 1.21.5 dans Docker. Les règles ci-dessous sur PHP 7.3, MongoDB 4.2 et la bibliothèque 1.0.0 décrivent **l'installation WAMP historique encore en service**. Elles ne doivent PAS servir à dégrader les dépendances ni le runtime Docker de cette branche. Ne pas déployer depuis cette branche ni modifier la production. Lire d'abord `README.md` et `PLAN.md`. Le code source et les dépendances du conteneur doivent être reproductibles à partir d'un clone Git, `vendor/` étant géré par le build et un volume dédié. La base `academy` ne se restaure que sur le volume isolé `medacap84` à partir d'une archive autorisée, validée pour MongoDB 8.0. Les tests métier sur copie restent obligatoires avant la fusion. Les commits/push restent conditionnés aux demandes explicites de l'utilisatrice.

Ce fichier est la **mémoire projet** pour les agents Cursor. Le consulter avant d’écrire ou modifier du PHP MEDACAP.

## Runtime cible

- **Cible PHP 7.3** (serveur prod) — **jamais** supposer PHP 7.4+ ou 8+
- ⚠ Le WAMP local tourne **PHP 7.4.33** (`bin/php/php7.4.33`, chargé par Apache) : `php -l` en local ne détecte donc **pas** les incompatibilités 7.4 → 7.3. Rester 7.3-compatible à la main.
- **MongoDB 4.2** — base `academy`
- URI prod : `mongodb://10.68.0.7:27017`
- URI local WAMP : souvent `mongodb://localhost:27017` (Mongo parfois indisponible en local)
- Variable d’env optionnelle : `MEDACAP_MONGO_URI`
- URL prod Cameroun : `http://10.68.0.7/medacap/`

## PHP 7.3 — interdictions fréquentes

Ne **pas** utiliser de syntaxe PHP 8+ :

| Interdit (PHP 8+) | Alternative PHP 7.3 |
|---|---|
| Arrow functions `fn($x) => …` | `function ($x) { return …; }` |
| `match` | `switch` |
| Propriétés typées dans les classes | Docblocks ou pas de type sur la propriété |
| Named arguments | Arguments positionnels |
| Union types `string\|null` | Docblocks `@param` / `@return` |
| Constructor property promotion | Assignation manuelle dans le constructeur |
| `str_contains()` | `strpos($haystack, $needle) !== false` |
| Nullsafe `?->` | Vérifications `isset()` / ternaires |

**Erreurs typiques :**

- `Parse error: unexpected '=>' (T_DOUBLE_ARROW)` → arrow function `fn()` interdite en PHP 7.3
- `TypeError: Argument must be array, BSONDocument given` → convertir le doc Mongo avant usage

## MongoDB / BSON

- Les documents MongoDB arrivent en `BSONDocument` / `BSONArray`, **pas** en `array` natif.
- Ne **pas** typer `array` sur un paramètre qui reçoit un doc Mongo directement.
- Helpers existants :
  - `paperReportToArr($v)` dans `paper_candidate_report_lib.php`
  - `bson_to_array($v)` dans certains cronjobs
  - `json_decode(json_encode($v), true)` en dernier recours
- Filtrer comptes `demo` / `loadtestTag` des stats recrutement.

### Lib `mongodb/mongodb` figée en **1.0.0** — `countDocuments()` interdit

`composer.json` verrouille `mongodb/mongodb` en `1.0.0`. `MongoDB\Collection` n'y expose que
`count($filter, $options)` : **`countDocuments()` et `estimatedDocumentCount()` n'existent pas**
(ajoutés en 1.4.0). Tout appel direct lève :

```
Error: Call to undefined method MongoDB\Collection::countDocuments()
```

**Toujours passer par le helper** `core/mongo_compat.php` :

```php
require_once __DIR__ . '/../../core/mongo_compat.php'; // ajuster la profondeur
$n = medacapCountDocuments($collection, $filter);      // countDocuments() si dispo, sinon count()
```

Ne jamais écrire `$collection->countDocuments(...)` en dur, même « juste pour un script ».

### `Error` n'est pas `Exception` — utiliser `catch (Throwable)`

En PHP 7, `Error` (donc `TypeError`, « undefined method », « undefined function ») **n'hérite pas**
de `Exception`. Un `catch (Exception $e)` le laisse passer.

- Autour d'un appel à une lib externe ou à une fonction typée : `catch (Throwable $e)`.
- Ne **jamais** avaler l'exception derrière un message générique sans `error_log()` du détail :
  classe + message + fichier + ligne.
- Un handler AJAX ne doit **jamais** répondre `success: true` depuis un `catch`.

**Cas réel (2026-08-21)** — finalisation des QCM factuels techniciens cassée en prod :
un `countDocuments()` introduit dans `QuizResultProcessor::processSpecialtyResult()` levait une
`Error`, rattrapée par le `catch (Throwable)` final de `submit()` qui affichait
« Server error during submission ». Trois couches masquaient la cause : `catch (Exception)` internes
qui laissaient filer l'`Error`, `error_log()` commenté, et `save()` qui renvoyait `success: true`
même en échec. Diagnostic impossible sans instrumenter d'abord.

## Includes — pages `views/measure/*.php`

Toujours préférer **`__DIR__`** pour les chemins relatifs (évite les erreurs selon le CWD).

| Fichier | Chemin correct depuis `views/measure/` |
|---|---|
| Language Measure | `__DIR__ . '/includes/language.php'` |
| Background manager | `__DIR__ . '/../partials/background-manager.php'` |
| Header Measure | `__DIR__ . '/partials/header.php'` |
| Footer Measure | `__DIR__ . '/partials/footer.php'` |
| Vendor autoload | `__DIR__ . '/../../vendor/autoload.php'` |
| sendMail | `__DIR__ . '/../sendMail.php'` |
| Libs locales | `__DIR__ . '/includes/nom_lib.php'` |
| Scripts CLI réutilisés | `__DIR__ . '/../../scripts/nom_script.php'` |

**Erreurs typiques :**

```
include_once(partials/background-manager.php): failed to open stream
Call to undefined function setPageBackground()
```

→ `background-manager.php` n’est **pas** dans `views/measure/partials/` mais dans **`views/partials/`**.

```
Warning: Illegal string offset 'enrolled' / 'threshold_pct'
```

→ `includes/language.php` écrase `$stats` avec `"Statistiques"`. Inclure `language.php` **en premier**, et utiliser un nom de variable métier distinct (`$completionStats`, pas `$stats`).

**Layout pages avec `openBackgroundContainer()` :**

- `.page-background-container { overflow: hidden }` peut couper le contenu — ajouter une classe scope (`rr-report-scope`) avec `overflow: visible; height: auto;` si besoin.
- Ne pas utiliser `scrollIntoView({ block: 'start' })` sous un header fixe — compenser la hauteur de `#kt_header` avant `window.scrollTo`.
- `#kt_header .title-banner` : centré dans le header (compact, sans `padding-left` fixe) ; titres longs en `clamp()` — ne pas remettre `p-4 mb-4` ni `overflow-hidden` sur le bandeau module.

**Modèle minimal page Measure :**

```php
session_start();
// garde session + profil…
include_once __DIR__ . '/includes/language.php'; // AVANT les variables métier ($stats est réservé)
require_once __DIR__ . '/../../vendor/autoload.php';
include_once __DIR__ . '/../partials/background-manager.php';
setPageBackground('bg-dashboard');
$title = 'Mon titre';
include_once __DIR__ . '/partials/header.php';
openBackgroundContainer();
// contenu…
closeBackgroundContainer();
include_once __DIR__ . '/partials/footer.php';
```

Variantes existantes dans le codebase (`../partials/`, `includes/background-manager.php`, `../core/background-manager.php`) — pour **nouveau code**, suivre le tableau ci-dessus.

## Conventions projet

- Pages Measure : session + garde profil (`Super Admin`, `Admin`, `Ressource Humaine`, etc.).
- Mails : `views/sendMail.php` — `CC_STATIC` inclut `jakaa-ext@cfao.com`, `lgonin@cfao.com`, `aybokally@cfao.com`.
- RH filiale Cameroun : `getRhAndDpsEmails('CFAO MOBILITY CAMEROON', $mongo)`.
- Menu Measure : collection Mongo `functionalities` + profils ; scripts `views/measure/scripts/setup_*.php`.
- Ne **pas** éditer les fichiers de plan `.cursor/plans/*.plan.md` sauf demande explicite.
- Commits / push **uniquement** sur demande utilisateur.
- Diff minimal — réutiliser l’existant, matcher le style du fichier.

## Mails

- Fonctions rapport recrutement : `sendPaperCompletionThresholdMail()`, `sendPaperManualReportToRh()` dans `sendMail.php`.
- En local WAMP : `mail()` échoue souvent (`localhost:25` absent) — normal, tester sur prod.
- Idempotence : champs `autoAlertSentAt` / `manualReportSentAt` dans `paper_campaign_status`.

## Rapports recrutement PL (papers figés)

### Fichiers clés

| Fichier | Rôle |
|---|---|
| `views/measure/includes/paper_candidate_report_lib.php` | Cœur métier rapport |
| `views/measure/includes/paper_macro_specialities.php` | Mapping macro-spécialités |
| `views/measure/rapportRecrutement.php` | UI web |
| `scripts/export_paper_scores.php` | Export Excel |
| `scripts/report_pl2026_composers.php` | Rapport console CLI |
| `cronjobs/check_paper_completion_threshold.php` | Cron alerte 60% |
| `views/measure/scripts/setup_rapport_recrutement_menu.php` | Menu + backfill statuts |
| `views/measure/userPaperQuiz.php` | Hook post-validation candidat |

### Papers Août 2026 Cameroun

| Épreuve | Paper ID |
|---|---|
| Recrutement Poids Lourds Aout 2026 | `6a82dbf45b3b2bb665098ec2` |
| Recrutement PL Electricien Aout 2026 | `6a82dafa726d3580f703ecc2` |

4 candidats composés en base (3 mécanique, 1 électricien).

### Règles métier

- Seuil alerte : **60% composés / inscrits par épreuve** (pas global).
- Alerte mail **uniquement au franchissement** du seuil (pas rétroactive).
- « Composé » = ≥ 1 réponse QCM **ou** statut allocation `Terminé`.
- Temps écoulé = durée paper − chrono restant.
- Vitesse = répondues / minutes écoulées (1 décimale).
- Macro-spécialités : Électricité vs Mécanique (voir `paper_macro_specialities.php`).
- **Maîtrise /20** : `(bonnes réponses ÷ questions répondues) × 20` — qualité sur ce qui a été traité.
- **Complétude /20** : `(bonnes réponses ÷ total banque) × 20` — pénalise les non-réponses.
- Pas de « note recrutement » côté MEDACAP : on transmet les notes de connaissances, le RH décide.
- Champs legacy `note_recrutement*` encore calculés en interne (blend) mais non affichés par défaut.

### Collection `paper_campaign_status`

```javascript
{ paper, enrolled, composed, completionPct, thresholdPct,
  autoAlertSentAt, manualReportSentAt, updated }
```

### Accès UI

- Lecture : Super Admin, Admin, Ressource Humaine
- Envoi mail RH : Super Admin, Admin
- URL : `views/measure/rapportRecrutement.php?paper=<id>&user=<id>`

## Tests CLI utiles

```bash
php -l views/measure/rapportRecrutement.php
php scripts/report_pl2026_composers.php --paper-id=6a82dbf45b3b2bb665098ec2 --mongo-uri=mongodb://10.68.0.7:27017
php scripts/export_paper_scores.php --paper-id=6a82dbf45b3b2bb665098ec2 --mongo-uri=mongodb://10.68.0.7:27017
php cronjobs/check_paper_completion_threshold.php
php views/measure/scripts/setup_rapport_recrutement_menu.php
# QCM offline brousse (export sur prod, import retour fichier)
php scripts/export_offline_qcm.php --agency=Bertoua --mongo-uri=mongodb://10.68.0.7:27017
php scripts/import_offline_qcm.php --file=exports/offline-qcm/Bertoua/Michel_BAYEMI_Junior_QCM.html
```

## QCM offline (brousse / hors réseau CFAO)

- Export : `scripts/export_offline_qcm.php` → **1 fichier HTML par technicien/niveau** (`Prenom_NOM_Junior_QCM.html`) avec questions intégrées
- Technicien : double-clic HTML → accueil (contexte épreuve) → saisie nom → compose → « Terminer » → télécharge `*_REPONSES.medacap.json`
- Import retour RH : `scripts/import_offline_qcm.php --file=...` (HTML complété ou `.medacap.json` legacy)
- Player dev / démo : `offline-qcm/player/` ; sample autonome : `php scripts/build_offline_sample_html.php` → `sample_QCM.html`
- Format retour : réponses **dans le HTML** (`#medacap-quiz-data`, status `completed` puis `imported`) ; JSON legacy accepté à l'import
- Moteur partagé web/offline : `views/measure/includes/quiz_factuel_engine.php` (requis par `userQuizFactuel.php`)

Toujours lancer `php -l` sur les fichiers PHP modifiés avant livraison.

## Déploiement prod

L'utilisateur lance lui-même (copie vers `Y:\` = `http://10.68.0.7/medacap/`) :

```powershell
.\deploy.ps1 -Version 4 -SpecificFiles "views\measure\X.php","views\language.php"
```

- `-Version` : `1` PATCH, `2` MINOR, `3` MAJOR, `4` version inchangée (sans le paramètre → prompt interactif).
- Toujours fournir la commande complète avec la liste des fichiers modifiés ; `-Version 4` par défaut (bump uniquement sur demande).
- Exclusions : `deploy.exclude`.

## Progression niveaux QCM (Junior → Senior → Expert)

- Déverrouillage quand Factuel **et** Déclaratif du niveau ont `active=true` (`allocation_progression.php`).
- **Automatique** : à la soumission Factuel/Déclaratif + à chaque ouverture de `ListeDesQcm.php` (`allocationProgression_syncUnlockedLevels`).
- Rattrapage CLI : `php scripts/fix_stuck_level_unlock_cm.php` (dry-run avec `--dry-run`).
