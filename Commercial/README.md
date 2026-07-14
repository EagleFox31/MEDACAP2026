# Intégration du Questionnaire Commercial

Ce dossier contient le module complet du questionnaire (Single Page Application, bilingue, anti-triche).

## 🚀 Comment l'intégrer dans un autre projet ?

**1. Copier le dossier**
Copiez tout ce dossier `Commercial` dans le répertoire `views/measure/` de votre nouvelle version.

**2. Ajuster les chemins (si besoin)**
Ouvrez `open-questionnaire.php` et vérifiez que les `include_once` pointent bien vers vos fichiers existants (ex: `../../language.php`, `../partials/header.php` et les chemins des logos).

**3. Ajouter le lien d'accès**
Ajoutez simplement ce lien dans votre menu pour y accéder :
`<a href="views/measure/Commercial/open-questionnaire.php">Questionnaire</a>`

---

## ⚙️ Ce qu'il faut savoir

* **Sauvegarde en base de données :** Le fichier obsolète `saveOpenAnswers.php` n'existe plus. Insérez simplement votre code d'enregistrement SQL/NoSQL tout en haut de `open-questionnaire.php`, dans la condition `if ($_SERVER['REQUEST_METHOD'] === 'POST')`.
* **Sessions requises :** Le module utilise la session globale (`$_SESSION['lang']`, `$_SESSION['firstName']`, etc.).
* **URL magiques :** Vous pouvez forcer une langue ou une marque directement via l'URL (ex: `?lang=EN` ou `?type=sinotruk`).
