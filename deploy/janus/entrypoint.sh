#!/bin/bash
# Renders the Janus configuration from the templates and the environment, starts the converter, starts Janus,
# and adds the application's token to the VideoRoom plugin through the Admin API once Janus answers (tokens live in memory, so each start needs it).
set -eu

CONF=/tmp/janus
mkdir -p "$CONF" /recordings/raw /recordings/ready /recordings/work

for template in /etc/janus-templates/*.jcfg; do
    envsubst '${JANUS_PUBLIC_IP} ${JANUS_ADMIN_SECRET} ${JANUS_WEBHOOK_SECRET} ${APP_WEBHOOK_BASE} ${STUN_SERVER} ${STUN_PORT}' < "$template" > "$CONF/$(basename "$template")"
done

umask 002
python3 /opt/converter/converter.py &
CONVERTER_PID=$!

janus -C "$CONF/janus.jcfg" -F "$CONF" &
JANUS_PID=$!

(
    for _ in $(seq 1 60); do
        if curl -fsS http://127.0.0.1:18088/janus/info >/dev/null 2>&1; then
            curl -fsS -X POST http://127.0.0.1:7088/admin \
                -H 'Content-Type: application/json' \
                -d "{\"janus\":\"add_token\",\"transaction\":\"boot\",\"admin_secret\":\"${JANUS_ADMIN_SECRET}\",\"token\":\"${JANUS_API_TOKEN}\",\"plugins\":[\"janus.plugin.videoroom\"]}" >/dev/null \
                && echo "API token registered" && exit 0
        fi
        sleep 1
    done
    echo "Could not register the API token" >&2
    kill "$JANUS_PID"
) &

# Either process dying takes the container down, so Docker restarts it and the failure is visible.
wait -n "$JANUS_PID" "$CONVERTER_PID"
kill "$JANUS_PID" "$CONVERTER_PID" 2>/dev/null || true
exit 1
