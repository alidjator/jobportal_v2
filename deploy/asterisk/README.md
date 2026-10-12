# Asterisk voice server (tele-interview form 3)

Design: `docs/design/phase-7-tele-interview.md`. One container on the host network runs Asterisk 22 LTS: the application drives it through ARI, the team's browsers register on it over WebSocket (WebRTC), and the candidate's phone is reached through a SIP trunk. Calls are one-way masked: the candidate sees only the platform number (`VOICE_CALLER_ID`).

| Piece | Listens on | Reachable from |
|---|---|---|
| ARI (`/ari`) and the browsers' WebSocket (`/ws`), one HTTP server | `127.0.0.1:8088` | the application (`ASTERISK_ARI_URL`); nginx passes only `/voice-ws` to `/ws` |
| SIP trunk | UDP `5060` | the provider's signalling addresses only (firewall) |
| Media (trunk and browsers) | UDP `10000-10200` | the internet (browsers can be anywhere) |

Needs from you before calls work: a licensed SIP trunk (the provider's SIP server, how it authenticates, its signalling network) and one number (DID) to show as the caller. Those values go in `asterisk.env` and in the application's `VOICE_CALLER_ID`.

## Install (root for `setup.sh` and nginx, a docker-group user for compose)

1. As root, from this directory: `./setup.sh` (creates the recordings directory for the application user and `asterisk.env` with a random ARI password; it starts nothing). Then set `ASTERISK_PUBLIC_IP` and the `TRUNK_*` values in `asterisk.env`.
2. `docker compose up -d --build`. The first build compiles Asterisk and takes several minutes (it downloads the 22 LTS source and pjproject; set `ASTERISK_VERSION` in the Dockerfile to pin a release). `docker compose logs asterisk` must show `Asterisk Ready.` and, with `TRUNK_AUTH=register`, a successful registration of `trunk`.
3. nginx: add `nginx/api-karir.satu.solutions.asterisk.snippet` to the TLS server block, `nginx -t && systemctl reload nginx`.
4. Firewall: UDP `10000-10200` open to everyone; UDP `5060` open to the provider's signalling addresses only.
5. Application `.env`: `ASTERISK_ARI_URL=http://127.0.0.1:8088/ari`, `ASTERISK_ARI_USER` and `ASTERISK_ARI_PASSWORD` (the values of `ARI_USER` and `ARI_PASSWORD` in `asterisk.env`), `ASTERISK_WSS_URL=wss://api-karir.satu.solutions/voice-ws`, `ASTERISK_TRUNK_ENDPOINT=trunk`, `VOICE_CALLER_ID=<the DID in +62 form>`, and the `TURN_URLS` and `TURN_SECRET` that the Janus stack already uses (browsers use the same coturn relay); then `php artisan config:cache`.
6. Supervisor: copy `../supervisor/jobportal-voice-listener.ini` (one process; without it no call event is handled), `supervisorctl reread && supervisorctl update`. The queue worker for the `bulk` queue must be running too: it fetches the call recordings.
7. Admin setting `interview.voice_enabled` = true.

## Prompts

The container plays two files from `jobportal/`: `recording-announcement` ("this call is recorded", played to everyone in the bridge when a recording starts) and `call-back-info` (what a candidate hears who calls the platform number: it only places calls). The image generates machine-voice placeholders from `sounds/prompts.txt` (Indonesian, espeak-ng). Replace them with recorded files: put `recording-announcement.wav` and `call-back-info.wav` (8 kHz, mono, 16-bit PCM) in `sounds/custom/` and restart the container; a file there wins over the placeholder. Check the wording with whoever answers for UU PDP.

## Recordings

Asterisk records the bridge into `RECORDINGS_HOST_DIR` as `interview-recording-<id>.wav`. The application fetches the file through ARI, stores it on its private disk and deletes it through ARI. The container also deletes anything older than a day every hour, so files of failed fetches do not pile up.

## What differs from a usual Asterisk

- The container is read-only; the configuration is rendered from `config/` into a tmpfs at every start (`entrypoint.sh`), so secrets live in memory only.
- `modules.conf` and the menuselect line in the `Dockerfile` leave out what phone interviews never use. There is no Opus (not in the open-source build); both legs use G.711, so a call is never transcoded.
- Browser accounts are PJSIP objects the application writes through the ARI dynamic configuration into the internal database (`sorcery.conf`, `astdb` wizard, in memory here). They vanish at a restart: the team clicks join again and the application issues a fresh account.
- The dialplan has two contexts and no way to dial out: browsers reach `interview-<id>` (handed to the application, which checks that the account belongs to that interview), the provider reaches the message-only context. Outbound calls exist only through ARI.

## Checks after the first start (written from the Asterisk documentation, not run in development)

- `curl -s -u "$ARI_USER:$ARI_PASSWORD" http://127.0.0.1:8088/ari/asterisk/info` answers JSON. From outside, `https://api-karir.satu.solutions/ari/...` must be 404.
- The ARI dynamic configuration works: `curl -s -u ... -X PUT -H 'Content-Type: application/json' -d '{"fields":[{"attribute":"auth_type","value":"userpass"},{"attribute":"username","value":"probe"},{"attribute":"password","value":"probe-password-123"}]}' http://127.0.0.1:8088/ari/asterisk/config/dynamic/res_pjsip/auth/probe` answers 200; read it back with a GET on the same URL, then DELETE it. If the PUT answers an error about the object type, `sorcery.conf` is not applied: check `docker compose logs asterisk`.
- Join a test interview (`POST .../voice/join`), register the browser with the returned account, dial the extension, and call the candidate's number. A call that never connects: the `Dial` shape is `PJSIP/<+62 number>@trunk`; if the provider wants the number as `62...` or `0...`, that is a one-line change in `AsteriskAriGateway::dial()`.
- An answered call enters the bridge (the application log shows the `StasisStart` of the candidate); `TIMEOUT(absolute)` hangs it up after the maximum length; a recording starts with the announcement and the file shows up in the interview's recordings a few seconds after it stops.
- Memory: `docker stats` during a call; the limit is 384 MB.
