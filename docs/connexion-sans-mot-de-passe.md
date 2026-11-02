# Connexion sans mot de passe — lien par mail

Décision : un manager (ou tout compte actif) qui a oublié son mot de passe saisit son adresse sur la page de connexion. Il reçoit un lien. Un clic ouvre sa session. Il ne choisit pas un nouveau mot de passe.

L'identifiant et le mot de passe actuels restent en place. Le lien est une seconde porte.

Contraintes : PHP 7.3, MongoDB 4.2, lib `mongodb/mongodb` 1.0.0 (`count()`, pas `countDocuments()`).

## Parcours

1. Sur `views/index.php`, deux entrées : le lien **Mot de passe oublié ?** sous le champ mot de passe, et le bouton **Me connecter par mail** sous le séparateur « ou ». En mode mail, le formulaire mot de passe est masqué ; après l'envoi, une confirmation affiche l'adresse masquée.
2. La personne saisit son adresse mail.
3. Si un seul compte `active: true` a cette adresse, un mail part. Dans tous les cas, la même confirmation s'affiche, avec le renvoi vers le support applicatif `jakaa-ext@cfao.com` si rien n'arrive sous 5 minutes. Aucun mot de passe n'est envoyé.
4. Le mail contient un bouton. Le lien est valable **30 minutes** et **une seule fois**.
5. La première ouverture affiche seulement **Ouvrir ma session**. Le jeton n'est consommé qu'au clic sur ce bouton, pour qu'un antivirus de messagerie ne le brûle pas en l'ouvrant tout seul.
6. Ce clic pose la même session qu'une connexion normale, puis redirige vers le même accueil :
   - Manager avec des évaluations restantes → `views/measure/userManagerList`
   - Manager sans évaluation restante → `views/measure/bilanTestNotes`
   - Technicien → `views/measure/ListeDesQcm`
   - Candidat → `views/measure/candidatePaperHome`
   - Les autres profils → `views/portal`

Le mail part sans copie. Les copies statiques des mails métier ne sont pas ajoutées.

## Données

Collection `login_links` :

| Champ | Rôle |
|---|---|
| `user` | ObjectId du compte |
| `purpose` | `login` (demande depuis la page de connexion) |
| `tokenHash` | SHA-256 du jeton. Le jeton en clair n'est pas stocké. |
| `expiresAt` | Date UTC, création + la plus longue des deux durées (lien 30 min, code 10 min) ; porte le TTL |
| `usedAt` | Vide tant que ni le lien ni le code n'a servi |
| `usedVia` | `link`, `code`, ou `revoked` (fermé par une autre connexion réussie du compte) |
| `created` | Date de la demande |
| `codeHash` | HMAC-SHA256 du code, clé liée au compte et au document (+ `MEDACAP_LOGIN_CODE_PEPPER` si posée) |
| `codeExpiresAt` | Date UTC, création + 10 minutes. Un nouvel envoi la ramène à maintenant sur les codes précédents. |
| `codeAttempts` | Essais de code sur ce document (5 maximum) |

Le jeton fait 32 octets aléatoires (`random_bytes`), encodé en hexadécimal dans l'URL. Le code fait 6 chiffres (`random_int`).

Collection `login_code_failures` : `{ user, created }`, un document par code faux, purgé après 24 h.

Index : `docker/migrations/002-login-links-indexes.js` (compare les clés, ne recrée pas un index déjà présent sous un autre nom). Index créés à la main en prod le 21/10/2026 :

```js
db.login_links.createIndex({ expiresAt: 1 }, { expireAfterSeconds: 0 })
db.login_links.createIndex({ tokenHash: 1 })
db.login_support_alerts.createIndex({ created: 1 }, { expireAfterSeconds: 2592000 }) // 30 jours
```

`users.password` et `users.visiblePassword` ne sont pas modifiés.

## Règles

- Adresse comparée sans tenir compte de la casse, après trim.
- Zéro compte, ou plusieurs comptes actifs avec la même adresse : pas de mail, même confirmation qu'un envoi réussi (pas d'énumération des comptes). Une ligne `login link: N active account(s) for <adresse>` part dans le log PHP pour le support.
- Alerte support : quand la demande n'aboutit pas (aucun compte actif, plusieurs comptes, échec d'envoi), `sendLoginSupportAlert()` écrit à `SUPER_ADMIN_EMAIL` : adresse saisie, demande (« Mot de passe oublié » si l'utilisateur est venu par ce lien — `motif=mdp` —, sinon « Connexion par mail »), problème (comptes concernés, comptes inactifs), langue de la page, date, IP, navigateur. Reply-To = adresse saisie. Au plus une alerte par adresse toutes les 30 minutes, 20 par heure au total (collection `login_support_alerts`).
- Compte `active: false` : ignoré.
- Trois demandes maximum pour la même adresse sur 15 minutes. Au-delà, le même message de confirmation s'affiche, sans nouveau mail.
- Le lien consommé ou expiré affiche une page courte : demander un nouveau lien. Pas d'erreur technique.
- Le mail utilise `generateEmailTemplate()` (`views/sendMail.php`), avec `buildHeaders(array(), null, false)` pour ne pas ajouter la copie statique.

URL du lien : même hôte que la demande s'il fait partie de la liste fermée (`10.68.0.7` interne, `129.0.64.34` externe), par exemple `http://10.68.0.7/medacap/views/loginLink.php?t=<jeton>`. Sinon, `medacapLinkForCountry()` selon le pays du compte. La liste fermée empêche qu'un en-tête `Host` forgé envoie le jeton vers un autre site.

## Code à 6 chiffres

Le mail est souvent lu sur un téléphone hors réseau CFAO, où le lien ne s'ouvre pas. Le même mail porte donc un code à taper sur le PC.

- Après l'envoi, l'écran « Vérifiez votre boîte mail » affiche le champ du code. L'adresse est gardée en session (`login_code_email`), posée sur toutes les réponses « envoyé » (y compris compte inconnu ou limite atteinte). Sans session (autre navigateur, lien « J'ai déjà un code » = `?code=1`), le formulaire demande aussi l'adresse.
- Code valable 10 minutes, une seule fois. Seul le dernier code envoyé est valable. Utiliser le code ferme le lien du même mail, et inversement ; une connexion réussie ferme aussi les autres envois en cours du compte.
- 5 essais par code (compteur incrémenté avant la comparaison), 15 échecs par compte sur 24 h. Au-delà, le code est refusé ; le lien reste utilisable.
- Saisie acceptée : `123456`, `123 456`, `123-456`. Une saisie qui n'a pas 6 chiffres ne consomme pas d'essai.
- Même message d'erreur dans tous les cas (code faux, expiré, compte inconnu). Même suite de requêtes que le compte existe ou non ; comparaison par `hash_equals`.
- Le code n'est jamais dans l'objet du mail (écran verrouillé du téléphone) ni dans les logs.

Fonctions partagées : `core/login_links.php` (`medacapIssueLoginLink()`, `medacapConsumeLoginCode()`, `medacapRevokeOtherLoginLinks()`, `medacapLoginLinkBase()`), `medacapLoginCodeBlockHtml()` dans `views/sendMail.php`.

## Compte à la demande

Quand quelqu'un demande un lien sans avoir de compte :

- L'alerte support (cause « aucun compte », sans compte inactif sur l'adresse) porte un bouton « Créer le compte » vers `views/explore/createUser.php?email=<adresse>`. Le formulaire s'ouvre avec l'adresse remplie. S'il existe un compte inactif, pas de bouton : le réactiver plutôt qu'en créer un second.
- Case « code et lien de connexion directe (72 h) », cochée par défaut, dans `createUser.php`. Elle s'applique aux mails envoyés au titulaire (Manager sans manager rattaché, Admin, RH, Direction). Le mail garde l'identifiant et le mot de passe, et ajoute un code et un lien valables 72 heures (`login_links`, `purpose: account_created`). Le code se saisit via « J'ai déjà un code » sur la page de connexion.
- Si l'envoi échoue, le document `login_links` est supprimé.
- Le créateur du compte reçoit une trace, avec le support en copie : titulaire, profil, identifiant, adresse, date. Elle ne contient ni mot de passe, ni code, ni lien.

## Fichiers

| Fichier | Rôle |
|---|---|
| `views/index.php` | Entrées « Mot de passe oublié ? » / « Me connecter par mail », formulaire d'adresse, confirmation d'envoi |
| `views/loginLink.php` | Affiche le bouton, consomme le jeton au clic, pose la session, redirige |
| `views/sendMail.php` | Fonction d'envoi du lien, sans copie ; `sendLoginSupportAlert()` pour les demandes sans suite |
| `core/login_session.php` | Pose `$_SESSION` et la ligne `connections`, partagé avec `views/index.php` |

La pose de session est dans `core/login_session.php`. Le mot de passe et le lien écrivent les mêmes clés : `username`, `profile`, `lastName`, `firstName`, `email`, `test`, `id`, `subsidiary`, `agency`, `department`, `country`, `lang`. `session_regenerate_id(true)` est appelé au début.

## Comptes sans adresse

À la connexion par mot de passe, un compte actif sans adresse valide (tous profils sauf Candidat) n'obtient pas de session : il est envoyé sur `views/addEmail.php`.

1. L'utilisateur saisit une adresse. Seules les adresses `@cfao.com` sont acceptées, et l'adresse ne doit être portée par aucun autre compte actif.
2. Un code à 6 chiffres part sur cette adresse (`login_links`, `purpose: email_verify`, champ `email`, 10 minutes). Le mail n'a pas de copie.
3. Le code saisi est vérifié avec les mêmes limites que le code de connexion. L'adresse est alors écrite dans `users.email` et la session s'ouvre.
4. L'unicité de l'adresse est contrôlée une seconde fois juste avant l'écriture.

- Le technicien peut passer avec « Plus tard » : il est le plus nombreux sans adresse et doit pouvoir composer. Les autres profils n'ont pas ce bouton.
- L'étape expire 30 minutes après la saisie du mot de passe. Ouvrir la page sans être passé par la connexion renvoie à l'accueil.
- Maximum 3 codes par 15 minutes.
- Fichiers : `core/email_gate.php` (règles), `views/addEmail.php` (page), `sendEmailVerifyCodeEmail()` dans `views/sendMail.php`.

## Hors périmètre

- Pas d'écran « choisir un mot de passe ».
- Pas de suppression des mots de passe existants.
- Le candidat sans adresse passe par le helpdesk.

## Vérification

1. Adresse d'un manager actif : le mail arrive, le lien ouvre `userManagerList` s'il a des évaluations en attente.
2. L'ouverture du lien affiche le bouton sans consommer le jeton. Le clic sur le bouton ouvre la session. Un second clic est refusé.
3. Lien de plus de 30 minutes : même refus.
4. Adresse inconnue, ou adresse partagée par deux comptes actifs : pas de mail, confirmation identique à un envoi réussi, ligne dans le log PHP.
5. Le mail n'a pas de ligne Copie.
6. La connexion identifiant + mot de passe fonctionne encore.
