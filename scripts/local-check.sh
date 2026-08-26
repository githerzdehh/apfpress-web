#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")/.."

if docker compose version >/dev/null 2>&1; then
    APF_COMPOSE=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
    APF_COMPOSE=(docker-compose)
else
    echo "Docker Compose is not installed." >&2
    exit 1
fi

if ! docker info >/dev/null 2>&1; then
    echo "Docker Engine is not running or is not accessible." >&2
    exit 1
fi

"${APF_COMPOSE[@]}" run --rm --no-deps vite npm run build
"${APF_COMPOSE[@]}" run --rm --no-deps vite npm test
"${APF_COMPOSE[@]}" run --rm app php artisan test
"${APF_COMPOSE[@]}" run --rm app php artisan route:list --except-vendor

curl --fail --silent --show-error http://localhost:8080/up >/dev/null
curl --fail --silent --show-error http://localhost:8080/api/v1/catalog >/dev/null

APF_VITE_PORT="$(sed -n 's/^VITE_DEV_PORT=//p' .env | tail -n 1 | tr -d '\"\r')"
APF_VITE_PORT="${APF_VITE_PORT:-5174}"
APF_APP_ORIGIN="$(sed -n 's/^VITE_APP_ORIGIN=//p' .env | tail -n 1 | tr -d '\"\r')"
APF_APP_ORIGIN="${APF_APP_ORIGIN:-http://localhost:8080}"
APF_CORS_HEADERS="$(mktemp)"
trap 'rm -f "$APF_CORS_HEADERS"' EXIT

curl --fail --silent --show-error \
    -D "$APF_CORS_HEADERS" \
    -o /dev/null \
    -H "Origin: ${APF_APP_ORIGIN}" \
    "http://localhost:${APF_VITE_PORT}/@vite/client"

if ! grep -Fq "Access-Control-Allow-Origin: ${APF_APP_ORIGIN}" "$APF_CORS_HEADERS"; then
    echo "Vite did not authorize the Laravel application origin." >&2
    exit 1
fi

echo "Build, automated tests, asset delivery, health endpoint, and catalogue API all passed."
