#!/usr/bin/env bash

set -Eeuo pipefail

readonly APF_APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
readonly APF_PHP_BINARY="${APF_PHP_BINARY:-/opt/cpanel/ea-php83/root/usr/bin/php}"
readonly APF_COMPOSER_BINARY="${APF_COMPOSER_BINARY:-/usr/local/bin/composer}"
readonly APF_PUBLIC_ROOT="${APF_PUBLIC_ROOT:-/home/apfpress/public_html}"
readonly APF_PUBLIC_MARKER=".apfpress-deploy-root"
readonly APF_PUBLIC_MARKER_VALUE="APF_PRESS_PRODUCTION"
readonly APF_DEPLOY_TARGET_FILE="${APF_APP_ROOT}/.deploy-target"
APF_MAINTENANCE_ENABLED=false

fail() {
    echo "Deployment error: $*" >&2
    exit 1
}

check_url() {
    local url="$1"

    for attempt in {1..5}; do
        if curl --fail --silent --show-error --max-time 20 "$url" >/dev/null; then
            return 0
        fi
        if [[ "$attempt" -lt 5 ]]; then
            sleep 2
        fi
    done

    fail "post-deployment health check failed for ${url}"
}

env_value() {
    local key="$1"
    local value

    value="$(sed -n "s/^${key}=//p" "${APF_APP_ROOT}/.env" | tail -n 1 | tr -d '\r')"
    value="${value#\"}"
    value="${value%\"}"
    printf '%s' "$value"
}

on_error() {
    local exit_code=$?

    if [[ "$APF_MAINTENANCE_ENABLED" == true ]]; then
        echo "Deployment failed while Laravel was in maintenance mode; it has intentionally been left down for diagnosis." >&2
    else
        echo "Deployment failed before Laravel entered maintenance mode." >&2
    fi

    exit "$exit_code"
}

trap on_error ERR

cd "$APF_APP_ROOT"

[[ "$(id -un)" == "apfpress" ]] || fail "run the deployment as the apfpress cPanel user"
[[ -x "$APF_PHP_BINARY" ]] || fail "PHP 8.3 was not found at ${APF_PHP_BINARY}"
[[ -r "$APF_COMPOSER_BINARY" ]] || fail "Composer was not found at ${APF_COMPOSER_BINARY}"
command -v curl >/dev/null 2>&1 || fail "curl is required for post-deployment health checks"
[[ -f .env ]] || fail "create the server-only .env file before deploying"

APF_DEPLOY_TARGET="staging"
if [[ -f "$APF_DEPLOY_TARGET_FILE" ]]; then
    APF_DEPLOY_TARGET="$(tr -d '[:space:]' < "$APF_DEPLOY_TARGET_FILE")"
fi

case "$APF_DEPLOY_TARGET" in
    staging|production) ;;
    *) fail ".deploy-target must contain either staging or production" ;;
esac

[[ "$(env_value APP_DEBUG)" == "false" ]] || fail "APP_DEBUG must be false on cPanel"
[[ -n "$(env_value APP_KEY)" ]] || fail "APP_KEY must be generated before deploying"

if [[ "$APF_DEPLOY_TARGET" == "production" ]]; then
    [[ "$(env_value APP_ENV)" == "production" ]] || fail "production deployment requires APP_ENV=production"
    [[ "$(env_value APP_URL)" == "https://apfpress.com" ]] || fail "production deployment requires APP_URL=https://apfpress.com"
    [[ "$(env_value DB_CONNECTION)" == "mysql" ]] || fail "production deployment requires DB_CONNECTION=mysql"
    [[ -n "$(env_value DB_HOST)" ]] || fail "production deployment requires DB_HOST"
    [[ -n "$(env_value DB_DATABASE)" ]] || fail "production deployment requires DB_DATABASE"
    [[ -n "$(env_value DB_USERNAME)" ]] || fail "production deployment requires DB_USERNAME"
    [[ -n "$(env_value DB_PASSWORD)" ]] || fail "production deployment requires DB_PASSWORD"
    [[ "$(env_value SESSION_SECURE_COOKIE)" == "true" ]] || fail "production deployment requires SESSION_SECURE_COOKIE=true"
    [[ -z "$(env_value APF_OWNER_PASSWORD)" ]] || fail "remove APF_OWNER_PASSWORD from .env after the production seed"
    [[ "$APF_PUBLIC_ROOT" == "/home/apfpress/public_html" ]] || fail "refusing an unexpected production public root"
    [[ -d "$APF_PUBLIC_ROOT" ]] || fail "production public root does not exist"
    [[ -f "${APF_PUBLIC_ROOT}/${APF_PUBLIC_MARKER}" ]] || fail "production public root is not armed; create ${APF_PUBLIC_ROOT}/${APF_PUBLIC_MARKER} after archiving WordPress"
    [[ "$(tr -d '\r\n' < "${APF_PUBLIC_ROOT}/${APF_PUBLIC_MARKER}")" == "$APF_PUBLIC_MARKER_VALUE" ]] || fail "production public-root marker has the wrong value"
    command -v rsync >/dev/null 2>&1 || fail "rsync is required for the production public sync"
fi

if [[ -f vendor/autoload.php ]]; then
    "$APF_PHP_BINARY" artisan down --retry=30
    APF_MAINTENANCE_ENABLED=true
fi

"$APF_PHP_BINARY" "$APF_COMPOSER_BINARY" install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

if [[ "$APF_MAINTENANCE_ENABLED" == false ]]; then
    "$APF_PHP_BINARY" artisan down --retry=30
    APF_MAINTENANCE_ENABLED=true
fi

"$APF_PHP_BINARY" artisan migrate --force
"$APF_PHP_BINARY" artisan storage:link --force
"$APF_PHP_BINARY" artisan optimize
"$APF_PHP_BINARY" artisan queue:restart

if [[ "$APF_DEPLOY_TARGET" == "production" ]]; then
    rsync --archive --delete-delay \
        --exclude="${APF_PUBLIC_MARKER}" \
        --exclude='.well-known/' \
        --exclude='cgi-bin/' \
        "${APF_APP_ROOT}/public/" \
        "${APF_PUBLIC_ROOT}/"
    install -m 0644 "${APF_APP_ROOT}/deploy/cpanel/public-index.php" "${APF_PUBLIC_ROOT}/index.php"
fi

"$APF_PHP_BINARY" artisan up
APF_MAINTENANCE_ENABLED=false

APF_APP_URL="$(env_value APP_URL)"
APF_APP_URL="${APF_APP_URL%/}"
check_url "${APF_APP_URL}/up"
check_url "${APF_APP_URL}/api/v1/catalog"

trap - ERR

echo "APF Press ${APF_DEPLOY_TARGET} deployment completed successfully."
