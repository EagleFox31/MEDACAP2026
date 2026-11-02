# Déploiement serveur (10.68.0.15)

Une commande, idempotente : `./deploy.sh` (sur la .15, Windows : `.\deploy-server.ps1`, qui le lance dans un conteneur). Pas de registre d'images : le serveur récupère le commit
dans Git et construit lui-même l'image, taguée par commit.

| Situation | Ce que fait `./deploy.sh` |
|---|---|
| Rien n'a changé | Remet en route ce qui manque (conteneur arrêté ou supprimé), sinon rien |
| Nouveau commit sur `DEPLOY_BRANCH` | Sauvegarde Mongo, construit `medacap84-web:<commit>`, recrée `web` et `cron` seulement, applique les migrations |
| Le nouveau commit ne démarre pas | Remet la version précédente (image encore présente) et sort en erreur |
| Volume Mongo vide (1re installation, disque perdu) | L'image Mongo restaure la dernière sauvegarde de `BACKUP_DIR`, sinon l'archive de `SEED_DIR` |
| `./deploy.sh <commit>` | Déploie ce commit précis (retour volontaire à une version) |

Port : **8010** = web principal (offset XX0) du bloc 8010-8019, plage 80xx « applications développées en
interne » de la convention de ports de la .15. MongoDB n'est publié sur aucun port (réseau Docker interne).
Le bloc 8010-8019 est à réserver dans le registre des ports de la .15 avant la mise en service.

## Services

| Service | Rôle |
|---|---|
| `mongo` | MongoDB 8.0, volume `medacap84`. Porte aussi le port HTTP (le site partage sa pile réseau) |
| `migrate` | Exécute `docker/migrations/NNN-*.js` absents de `academy._migrations`, à chaque déploiement |
| `web` | Apache + PHP 8.4, code du commit dans l'image. Fichiers écrits par le site sur le volume `medacap84-data` |
| `cron` | `docker/server/crontab` (supercronic, utilisateur www-data) |
| `backup` | `mongodump` quotidien dans `BACKUP_DIR`, 14 jours gardés, test de restauration le dimanche |

## Installation sur la .15 (Windows Server 2022 + Docker Desktop, une fois)

La .15 n'a pas de distribution Linux : `.\deploy-server.ps1` lance `deploy.sh` dans un conteneur
(`docker/deployer`) qui pilote le Docker de la machine. Rien à installer, Docker n'est pas redémarré.
Les autres applications de la .15 ne sont pas touchées (projet compose `medacap84`, port 8010).
Accès direct à GitHub en 443 : pas de proxy, `HTTP_PROXY` reste vide.

PowerShell, avec le compte qui déploiera :

1. Clé de déploiement GitHub, lecture seule, limitée à ce dépôt :
   ```powershell
   ssh-keygen -t ed25519 -N '""' -f $HOME\.ssh\medacap_deploy -C medacap-015
   Get-Content $HOME\.ssh\medacap_deploy.pub   # GitHub > dépôt > Settings > Deploy keys > Add (sans écriture)
   ```
2. Clone (`autocrlf=false` obligatoire : le dépôt est relu par le git Linux du conteneur) et réglages :
   ```powershell
   $env:GIT_SSH_COMMAND = "ssh -i ~/.ssh/medacap_deploy -o StrictHostKeyChecking=accept-new"
   git clone -c core.autocrlf=false ssh://git@ssh.github.com:443/EagleFox31/MEDACAP2026.git E:\medacap84
   cd E:\medacap84
   Copy-Item .env.server.example .env.server; notepad .env.server
   New-Item -ItemType Directory seed; Copy-Item C:\chemin\academy.archive.gz seed\
   .\deploy-server.ps1
   ```

Si la 1re installation échoue sur « dependency mongo failed to start » (mongo redémarre une fois après
son initialisation sur une machine chargée), relancer `.\deploy-server.ps1` : la base créée est conservée.

Mise à jour : `cd E:\medacap84; .\deploy-server.ps1` (ou `.\deploy-server.ps1 <commit>`), mêmes règles que `./deploy.sh` ci-dessous.
Commandes compose à la main depuis PowerShell : `ps`, `logs`, `exec` seulement, jamais `up` (les chemins
Windows recréeraient les conteneurs) :
```powershell
$env:MEDACAP_TAG = git rev-parse --short=12 (Get-Content .deploy\current)
docker compose -f docker-compose.server.yml --env-file .env.server ps
```
Après un redémarrage du serveur, la stack ne repart qu'au lancement de Docker Desktop (session ouverte),
comme les autres applications de la .15.

## Installation sur un serveur Linux (une fois)

1. Proxy pour le démon Docker (téléchargement des images de base) :
   ```sh
   sudo mkdir -p /etc/systemd/system/docker.service.d
   printf '[Service]\nEnvironment="HTTP_PROXY=http://PROXY:PORT" "HTTPS_PROXY=http://PROXY:PORT" "NO_PROXY=localhost,127.0.0.1,.cfao.corp"\n' \
     | sudo tee /etc/systemd/system/docker.service.d/http-proxy.conf
   sudo systemctl daemon-reload && sudo systemctl restart docker
   ```
2. Clé de déploiement GitHub, lecture seule, limitée à ce dépôt (pas de compte personnel sur le serveur) :
   ```sh
   ssh-keygen -t ed25519 -N '' -f ~/.ssh/medacap_deploy
   cat ~/.ssh/medacap_deploy.pub   # GitHub > dépôt > Settings > Deploy keys > Add (sans écriture)
   ```
   SSH passe par le proxy sur le port 443 de GitHub, dans `~/.ssh/config` :
   ```
   Host github.com
     HostName ssh.github.com
     Port 443
     User git
     IdentityFile ~/.ssh/medacap_deploy
     ProxyCommand nc -X connect -x PROXY:PORT %h %p
   ```
   Test : `ssh -T git@github.com` doit répondre « successfully authenticated ».
3. Clone et réglages :
   ```sh
   git clone git@github.com:EagleFox31/MEDACAP2026.git /opt/medacap84 && cd /opt/medacap84
   cp .env.server.example .env.server && vi .env.server   # proxy, URL publique
   mkdir -p seed && cp /chemin/academy.archive.gz seed/  # données initiales (mongodump --gzip --archive)
   ./deploy.sh
   ```

## Mises à jour

Après chaque merge sur `main`, sur le serveur : `cd /opt/medacap84 && ./deploy.sh`. Le script récupère
le dernier commit sur GitHub (via le proxy) et ne fait rien s'il est déjà en ligne. Rien ne tourne en tâche
de fond : le serveur ne contacte GitHub que lorsqu'on lance le script.

Historique des versions : `.deploy/history`. Version en ligne : `.deploy/current`.

- Commit en échec : remis automatiquement sur la version précédente et noté dans `.deploy/failed` ; `./deploy.sh` sans
  argument ne le retente pas.
  Pousser un correctif, ou forcer : `./deploy.sh <commit>`. Retour à une version antérieure : `./deploy.sh <commit>`.
- Une modification de `deploy.sh` lui-même ne s'applique qu'au déploiement suivant.
- Commandes compose à la main : le fichier exige `MEDACAP_TAG`, et `-f` est obligatoire (sinon `docker-compose.yml`, celui du PC, est lu) :
  ```bash
  export MEDACAP_TAG=$(git rev-parse --short=12 "$(cat .deploy/current)")
  docker compose -f docker-compose.server.yml --env-file .env.server ps     # ou logs cron, exec mongo mongosh academy…
  ```
- Base perdue (volume `medacap84_medacap84` supprimé) : `./deploy.sh` la recrée depuis la sauvegarde la plus récente de `BACKUP_DIR`.

## Règles pour le code

- Ne rien modifier sur le serveur : `deploy.sh` refuse de tourner si un fichier suivi par Git a été modifié.
- Une migration (`docker/migrations/NNN-nom.js`) est jouée une seule fois, dans l'ordre des numéros.
  Elle doit rester compatible avec la version précédente du code (ajouter des index ou des champs, pas en supprimer),
  puisque le retour arrière ne rejoue pas les migrations à l'envers.
- Nouveau chemin écrit par le site : l'ajouter à la boucle `for p in ...` de `docker/Dockerfile` (cible `server`),
  sinon le fichier est perdu au prochain déploiement.
- Nouvelle tâche planifiée : une ligne dans `docker/server/crontab`.
- Scripts shell (`deploy.sh`, `docker/mongo-init/*.sh`) : garder le bit exécutable dans Git
  (`git update-index --chmod=+x <fichier>`) ; depuis Windows, un `git commit -a` peut le retirer.
