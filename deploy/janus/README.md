# Janus video server (tele-interview form 1)

Design: `docs/design/phase-7-tele-interview.md`. `docker-compose.yml` runs two containers on the host network: `janus` (VideoRoom plugin and the recording converter) and `coturn` (TURN relay); the host nginx fronts the WebSocket and the webhooks.

| Piece | Listens on | Reachable from |
|---|---|---|
| Janus HTTP API | `127.0.0.1:18088/janus` | the application (`JANUS_HTTP_URL`) |
| Janus Admin API | `127.0.0.1:7088/admin` | the application (`JANUS_ADMIN_URL`) |
| Janus WebSocket | `127.0.0.1:8188` | browsers, through nginx `wss://<host>/janus-ws` (`JANUS_WS_URL`) |
| Converter | `127.0.0.1:18090/convert` | the application (`JANUS_CONVERTER_URL`) |
| Janus media | UDP `20000-20200` | the internet (open in the firewall) |
| coturn | `3478` UDP/TCP, relay UDP `50000-50500` (no TLS) | the internet (open in the firewall) |
| Application webhooks | `127.0.0.1:18089/api/v1/internal/janus/*` (nginx) | Janus and the converter only |

## Install (root for `setup.sh` and nginx, a docker-group user for compose)

1. As root, from this directory: `./setup.sh` (creates the recordings directory for the application user and `janus.env` with random secrets; it starts nothing). Then set `JANUS_PUBLIC_IP` in `janus.env`.
2. `systemctl disable --now coturn` if the host package is running (the container takes its port).
3. `docker compose up -d --build` (Compose reads `janus.env` through the `.env` symlink that `setup.sh` makes; without it every compose command needs `--env-file janus.env`); `docker compose logs janus` must show `API token registered`, and `docker compose logs coturn` no errors.
4. nginx: copy `nginx/janus-internal.conf` to `/etc/nginx/conf.d/`, add `nginx/api-karir.satu.solutions.janus.snippet` to the TLS server block, `nginx -t && systemctl reload nginx`.
5. Open the firewall ports above (UDP 20000-20200, 3478 UDP and TCP, UDP 50000-50500).
6. Application `.env`: `JANUS_HTTP_URL=http://127.0.0.1:18088/janus`, `JANUS_ADMIN_URL=http://127.0.0.1:7088/admin`, `JANUS_ADMIN_SECRET`, `JANUS_API_TOKEN`, `JANUS_WS_URL=wss://api-karir.satu.solutions/janus-ws`, `JANUS_WEBHOOK_SECRET`, `JANUS_CONVERTER_URL=http://127.0.0.1:18090/convert`, `JANUS_RECORDINGS_DIR=/recordings`, `JANUS_RECORDINGS_PATH=/var/lib/jobportal/janus-recordings`, `TURN_URLS=turn:<public host>:3478?transport=udp,turn:<public host>:3478?transport=tcp`, `TURN_SECRET` (same as in `janus.env`); `php artisan config:cache`.
7. Admin setting `interview.video_enabled` = true.

## Checks after the first start (the shapes below were written from the Janus documentation and could not be run in development)

- `curl -s http://127.0.0.1:18088/janus/info` answers JSON with the VideoRoom plugin; a request without `token` is refused (`token_auth`).
- Join a test interview from two browsers: the application log must show `joined` events accepted at `internal/janus/events`, and `GET company/interviews/{id}/attendance` must list both users. If it lists nobody, compare the event body in `/var/log/nginx/janus-internal-access.log` or the application log with `RecordJanusEvents` (`session_id`, microsecond `timestamp`, `event.data.{event,room,display}`).
- The browser must use the `display` value of the join credentials as its display name (attendance counts only signed names).
- Record two minutes: `ls -l <JANUS_RECORDINGS_PATH>/raw/<id>` shows `.mjr` files; the file name pattern is `...-<timestamp>-audio.mjr` and `...-video.mjr` (`converter.py` reads the timestamp from the name to align late joiners; if the pattern differs, tracks start together). Then `ready/<id>.webm` appears and the application stores it and clears both folders.
- Memory: `docker stats` during a recording conversion; the container limit is 900 MB.
