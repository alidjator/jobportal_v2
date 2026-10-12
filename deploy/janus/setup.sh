#!/bin/sh
# Prepares the host for the Janus stack; it never starts anything. Run it as root (it creates a directory for the application user), then fill the
# one value it cannot know (JANUS_PUBLIC_IP) in janus.env. Safe to run again: an existing janus.env is left alone.
set -eu

cd "$(dirname "$0")"
APP_USER=${APP_USER:-jobportal}
DIR=${RECORDINGS_HOST_DIR:-/var/lib/jobportal/janus-recordings}

install -d -o "$APP_USER" -g "$(id -gn "$APP_USER")" -m 2770 "$DIR"

if [ -e janus.env ]; then
    ln -sf janus.env .env
    echo "janus.env exists, left unchanged"
    exit 0
fi

secret() { openssl rand -hex 32; }

sed \
    -e "s|^APP_UID=.*|APP_UID=$(id -u "$APP_USER")|" \
    -e "s|^APP_GID=.*|APP_GID=$(id -g "$APP_USER")|" \
    -e "s|^RECORDINGS_HOST_DIR=.*|RECORDINGS_HOST_DIR=$DIR|" \
    -e "s|^JANUS_ADMIN_SECRET=.*|JANUS_ADMIN_SECRET=$(secret)|" \
    -e "s|^JANUS_API_TOKEN=.*|JANUS_API_TOKEN=$(secret)|" \
    -e "s|^JANUS_WEBHOOK_SECRET=.*|JANUS_WEBHOOK_SECRET=$(secret)|" \
    -e "s|^TURN_SECRET=.*|TURN_SECRET=$(secret)|" \
    janus.env.example > janus.env
chown "$APP_USER": janus.env
chmod 600 janus.env
ln -sf janus.env .env

echo "janus.env created. Set JANUS_PUBLIC_IP in it, then copy the secrets to the application's .env (README, step 6)."
