#!/usr/bin/env bash
# MEDACAP 8.4 — déploiement serveur en une commande, idempotent. À lancer depuis le clone du dépôt sur le serveur.
#   ./deploy.sh            déploie le dernier commit de DEPLOY_BRANCH (défaut main)
#   ./deploy.sh <commit>   déploie un commit précis (retour à une version antérieure)
# Relancer sans changement ne fait rien d'autre que remettre en route ce qui manque.
# Nouveau commit : sauvegarde de la base, image construite une fois et taguée par commit,
# seuls les conteneurs dont l'image ou la config change sont recréés, migrations appliquées,
# contrôle de santé, et retour automatique à la version précédente en cas d'échec.
# Volume Mongo vide : restauration automatique (docker/mongo-init). Voir docs/deploiement-serveur.md.
set -euo pipefail

STATE=.deploy
IMAGE=medacap84-web
KEEP_IMAGES=3

log() { echo "$(date '+%Y-%m-%d %H:%M:%S') $*"; }
die() { log "ERREUR: $*" >&2; exit 1; }

compose() { docker compose -f docker-compose.server.yml --env-file .env.server "$@"; }

# Santé : migrations terminées sans erreur, site sain et répondant, cron démarré.
wait_healthy() {
  local i status code
  for i in $(seq 1 36); do
    status=$(docker inspect -f '{{.State.Health.Status}}' "$(compose ps -q web)" 2>/dev/null || echo absent)
    if [ "$status" = "healthy" ]; then
      code=$(compose exec -T web php -r '$h = @get_headers("http://127.0.0.1/index.php"); echo $h ? substr($h[0], 9, 3) : 0;' 2>/dev/null || echo 0)
      if [ "$code" -ge 200 ] && [ "$code" -lt 500 ] && [ -n "$(compose ps -q --status running cron)" ]; then
        log "santé ok (HTTP $code)"
        return 0
      fi
    fi
    sleep 5
  done
  log "santé KO : web=$status HTTP=${code:-?}"
  compose logs --tail 40 migrate web cron >&2 || true
  return 1
}

# Construit l'image du commit si elle n'existe pas encore, puis aligne la stack dessus.
apply() {
  local commit=$1 tag
  tag=$(git rev-parse --short=12 "$commit")
  # Appelée dans un if : set -e n'y joue pas, d'où les || return 1.
  git checkout --quiet --detach "$commit" || return 1
  export MEDACAP_TAG=$tag
  if ! docker image inspect "$IMAGE:$tag" >/dev/null 2>&1; then
    log "construction de $IMAGE:$tag"
    # --pull : chaque nouvelle version reprend le dernier correctif de sécurité de php:8.4-apache.
    compose build --pull web || return 1
  fi
  compose up -d --remove-orphans && wait_healthy
}

main() {
  cd "$(dirname "$(readlink -f "$0")")"
  [ -f .env.server ] || die ".env.server absent : cp .env.server.example .env.server puis le remplir"
  mkdir -p "$STATE"
  exec 9>"$STATE/lock"
  flock -n 9 || die "un déploiement est déjà en cours"

  # Proxy (git, construction d'image) et réglages, depuis .env.server.
  set -a
  . ./.env.server
  set +a
  local branch=${DEPLOY_BRANCH:-main}

  if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    git status --short --untracked-files=no >&2
    die "fichiers du dépôt modifiés sur le serveur : corriger dans Git, pas ici (git checkout -- . pour annuler)"
  fi

  git fetch --quiet origin "$branch"
  local target current
  target=$(git rev-parse --verify "${1:-origin/$branch}^{commit}")
  current=$(cat "$STATE/current" 2>/dev/null || true)

  # Commit déjà annulé : une relance sans argument ne le retente pas (site en erreur à chaque essai).
  if [ -z "${1:-}" ] && [ -n "$current" ] && [ "$target" = "$(cat "$STATE/failed" 2>/dev/null || true)" ]; then
    log "$(git rev-parse --short=12 "$target") déjà en échec, ignoré (pousser un correctif, ou ./deploy.sh <commit> pour forcer)"
    target=$current
  fi

  if [ "$target" = "$current" ]; then
    log "déjà sur $(git rev-parse --short=12 "$target"), vérification de la stack"
    apply "$target" || die "stack en échec sur la version courante"
    exit 0
  fi

  log "déploiement ${current:0:12} -> ${target:0:12} ($(git log -1 --format=%s "$target"))"
  if [ -n "$current" ]; then
    # Le compose exige MEDACAP_TAG même pour ps/exec : celui de la version en service.
    export MEDACAP_TAG=$(git rev-parse --short=12 "$current")
    if [ -n "$(compose ps -q --status running backup)" ]; then
      local dump="/backups/academy-predeploy-$(date +%Y%m%d-%H%M%S).archive.gz"
      compose exec -T backup mongodump --host mongo --db academy --archive="$dump" --gzip --quiet \
        && log "sauvegarde avant déploiement : $dump" \
        || die "sauvegarde avant déploiement en échec, rien n'a été changé"
    else
      log "service backup arrêté : pas de sauvegarde avant déploiement"
    fi
  fi

  if apply "$target"; then
    echo "$target" > "$STATE/current"
    echo "$(date -Is) ${current:-initial} -> $target" >> "$STATE/history"
    docker image ls "$IMAGE" --format '{{.Repository}}:{{.Tag}}' | tail -n +$((KEEP_IMAGES + 1)) | xargs -r docker image rm >/dev/null 2>&1 || true
    log "déployé : ${target:0:12}"
    exit 0
  fi

  if [ -z "$current" ]; then
    die "premier déploiement en échec, pas de version précédente"
  fi
  log "retour à la version précédente ${current:0:12}"
  apply "$current" || die "retour arrière en échec aussi : intervention manuelle"
  echo "$target" > "$STATE/failed"
  echo "$(date -Is) ${current} -> $target ÉCHEC, revenu à $current" >> "$STATE/history"
  die "déploiement de ${target:0:12} annulé, ${current:0:12} remis en service"
}

main "$@"
