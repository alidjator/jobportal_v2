#!/bin/sh
# Prepares the host for the Asterisk container; it never starts anything. Run it as root (it creates a directory for the application user), then fill the
# values it cannot know in asterisk.env (ASTERISK_PUBLIC_IP and the trunk). Safe to run again: an existing asterisk.env is left alone.
set -eu

cd "$(dirname "$0")"
APP_USER=${APP_USER:-jobportal}
DIR=${RECORDINGS_HOST_DIR:-/var/lib/jobportal/asterisk-recordings}

install -d -o "$APP_USER" -g "$(id -gn "$APP_USER")" -m 2770 "$DIR"

if [ -e asterisk.env ]; then
    ln -sf asterisk.env .env
    echo "asterisk.env exists, left unchanged"
    exit 0
fi

sed \
    -e "s|^APP_UID=.*|APP_UID=$(id -u "$APP_USER")|" \
    -e "s|^APP_GID=.*|APP_GID=$(id -g "$APP_USER")|" \
    -e "s|^RECORDINGS_HOST_DIR=.*|RECORDINGS_HOST_DIR=$DIR|" \
    -e "s|^ARI_PASSWORD=.*|ARI_PASSWORD=$(openssl rand -hex 32)|" \
    asterisk.env.example > asterisk.env
chown "$APP_USER": asterisk.env
chmod 600 asterisk.env
ln -sf asterisk.env .env

echo "asterisk.env created. Set ASTERISK_PUBLIC_IP and the TRUNK_* values in it, then copy ARI_USER and ARI_PASSWORD to the application's .env (README, step 5)."
