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
| `tokenHash` | SHA-256 du jeton. Le jeton en clair n'est pas stocké. |
| `expiresAt` | Date UTC, création + 30 minutes |
| `usedAt` | Vide tant que le lien n'a pas été ouvert |
| `created` | Date de la demande |

Le jeton fait 32 octets aléatoires (`random_bytes`), encodé en hexadécimal dans l'URL.

Index créés en prod le 21/10/2026 (le TTL supprime les liens expirés) :

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

## Fichiers

| Fichier | Rôle |
|---|---|
| `views/index.php` | Entrées « Mot de passe oublié ? » / « Me connecter par mail », formulaire d'adresse, confirmation d'envoi |
| `views/loginLink.php` | Affiche le bouton, consomme le jeton au clic, pose la session, redirige |
| `views/sendMail.php` | Fonction d'envoi du lien, sans copie ; `sendLoginSupportAlert()` pour les demandes sans suite |
| `core/login_session.php` | Pose `$_SESSION` et la ligne `connections`, partagé avec `views/index.php` |

La pose de session est dans `core/login_session.php`. Le mot de passe et le lien écrivent les mêmes clés : `username`, `profile`, `lastName`, `firstName`, `email`, `test`, `id`, `subsidiary`, `agency`, `department`, `country`, `lang`. `session_regenerate_id(true)` est appelé au début.

## Hors périmètre

- Pas de code à 6 chiffres.
- Pas d'écran « choisir un mot de passe ».
- Pas de suppression des mots de passe existants.
- Les comptes sans adresse passent par le helpdesk.

## Vérification

1. Adresse d'un manager actif : le mail arrive, le lien ouvre `userManagerList` s'il a des évaluations en attente.
2. L'ouverture du lien affiche le bouton sans consommer le jeton. Le clic sur le bouton ouvre la session. Un second clic est refusé.
3. Lien de plus de 30 minutes : même refus.
4. Adresse inconnue, ou adresse partagée par deux comptes actifs : pas de mail, confirmation identique à un envoi réussi, ligne dans le log PHP.
5. Le mail n'a pas de ligne Copie.
6. La connexion identifiant + mot de passe fonctionne encore.
