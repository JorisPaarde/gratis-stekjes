#!/usr/bin/env bash
# Veilige double-quoted dotenv-waarden voor phpdotenv v5.
# Gebruik: source scripts/dotenv-quote.sh
#   dotenv_quote "$waarde"           → "waarde" (stdout; \ " $ ge-escaped)
#   dotenv_line KEY "$waarde"        → KEY="waarde"\n (stdout)
# Nooit waarden loggen. Faalt (exit 1) bij newline/CR.

dotenv_quote() {
  # Alleen via printf — geen echo (flags/backslashes).
  if [ "$#" -ne 1 ]; then
    printf '%s\n' 'dotenv_quote: verwacht precies 1 argument' >&2
    return 1
  fi
  # $1 mag leeg zijn (lege secret), maar geen newline/CR.
  case $1 in
    *$'\n'*|*$'\r'*)
      printf '%s\n' 'dotenv_quote: waarde bevat newline/CR — geweigerd' >&2
      return 1
      ;;
  esac
  # Escape voor double-quoted dotenv: \, ", $ (voorkomt interpolatie / afkappen).
  local escaped=$1
  escaped=${escaped//\\/\\\\}
  escaped=${escaped//\"/\\\"}
  escaped=${escaped//\$/\\\$}
  printf '"%s"' "$escaped"
}

dotenv_line() {
  if [ "$#" -ne 2 ]; then
    printf '%s\n' 'dotenv_line: verwacht KEY en waarde' >&2
    return 1
  fi
  local key=$1
  case $key in
    ''|*[!A-Za-z0-9_]*)
      printf '%s\n' 'dotenv_line: ongeldige KEY' >&2
      return 1
      ;;
  esac
  printf '%s=%s\n' "$key" "$(dotenv_quote "$2")"
}

# Strip omringende quotes van een .env-waarde (bij herlezen van APP_KEY e.d.).
# Double-quoted: unescape \\ \" \$ (volgorde: eerst \\, dan \" en \$).
dotenv_unquote() {
  local v=${1-}
  if [[ $v == \'*\' ]]; then
    v=${v:1:-1}
  elif [[ $v == \"*\" ]]; then
    v=${v:1:-1}
    v=${v//\\\"/\"}
    v=${v//\\\$/\$}
    v=${v//\\\\/\\}
  fi
  printf '%s' "$v"
}

# Lees KEY uit een .env-bestand (default: .env) en strip quotes.
# Gebruik: dotenv_env_get APP_ENV   of   dotenv_env_get APP_KEY /pad/naar/.env
dotenv_env_get() {
  if [ "$#" -lt 1 ] || [ "$#" -gt 2 ]; then
    printf '%s\n' 'dotenv_env_get: verwacht KEY [bestand]' >&2
    return 1
  fi
  local key=$1
  local file=${2:-.env}
  case $key in
    ''|*[!A-Za-z0-9_]*)
      printf '%s\n' 'dotenv_env_get: ongeldige KEY' >&2
      return 1
      ;;
  esac
  if [ ! -f "$file" ]; then
    printf '%s\n' "dotenv_env_get: bestand ontbreekt" >&2
    return 1
  fi
  local raw
  raw=$(grep -E "^${key}=" "$file" | head -1 | cut -d= -f2- || true)
  dotenv_unquote "$raw"
}
