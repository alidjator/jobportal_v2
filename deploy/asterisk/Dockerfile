# Asterisk 22 LTS for the job portal phone interviews (docs/design/phase-7-tele-interview.md, form 3): ARI for the application, WebRTC (WSS) for the team's
# browsers, PJSIP for the SIP trunk. Debian 12 only ships Asterisk 20, so this builds the 22 LTS source. ASTERISK_VERSION is a release file name under
# https://downloads.asterisk.org/pub/telephony/asterisk/ ("asterisk-22-current" follows the newest 22.x; pin a release such as "asterisk-22.5.2" once one is chosen).

# Stage 1: placeholder Indonesian prompts, spoken by espeak-ng, so the stack works before real recordings exist (put your own wav files in ./sounds, see README).
FROM debian:12-slim AS sounds
RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends espeak-ng ffmpeg \
    && rm -rf /var/lib/apt/lists/*
COPY sounds/prompts.txt /prompts.txt
COPY sounds/make-prompts.sh /make-prompts.sh
RUN sh /make-prompts.sh /prompts.txt /out

# Stage 2: Asterisk.
FROM debian:12-slim

ARG ASTERISK_VERSION=asterisk-22-current

# Runtime libraries are installed by name (and so kept when the build tools go); the -dev packages and compilers are removed again after the build.
RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        ca-certificates curl wget gettext-base tini \
        libedit2 libxml2 libsqlite3-0 libssl3 libuuid1 libjansson4 libsrtp2-1 liburiparser1 libcap2 libxslt1.1 zlib1g \
        build-essential pkg-config autoconf automake libtool bzip2 xz-utils patch python3 \
        libedit-dev libxml2-dev libsqlite3-dev libssl-dev uuid-dev libjansson-dev libsrtp2-dev liburiparser-dev libcap-dev libxslt1-dev zlib1g-dev \
    && cd /usr/src \
    && wget -q "https://downloads.asterisk.org/pub/telephony/asterisk/${ASTERISK_VERSION}.tar.gz" \
    && mkdir asterisk && tar -xzf "${ASTERISK_VERSION}.tar.gz" -C asterisk --strip-components=1 \
    && cd asterisk \
    && ./configure --prefix=/usr --sysconfdir=/etc --localstatedir=/var --with-jansson-bundled --with-pjproject-bundled \
    && make menuselect.makeopts \
    && menuselect/menuselect \
        --disable BUILD_NATIVE \
        --disable-category MENUSELECT_CORE_SOUNDS --disable-category MENUSELECT_MOH --disable-category MENUSELECT_EXTRA_SOUNDS \
        --disable chan_sip --disable chan_iax2 --disable res_fax --disable res_calendar --disable res_stir_shaken \
        menuselect.makeopts \
    && make -j"$(nproc)" \
    && make install \
    && cd / && rm -rf /usr/src/asterisk* \
    && apt-get purge -y --auto-remove build-essential pkg-config autoconf automake libtool patch wget \
        libedit-dev libxml2-dev libsqlite3-dev libssl-dev uuid-dev libjansson-dev libsrtp2-dev liburiparser-dev libcap-dev libxslt1-dev zlib1g-dev \
    && rm -rf /var/lib/apt/lists/* /etc/asterisk/* \
    && mkdir -p /etc/asterisk-templates /opt/sounds-default /opt/sounds-custom /var/lib/asterisk/sounds/jobportal /var/spool/asterisk/recording

COPY config/ /etc/asterisk-templates/
COPY --from=sounds /out/ /opt/sounds-default/
COPY entrypoint.sh /opt/entrypoint.sh
RUN chmod +x /opt/entrypoint.sh

ENTRYPOINT ["/usr/bin/tini", "--", "/opt/entrypoint.sh"]
