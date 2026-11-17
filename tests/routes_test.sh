#!/usr/bin/env bash
# Run against the local router:
#   php -S 127.0.0.1:8000 router.php
#   BASE_URL=http://127.0.0.1:8000 bash tests/routes_test.sh
set -u

BASE="${BASE_URL:-http://127.0.0.1:8000}"
BASE="${BASE%/}"
FAILS=0
TOTAL=0

check() {
  local label="$1" path="$2" expected="$3"
  local code
  code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE$path")
  TOTAL=$((TOTAL + 1))
  if [ "$code" = "$expected" ]; then
    echo "PASS  $label ($code)"
  else
    echo "FAIL  $label — expected $expected, got $code"
    FAILS=$((FAILS + 1))
  fi
}

check_any() {
  local label="$1" path="$2" expected="$3"
  local code
  code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE$path")
  TOTAL=$((TOTAL + 1))
  case " $expected " in
    *" $code "*) echo "PASS  $label ($code)" ;;
    *) echo "FAIL  $label — expected one of $expected, got $code"; FAILS=$((FAILS + 1)) ;;
  esac
}

expect_location() {
  local label="$1" path="$2" expected="$3"
  local location
  location=$(curl -s -o /dev/null -w '%{redirect_url}' "$BASE$path")
  TOTAL=$((TOTAL + 1))
  if [ "$location" = "$expected" ]; then
    echo "PASS  $label -> $location"
  else
    echo "FAIL  $label — expected $expected, got $location"
    FAILS=$((FAILS + 1))
  fi
}

# Public pages
check "dashboard" "/" 200
check "login page" "/login" 200
check "input when signed out" "/input" 302
check "mapping when signed out" "/mapping" 302
check "report when signed out" "/report" 302
check "fields when signed out" "/fields" 302
check "export route" "/export" 400

# Clean URL redirects
check "direct .php" "/input.php" 301
expect_location "direct .php keeps query" "/input.php?period=2026-03" "$BASE/input?period=2026-03"
expect_location "trailing slash" "/input/" "$BASE/input"
expect_location "index .php" "/index.php" "$BASE/index"
expect_location "fields .php" "/fields.php" "$BASE/fields"

# Private files
check "config.php" "/config.php" 404
check "config.local.php" "/config.local.php" 404
check "config.local.example.php" "/config.local.example.php" 404
check "EspoClient.php" "/EspoClient.php" 404
check "bootstrap.php" "/bootstrap.php" 404
check "store.php" "/store.php" 404
check "auth.php" "/auth.php" 404
check "ui.php" "/ui.php" 404
check "router.php" "/router.php" 404
check "sources.php" "/sources.php" 404
check "tests directory" "/tests/" 404
check "test.php" "/test.php" 404
check "HOW_TO_CUSTOMIZE.php" "/HOW_TO_CUSTOMIZE.php" 404

# Private directories and docs
check_any "data directory" "/data/" "403 404"
check_any "data sqlite" "/data/app.sqlite" "403 404"
check "cache directory" "/cache/" 404
check "docs directory" "/docs/" 404
check "march pdf" "/docs/LAPORAN%20MARET%202026.pdf" 404
check "readme" "/README.md" 404

# Static assets
check "stylesheet" "/assets/style.css" 200
check "app js" "/assets/app.js" 200

echo
echo "$((TOTAL - FAILS))/$TOTAL checks passed"
exit $((FAILS > 0 ? 1 : 0))
