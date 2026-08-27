#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")/.."

if docker compose version >/dev/null 2>&1; then
    APF_COMPOSE=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
    APF_COMPOSE=(docker-compose)
else
    echo "Docker Compose is not installed. Install Docker Desktop or the Compose plugin first." >&2
    exit 1
fi

if ! docker info >/dev/null 2>&1; then
    echo "Docker Engine is not running or the current user cannot access it." >&2
    echo "Start Docker Desktop, or start the Docker service and then rerun this script." >&2
    exit 1
fi

if [[ ! -f .env ]]; then
    cp .env.example .env
fi

"${APF_COMPOSE[@]}" build app
"${APF_COMPOSE[@]}" up -d mysql mailpit
"${APF_COMPOSE[@]}" run --rm app composer install
"${APF_COMPOSE[@]}" run --rm --no-deps vite npm ci

if ! grep -Eq '^APP_KEY=.+$' .env; then
    "${APF_COMPOSE[@]}" run --rm app php artisan key:generate
fi

"${APF_COMPOSE[@]}" run --rm app php artisan migrate --seed
"${APF_COMPOSE[@]}" run --rm app php artisan storage:link || true
"${APF_COMPOSE[@]}" up -d app vite

APF_VITE_PORT="$(sed -n 's/^VITE_DEV_PORT=//p' .env | tail -n 1 | tr -d '\"\r')"
APF_VITE_PORT="${APF_VITE_PORT:-5174}"
APF_VITE_READY=false

for _ in $(seq 1 30); do
    if curl --fail --silent "http://localhost:${APF_VITE_PORT}/@vite/client" >/dev/null 2>&1; then
        APF_VITE_READY=true
        break
    fi
    sleep 1
done

if [[ "$APF_VITE_READY" != true ]]; then
    echo "Vite did not become ready; falling back to compiled frontend assets." >&2
    rm -f public/hot
    "${APF_COMPOSE[@]}" run --rm --no-deps vite npm run build
fi

echo
echo "APF Press is ready:"
echo "  Website: http://localhost:8080"
echo "  Admin:   http://localhost:8080/admin"
echo "  Mailpit: http://localhost:8025"
echo
echo "Local owner: owner@apfpress.test / ChangeMe!12345"
