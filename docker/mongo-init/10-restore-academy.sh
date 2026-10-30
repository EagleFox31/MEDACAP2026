#!/bin/sh
# Lancé par l'image mongo seulement si /data/db est vide (première installation ou volume perdu).
# Source : la sauvegarde quotidienne la plus récente (/backups), sinon la graine (/seed/*.archive.gz).
# Sans archive : base vide, le site démarre quand même (les migrations créent les index).
set -eu

ARCHIVE=$(ls -1t /backups/academy-*.archive.gz 2>/dev/null | head -n 1 || true)
if [ -z "$ARCHIVE" ]; then
  ARCHIVE=$(ls -1t /seed/*.archive.gz 2>/dev/null | head -n 1 || true)
fi

if [ -z "$ARCHIVE" ]; then
  echo "medacap-init: aucune archive dans /backups ni /seed, base academy laissée vide" >&2
  exit 0
fi

echo "medacap-init: restauration de $ARCHIVE"
mongorestore --host 127.0.0.1 --port 27017 --archive="$ARCHIVE" --gzip --nsInclude='academy.*'
echo "medacap-init: restauration terminée"
