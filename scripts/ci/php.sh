#!/usr/bin/env bash
# CI : php -l sur chaque fichier PHP (hors vendor) puis présence des classes du moteur de quiz.
# Lancé tel quel sur ubuntu (ci-github.yml) ou dans php:8.4-cli sur la .15 (ci-local.yml).
set -uo pipefail

list=$(mktemp)
if git rev-parse --git-dir >/dev/null 2>&1; then
  git ls-files -z '*.php' ':(exclude)vendor/**' > "$list"
else
  find . -name '*.php' -type f -not -path './vendor/*' -not -path './.git/*' -print0 > "$list"
fi
echo "$(tr -cd '\0' < "$list" | wc -c) fichiers PHP ($(php -r 'echo PHP_VERSION;'))"

failed=0
while IFS= read -r -d '' f; do
  out=$(php -l "$f" 2>&1) || {
    echo "::error file=${f#./}::$(printf '%s\n' "$out" | grep -m1 -iE 'error' || printf '%s' "$out")"; failed=1; }
done < "$list"
rm -f "$list"

php -r '
  require "views/measure/includes/quiz_validator.php";
  require "views/measure/includes/quiz_factuel_engine.php";
  foreach (["QuizConstants","QuizValidator","QuizDataFetcher","QuizPercentageCalculator","QuizQuestionGenerator","QuizResultProcessor"] as $c) {
    if (!class_exists($c)) { fwrite(STDERR, "Classe absente : $c\n"); exit(1); }
  }
  echo "Classes du quiz OK\n";
' || failed=1

exit $failed
