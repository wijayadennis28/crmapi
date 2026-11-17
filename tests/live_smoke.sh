#!/usr/bin/env bash
# Live smoke test for routes, private-file blocks, and versioned assets.
# Usage:
#   BASE_URL=https://dashboard.example.com bash tests/live_smoke.sh
#   BASE_URL=https://dashboard.example.com COOKIE='PHPSESSID=...' bash tests/live_smoke.sh
set -u

BASE_URL="${BASE_URL:-}"
if [ -z "$BASE_URL" ]; then
    echo "Usage: BASE_URL=https://example.com bash tests/live_smoke.sh" >&2
    exit 2
fi
BASE_URL="${BASE_URL%/}"
COOKIE="${COOKIE:-}"
pass=0
fail=0

check() {
    label="$1"
    expected="$2"
    actual="$3"
    if [ "$actual" = "$expected" ]; then
        echo "PASS  $label"
        pass=$((pass + 1))
    else
        echo "FAIL  $label (expected $expected, got $actual)"
        fail=$((fail + 1))
    fi
}

code() {
    curl -sS --max-time 20 -o /dev/null -w '%{http_code}' "$@"
}

check 'login page' 200 "$(code "$BASE_URL/login")"

if [ -n "$COOKIE" ]; then
    check 'input page signed in' 200 "$(code -b "$COOKIE" "$BASE_URL/input")"
else
    input_code="$(code "$BASE_URL/input")"
    if [ "$input_code" = '200' ] || [ "$input_code" = '302' ]; then
        echo "PASS  input route"
        pass=$((pass + 1))
    else
        echo "FAIL  input route (expected 200/302, got $input_code)"
        fail=$((fail + 1))
    fi
fi

check 'direct input.php redirect' 301 "$(code "$BASE_URL/input.php")"
redirect="$(curl -sS --max-time 20 -o /dev/null -w '%{redirect_url}' "$BASE_URL/input.php")"
case "$redirect" in
    */input)
        echo "PASS  input.php redirect target"
        pass=$((pass + 1))
        ;;
    *)
        echo "FAIL  input.php redirect target ($redirect)"
        fail=$((fail + 1))
        ;;
esac

for path in README.md config.php config.local.php store.php auth.php ui.php bootstrap.php test.php docs/ data/ cache/; do
    check "blocked /$path" 404 "$(code "$BASE_URL/$path")"
done

css_path="$(curl -sS --max-time 20 "$BASE_URL/login" | grep -o 'assets/style\.css?v=[0-9]*' | head -1)"
if [ -n "$css_path" ] && curl -sS --max-time 20 "$BASE_URL/$css_path" | grep -q -- '--font-body'; then
    echo "PASS  versioned style.css"
    pass=$((pass + 1))
else
    echo "FAIL  versioned style.css"
    fail=$((fail + 1))
fi

js_path="$(curl -sS --max-time 20 "$BASE_URL/" | grep -o 'assets/app\.js?v=[0-9]*' | head -1)"
if [ -n "$js_path" ] && curl -sS --max-time 20 "$BASE_URL/$js_path" | grep -q "closest('.cell-wrap')"; then
    echo "PASS  versioned app.js"
    pass=$((pass + 1))
else
    echo "FAIL  versioned app.js"
    fail=$((fail + 1))
fi

echo
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
