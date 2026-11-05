#!/usr/bin/env bash
# Release = ce qui est en production. Lancé dans un clone complet (historique + tags) :
# sur ubuntu (job « Release GitHub ») ou dans alpine sur la .15 (job « Release .15 »).
# Version SemVer depuis la plus haute de : dernier tag vX.Y.Z, fichier VERSION du commit.
# Depuis la dernière release : branche ou commit « major/ » ou « BREAKING » -> majeure,
# branche « feat/ » ou commit « feat: » -> mineure, sinon corrective. Notes générées par GitHub (PR fusionnées).
# Variables : SHA, GH_TOKEN, GITHUB_API_URL, GITHUB_REPOSITORY, GITHUB_SERVER_URL, GITHUB_STEP_SUMMARY (facultative).
set -euo pipefail

if [ -n "$(git tag --points-at "$SHA" --list 'v*')" ]; then
  echo "Commit déjà publié : $(git tag --points-at "$SHA" --list 'v*')"; exit 0
fi
last_tag=$(git tag --merged "$SHA" --list 'v[0-9]*.[0-9]*.[0-9]*' --sort=-v:refname | head -n1)
file_version=$(git show "$SHA:VERSION" 2>/dev/null | tr -d ' \r\n' || true)
base=$(printf '%s\n%s\n' "${last_tag#v}" "$file_version" | grep -E '^[0-9]+\.[0-9]+\.[0-9]+$' | sort -V | tail -n1 || true)
base=${base:-0.0.0}
range="$SHA"; [ -n "$last_tag" ] && range="$last_tag..$SHA"
subjects=$(git log --format='%s%n%b' "$range")
IFS=. read -r major minor patch <<< "$base"
if grep -qiE 'BREAKING|^Merge pull request #[0-9]+ from [^/]+/major/' <<< "$subjects"; then
  major=$((major + 1)); minor=0; patch=0; kind=majeure
elif grep -qE '^feat(\(.+\))?!?:|^Merge pull request #[0-9]+ from [^/]+/feat/' <<< "$subjects"; then
  minor=$((minor + 1)); patch=0; kind=mineure
else
  patch=$((patch + 1)); kind=corrective
fi
tag="v$major.$minor.$patch"
echo "Base $base (dernier tag : ${last_tag:-aucun}, VERSION : ${file_version:-absent}) -> $tag ($kind)"

if [ "${DRY_RUN:-}" = "1" ]; then echo "DRY_RUN : release non créée"; exit 0; fi

body=$(jq -n --arg t "$tag" --arg c "$SHA" \
  '{tag_name: $t, target_commitish: $c, name: ("MEDACAP " + $t), generate_release_notes: true, make_latest: "true"}')
url=$(curl -fsS -X POST \
  -H "Authorization: Bearer $GH_TOKEN" -H "Accept: application/vnd.github+json" \
  "$GITHUB_API_URL/repos/$GITHUB_REPOSITORY/releases" -d "$body" | jq -r .html_url)
echo "Release publiée : $url"
if [ -n "${GITHUB_STEP_SUMMARY:-}" ]; then
  printf '## Release %s (%s)\n\n%s\n' "$tag" "$kind" "$url" >> "$GITHUB_STEP_SUMMARY"
fi
