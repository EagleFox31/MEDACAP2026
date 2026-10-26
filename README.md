# MEDACAP 8.4 — plan de refonte

Dossier de travail de la refonte. La copie gelée du 23 octobre 2026 est `C:\wamp64\www\MEDACAP-sauvegarde-20261023` (version **2.9.4**). Le site WAMP actuel reste `C:\wamp64\www\MEDACAP` et continue d'être déployé tel quel vers le disque `Y:` jusqu'à la bascule.

Standard de conception : **RAIDER**. Chaque phase lit l'état courant, calcule l'état voulu, et n'écrit que l'écart. Un second passage sur un état déjà conforme ne réécrit rien.

## État de départ

| Élément | Aujourd'hui |
|---|---|
| PHP visé en production | 7.3 |
| PHP du WAMP local | 7.4.33 |
| MongoDB | 4.2, base `academy` |
| Bibliothèque `mongodb/mongodb` | 1.0.0 |
| PhpSpreadsheet | 1.25.2 |
| Hébergement | fichiers PHP sur Windows, Apache du WAMP |
| Code | environ 670 fichiers PHP, 380 000 lignes, hors `vendor` et `scratch` |

`php -l` ne voit pas les incompatibilités 7.3 vers 8.4. Une page ne compte comme vérifiée que si elle a été ouverte et parcourue.

## État voulu

Deux conteneurs Linux, le même Compose sur la machine de travail et sur le serveur Windows Server 2022. Les deux moteurs Docker sont déjà Linux via WSL.

| Service | Image |
|---|---|
| Application | `php:8.4-apache` |
| Base | `mongo:8.0` |

Apache conserve les `.htaccess`. Les variables portent le contexte :

- `MEDACAP_MONGO_URI`
- `MEDACAP_PUBLIC_URL`
- relais SMTP (`smtp.cfao.corp`, port 25, expéditeur `helpdeskcm@cfao.com`)

Sans ces variables, l'application continue de parler à `mongodb://localhost:27017`. Le WAMP local ne doit pas casser pendant la refonte.

## Décisions

| Besoin | Choix | Décision |
|---|---|---|
| PHP 8.4 et Apache | image officielle `php:8.4-apache` | Adopt |
| MongoDB 8.0 | image officielle `mongo:8.0` | Adopt |
| Montée 4.2 vers 8.0 | paliers officiels, un cran à la fois | Adopt |
| Câblage MEDACAP | Compose, variables, `.htaccess` | Adapt |
| Image PHP Windows ou orchestrateur maison | — | Build écarté |
| Bibliothèque MongoDB 2.x | — | hors de ce chantier |
| PhpSpreadsheet 2.x | — | hors de ce chantier |

La bibliothèque PHP MongoDB passe en **1.21**, avec l'extension **1.21**. Cette branche parle à MongoDB 4.2 et à MongoDB 8.0, et `Collection::count()` y existe encore. PhpSpreadsheet passe en **1.29.2** ou **1.30.x**, le premier niveau qui accepte PHP 8.4.

Le processeur du serveur doit exposer AVX. MongoDB 5.0 et les suivantes refusent de démarrer sans.

## Ordre des travaux

### 1. Compose

Créer le Compose dans ce dossier. L'application monte le code de `MEDACAP-8.4`. La base monte un volume neuf, alimenté plus tard par une copie de `academy`, jamais par l'instance de production.

Vérifier `docker info --format "{{.OSType}}"`. La valeur attendue est `linux`.

Extensions PHP à prévoir dans l'image : `mongodb` 1.21, `gd`, `zip`, `mbstring`, `xml`.

### 2. Une seule lecture de la configuration

Les pages ouvrent aujourd'hui `new MongoDB\Client("mongodb://localhost:27017")`, et plusieurs scripts pointent en dur vers `mongodb://10.68.0.7:27017`. Les adresses `10.68.0.7` et `129.0.64.34` sont aussi écrites dans les liens de mail.

Remplacer ces ouvertures par un point unique qui lit `MEDACAP_MONGO_URI` et `MEDACAP_PUBLIC_URL`, avec les valeurs actuelles comme défaut. Un fichier déjà migré ne change plus au second passage.

### 3. Dépendances

Dans ce dossier seulement :

- `mongodb/mongodb` 1.21
- `phpoffice/phpspreadsheet` 1.29.2 ou 1.30.x
- image PHP avec l'extension MongoDB 1.21

Garder `core/mongo_compat.php` et `medacapCountDocuments()`. Ne pas introduire d'appel direct à `countDocuments()` dispersé dans les pages.

### 4. Faire tourner le PHP 8.4

Ouvrir les parcours et corriger ce qui casse à l'exécution. Les causes attendues sont un `null` passé à une fonction interne (`htmlspecialchars`, `strlen`, et équivalents) et un paramètre obligatoire placé après un paramètre optionnel.

Ne pas réécrire le style des 670 fichiers. Ne pas introduire de syntaxe réservée à PHP 8 si le même fichier doit encore être lisible pendant la transition ; une fois ce dossier seul sur 8.4, la syntaxe 8.4 y est permise.

### 5. Monter la base, sur une copie

La production MongoDB 4.2 ne se met pas à jour sur place. La copie passe par chaque version, et `featureCompatibilityVersion` est aligné avant le cran suivant :

`4.2 → 4.4 → 5.0 → 6.0 → 7.0 → 8.0`

À chaque cran : lire la version, ne rien faire si elle est déjà la bonne, sinon remplacer le binaire, poser la compatibilité, puis rejouer la connexion, un QCM, un export Excel et un envoi de mail.

Les index encore absents de `login_links` se créent sur la copie, puis sur la base définitive :

```javascript
db.login_links.createIndex({ expiresAt: 1 }, { expireAfterSeconds: 0 })
db.login_links.createIndex({ tokenHash: 1 })
```

### 6. Contrôles avant bascule

- connexion par mot de passe ;
- lien sans mot de passe : le GET affiche le bouton et ne consomme pas le jeton, le POST le consomme une fois, `session_regenerate_id(true)` ouvre la session ;
- QCM factuel et déclaratif, déverrouillage Junior → Senior → Expert ;
- mail en quoted-printable, sujet UTF-8, accents intacts ;
- classeur d'envoi des techniciens patché dans le zip, sans repasser par PhpSpreadsheet ;
- tableaux de bord Measure.

### 7. Bascule

Le même Compose démarre sur le serveur 2022. Le conteneur doit joindre `smtp.cfao.corp:25`. L'URL publique suit le nouveau nom ou la nouvelle adresse, via `MEDACAP_PUBLIC_URL`.

`deploy.ps1` reste le déploiement de l'ancienne arborescence tant que la bascule n'est pas faite. Après la bascule, le déploiement devient le Compose, pas une copie de fichiers PHP vers `Y:`.

## Contrats à préserver

- Comptes `demo` et `loadtestTag` exclus des statistiques de recrutement.
- Alias `CAMEROON MOTORS INDUSTRIES` vers le nom stocké `CFAO MOBILITY CAMEROON`.
- Mails : `views/sendMail.php`, expéditeur `helpdeskcm@cfao.com`. Les copies fixes actuelles restent un paramètre, pas une adresse en dur ajoutée dans un nouveau fichier.
- Seuil d'alerte recrutement : 60 % de composés par épreuve, au franchissement seulement.
- Maîtrise /20 = bonnes réponses ÷ questions répondues × 20. Complétude /20 = bonnes réponses ÷ banque × 20.
- Un examen manager incomplet ne se ferme pas. Le champ `total` est le nombre de réponses ; la chaîne `"null"` dans `answers` n'est pas une réponse.
- Lien de connexion : jeton 32 octets, stocké en SHA-256, 30 minutes, usage unique, le GET de l'antivirus ne le consomme pas.

## Leçons à ne pas réouvrir

Il n'existe pas encore de `docs/engineering/lessons-learned.md`. L'ajouter dans ce dossier au premier correctif significatif de la refonte, avec la cause, pas seulement le symptôme.

- `countDocuments()` n'existe pas sur `mongodb/mongodb` 1.0.0. L'appel a déjà cassé la soumission des QCM en production. Rester sur l'API 1.21 et sur le helper existant.
- Un classeur Excel déjà mis en forme se corrompt s'il est rouvert par PhpSpreadsheet. Le correctif est le patch du zip. Monter la bibliothèque en 1.30 ne change pas cette règle.
- Envoyer un script PHP à `php.exe` par un pipe PowerShell ré-encode le corps en Windows-1252. Les accents deviennent `?`. Le mail part d'un fichier PHP, en quoted-printable.
- `catch (Exception)` laisse passer les `Error` de PHP 7. Autour du driver et des bibliothèques : `catch (Throwable)`, avec `error_log()` du détail. Un handler AJAX ne répond pas `success: true` depuis un `catch`.
- Inclure `views/measure/includes/language.php` écrase la variable `$stats`.

## Hors de ce chantier

- Bibliothèque MongoDB 2.x et PhpSpreadsheet 2.x.
- Réécriture des pages, du menu ou des droits.
- Modification de la base de production actuelle.
- Fermeture d'examens managers déjà ouverts.

## Terminé quand

- le Compose démarre sur la machine de travail et sur le serveur, avec `OSType` linux ;
- une seconde mise en route ne réécrit ni les conteneurs convergents, ni un palier Mongo déjà atteint ;
- les parcours de la section 6 passent sur PHP 8.4 et sur la copie MongoDB 8.0 ;
- l'ancienne arborescence et la sauvegarde du 23 octobre 2026 sont encore là, non écrasées ;
- les leçons ci-dessus sont dans `docs/engineering/lessons-learned.md`.
