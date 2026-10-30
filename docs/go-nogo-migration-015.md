# GO/NO-GO — MEDACAP 8.4 (Docker) sur 10.68.0.15

Recette du 29/10/2026 sur la stack locale `MEDACAP-8.4`, identique à celle de la .15 (PHP 8.4.27, MongoDB 8.0, dump prod `academy`).
Écritures uniquement via les comptes de test **Demo GoTech** (technicien) et **Demo GoCand** (candidat). Tous les mails ont été redirigés vers jakaa-ext@cfao.com.

## Verdict : GO sous conditions

Les 9 critères sont tenus. Il reste 4 conditions à remplir avant la bascule, détaillées plus bas.

| # | Critère | Résultat | Preuve |
|---|---|---|---|
| 1 | Connexion de tous les profils | **GO** (9/10 profils testés) | Parcours Playwright relancé après tous les correctifs : 9 profils, 122 pages, 0 erreur, 0 Fatal PHP. Super Admin n'a pas pu être testé : le mot de passe du compte de recette n'est plus valide. |
| 2 | Résultats visibles | **GO** après correctif | 8 pages `explore/*` plantaient (`DivisionByZeroError`, fatal en PHP 8) dès qu'une évaluation manquait. Corrigé, rendu vérifié en Admin et en technicien. |
| 3 | Technicien : Factuel + Déclaratif | **GO** | Demo GoTech : Factuel 50/50 et Déclaratif 114/114 soumis par l'interface réelle. `results` sont écrits avec la même structure qu'un technicien de prod. |
| 4 | Plans de formation | **GO** (réserve) | Pages Define/Acquire OK pour tous les profils. Le code est identique à la prod (hors URI Mongo). La fonctionnalité est quasi inutilisée : 1 seul plan validé en base. |
| 5 | Candidats : papers | **GO** | Demo GoCand : 100 QCM + 7 questions d'orientation jusqu'à « Félicitations ». Allocation `done`, `paper_campaign_status` à jour, ligne correcte dans le rapport recrutement. |
| 6 | Aucune fuite entre comptes | **GO** après correctif | En technicien, 6 pages affichaient les résultats d'un autre utilisateur en changeant l'id dans l'URL. Corrigé. Retest Technicien + Manager : 0 fuite. |
| 7 | Sauvegarde Mongo | **GO** | Service `backup` : `dump ok`. |
| 8 | Mails | **GO** (à refaire depuis la .15) | msmtp → smtp.cfao.corp : mail reçu dans Outlook, accents OK. Mails applicatifs (création technicien, accès candidat) partis avec rc=0. |
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

## Conditions avant bascule

1. **Super Admin** : se connecter une fois sur la .15 avec un vrai compte Super Admin et ouvrir le portail et les pages d'administration (menus, profils, fonctionnalités).
2. **Mail depuis la .15** : refaire l'envoi test depuis le conteneur web de la .15. Le relais SMTP doit accepter cette IP.
3. **Comptes de test** : purger Demo GoTech et Demo GoCand (users, allocations, tests, results, exams, connections, et l'entrée dans le tableau `users` de la51di) **si** la base testée est celle qui part en prod.
4. **Licence Highcharts** : l'usage commercial exige une licence Highsoft. À confirmer côté CFAO, c'était déjà le cas en prod.

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
