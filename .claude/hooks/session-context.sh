#!/usr/bin/env bash
# SessionStart hook: print git state and open plan tasks so a new session starts with context.
root="$(git rev-parse --show-toplevel 2>/dev/null)" || exit 0
cd "$root" || exit 0

echo "## Session context (auto)"
echo "Branch: $(git branch --show-current)"
echo
echo "### git status --short (max 25)"
git status --short | head -25
echo
echo "### git log -n 5"
git log -n 5 --oneline
plan="$(ls -1 docs/plan/tugas-*.md 2>/dev/null | sort | tail -1)"
if [ -n "$plan" ]; then
  echo
  echo "### Open tasks in $plan"
  grep -E '^\| *[0-9]+ *\|' "$plan" | grep -viE '\| *Selesai[^|]*\| *$' | head -20
fi
exit 0
