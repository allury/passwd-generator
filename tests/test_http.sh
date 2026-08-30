#!/usr/bin/env bash

set -euo pipefail

repo_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
test_dir="$(mktemp -d)"
test_port="$((18080 + RANDOM % 1000))"
server_pid=""

cleanup() {
    if [[ -n "$server_pid" ]] && kill -0 "$server_pid" 2>/dev/null; then
        kill "$server_pid" 2>/dev/null || true
        wait "$server_pid" 2>/dev/null || true
    fi
    rm -rf "$test_dir"
}
trap cleanup EXIT INT TERM

php -S "127.0.0.1:${test_port}" -t "$repo_dir" >"$test_dir/server.log" 2>&1 &
server_pid="$!"
base_url="http://127.0.0.1:${test_port}/passwd.php"

server_ready=0
for _ in {1..50}; do
    if curl --silent --show-error --fail "$base_url" --output /dev/null; then
        server_ready=1
        break
    fi
    if ! kill -0 "$server_pid" 2>/dev/null; then
        sed -n '1,120p' "$test_dir/server.log" >&2
        exit 1
    fi
    sleep 0.1
done

if [[ "$server_ready" -ne 1 ]]; then
    echo "PHP test server did not become ready." >&2
    sed -n '1,120p' "$test_dir/server.log" >&2
    exit 1
fi

curl --silent --show-error --fail \
    --dump-header "$test_dir/page.headers" \
    --output "$test_dir/page.html" \
    "$base_url"

grep -Eqi '^Content-Type: text/html; charset=UTF-8' "$test_dir/page.headers"
grep -Eqi '^X-Content-Type-Options: nosniff' "$test_dir/page.headers"
grep -Eqi '^X-Frame-Options: DENY' "$test_dir/page.headers"
grep -Eqi '^Referrer-Policy: no-referrer' "$test_dir/page.headers"
grep -Eqi '^Content-Security-Policy:' "$test_dir/page.headers"
grep -Fq "connect-src 'self'" "$test_dir/page.headers"
grep -Fq 'https://fonts.googleapis.com' "$test_dir/page.headers"
grep -Fq 'https://fonts.gstatic.com' "$test_dir/page.headers"
grep -Fq 'id="strengthText">-</span>' "$test_dir/page.html"

ajax_status="$(curl --silent --show-error \
    --request POST \
    --header 'X-Requested-With: XMLHttpRequest' \
    --data 'form_submitted=1&length=16&lowercase=on&uppercase=on&numbers=on&symbols=on' \
    --dump-header "$test_dir/ajax.headers" \
    --output "$test_dir/ajax.json" \
    --write-out '%{http_code}' \
    "$base_url")"

[[ "$ajax_status" == "200" ]]
grep -Eqi '^Content-Type: application/json; charset=UTF-8' "$test_dir/ajax.headers"
php -r '
    $data = json_decode(file_get_contents($argv[1]), true);
    if (!is_array($data) || !isset($data["password"]) || strlen($data["password"]) !== 16) {
        fwrite(STDERR, "Invalid AJAX password response.\n");
        exit(1);
    }
' "$test_dir/ajax.json"

error_status="$(curl --silent --show-error \
    --request POST \
    --header 'X-Requested-With: XMLHttpRequest' \
    --data 'form_submitted=1&length=16' \
    --output "$test_dir/error.json" \
    --write-out '%{http_code}' \
    "$base_url")"

[[ "$error_status" == "422" ]]
php -r '
    $data = json_decode(file_get_contents($argv[1]), true);
    if (!is_array($data) || !isset($data["error"]) || !str_starts_with($data["error"], "错误：")) {
        fwrite(STDERR, "Invalid AJAX error response.\n");
        exit(1);
    }
' "$test_dir/error.json"

curl --silent --show-error --fail \
    --request POST \
    --data 'form_submitted=1&length=16&lowercase=on&uppercase=on&numbers=on&symbols=on' \
    --output "$test_dir/form.html" \
    "$base_url"

php -r '
    $html = file_get_contents($argv[1]);
    if (!preg_match("~<textarea[^>]*id=\"result\"[^>]*>([^<]*)</textarea>~", $html, $matches)) {
        fwrite(STDERR, "Server-rendered password was not found.\n");
        exit(1);
    }
    $password = html_entity_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    if (strlen($password) !== 16) {
        fwrite(STDERR, "Server-rendered password has an invalid length.\n");
        exit(1);
    }
' "$test_dir/form.html"

echo "HTTP integration tests passed."
