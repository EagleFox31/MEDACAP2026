# MEDACAP QCM Offline Player

Lecteur d'épreuve QCM **hors ligne** pour techniciens CFAO en zone bush (sans connexion Internet).

## Flux technicien (production)

1. Le RH envoie **un seul fichier** : `Prenom_NOM_Junior_QCM.html` (généré par `export_offline_qcm.php`)
2. Double-clic dans **Chrome** → **écran d'accueil** (agence, niveau, durée, candidat attendu)
3. **Identification** : saisir nom et prénom, puis « Commencer l'épreuve »
4. Composer, puis **Terminer l'épreuve**
5. Le navigateur **télécharge le même HTML** avec les réponses intégrées dans le fichier
6. Renvoyer ce **fichier HTML complété** au RH (WhatsApp, mail, clé USB)

Un seul fichier du début à la fin — pas de JSON séparé.

## Test local

```bash
php scripts/build_offline_sample_html.php
```

Puis double-clic sur `offline-qcm/player/sample_QCM.html`.

Le dossier `player/` (index.html + app.js séparés) reste utile pour le développement et l'import manuel de `.medacap.json`.

## Contenu du dossier

| Fichier | Rôle |
|---------|------|
| `index.html` | Page principale (mode dev / import manuel) |
| `styles.css` | Interface glassmorphism CFAO |
| `app.js` | Logique QCM, sauvegarde IndexedDB, timer |
| `sample.medacap.json` | Données de démo |
| `sample_QCM.html` | Kit autonome généré (test) |

## Sauvegarde

- **Pendant l'épreuve** : IndexedDB uniquement (reprise si fermeture du navigateur)
- **À la fin** : téléchargement du HTML avec bloc `#medacap-quiz-data` mis à jour (`status: completed` + `answers`)

## Import RH

```bash
php scripts/import_offline_qcm.php --file=chemin/Prenom_NOM_Junior_QCM.html
```

Le script lit les réponses dans le HTML et marque le fichier `imported` après import.

## Contact

Remettez le fichier HTML terminé à votre point de contact MEDACAP / RH CFAO.
