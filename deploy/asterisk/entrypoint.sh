#!/bin/bash
# Renders the Asterisk configuration from the templates and the environment (into the tmpfs at /etc/asterisk), puts the prompts where Asterisk plays them from,
# starts a cleanup of old recordings, and runs Asterisk in the foreground. The container is read-only: only the tmpfs mounts and the recordings directory are written.
set -eu

: "${ASTERISK_PUBLIC_IP:?set ASTERISK_PUBLIC_IP in asterisk.env}"
: "${ARI_USER:?}" "${ARI_PASSWORD:?}"
: "${TRUNK_HOST:?set TRUNK_HOST in asterisk.env}" "${TRUNK_MATCH:?set TRUNK_MATCH in asterisk.env}"
export TRUNK_PORT="${TRUNK_PORT:-5060}"

CONF=/etc/asterisk
VARS='${ASTERISK_PUBLIC_IP} ${ARI_USER} ${ARI_PASSWORD} ${STUN_SERVER} ${STUN_PORT} ${TRUNK_HOST} ${TRUNK_PORT} ${TRUNK_MATCH} ${TRUNK_USERNAME} ${TRUNK_PASSWORD}'

# The configuration holds the ARI password and the trunk secret, so it lives in memory only and is readable by the Asterisk user alone.
umask 077
for template in /etc/asterisk-templates/*.conf; do
    envsubst "$VARS" < "$template" > "$CONF/$(basename "$template")"
done

# The trunk either registers with the provider (user name and password) or is recognised by its address (`TRUNK_AUTH=ip`).
if [ "${TRUNK_AUTH:-register}" = "register" ]; then
    : "${TRUNK_USERNAME:?set TRUNK_USERNAME and TRUNK_PASSWORD in asterisk.env, or TRUNK_AUTH=ip}" "${TRUNK_PASSWORD:?}"
    envsubst "$VARS" < /etc/asterisk-templates/trunk/register.conf > "$CONF/pjsip.trunk-auth.conf"
    echo 'outbound_auth=trunk' > "$CONF/pjsip.trunk-endpoint.conf"
else
    : > "$CONF/pjsip.trunk-auth.conf"
    : > "$CONF/pjsip.trunk-endpoint.conf"
fi

# Prompts: the generated placeholders first, then any recorded file the operator mounted over them.
SOUNDS=/var/lib/asterisk/sounds/jobportal
cp /opt/sounds-default/*.wav "$SOUNDS"/
for custom in /opt/sounds-custom/*.wav; do
    [ -e "$custom" ] && cp "$custom" "$SOUNDS"/
done

# A recording is fetched by the application a moment after the call and then deleted through ARI; what is still here after a day was never fetched.
(
    while sleep 3600; do
        find /var/spool/asterisk/recording -type f -mmin +1440 -delete
    done
) &

umask 002
exec asterisk -f -C "$CONF/asterisk.conf"
