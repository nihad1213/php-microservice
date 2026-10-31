#!/usr/bin/env bash
set -e
cd "$(dirname "$0")/.."

SERVICES="gateway auth tasks notifications"

OWNER="$(id -u):$(id -g)"
if [ "$(id -u)" -eq 0 ] && [ -n "${SUDO_UID:-}" ]; then
    OWNER="${SUDO_UID}:${SUDO_GID}"
fi

if docker info >/dev/null 2>&1 || [ "$(id -u)" -eq 0 ]; then
    DOCKER="docker"
else
    DOCKER="sudo docker"
fi

fix_owner() {
    if [ -n "$(find services ! -user "${OWNER%%:*}" -print -quit)" ]; then
        if [ "$(id -u)" -eq 0 ]; then
            chown -R "$OWNER" services
        else
            sudo chown -R "$OWNER" services
        fi
    fi
}

fix_owner

for service in $SERVICES; do
    mkdir -p "services/$service/storage/logs"
    chmod -R 777 "services/$service/storage"
    if [ ! -f "services/$service/.env" ]; then
        cp "services/$service/.env.example" "services/$service/.env"
    fi
done

$DOCKER compose build

for service in $SERVICES; do
    $DOCKER compose run --rm --no-deps \
        --user "$OWNER" -e COMPOSER_HOME=/tmp/composer \
        "$service" composer install --no-interaction
done

fix_owner

echo "Setup finished. Start everything with: $DOCKER compose up"