# MEDACAP 8.4

Copie de travail pour faire tourner MEDACAP en PHP 8.4 et MongoDB 8.0, dans Docker. Les parcours utilisateurs n'ont pas été réécrits.

Branche : `fix/quiz-factuel-countdocuments`  
Dépôt : https://github.com/EagleFox31/MEDACAP2026

| Dossier | Rôle |
|---|---|
| `C:\wamp64\www\MEDACAP-8.4` | Cette copie. C'est ici qu'on modifie. |
| `C:\wamp64\www\MEDACAP` | Arborescence WAMP d'origine. PHP 7.3 visé, lib MongoDB 1.0.0. |
| `C:\wamp64\www\MEDACAP-sauvegarde-20261023` | Copie gelée du 23 octobre 2026, version 2.9.4. Ne pas modifier. |

Le détail des phases est dans `PLAN.md`.

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

## Lancer

Depuis ce dossier, avec Docker en mode Linux :

```powershell
docker compose up -d
```

| Quoi | Où |
|---|---|
| Site | http://127.0.0.1:8084/ |
| Mongo vu depuis le poste | `mongodb://127.0.0.1:27018`, base `academy` |
| Mongo vu par PHP dans le conteneur | `mongodb://127.0.0.1:27017` |

Le conteneur web partage la pile réseau du conteneur mongo. Le port 27017 de l'hôte WAMP n'est pas publié par Compose. Ne pas faire pointer le conteneur vers `localhost:27017` de l'hôte.

Les variables du service web sont déjà dans `docker-compose.yml` : `MEDACAP_MONGO_URI=mongodb://127.0.0.1:27017` et `MEDACAP_PUBLIC_URL=http://127.0.0.1:8084`.

Mongo et le conteneur de sauvegarde sont en `mongo:8.0`. Le contrôle de vie utilise `mongosh`. La sauvegarde quotidienne écrit dans `C:\wamp64\medacap-backups`. Les plafonds mémoire et CPU sont dans `docker-compose.yml`. Ne pas les monter sans noter pourquoi.

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
