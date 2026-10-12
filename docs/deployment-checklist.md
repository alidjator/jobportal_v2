# Go-live deployment checklist

One list for the day the API goes to production (architecture review finding 13, decided 2026-10-11). Every item is a thing **the user** does or approves (root, the real database, `.env` secrets); nothing here is run by a coding session on its own. Tick an item only after checking it on the server. Status as of 2026-10-11 is in `docs/HANDOFF.md`; this file does not repeat it.

Why it is mandatory: until the items in sections 2 and 3 are done, the hardening of the security review and the performance audit is **not active**. The rate limiter on a file cache is not atomic (throttles can be passed under concurrency), and `QUEUE_CONNECTION=sync` runs queued work (erasure listeners, bulk sends, recommendation batches) inside the HTTP request.

## 1. Server and PHP

- [ ] PHP 8.3 with `pdo_pgsql` (application), `pdo_mysql` (legacy import only), `pcntl` (queue workers), `redis` not needed (`predis` is a composer package).
- [ ] PHP `upload_max_filesize` and `post_max_size` at least 12 MB (5 files of 5 MB per chat message; the largest single upload is a company banner, default 5 MB, setting limit 10 MB).
- [ ] nginx `client_max_body_size 12m` (already in `/etc/nginx/conf.d/api-karir.satu.solutions.conf`; keep it in the production config).
- [ ] PostgreSQL 16 with the `pg_trgm` and `unaccent` extensions; the application role can run migrations.
- [ ] Redis reachable from the app (`REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`).
- [ ] `storage/` and `bootstrap/cache` writable by `jobportal` (POSIX ACLs are not in git); artisan is always run as `jobportal`, never as root.
- [ ] `composer install --no-dev --optimize-autoloader` on the release.

## 2. `.env` (secrets only here; never commit or paste them)

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` set, `APP_URL` is the public API URL.
- [ ] `QUEUE_CONNECTION=database` (**not** `sync`) and `QUEUE_BULK_RETRY_AFTER` left at its default unless the `bulk` timeout changes (it must stay above the `--timeout=540` of the bulk worker).
- [ ] `CACHE_STORE=redis` and `REDIS_CLIENT=predis` (the rate limiter, the settings cache and the review summaries need an atomic store).
- [ ] `CORS_ALLOWED_ORIGINS` = the front-end origin(s), comma separated (empty refuses every browser; the user has to give the origin).
- [ ] `API_DOCS_ENABLED=false` in production (the docs route is served only when `true`); `API_RATE_LIMIT_USER` and `API_RATE_LIMIT_GUEST` checked.
- [ ] Mail: `MAIL_MAILER` (not `log`), `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`.
- [ ] Public link templates: `COMPANY_INVITATION_URL`, `CAMPAIGN_REGISTRATION_URL` (`https://<client site>/{code}/register`), `NEWSLETTER_CONFIRM_URL`, `NEWSLETTER_UNSUBSCRIBE_URL`.
- [ ] Training (Moodle): `MOODLE_BASE_URL`, `MOODLE_TOKEN`, `MOODLE_COURSE_URL`.
- [ ] Video interviews (Janus and coturn; the container files are in `deploy/janus/`, install steps in its README): `JANUS_HTTP_URL`, `JANUS_ADMIN_URL`, `JANUS_ADMIN_SECRET`, `JANUS_API_TOKEN` (a token the Janus start script adds for the VideoRoom plugin), `JANUS_WS_URL`, `JANUS_WEBHOOK_SECRET`, `TURN_URLS`, `TURN_SECRET`; then the admin setting `interview.video_enabled`. The scheduler runs `recruitment:close-video-rooms` every minute.
- [ ] Recording of video interviews: `JANUS_CONVERTER_URL` (the converter service next to Janus), `JANUS_RECORDINGS_DIR` (the volume shared with Janus as the Janus container sees it) and `JANUS_RECORDINGS_PATH` (the same volume as the application sees it, mounted read-write so it can delete raw files); the queue worker must run for the `default` and `bulk` queues; the webhook routes `/api/v1/internal/janus/*` must not be reachable from outside (`deploy/janus/nginx/`: a loopback-only server block for Janus, a 404 for `/api/v1/internal/` on the public site, and the `/janus-ws` WebSocket proxy). The scheduler runs `recruitment:purge-recordings` daily.
- [ ] Phone interviews (Asterisk, see `docs/design/phase-7-tele-interview.md` form 3): `.env` values `ASTERISK_ARI_URL`, `ASTERISK_ARI_USER`, `ASTERISK_ARI_PASSWORD`, `ASTERISK_WSS_URL`, `ASTERISK_SIP_DOMAIN`, `ASTERISK_TRUNK_ENDPOINT` and `VOICE_CALLER_ID`; a Supervisor program runs `php artisan interview:voice-listen` (without it no call is recorded and no browser reaches the bridge); the scheduler runs `recruitment:close-voice-sessions` every minute; recorded calls need the sound `jobportal/recording-announcement` in the Asterisk sound library and a queue worker on the `bulk` queue (it fetches the audio); the Asterisk container is in `deploy/asterisk/` (its README has the install steps and the firewall ports: UDP 10000-10200 open, UDP 5060 to the provider only), the listener's Supervisor program in `deploy/supervisor/jobportal-voice-listener.ini`, and the nginx `/voice-ws` snippet in `deploy/asterisk/nginx/`.
- [ ] Video answers of skill tests: nginx `client_max_body_size 12m` already covers the 5 MB chunks; the scheduler runs `assessment:purge-expired-videos` daily and `assessment:purge-stale-video-chunks` hourly; the private disk needs room for `assessment.max_video_megabytes` (100 MB) per video answer for `assessment.video_retention_days` (90) days.
- [ ] Janus host setup: open UDP 20000-20200 (Janus media), 3478 (UDP and TCP) and UDP 50000-50500 (coturn) in the firewall; coturn runs in the same compose file (the host `turnserver` service stays disabled); check free memory before raising the 900 MB container limit.
- [ ] Sign-in with Google: `GOOGLE_CLIENT_IDS`.
- [ ] Reverb (only when chat goes real time, section 4): `BROADCAST_CONNECTION=reverb`, `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` (random strings you generate), `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`, `REVERB_ALLOWED_ORIGINS`.
- [ ] No `trustProxies` setting: production is served straight by nginx, no proxy or CDN (user, 2026-10-10). Revisit if a proxy or CDN is added.

## 3. Database, in this order

- [ ] Back up the real database before the first migration run.
- [ ] `php artisan migrate --force` brings the real `jobportal_v2` from `000110` to the latest migration (000111 admin second factor, 000112 foreign keys and candidate indexes, 000113 `jobalert_alerts.keywords`, 000114 `identity_erasure_receipts`; ask before each run against the real tables; check `php artisan migrate:status` first).
- [ ] `php artisan db:seed` (reference data), `php artisan identity:sync-permissions` (after every deployment that adds a permission).
- [ ] `php artisan identity:create-admin`; the admin enrols the authenticator at the first sign-in (second factor is mandatory for admins).
- [ ] Grant `cv_templates.manage` to the admins who manage the CV templates.
- [ ] The legacy import, only after the import rehearsal and the freeze date: dry run `php artisan legacy:import --all` (reads and reports only), then ask before `--write`; mapping files `--map=masters=<path>` and `--map=groups=<path>`; `KARIR_PKP_FILES_ROOT` set; the `legacy` connection (MariaDB, read-only account) configured.
- [ ] After the import: `php artisan analytics:rollup --backfill`.
- [ ] Password reset emails for the accounts that have no password (list in `docs/HANDOFF.md`): dry run first, ask before sending, only after SMTP works.

## 4. Processes

- [ ] Scheduler: the `jobportal` crontab has `* * * * * cd /usr/local/src/jobportal && /usr/bin/php artisan schedule:run >> /dev/null 2>&1`.
- [ ] Queue workers: copy `deploy/supervisor/jobportal-queue.ini` to `/etc/supervisord.d/service-available/` and link it from `service-enabled`, then `supervisorctl reread && supervisorctl update`. It runs `critical,default` (2 processes) and `bulk` on connection `bulk` with `--timeout=540` (1 process). Check `supervisorctl status` shows all three running.
- [ ] After every deploy: `php artisan queue:restart` so the workers load the new code.
- [ ] Reverb (finding 19: a removed member or revoked token keeps receiving chat events until the connection drops; accepted by the user, `architecture.md` 2.13; confirm it on the running server): run `php artisan reverb:start` under a process manager as `jobportal`; chat works over plain REST (clients poll) until then, and the recruiter shows offline.
- [ ] nginx: proxy the WebSocket path to Reverb (`Upgrade` and `Connection` headers, long `proxy_read_timeout`), TLS on the public name.
- [ ] Caches on the release: `php artisan config:cache`, `route:cache`, `event:cache`; clear them first when a deploy changes `.env`.

## 5. nginx and the edge

- [ ] HSTS stays in nginx; the other security `add_header` lines are removed (the application sends `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` and `Content-Security-Policy`; a header sent twice is invalid in some browsers). Done on the dev site 2026-10-10; repeat on production.
- [ ] Consider nginx `limit_req` for unknown paths (the application rate limiter covers only the `api` group).
- [ ] The live config is root-owned and not in git (a copy is in `/home/jobportal/nginx/`); keep the two in step.

## 6. Smoke test after the switch

- [ ] `GET /up` answers 200; a public list endpoint answers 200 with the security headers; `/docs/api` answers 404.
- [ ] Sign in as the admin with the second factor; sign in as a company member and a job seeker.
- [ ] Trigger one queued action (for example an application email) and see it processed by the worker, not in the request.
- [ ] Hit a throttled endpoint past its limit and see 429 (proves the Redis limiter).
- [ ] Send a test email; open the unsubscribe and confirm links.
- [ ] `php artisan schedule:list` shows the nightly tasks; the next morning check the log for failed erasure steps (`identity:reconcile-erasure`) and failed jobs (`php artisan queue:failed`).

## 7. Not code, but blocking

- [ ] Legal review answer (`docs/legal-review.md`); an admin then decides the setting `job_seeker.candidate_sensitive_filters_enabled` (off by default).
- [ ] `docs/analysis/legacy-security-report.md` (S1 to S3, critical) delivered to the maintainers of the running karir.pkp.
- [ ] Front-end origin and the invitation page URL given by the user.
