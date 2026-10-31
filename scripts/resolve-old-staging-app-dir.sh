#!/usr/bin/env bash
# Resolve Laravel APP_DIR op de oude staging.stekjes-host (SSH of lokaal).
# Read-only: leest index.php / directory-listings; schrijft niets op de host
# behalve dit script zelf tijdelijk via de workflow (scp + rm).
#
# Usage:
#   resolve-old-staging-app-dir.sh --docroot /path/to/docroot [--override /path/to/app]
#
# Volgorde:
#   1) --override (workflow-input old_app_dir) als gezet — check artisan
#   2) DEPLOY_PATH/index.php: require …/vendor/autoload.php (absolute of relatief)
#   3) Unieke kandidaat onder siblings van docroot / $HOME/* (nooit app-prod/app-staging)
#
# Stdout: één regel = APP_DIR. Stderr: notices (paden OK; geen secrets/PII).
# Compat: bash 3.2+ (geen associatieve arrays / mapfile).
set -euo pipefail

DOCROOT=""
OVERRIDE=""

usage() {
  echo "usage: $0 --docroot DIR [--override APP_DIR]" >&2
  exit 1
}

while [ $# -gt 0 ]; do
  case "$1" in
    --docroot)
      [ $# -ge 2 ] || usage
      DOCROOT="$2"
      shift 2
      ;;
    --override)
      [ $# -ge 2 ] || usage
      OVERRIDE="$2"
      shift 2
      ;;
    -h|--help) usage ;;
    *)
      echo "onbekend argument: $1" >&2
      usage
      ;;
  esac
done

[ -n "$DOCROOT" ] || usage
DOCROOT="${DOCROOT%/}"

is_laravel_app() {
  local d="$1"
  [ -n "$d" ] && [ -f "$d/artisan" ] && [ -f "$d/bootstrap/app.php" ]
}

# Nieuwe cPanel-hosting — nooit kiezen als oude staging.
is_excluded_basename() {
  case "$(basename "$1")" in
    app-prod|app-staging) return 0 ;;
    *) return 1 ;;
  esac
}

abs_path() {
  local p="$1"
  if command -v realpath >/dev/null 2>&1; then
    realpath "$p" 2>/dev/null && return 0
  fi
  (cd "$p" && pwd -P) 2>/dev/null
}

# Strip trailing /vendor/autoload.php of /bootstrap/app.php → app-root.
strip_to_app_root() {
  local p="$1"
  case "$p" in
    */vendor/autoload.php) printf '%s\n' "${p%/vendor/autoload.php}" ;;
    */bootstrap/app.php) printf '%s\n' "${p%/bootstrap/app.php}" ;;
    *) return 1 ;;
  esac
}

# Probeer één require-pad (absoluut of __DIR__-relatief, incl. '/../app/...').
try_require_path() {
  local doc="$1"
  local rel="$2"
  local stripped candidate alt

  stripped="$(strip_to_app_root "$rel")" || return 1
  [ -n "$stripped" ] || return 1

  # 1) Echt absoluut pad dat als Laravel-app bestaat (/home/.../gratisplanten-staging-app).
  if [ "${stripped#/}" != "$stripped" ] && [ -d "$stripped" ] && is_laravel_app "$stripped"; then
    if ! is_excluded_basename "$stripped"; then
      abs_path "$stripped" 2>/dev/null || printf '%s\n' "$stripped"
      return 0
    fi
  fi

  # 2) Relatief t.o.v. docroot — ook voor '/../app' uit __DIR__ . '/../app/...'.
  if candidate="$(abs_path "$doc/$stripped" 2>/dev/null)" && is_laravel_app "$candidate"; then
    if ! is_excluded_basename "$candidate"; then
      printf '%s\n' "$candidate"
      return 0
    fi
  fi

  # 3) Leading slash weg ( '/../app' → '../app' ) en opnieuw t.o.v. docroot.
  alt="${stripped#/}"
  if [ "$alt" != "$stripped" ]; then
    if candidate="$(abs_path "$doc/$alt" 2>/dev/null)" && is_laravel_app "$candidate"; then
      if ! is_excluded_basename "$candidate"; then
        printf '%s\n' "$candidate"
        return 0
      fi
    fi
  fi

  return 1
}

# Haal app-map uit docroot/index.php. Prefer vendor/autoload.php, dan bootstrap/app.php.
parse_app_dir_from_index() {
  local doc="$1"
  local index="$doc/index.php"
  local matches line rel candidate
  [ -f "$index" ] || return 1

  matches="$(grep -oE "['\"][^'\"]*(vendor/autoload|bootstrap/app)\.php['\"]" "$index" 2>/dev/null || true)"
  [ -n "$matches" ] || return 1

  # Eerste pass: alleen vendor/autoload.php (zoals hosting index.php).
  while IFS= read -r line; do
    [ -n "$line" ] || continue
    case "$line" in
      *vendor/autoload.php*) ;;
      *) continue ;;
    esac
    rel="$(printf '%s' "$line" | sed -E "s/^['\"]//; s/['\"]$//")"
    if candidate="$(try_require_path "$doc" "$rel")"; then
      printf '%s\n' "$candidate"
      return 0
    fi
  done <<< "$matches"

  # Tweede pass: bootstrap/app.php
  while IFS= read -r line; do
    [ -n "$line" ] || continue
    case "$line" in
      *bootstrap/app.php*) ;;
      *) continue ;;
    esac
    rel="$(printf '%s' "$line" | sed -E "s/^['\"]//; s/['\"]$//")"
    if candidate="$(try_require_path "$doc" "$rel")"; then
      printf '%s\n' "$candidate"
      return 0
    fi
  done <<< "$matches"

  return 1
}

# Unieke Laravel-kandidaten: siblings van docroot + $HOME/*
collect_candidates() {
  local doc="$1"
  local parent home d doc_abs list tmp
  parent="$(dirname "$doc")"
  home="${HOME:-}"
  if [ -z "$home" ] || [ ! -d "$home" ]; then
    home="$(eval echo "~${USER:-}")" || home=""
  fi
  doc_abs="$(abs_path "$doc" 2>/dev/null || printf '%s' "$doc")"
  tmp="$(mktemp)"
  # shellcheck disable=SC2086
  for d in "$parent"/* ${home:+$home/*}; do
    [ -d "$d" ] || continue
    list="$(abs_path "$d" 2>/dev/null || printf '%s' "$d")"
    [ "$list" = "$doc_abs" ] && continue
    is_excluded_basename "$d" && continue
    is_laravel_app "$d" || continue
    printf '%s\n' "$list" >> "$tmp"
  done
  if [ -s "$tmp" ]; then
    sort -u "$tmp"
  fi
  rm -f "$tmp"
}

# --- override (workflow-input old_app_dir) ---
if [ -n "$OVERRIDE" ]; then
  OVERRIDE="${OVERRIDE%/}"
  if ! is_laravel_app "$OVERRIDE"; then
    echo "APP_DIR override mist artisan (of bootstrap/app.php): ${OVERRIDE}" >&2
    exit 1
  fi
  if is_excluded_basename "$OVERRIDE"; then
    echo "APP_DIR override is app-prod/app-staging (nieuwe hosting) — geweigerd: ${OVERRIDE}" >&2
    exit 1
  fi
  OUT="$(abs_path "$OVERRIDE" || printf '%s' "$OVERRIDE")"
  echo "APP_DIR via override: ${OUT}" >&2
  printf '%s\n' "$OUT"
  exit 0
fi

[ -d "$DOCROOT" ] || { echo "docroot ontbreekt: ${DOCROOT}" >&2; exit 1; }

# --- 1) index.php (absolute require eerst, dan relatief) ---
APP="$(parse_app_dir_from_index "$DOCROOT" || true)"
if [ -n "${APP:-}" ] && is_laravel_app "$APP"; then
  echo "APP_DIR via docroot/index.php: ${APP}" >&2
  printf '%s\n' "$APP"
  exit 0
fi

# --- 2) siblings + home ---
CANDS_FILE="$(mktemp)"
collect_candidates "$DOCROOT" > "$CANDS_FILE" || true
N="$(grep -c . "$CANDS_FILE" 2>/dev/null || echo 0)"
N="$(printf '%s' "$N" | tr -d '[:space:]')"
[ -n "$N" ] || N=0

if [ "$N" -eq 1 ]; then
  APP="$(head -n1 "$CANDS_FILE")"
  rm -f "$CANDS_FILE"
  echo "APP_DIR via unieke kandidaat (sibling/~): ${APP}" >&2
  printf '%s\n' "$APP"
  exit 0
fi

echo "APP_DIR kon niet eenduidig worden afgeleid van DEPLOY_PATH=${DOCROOT}." >&2
echo "Geef workflow-input old_app_dir, of zorg dat index.php naar de app wijst." >&2
if [ "$N" -eq 0 ]; then
  echo "Geen kandidaten met artisan (siblings van docroot / ~/*), exclusief app-prod/app-staging." >&2
else
  echo "Meerdere kandidaten (${N}):" >&2
  while IFS= read -r c; do
    [ -n "$c" ] || continue
    echo "  - ${c}" >&2
  done < "$CANDS_FILE"
fi
rm -f "$CANDS_FILE"
exit 1
