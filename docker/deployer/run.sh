#!/usr/bin/env bash
# Entrée du conteneur lancé par deploy-server.ps1 : prépare ssh, puis lance deploy.sh du dépôt monté.
set -euo pipefail

# Clé montée depuis Windows avec des droits trop ouverts pour ssh : copie privée.
install -m 600 /deploy-key /root/.ssh/id_ed25519
mkdir -p .deploy
export GIT_SSH_COMMAND="ssh -i /root/.ssh/id_ed25519 -o StrictHostKeyChecking=accept-new -o UserKnownHostsFile=$PWD/.deploy/known_hosts"

# .env.server édité sous Windows : sans ça, bash garde un \r à la fin de chaque valeur.
[ -f .env.server ] && sed -i 's/\r$//' .env.server

exec bash ./deploy.sh "$@"
