#!/usr/bin/env bash
# Veilige single-quoted dotenv-waarden voor phpdotenv v5.
# Gebruik: source scripts/dotenv-quote.sh
#   dotenv_quote "$waarde"           → 'waarde' (stdout)
#   dotenv_line KEY "$waarde"        → KEY='waarde'\n (stdout)
# Nooit waarden loggen. Faalt (exit 1) bij newline of single quote.

dotenv_quote() {
  # Alleen via printf — geen echo (flags/backslashes).
  if [ "$#" -ne 1 ]; then
    printf '%s\n' 'dotenv_quote: verwacht precies 1 argument' >&2
    return 1
  fi
  # $1 mag leeg zijn (lege secret), maar geen newline/CR of single quote.
  case $1 in
    *$'\n'*|*$'\r'*)
      printf '%s\n' 'dotenv_quote: waarde bevat newline/CR — geweigerd' >&2
      return 1
      ;;
    *"'"*)
      printf '%s\n' "dotenv_quote: waarde bevat single quote (') — geweigerd; kies een secret zonder '" >&2
      return 1
      ;;
  esac
  printf "'%s'" "$1"
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
dotenv_unquote() {
  local v=${1-}
  if [[ $v == \'*\' ]]; then
    v=${v:1:-1}
  elif [[ $v == \"*\" ]]; then
    v=${v:1:-1}
  fi
  printf '%s' "$v"
}
