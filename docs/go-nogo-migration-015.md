# GO/NO-GO — MEDACAP 8.4 (Docker) sur 10.68.0.15

Recette du 29/10/2026 sur la stack locale `MEDACAP-8.4`, identique à celle de la .15 (PHP 8.4.27, MongoDB 8.0, dump prod `academy`).
Écritures uniquement via les comptes de test **Demo GoTech** (technicien) et **Demo GoCand** (candidat). Tous les mails ont été redirigés vers jakaa-ext@cfao.com.

## Verdict : GO sous conditions

Les 9 critères sont tenus. Il reste 4 conditions à remplir avant la bascule, détaillées plus bas.

| # | Critère | Résultat | Preuve |
|---|---|---|---|
| 1 | Connexion de tous les profils | **GO** (9/10 profils testés) | Parcours Playwright relancé après tous les correctifs (30/10) : 9 profils + Demo GoManager, 134 pages, 0 erreur. 2 Fatal PHP sur `userSubsidiaryResult.php` (manager vide) corrigés puis retestés : 0 Fatal. Super Admin n'a pas pu être testé : le mot de passe du compte de recette n'est plus valide. |
| 2 | Résultats visibles | **GO** après correctif | 8 pages `explore/*` plantaient (`DivisionByZeroError`, fatal en PHP 8) dès qu'une évaluation manquait. Corrigé, rendu vérifié en Admin et en technicien. |
| 3 | Technicien : Factuel + Déclaratif | **GO** après correctif | Demo GoTech : Factuel 50/50 et Déclaratif 114/114 soumis par l'interface réelle. `results` sont écrits avec la même structure qu'un technicien de prod. Le manager Demo GoManager a passé ses propres QCM (50 + 114) et a évalué Demo GoTech (114). La note croisée technicien–manager est identique chez le manager, le technicien, la RH et l'Admin. Les fiches résultats d'un manager sans N+1 plantaient (`ObjectId('')` fatal, 3 vrais managers concernés) : corrigé. Manager avec 2 techniciens (Demo GoTech Junior, Demo GoExpert Expert) : il évalue le niveau Expert en premier alors que le technicien n'a que le Junior débloqué ; le niveau Expert reste verrouillé côté technicien. Failles corrigées : un technicien ouvrait un niveau verrouillé en tapant l'URL ; `userEvaluation.php` acceptait n'importe quel `?id=` manager (un technicien pouvait s'auto-évaluer en manager) et rouvrait une évaluation terminée ; si le manager évaluait en premier, le Déclaratif du technicien apparaissait « terminé » dans sa liste. Progression Junior → Senior → Expert vérifiée. Reprise après abandon (Factuel, Déclaratif, évaluation manager) : réponses restaurées à l'identique. |
| 4 | Plans de formation | **GO** (réserve) | Pages Define/Acquire OK pour tous les profils. Le code est identique à la prod (hors URI Mongo). La fonctionnalité est quasi inutilisée : 1 seul plan validé en base. |
| 5 | Candidats : papers | **GO** | Demo GoCand : 100 QCM + 7 questions d'orientation jusqu'à « Félicitations ». Allocation `done`, `paper_campaign_status` à jour, ligne correcte dans le rapport recrutement. |
| 6 | Aucune fuite entre comptes | **GO** après correctif | En technicien, 6 pages affichaient les résultats d'un autre utilisateur en changeant l'id dans l'URL. Corrigé. Retest Technicien + Manager : 0 fuite. |
| 7 | Sauvegarde Mongo | **GO** | Service `backup` : `dump ok`. |
| 8 | Mails | **GO** (à refaire depuis la .15) | msmtp → smtp.cfao.corp : mail reçu dans Outlook, accents OK. Mails applicatifs (création technicien, accès candidat) partis avec rc=0. Donnée à corriger : 2 comptes (thierrydg, thierrydf) ont l'adresse `tkougha@cfao,com`, en copie de l'alerte « évaluation manager finalisée ». |
| 9 | Libs PHP 8.4, sans lib ancienne | **GO** | Composer reconstruit avec PHP 8.4. Côté front : Bootstrap 4.0/4.5/4.6.0 → 4.6.2, jQuery 3.6 → 3.7.1, Highcharts épinglé (voir plus bas). |

## Correctifs apportés pendant la recette

- **Fuites de données** :
  - Nouveau garde `core/user_access.php` (`medacapRequireUserAccess`). Un technicien ou un candidat ne voit que lui-même, un manager voit lui-même et son équipe, les autres profils sont inchangés.
  - Le garde est appliqué sur `explore/{result,detail,brandResult,system,userSpeciality,historiqueResult,historiqueDetail,historiqueBrandResult,historiqueSystem,managerBrandResult}.php`. Un accès refusé redirige vers le portail.
  - `acquire/trainingState.php` : un technicien ne voyait pas que lui-même mais tous les techniciens. Il est désormais limité à lui-même.
- **Plantages PHP 8** :
  - Les divisions `score × 100 / total` de `views/explore/*` sont protégées par `max(1, total)`.
  - Un résultat absent affiche donc 0 % au lieu d'une page blanche. En PHP 7, on avait un warning et « INF % ».
- **URL `/MEDACAP/...` en dur** : un alias Apache dans `docker/Dockerfile` sert ce préfixe, ainsi que les favoris `/medacap/` de l'ancienne prod.
- **Includes en dur** `D:/Program/wamp64/...` remplacés par `__DIR__` dans `define/manageGroups.php`, `define/managePermissions.php` et `explore/managePermissions.php`.
- **Highcharts** :
  - Avant, le chargement se faisait depuis `code.highcharts.com` en version « latest », c'est-à-dire la v13 sortie en septembre 2026, jamais testée avec ce code.
  - Ce CDN **refuse les Referer en IP privée** (`Non-identifiable referer`). Sur la .15, les graphiques auraient donc échoué de façon aléatoire.
  - Highcharts est désormais chargé depuis jsDelivr, épinglé en `highcharts@12.4.0`.
- **Défauts préexistants corrigés au passage** :
  - Filtre manager de `trainingState` : l'appel visait `getManagers.php` (404), il vise maintenant `../define/getManagers.php`.
  - `chartjs-plugin-datalabels@2.3.0` n'existe pas : remplacé par 2.2.0.
  - Lien Bootstrap 5.0.0 StackPath retiré du portail : cette version n'a jamais été publiée sur ce CDN.

### Ajouts du 30/10 (recette manager, progression, Super Admin)

- **Plans de formation visibles par tout le pays** (identique en prod) : dans `views/userFilters.php` et `views/define/userFilters.php`, un manager n'était filtré que par pays. Il est désormais limité à `manager = lui-même`, quel que soit le `?manager=` reçu. Retest : 3 techniciens de l'équipe, 0 étranger.
- **Manager sans N+1** : `new ObjectId('')` est fatal en PHP 8. 11 fichiers `views/explore` utilisent un ObjectId nul quand `manager` est vide (`null` aurait compté les auto-évaluations comme notes manager).
- **`explore/brandResult.php`** : la vue RH faisait une 500 (`count(null)`). Corrigé.
- **`measure/ListeDesQcm.php`** (identique en prod) : les examens du manager étaient comptés comme ceux du technicien, donc un Déclaratif apparaissait « terminé » si le manager évaluait d'abord. Corrigé.
- **Niveau verrouillé ouvrable par URL** (identique en prod) : `userQuizFactuel.php` et `userQuizDeclaratif.php` refusent un test `activeTest:false`. `userEvaluation.php` exige d'être le manager du technicien (hors Super Admin) et refuse une évaluation déjà faite.
- **Gestionnaire d'erreurs centralisé** (`core/error_handler.php`, chargé par `auto_prepend_file` dans l'image) :
  - Toute exception non rattrapée ou erreur fatale renvoie une page propre (ou du JSON `success:false` en AJAX) avec une référence `ERR-XXXXXX`, au lieu d'une page blanche.
  - Chaque erreur est enregistrée dans `academy.app_errors`, regroupée par type, fichier et ligne (compteur et 20 dernières occurrences avec l'utilisateur et l'URL). Les warnings y sont aussi comptés, sans mail.
  - Un mail d'alerte part pour chaque erreur bloquante, au plus 1 par heure et par erreur, vers `MEDACAP_ERROR_MAIL` (par défaut jakaa-ext@cfao.com ; `off` pour couper).
  - La page `views/appErrors.php` (Super Admin uniquement) liste ces erreurs.
  - Tests : exception, fatale (mémoire), AJAX, warning et exception rattrapée (non touchée). 3 occurrences de la même erreur donnent 1 document (compteur 3) et 1 mail.

### Ajouts du 30/10 (soir) : plus rien de PHP 7.3

- `composer.json` exige PHP >= 8.4 ; `composer audit` : aucune faille connue sur les dépendances.
- Lint PHP 8.4.27 de tout le code suivi par Git : 0 erreur. `define/editDeletTraining.php` ne compilait pas (balise `<?php` imbriquée, page blanche) : corrigé.
- `FILTER_SANITIZE_STRING` (déprécié depuis PHP 8.1) remplacé à l'identique dans `measure/display_event.php`, `measure/includes/fixed_paper_builder.php`, `explore/createUser.php`, `define/dashboard2026.php`.
- `deploy.sh` construit avec `--pull` : chaque version reprend le dernier correctif de sécurité de l'image `php:8.4-apache`.

## Conditions avant bascule

1. **Super Admin** : se connecter une fois sur la .15 avec un vrai compte Super Admin et ouvrir le portail et les pages d'administration (menus, profils, fonctionnalités).
2. **Mail depuis la .15** : refaire l'envoi test depuis le conteneur web de la .15. Le relais SMTP doit accepter cette IP.
3. **Comptes de test** : purger Demo GoTech, GoCand, GoManager, GoExpert, GoResume et GoSuper (users, allocations, tests, results, exams, connections) **si** la base testée est celle qui part en prod.
4. **Licence Highcharts** : l'usage commercial exige une licence Highsoft. À confirmer côté CFAO, c'était déjà le cas en prod.
5. **Ports** : bloc 8010-8019 réservé à MEDACAP dans le registre de ports de la .15 (web sur 8010, MongoDB non publié), validation de l'équipe infra.
6. **Déploiement** : `./deploy.sh` lancé à la main sur la .15 (voir `docs/deploiement-serveur.md`). Il construit l'image et crée les index `app_errors` (migration `001`).

## Défauts connus, non bloquants (identiques en prod)

- **Paper candidat avec orientation** : la clôture n'écrit pas de résultat `Factuel` (cas `valid_orientation` de `userPaperQuiz.php`).
  - Le rapport recrutement n'est pas impacté, car il se base sur les réponses de l'examen.
  - 3 candidats réels sont dans le même cas.
- **`define/applications.php`** : la confirmation d'inscription à une formation utilise des variables jamais définies (`$date`, `$userData`, `$register`).
- **404 cosmétiques** : snippet GTM cassé dans les headers acquire, define et explore ; `/views/public/js/main.js` ; `public/assets/css/public/images/20230310_173722.jpg`.
- **Front** :
  - Plusieurs jQuery sont chargés par page (le bundle Metronic embarque déjà la 3.7.0).
  - Bootstrap 4.6.2 et 5.3 cohabitent dans les headers.
  - Axios 1.4.0 est embarqué dans `plugins.bundle.js` de Metronic, mais n'est pas utilisé par le code MEDACAP.
  - Les unifier demande une recette visuelle complète, à faire après la migration.
- **Warnings PHP 8** (« Attempt to read property on null ») sur les pages de résultats quand une évaluation manque. Ils sont seulement journalisés, rien ne s'affiche.
- **Tableau de bord Define (manager, directeurs)** : il lit la collection `managersBySubsidiaryAgency`, figée au 16/05/2025, qu'aucun code ne régénère. Elle ne contient que 27 techniciens CMI sur 66, et aucune équipe pour les managers créés depuis. Correctif à décider : script de reconstruction, ou lecture directe de `users.manager`.
- **`explore/editDeletQuestion.php`** (Super Admin) : la page rend 2 modales complètes par question active (1 236 questions), soit 37 Mo de HTML. Le navigateur se fige plusieurs minutes. Correctif : une seule modale remplie en JS au clic.
