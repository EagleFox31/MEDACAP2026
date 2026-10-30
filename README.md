# MEDACAP 8.4

Copie de travail pour faire tourner MEDACAP en PHP 8.4 et MongoDB 8.0, dans Docker. Les parcours utilisateurs n'ont pas été réécrits.

Branche : `fix/quiz-factuel-countdocuments`  
Dépôt : https://github.com/EagleFox31/MEDACAP2026/tree/fix/quiz-factuel-countdocuments

Ne sont pas sur GitHub : `vendor/`, `scratch/`, les scripts jetables (`scripts/tmp_*`, scripts nominatifs), le `.htaccess` local, les `.env`, les présentations `docs/*.pptx`, les dumps et les réponses de techniciens.

Le détail des phases est dans `PLAN.md`.

## Pour reprendre le projet (personne ou assistant de code)

À lire dans cet ordre : ce README, puis `AGENTS.md` (règles de code, pièges connus), puis `PLAN.md`. `AGENTS.md` est le fichier d'instructions standard lu par les assistants de code ; si l'outil utilisé lit un autre nom de fichier, créer en local un fichier qui renvoie vers `AGENTS.md`, sans le versionner.

1. Cloner, lancer Docker et restaurer une copie de la base (section « Démarrer sur une machine neuve »).
2. Modifier le code. Le conteneur monte le dossier : pas de rebuild pour un changement PHP.
3. Vérifier : `docker compose exec -T web php -l <fichier>` puis rejouer les parcours (section « Parcours par profil »).
4. Commit sur une branche, puis PR vers `main`. Ne jamais pointer ce Compose sur la base de production.

Aucun chemin propre à un poste n'est requis : tout se règle par variables (`.env` local pour `BACKUP_DIR`, `.env.server` pour le serveur, voir `.env.server.example`).

## Ce qui a changé

| | Avant | Cible | Fait dans ce dossier |
|---|---|---|---|
| Application | PHP 7.3, Apache du WAMP, fichiers copiés | PHP 8.4 | PHP 8.4.27, image `php:8.4-apache` |
| Base | MongoDB 4.2, base `academy` | MongoDB 8.0, montée un cran à la fois | MongoDB 8.0.34, compatibilité 8.0, volume `medacap84` |
| Bibliothèque MongoDB | `mongodb/mongodb` 1.0.0 | 1.21, pas la 2.x | 1.21.5, extension 1.21.0 |
| Excel | PhpSpreadsheet 1.25.2 | 1.30.x, pas la 2.x | 1.30.7 |
| Adresse de la base et URL publique | Écrites dans les pages | Une seule lecture | `core/medacap_config.php` |
| Hébergement | Copie de fichiers | Le même Compose sur Windows Server 2022 | Compose prêt sur ce poste. Le serveur 2022 n'est pas ouvert. |
| Courrier | Relais `smtp.cfao.corp:25` | Joignable depuis le conteneur du serveur | Corps en quoted-printable. Relais pas encore vérifié. |

La base du conteneur est une copie. L'hôte `mongodb://localhost:27017` et `mongodb://10.68.0.7:27017` n'ont pas été montés de version.

## Code

`core/medacap_config.php` est le seul `getenv`.

- `medacapMongoUri()` lit `MEDACAP_MONGO_URI`. Sans variable, le défaut reste `mongodb://localhost:27017`. Les scripts qui pointaient la prod gardent `mongodb://10.68.0.7:27017` quand la variable est vide.
- `medacapPublicUrl()` lit `MEDACAP_PUBLIC_URL`.
- `medacapAppBaseUrl($country)` renvoie cette URL si elle est posée. Sinon, Cameroun reste `http://10.68.0.7/medacap/`, le reste `http://129.0.64.34/medacap`.

Les pages, Commercial, cronjobs, `core/`, concours et `scripts/` passent par là. `scratch/` et les PHP à la racine du dépôt gardent une adresse en dur.

`core/mongo_compat.php` et `medacapCountDocuments()` restent le seul chemin de comptage. Ne pas appeler `countDocuments()` directement dans une page. Autour du driver : `catch (Throwable)`, pas seulement `Exception`.

`composer.json` fixe `mongodb/mongodb` 1.21.5 et `phpoffice/phpspreadsheet` 1.30.7. Composer se lance dans le conteneur web. Le PHP 7.4 du WAMP ne charge plus ce `vendor`.

L'image ajoute `gd` et `zip`, exigés par PhpSpreadsheet 1.30. `error_reporting` est `E_ALL`. Le tampon de sortie est à 4096, parce que `index.php` envoie du HTML avant la redirection de connexion. Les liens `./quiz?...` sans `.php` sont réécrits vers le fichier PHP.

Un classeur déjà mis en forme ne se rouvre pas avec PhpSpreadsheet. Le fichier `exports/suivi_qcm_techniciens_cm_2026-09-11-send.xlsx` se patche dans le zip.

## Démarrer sur une machine neuve (copie de travail uniquement)

Prérequis : Docker Desktop (conteneurs **Linux**), Docker Compose V2, accès au dépôt privé, suffisamment d'espace disque et **une archive MongoDB 8.0 de test autorisée et préalablement validée**. Cette branche n'embarque aucune donnée utilisateur. Ne jamais diriger ce Compose vers le MongoDB WAMP / serveur de production.

```powershell
git clone https://github.com/EagleFox31/MEDACAP2026.git
cd MEDACAP2026
git switch --track origin/fix/quiz-factuel-countdocuments
docker compose config --quiet
docker compose up -d --build
docker compose ps
docker compose exec -T web php -r "require 'vendor/autoload.php'; echo 'Composer OK', PHP_EOL;"
```

Le Dockerfile installe PHP 8.4, les extensions MongoDB, GD, ZIP et MBString, puis les **versions fixées par `composer.lock`** avec Composer 2 au moment de la construction. `vendor` est copié au démarrage dans le volume dédié `medacap84-vendor`; inutile d'installer Composer sous le PHP du WAMP. La création du site est possible sans données, **mais les parcours métier ne sont pas opérationnels tant que la copie `academy` n'est pas restaurée**.

### Restaurer uniquement la copie de test

Le fichier `C:\\chemin\\autorise\\academy-8.0.archive.gz` ci-dessous est **un exemple de chemin** : obtenir une archive validée de la base `academy` **au format `mongodump --archive --gzip`, compatible avec MongoDB 8.0**, depuis un stockage autorisé. Ne pas la verser dans Git ni inclure de données réelles non autorisées. La montée 4.2 → 8.0 s'effectue **sur une copie** selon `PLAN.md`, jamais directement sur la production.

**Méthode recommandée sur Windows (vérifications et restauration contrôlées) :**

```powershell
.\scripts\init-medacap84-copy.ps1 -Archive "C:\chemin\autorise\academy-8.0.archive.gz"
```

Ce script vérifie que le service Docker `mongo` publie seulement `127.0.0.1:27018`, contrôle que `academy` n'a aucune collection, importe l'archive dans le conteneur isolé et affiche un premier comptage. Il refuse de remplacer une base déjà remplie. **Ne pas exécuter ensuite la méthode manuelle ci-dessous pour la même archive.**

**Méthode manuelle alternative (sans le script) :**

Avant toute restauration, vérifier que la base du conteneur est vide :

```powershell
docker compose exec -T mongo mongosh academy --quiet --eval "db.getCollectionNames().length"
```

**N'effectuer les commandes suivantes que si le résultat est `0`** et si l'archive est autorisée :

```powershell
docker compose cp "C:\\chemin\\autorise\\academy-8.0.archive.gz" mongo:/tmp/academy-8.0.archive.gz
docker compose exec -T mongo mongorestore --gzip --archive=/tmp/academy-8.0.archive.gz --nsInclude="academy.*"
docker compose exec -T mongo mongosh academy --quiet --eval "db.users.countDocuments({})"
```

Comparer les comptes de collections attendus à ceux du dump source. **Ne pas utiliser `--drop` sur une base existante**, ni toucher au port `27017` de l'hôte. Le stockage de la copie est le volume Docker `medacap84`, pas la base WAMP.

### Accès et limites

| Quoi | Où |
|---|---|
| Site de test | http://127.0.0.1:8084/ |
| Mongo de la copie depuis le poste | `mongodb://127.0.0.1:27018`, base `academy` |
| Mongo vu depuis PHP dans le conteneur | `mongodb://127.0.0.1:27017` |
| Dépendances Composer | Volume `medacap84-vendor` |
| État des tests de migration | `PLAN.md`, phase 5, vérifications locales rapportées le 23/10/2026 |

Le conteneur web partage la pile réseau de Mongo. Le `27017` de WAMP n'est pas publié. Les variables `MEDACAP_MONGO_URI` et `MEDACAP_PUBLIC_URL` sont configurées dans `docker-compose.yml`.

La sauvegarde quotidienne écrit dans `BACKUP_DIR` (fichier `.env` local, non versionné), sinon dans `./backups`. **Vérifier la restauration d'essai** avant exploitation. Les quotas CPU/mémoire sont dans le Compose.

### Tests à effectuer avant de proposer une fusion dans `main`

1. Sur un clone neuf, vérifier la construction de l'image et la présence de `vendor/autoload.php`.
2. Exécuter `php -l` sur `views/measure/userQuizFactuel.php` et ses deux fichiers `includes/quiz_validator.php` et `includes/quiz_factuel_engine.php`.
3. Sur **la copie MongoDB uniquement**, tester l'ouverture, la sauvegarde et la finalisation d'un QCM factuel, puis comparer `exams`, `results` et `allocations` avant et après.
4. Rejouer la liste de contrôle complète de `PLAN.md`. Tester SMTP et sauvegardes sur le serveur cible **avant** toute bascule.

La présence des fichiers manquants dans Git ne constitue pas une preuve de réussite des tests métier. `main`, WAMP et le serveur de production sont inchangés tant que la PR reste en brouillon.

## Serveur

`docker-compose.server.yml` est autonome (ne pas le combiner avec `docker-compose.yml`) :

```powershell
copy .env.server.example .env.server   # puis renseigner MEDACAP_PUBLIC_URL, HTTP_PORT, BACKUP_DIR
docker compose -f docker-compose.server.yml --env-file .env.server up -d --build
```

Différences avec le poste : port HTTP publié sur le réseau, MongoDB non publié, redémarrage automatique, erreurs PHP masquées (`docker/server/php-server.ini`), dossiers internes et scripts de la racine refusés par Apache (`docker/server/medacap-server.conf`).

## Parcours par profil

`tests/parcours/parcours_profils.py` (Python 3 + Playwright) se connecte avec un compte par profil, parcourt les menus sur 2 niveaux, prend une capture par page et signale les erreurs PHP, les codes HTTP ≥ 400 et les liens vers la page 404.

- Lecture seule : les liens d'action, d'envoi, d'export, de suppression, de déconnexion et les pages de QCM ne sont pas suivis. Rejouer le script ne change pas la base.
- Rejouable : chaque passage remplace les captures du profil passé ; `docs/captures/rapport.json` reprend tous les profils.
- Les identifiants viennent d'un fichier local jamais versionné (`tests/parcours/comptes*.json` est ignoré).

```powershell
pip install playwright ; python -m playwright install chromium   # ou Chrome / Edge déjà installé
python tests/parcours/parcours_profils.py --accounts tests/parcours/comptes.json --base http://127.0.0.1:8084 --max 30
python tests/parcours/parcours_profils.py --accounts tests/parcours/comptes.json --only "Manager,Technicien"
```

Format de `comptes.json` : `{"Technicien": {"username": "…", "password": "…"}, "Manager": {…}}`. Les erreurs PHP n'étant plus affichées dans les pages (`display_errors=Off`), contrôler aussi `docker compose logs web --since 30m` après un passage.

Captures du dernier passage : `docs/captures/<profil>/`.

## Preuve déjà rejouée

Sur http://127.0.0.1:8084/ , sans envoyer de réponse de QCM. `exams` est resté à 1 562.

Technicien Junior (question, case vide), Définir, évaluation manager, manager avec `test` vrai, candidat (chrono, QCM, questions de préférence), rapport RH (maîtrise et complétude), Exploiter, lien sans mot de passe, classeur d'envoi ouvert dans Excel. Déverrouillage : 73 conformes, 0 écart, sur 80 techniciens actifs.

## Pas encore fait

Poser ce Compose sur le Windows Server 2022, puis vérifier `smtp.cfao.corp` port 25 depuis le conteneur. L'adresse du serveur n'est pas dans le dépôt.

## Volet client — mesure des compétences

À ajouter. La mesure des compétences doit pouvoir être vue par client, pas seulement avec le vocabulaire CFAO.

Kinross est le premier client. Il n'a que Toyota. Sur son interface, les termes parlent de Toyota, pas des autres marques.

Les niveaux affichés chez lui sont G1, G2, G3 et G4. Le niveau Expert MEDACAP se coupe en deux :

| Niveau client | Correspondance MEDACAP |
|---|---|
| G1 | Expert, sans outils de diagnostic |
| G2 | Expert, avec outils de diagnostic |
| G3 | Pas encore fixé |
| G4 | Pas encore fixé |

G3 et G4 ne sont pas encore rattachés à Junior ou à Senior. Ne pas inventer ce lien.

## À ne pas casser

- `"null"` dans `answers` n'est pas une réponse. Un examen manager incomplet ne se ferme pas.
- Maîtrise /20 = bonnes réponses ÷ questions répondues × 20. Complétude /20 = bonnes réponses ÷ banque × 20.
- Alias `CAMEROON MOTORS INDUSTRIES`, nom stocké `CFAO MOBILITY CAMEROON`.
- `views/measure/includes/language.php` écrase `$stats`. L'inclure en premier.
- `background-manager.php` est dans `views/partials/`.
- Comptes `demo` et `loadtestTag` hors des stats de recrutement.
