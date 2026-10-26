#!/bin/sh
# Sauvegarde quotidienne de la base du conteneur. N'envoie aucun mail.
# Le shell s'appelle mongo jusqu'à l'image 4.4, puis mongosh.
set -u
DEST=/backups
mkdir -p "$DEST"

shell_eval() {
  if command -v mongosh >/dev/null 2>&1; then
    mongosh --host mongo --quiet --eval "$1"
  else
    mongo --host mongo --quiet --eval "$1"
  fi
}

while true; do
  STAMP=$(date +%Y%m%d-%H%M)
  ARCHIVE="$DEST/academy-$STAMP.archive.gz"
  if mongodump --host mongo --port 27017 --db academy --archive="$ARCHIVE" --gzip; then
    echo "$(date '+%Y-%m-%dT%H:%M:%S%z') dump ok $ARCHIVE"
    find "$DEST" -name 'academy-*.archive.gz' -type f -mtime +14 -delete
  else
    echo "$(date '+%Y-%m-%dT%H:%M:%S%z') dump failed"
    rm -f "$ARCHIVE"
  fi
  if [ "$(date +%u)" = "7" ]; then
    LAST=$(ls -1t "$DEST"/academy-*.archive.gz 2>/dev/null | head -n 1 || true)
    if [ -n "$LAST" ]; then
      shell_eval 'db.getSiblingDB("academy_backup_check").dropDatabase()'
      if mongorestore --host mongo --port 27017 --archive="$LAST" --gzip --nsFrom='academy.*' --nsTo='academy_backup_check.*' --drop; then
        shell_eval 'db.getSiblingDB("academy_backup_check").dropDatabase()'
        echo "$(date '+%Y-%m-%dT%H:%M:%S%z') restore check ok"
      else
        shell_eval 'db.getSiblingDB("academy_backup_check").dropDatabase()'
        echo "$(date '+%Y-%m-%dT%H:%M:%S%z') restore check failed"
      fi
    fi
  fi
  sleep 86400
done
