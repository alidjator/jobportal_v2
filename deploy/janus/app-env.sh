#!/bin/sh
# Prints the application's .env lines for the video interviews, read from janus.env (it changes nothing).
# Append them yourself:  ./app-env.sh >> ../../.env   then   php artisan config:cache
set -eu

cd "$(dirname "$0")"
value() { sed -n "s/^$1=//p" janus.env | head -1; }
HOST=${API_HOST:-api-karir.satu.solutions}

cat <<ENV

# Video interviews (deploy/janus)
JANUS_HTTP_URL=http://127.0.0.1:18088/janus
JANUS_ADMIN_URL=http://127.0.0.1:7088/admin
JANUS_ADMIN_SECRET=$(value JANUS_ADMIN_SECRET)
JANUS_API_TOKEN=$(value JANUS_API_TOKEN)
JANUS_WS_URL=wss://$HOST/janus-ws
JANUS_WEBHOOK_SECRET=$(value JANUS_WEBHOOK_SECRET)
JANUS_CONVERTER_URL=http://127.0.0.1:18090/convert
JANUS_RECORDINGS_DIR=/recordings
JANUS_RECORDINGS_PATH=$(value RECORDINGS_HOST_DIR)
TURN_URLS=turn:$HOST:3478?transport=udp,turn:$HOST:3478?transport=tcp
TURN_SECRET=$(value TURN_SECRET)
ENV
