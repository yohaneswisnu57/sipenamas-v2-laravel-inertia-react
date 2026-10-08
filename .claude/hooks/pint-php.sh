#!/usr/bin/env bash
# PostToolUse hook (Edit|Write): run Pint on the edited PHP file inside sipenamas_v2_backend.
file="$(php -r '$d = json_decode(stream_get_contents(STDIN), true); echo $d["tool_input"]["file_path"] ?? "";' 2>/dev/null)"
case "$file" in
  *.php) ;;
  *) exit 0 ;;
esac
case "$file" in
  */sipenamas_v2_backend/*) ;;
  *) exit 0 ;;
esac
backend="${file%%/sipenamas_v2_backend/*}/sipenamas_v2_backend"
[ -x "$backend/vendor/bin/pint" ] || exit 0
cd "$backend" && vendor/bin/pint --format agent "$file" >/dev/null 2>&1
exit 0
