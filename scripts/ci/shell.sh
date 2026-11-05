#!/usr/bin/env bash
# CI : syntaxe des scripts shell (bash -n), hors vendor.
set -uo pipefail

failed=0
while IFS= read -r -d '' f; do
  bash -n "$f" || { echo "::error file=${f#./}::bash -n en échec"; failed=1; }
done < <(find . -name '*.sh' -type f -not -path './vendor/*' -not -path './.git/*' -print0)
exit $failed
