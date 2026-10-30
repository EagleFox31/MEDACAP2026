#!/bin/sh
# Applique dans l'ordre les fichiers NNN-*.js absents de academy._migrations, puis les y note.
# Une migration doit rester compatible avec la version précédente du code (retour arrière de deploy.sh).
set -eu

i=0
until mongosh --quiet --eval 'db.adminCommand("ping")' >/dev/null 2>&1; do
  i=$((i + 1))
  if [ "$i" -ge 30 ]; then
    echo "migrate: MongoDB injoignable" >&2
    exit 1
  fi
  sleep 2
done

for f in /migrations/[0-9]*.js; do
  [ -e "$f" ] || continue
  id=$(basename "$f" .js)
  # getCollection : mongosh ne résout pas db._migrations (nom commençant par _).
  applied=$(mongosh academy --quiet --eval "db.getCollection('_migrations').countDocuments({_id: '$id'})")
  if [ "$applied" != "0" ]; then
    continue
  fi
  echo "migrate: $id"
  mongosh academy --quiet --file "$f"
  mongosh academy --quiet --eval "db.getCollection('_migrations').insertOne({_id: '$id', appliedAt: new Date()})" >/dev/null
done
echo "migrate: à jour"
