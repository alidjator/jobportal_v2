# Janus (VideoRoom) plus the recording converter for the job portal video interviews (docs/design/phase-7-tele-interview.md, form 1).
# One container: Janus serves WebRTC, the converter turns the raw tracks of a recording into one file. Debian 12 ships Janus 1.1 and janus-pp-rec.
FROM debian:12-slim

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        janus janus-tools ffmpeg python3 gettext-base curl ca-certificates tini \
    && rm -rf /var/lib/apt/lists/*

COPY config/ /etc/janus-templates/
COPY converter/converter.py /opt/converter/converter.py
COPY entrypoint.sh /opt/entrypoint.sh
RUN chmod +x /opt/entrypoint.sh

ENTRYPOINT ["/usr/bin/tini", "--", "/opt/entrypoint.sh"]
