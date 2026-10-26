# Plan de refonte MEDACAP 8.4

Décision : monter PHP 8.4 et MongoDB 8.0 dans des conteneurs Linux, sans changer ce que les gens font dans l'application. Le code de travail est `C:\wamp64\www\MEDACAP-8.4`. Le WAMP actuel (`C:\wamp64\www\MEDACAP`, base locale `academy`) et le disque `Y:` restent la version en service jusqu'à la bascule.

Aucune phase ne commence tant que la précédente n'a pas rejoué la liste de contrôle. `php -l` ne compte pas comme une preuve.

## Produit

| | |
|---|---|
| Pour qui | Techniciens, managers, candidats, RH, admins et directeurs déjà dans MEDACAP |
| Problème | La version en service est liée à PHP 7.3, à MongoDB 4.2 et à une copie de fichiers sur un vieux serveur |
| Résultat | Les mêmes parcours, sur PHP 8.4 et MongoDB 8.0, dans Docker Linux, sur la machine de travail puis sur Windows Server 2022 |
| Hors sujet | Nouvelles fonctions, bibliothèque MongoDB 2.x, PhpSpreadsheet 2.x, réécriture des 670 pages, fermeture des examens managers incomplets |
| Preuve | La liste de contrôle ci-dessous, sur la base de la phase, après chaque phase |

L'intelligence artificielle n'entre pas dans cette refonte. Les règles de déverrouillage, de score et de session restent du code déterministe.

## Tel quel, puis cible

| | Aujourd'hui | Cible |
|---|---|---|
| Exécution | Apache du WAMP, PHP 7.4.33 en local, PHP 7.3 visé en production | Conteneur `php:8.4-apache` |
| Données | MongoDB 4.2, base `academy` | Conteneur `mongo:8.0`, volume propre à la refonte |
| Code servi | Dossier `MEDACAP`, copie vers `Y:` | Dossier `MEDACAP-8.4`, même Compose en local et sur le serveur |
| Identité | Session PHP, profil copié au login | Inchangée |
| Preuve de non-régression | Parcours ouverts à la main | Même parcours, liste écrite, après chaque phase |

La base locale `academy` vient d'être complétée avec le dump en ligne. Les compositions d'hier y sont. Le conteneur de la refonte reçoit une copie de cette base. Il n'écrit pas dedans.

## Liste de contrôle

À rejouer après chaque phase. Une case vide arrête la suite.

| Acteur | Action | Réponse attendue | Donnée |
|---|---|---|---|
| Technicien | Connexion par mot de passe | Arrive sur la liste des QCM | Session : profil, filiale, agence, département, pays |
| Technicien | Ouvre un Factuel Junior disponible | Le QCM s'affiche, une réponse se reprend | Examen `active` vrai, `"null"` pour une case vide |
| Technicien | Termine Factuel et Déclaratif du niveau | Le niveau suivant reçoit `activeTest` | Allocations du niveau courant `active` vrai |
| Manager | Connexion avec un déclaratif non validé | Arrive sur la liste à évaluer | Lecture du tableau `users` du manager |
| Manager | Reprend une évaluation en cours | L'examen manager reste ouvert, les `"null"` ne comptent pas comme réponses | Examen `type` Manager, `active` vrai |
| Manager avec `test` vrai | Ouvre ses QCM | Voit ses épreuves, pas la liste des collaborateurs à cet endroit | `test` vrai |
| Candidat | Ouvre une épreuve figée | Chrono, QCM, puis questions de préférence | `papers` du compte |
| RH | Ouvre un rapport de recrutement | Maîtrise et complétude sur 20, pas une note de recrutement | Résultats `typeR` Candidat |
| Technicien | Ouvre Définir | Formations actives filtrables par marque et niveau | Collection `trainings` |
| Directeur | Ouvre Exploiter | Comptage des techniciens actifs de son pays, hors candidats | Session `country` |
| Tout profil | Demande un lien sans mot de passe | Le GET affiche le bouton et ne consomme pas le jeton. Le POST ouvre la session une fois | `login_links`, `session_regenerate_id` |
| Admin | Export du classeur d'envoi déjà mis en forme | Le fichier s'ouvre dans Excel | Patch du zip, pas PhpSpreadsheet |

Le mail réel vers `smtp.cfao.corp` se vérifie une fois le conteneur lancé, pas à chaque petite correction de page. Jusque-là, on vérifie que le corps est construit en quoted-printable et que les accents restent des accents.

## Architecture

Trois options :

| | Rester sur le WAMP | Tout réécrire | Conteneurs, code actuel |
|---|---|---|---|
| Délai | Nul, et PHP 8.4 n'y est pas | Plusieurs mois | Semaines |
| Parcours existants | Déjà là | À reconstruire | Rejoués à chaque phase |
| MongoDB 8 | Non | Oui | Oui, sur une copie |
| Décision | Écartée | Écartée | Retenue |

Deux conteneurs : `php:8.4-apache` et, au début, une MongoDB encore en 4.2. L'image `mongo:8.0` n'arrive qu'après la montée des données. L'extension et la bibliothèque PHP MongoDB passent ensemble en **1.21**. PhpSpreadsheet passe en **1.29.2** ou **1.30.x**. Apache garde les `.htaccess`.

`MEDACAP_MONGO_URI` et `MEDACAP_PUBLIC_URL` portent l'environnement. Sans variable, l'application continue de parler à `mongodb://localhost:27017`.

Portes difficiles à refermer, écrites ici avant de les franchir :

- Poser `featureCompatibilityVersion` à 8.0 sur la copie. Retour arrière seulement avec une restauration.
- Abandonner la syntaxe PHP 7.3 dans le dossier `MEDACAP-8.4` une fois que ce dossier ne sert plus qu'à PHP 8.4.
- Diriger la production vers le conteneur. Jusque-là, `deploy.ps1` et le disque `Y:` ne changent pas.

## Phases

### 0. Ligne de base

Aucune modification de code. Rejouer la liste sur le WAMP actuel et la base `academy`. Noter ce qui échoue déjà. Ces échecs ne seront pas attribués à la refonte.

Copier `academy` vers une base `academy_84`. C'est la seule base que les phases suivantes ont le droit de modifier.

### 1. Le même code dans Docker, MongoDB encore en 4.2

Compose dans `MEDACAP-8.4` : PHP 8.4, extension MongoDB 1.21, volume de données initialisé depuis `academy_84`. Le WAMP ne démarre pas cette base.

Liste de contrôle sur `http://localhost` du conteneur. Les plantages PHP 8.4 trouvés ici sont corrigés dans `MEDACAP-8.4` seulement, puis la liste est rejouée.

### 2. Une seule lecture de la configuration

Les pages qui ouvrent `mongodb://localhost:27017` ou qui écrivent `10.68.0.7` passent par `MEDACAP_MONGO_URI` et `MEDACAP_PUBLIC_URL`. Le défaut reste l'adresse actuelle.

Liste de contrôle deux fois : une fois sous WAMP sans variable, une fois sous Docker avec les variables.

### 3. Bibliothèques

Dans `MEDACAP-8.4` : `mongodb/mongodb` 1.21 et PhpSpreadsheet 1.29.2 ou 1.30.x. `medacapCountDocuments()` reste le seul chemin de comptage.

Liste de contrôle, plus un classeur d'envoi ouvert dans Excel après export.

### 4. Montée de la copie MongoDB

Uniquement `academy_84`, dans le conteneur :

`4.2 → 4.4 → 5.0 → 6.0 → 7.0 → 8.0`

À chaque cran : lire la version, ne rien faire si elle est déjà la bonne, aligner `featureCompatibilityVersion`, puis rejouer la connexion, un QCM, une lecture de résultat et une épreuve candidat. Le processeur doit exposer AVX à partir de 5.0.

L'image finale devient `mongo:8.0` seulement quand le cran 8.0 a passé ce sous-ensemble.

### 5. Liste complète, puis seulement la bascule

Toute la liste de contrôle sur le Compose PHP 8.4 + MongoDB 8.0. Ensuite, et seulement ensuite, le même Compose sur le serveur 2022, avec le relais `smtp.cfao.corp:25` joignable depuis le conteneur.

## Constat phase 0 — 23 octobre 2026

La base `academy` a été copiée vers `academy_84` (`mongorestore`, 46 800 documents, 0 échec). Les deux bases portent les mêmes comptes : users 567, allocations 6 123, exams 1 562, results 32 351, papers 23, trainings 158, login_links 0. Le WAMP continue de lire `academy`. `academy_84` n'est pas branchée.

Liste rejouée sur `http://localhost/MEDACAP/`, sans envoi de réponse et sans modification du classeur d'envoi.

| Contrôle | Constat |
|---|---|
| Technicien, mot de passe | Passe. Redirection 302 vers la liste des QCM. |
| Factuel Junior encore ouvert | Passe. Bouton Continuer, page du QCM en 200, question affichée, case vide marquée non répondue. Aucun examen créé : la collection `exams` reste à 1 562. Le premier compte tiré avait Junior, Senior et Expert déjà terminés, donc aucun lien : ce n'est pas un échec du produit. |
| Définir | Passe. Page des formations personnelles en 200. |
| Manager, déclaratif non validé | Passe. Redirection 302 vers la liste à évaluer. |
| Liste manager | Passe. |
| Manager avec `test` vrai | Passe. La liste des QCM affiche « Tests à passer », « Mon parcours » et le tableau personnel. La section « Mesure les compétences de mes collaborateurs » est absente. Ce compte atterrit sur le bilan, parce que son équipe est déjà validée. Un premier essai avait échoué sur un mot de passe visible qui n'ouvrait pas de session. |
| Candidat | Passe. Accueil de l'épreuve en 200. |
| RH, rapport | Passe. La page porte maîtrise, complétude et recrutement. |
| Directeur, Exploiter | Passe. Tableau de bord en 200. |
| Déverrouillage des niveaux | Passe. 73 techniciens conformes, 0 écart, sur un échantillon de 80 comptes actifs. |
| Examen manager encore ouvert | Passe. Au moins un examen Manager `active` vrai. Il n'a pas été fermé. |
| Classeur d'envoi | Passe. Le zip s'ouvre et contient `[Content_Types].xml`. Fichier non modifié. |
| Lien sans mot de passe | Passe. Le GET affiche « Ouvrir ma session » et laisse le jeton inutilisé. Le POST ouvre la session, le second appel répond que le lien n'est plus valable. Le jeton d'essai a été supprimé. `login_links` est revenu à 0. |

Aucun échec déjà présent à reporter sur la refonte. Le mail SMTP n'a pas été envoyé : le relais se vérifie quand le conteneur est lancé.

## Constat phase 1 — 23 octobre 2026

Le même code tourne dans Docker. PHP 8.4.27, extension MongoDB 1.21.0, MongoDB 4.2.24. Le site est sur `http://127.0.0.1:8084/`. Le mongod du conteneur est sur `127.0.0.1:27018`. Le WAMP reste sur le port 80 et sur `localhost:27017`.

La copie a été restaurée dans le conteneur sous le nom `academy`, parce que le code lit encore cette base. Comptes : users 567, allocations 6 123, exams 1 562, results 32 351, papers 23, trainings 158. La collection de logs en séries temporelles n'entre pas dans MongoDB 4.2. Elle n'est pas utilisée par les pages. Après les essais, `exams` est resté à 1 562 dans le conteneur et sur l'hôte. `login_links` est à 0 des deux côtés.

| Contrôle | Constat sur le conteneur |
|---|---|
| Technicien, mot de passe | Passe. 302 vers la liste des QCM. |
| Factuel Junior encore ouvert | Passe. Bouton Continuer, QCM en 200, question affichée. Aucun examen créé. |
| Définir | Passe. 200, pas d'erreur fatale. |
| Manager, déclaratif non validé | Passe. 302 vers la liste à évaluer, page en 200. |
| Manager avec `test` vrai | Passe. « Tests à passer », section collaborateurs absente. |
| Candidat | Passe. 302 vers l'accueil de l'épreuve, page en 200. |
| RH, rapport | Passe. 200, la page porte la maîtrise. |
| Directeur, Exploiter | Passe. 200. |
| Déverrouillage | Passe sur la copie. 73 conformes, 0 écart, échantillon de 80. |
| Lien sans mot de passe | Passe. GET sans consommation, POST 302, second appel refusé, jeton supprimé. Écrit seulement dans le conteneur. |
| Classeur d'envoi | Inchangé depuis la phase 0. Le fichier n'a pas été réécrit. |

Deux réglages du conteneur, pas du code des pages :

- Tampon de sortie à 4 096 octets, comme le WAMP. Sans ça, le HTML envoyé avant la redirection de connexion empêche le `header`, et l'extension 1.21 affiche une dépréciation de la bibliothèque 1.0.0. Ces dépréciations sont masquées jusqu'au passage de la bibliothèque en 1.21.
- Les adresses sans `.php` (`/quiz`) sont réécrites vers le fichier PHP. MultiViews de l'image officielle ne négocie pas le handler PHP.

Le mail SMTP n'a pas été envoyé.

Plafonds posés ensuite. Le moteur Docker de cette machine a 3,8 Go et 2 processeurs. MongoDB est limité à 1,5 Go, 1 processeur et 128 processus, avec un cache WiredTiger de 0,5 Go. Apache est limité à 1 Go, 1 processeur et 256 processus. La sauvegarde est limitée à 384 Mo et un demi-processeur. Pas d'échange disque au-delà de ces plafonds. Référence : CIS Docker Benchmark 5.10, 5.11 et 5.28, et NIST SP 800-190.

## Exploitation retenue

Validé le 23 octobre 2026. Les deux tâches planifiées (`check_paper_completion_threshold.php` et `generate_subsidiary_reports.php`) ne sont pas lancées : leurs mails feraient du bruit. On les reprendra seulement sur demande.

| | |
|---|---|
| Sauvegarde | Chaque jour, `mongodump` de la base du conteneur vers `C:\wamp64\medacap-backups`, en dehors du site et du volume de données. Conservation 14 jours. Le dimanche, restauration d'essai dans `academy_backup_check`, puis suppression de cette base d'essai. |
| Reprise | Si le processus s'arrête, le conteneur repart au plus cinq fois. Un processus encore vivant mais bloqué est signalé par le contrôle de vie, il n'est pas tué tout seul. |
| Horloge | `Africa/Douala` |
| Sessions | Volume propre. Un redémarrage d'Apache ne déconnecte plus les sessions déjà ouvertes. |
| Jetons sans mot de passe | Index d'expiration sur `login_links.expiresAt`, et index sur `tokenHash`, sur la base du conteneur seulement. |

## Constat phase 2 — 23 octobre 2026

`core/medacap_config.php` est le seul endroit qui lit `MEDACAP_MONGO_URI` et `MEDACAP_PUBLIC_URL`. Sans variable, Mongo reste `mongodb://localhost:27017` et les liens gardent `10.68.0.7` / `129.0.64.34`. Un second passage du branchement ne réécrit rien.

Le conteneur web a `MEDACAP_MONGO_URI=mongodb://127.0.0.1:27017` et `MEDACAP_PUBLIC_URL=http://127.0.0.1:8084`. Apache du conteneur les voit. Le WAMP, non.

Liste rejouée des deux côtés, sans envoi de réponse. `exams` est resté à 1 562. Le jeton d'essai a été supprimé, `login_links` est revenu à 0. Technicien Junior, Définir, manager à évaluer (page ouverte), manager avec `test` vrai, candidat, rapport RH (maîtrise), Exploiter, déverrouillage 73 / 0. Aucune erreur fatale.

Les scripts à la racine et `scratch/` gardent une adresse en dur. Trois pages étaient déjà illisibles par `php -l` sur la copie WAMP du matin : `views/define/editDeletTraining.php`, `views/define/selectTraining_fixed.php`, `views/editDeletUser copy.php`.

## Constat phase 3 — 23 octobre 2026

Dans `MEDACAP-8.4` seulement : `mongodb/mongodb` **1.21.5** (extension déjà 1.21.0, contrainte `^1.21`) et `phpoffice/phpspreadsheet` **1.30.7**. 1.30.1 et 1.30.2 sont refusés par les avis de sécurité ; 1.30.7 est la 1.30 qui passe, et elle accepte PHP 8.4. Pas de branche 2.x. `medacapCountDocuments()` est inchangé. Le masque `E_DEPRECATED` est retiré (`error_reporting=30719`, soit `E_ALL`). L'image a aussi `gd` et `zip`, exigés par PhpSpreadsheet.

Liste rejouée dans le conteneur, sans envoi de réponse. Aucune ligne `Deprecated:`. `exams` est resté à 1 562. Jeton d'essai supprimé, `login_links` revenu à 0. Mêmes parcours qu'en phase 2, déverrouillage 73 / 0.

Le classeur `exports/suivi_qcm_techniciens_cm_2026-09-11-send.xlsx` s'ouvre dans Excel en lecture seule : 4 feuilles (Tableau de bord, Suivi QCM, Legende, Synthese agences). L'empreinte du fichier est la même après fermeture. Il n'a pas été réécrit par PhpSpreadsheet.

Ce dossier ne tourne plus sous le PHP 7.4 du WAMP : la bibliothèque 1.21 exige PHP 8.1. Le site en service `C:\wamp64\www\MEDACAP` n'a pas été modifié.

## Constat phase 4 — 23 octobre 2026

Uniquement le mongod du volume `medacap84`. L'hôte `mongodb://localhost:27017` et la prod n'ont pas été touchés.

Avant chaque binaire, `mongodump` de `academy` depuis le serveur encore en place, copié hors du volume dans `C:\wamp64\medacap-backups` :

| Avant | Fichier | Taille |
|---|---|---|
| 4.4 | `academy-pre-4.4-20261023.archive.gz` | 7 468 372 |
| 5.0 | `academy-pre-5.0-20261023.archive.gz` | 7 470 683 |
| 6.0 | `academy-pre-6.0-20261023.archive.gz` | 7 468 454 |
| 7.0 | `academy-pre-7.0-20261023.archive.gz` | 7 468 325 |
| 8.0 | `academy-pre-8.0-20261023.archive.gz` | 7 465 979 |

Chaque dump contient exams 1 562, results 32 351, users 567, `login_links` 0.

| Cran | Binaire | FCV |
|---|---|---|
| 4.4 | 4.4.30 | 4.4 |
| 5.0 | 5.0.33 | 5.0 |
| 6.0 | 6.0.28 | 6.0 |
| 7.0 | 7.0.45 | 7.0 (`confirm: true`) |
| 8.0 | 8.0.34 | 8.0 (`confirm: true`) |

AVX déjà présent dans le conteneur avant le cran 5.0. À partir de 5.0, le contrôle de vie et le script du dimanche utilisent `mongosh`. `docker/backup-loop.sh` prend `mongosh` s'il est là, sinon `mongo`. Les images `mongo` et `backup` sont `mongo:8.0`. Plafonds mémoire, CPU et PID inchangés.

Sous-ensemble rejoué à chaque cran (`scratch/phase4_cran.php`, dans le conteneur, sans envoi de réponse) : connexion technicien, question Factuel, rapport RH avec maîtrise, accueil candidat. Aucune ligne `Deprecated:` ni erreur fatale. `exams` est resté à 1 562 avant et après.

## Constat phase 5 — liste locale, 23 octobre 2026

Liste complète rejouée dans le conteneur, PHP 8.4 et MongoDB **8.0.34** (FCV 8.0), sans envoi de réponse. `exams` est resté à 1 562. Le jeton d'essai a été supprimé, `login_links` est revenu à 0. Aucune ligne `Deprecated:` ni erreur fatale.

| Contrôle | Constat |
|---|---|
| Technicien, mot de passe | Passe. 302 vers la liste des QCM. |
| Factuel Junior encore ouvert | Passe. Bouton Continuer, QCM en 200, question affichée, case vide (`unanswered`). Niveau Junior. |
| Définir | Passe. 200. |
| Manager, déclaratif non validé | Passe. 302 vers la liste à évaluer, page en 200. Le lien d'évaluation s'ouvre en 200, les cases vides restent `unanswered`. Aucune réponse envoyée. |
| Manager avec `test` vrai | Passe. Atterrit sur le bilan. « Tests à passer » présent, section collaborateurs absente. |
| Candidat | Passe. Accueil 200, puis l'épreuve en GET 200 : chrono `timer_sec`, QCM, questions de préférence (`orient-opt`). Pas de POST. |
| RH, rapport | Passe. 200. La page porte la maîtrise et la complétude. |
| Directeur, Exploiter | Passe. 200. |
| Déverrouillage | Passe. 73 conformes, 0 écart, échantillon de 80. |
| Lien sans mot de passe | Passe. GET sans consommation, POST 302, second appel refusé, jeton supprimé. |
| Classeur d'envoi | Passe. Excel en lecture seule : 4 feuilles (Tableau de bord, Suivi QCM, Legende, Synthese agences). Empreinte inchangée. |

Le corps des mails reste en quoted-printable dans `views/sendMail.php`. Aucun mail n'a été envoyé. Le Compose n'a pas été copié sur Windows Server 2022 : cette machine n'est pas le serveur, et aucun hôte n'est indiqué pour la copie.

## Prochaine action

Copier le même Compose sur Windows Server 2022, puis vérifier que le conteneur joint `smtp.cfao.corp:25`. Le conteneur de cette machine ne résout pas ce nom (23 octobre 2026, aucun mail envoyé). La production et `Y:` restent fermés. L'adresse du serveur 2022 n'est pas écrite dans le plan.
