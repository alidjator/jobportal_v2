# Go-live deployment checklist

One list for the day the API goes to production (architecture review finding 13, decided 2026-10-11). Every item is a thing **the user** does or approves (root, the real database, `.env` secrets); nothing here is run by a coding session on its own. Tick an item only after checking it on the server. The current status is in `docs/HANDOFF.md`; this file does not repeat it.

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
- [ ] Public link templates: `COMPANY_INVITATION_URL`, `CAMPAIGN_REGISTRATION_URL` (`https://<client site>/{code}/register`), `NEWSLETTER_CONFIRM_URL`, `NEWSLETTER_UNSUBSCRIBE_URL`, `JOB_SEEKER_PROFILE_URL` (the profile page linked from the profile reminder email; empty means no link).
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
- [ ] `php artisan migrate --force` brings the real `jobportal_v2` to the latest migration (`000135` on 2026-10-13; `000134` and `000135` widen the history text columns and the GPA for the legacy import); ask before each run against the real tables; check `php artisan migrate:status` first. Migrate **before** the new code serves traffic: the scheduler fails whenever code that needs a new migration is live first (as on 2026-10-12).
- [ ] `php artisan db:seed` (reference data), `php artisan identity:sync-permissions` (after every deployment that adds a permission).
- [ ] `php artisan identity:create-admin`; the admin enrols the authenticator at the first sign-in (second factor is mandatory for admins).
- [ ] Grant `cv_templates.manage` to the admins who manage the CV templates.
- [ ] Verification document types: create at least one required type (`POST admin/company-document-types`) before the companies go live. With none, nobody holds the verification badge and, with `jobs.approval_for_unverified` on (the default), **every job waits for an admin's review**; the review queue (`GET admin/jobs?filter[status]=pending_review`) shows the flagged ones first. Review the list in `jobs.suspicious_phrases` and `jobs.report_hold_threshold` (3 distinct reporters hold a job) in the admin settings.
- [ ] The legacy import, only after the freeze date (not set yet) and with a **new copy** of the karir.pkp database and of its folder taken at the freeze (the 2026-10-13 copy was for the rehearsal). Rehearsal done 2026-10-13 on PostgreSQL (`jobportal_v2_rehearsal`, report in its scratch storage): everything imports as it is, with no count limits; what is skipped is a row without a required value or a child of a row not imported. Steps: `KARIR_PKP_FILES_ROOT` set to the copy; the `legacy` connection (MariaDB, read-only account) configured; **all legacy avatar and resume files present under that root** (the rehearsal copy lacked 964 avatars and 3 resumes, which were skipped); dry run `php artisan legacy:import --all` (reads and reports only), compare it with the rehearsal report; then ask before `--write`, run as `jobportal` (never root) with `--map=masters=<path>` (`/home/jobportal/import/masters.draft.json`, approved) and `--map=groups=<path>`; the import adds only and never updates, so a second run creates no duplicates.
- [ ] After the import: `php artisan analytics:rollup --backfill`; read the printed report: unmatched masters, skipped reasons and notes (`created` masters: skills, job roles, categories, languages).
- [ ] After the import: `php artisan recruitment:build-application-facets` builds the search facets of the imported applications (`phase-8-applicant-search.md` section 1; the import writes applications directly, so they have none). Until it runs, imported applications match no profile filter and no keyword other than the name. Safe to run again.
- [ ] Languages created by the import carry a **placeholder code** made from their name (the legacy list has no codes); set ISO codes by hand where they are needed.
- [ ] Legacy data is kept as it is, so some migrated profiles exceed the rules of the new API (skills over 30, links that are not https, a GPA over 4, long texts); editing them is allowed as long as the list does not grow and a text or GPA is not changed (`phase-1-migration.md`). Jobs incomplete at import are `draft`; applications of an unmapped group sit in the default stage.
- [ ] Activation emails to the imported accounts (replaces the old reset-code mail; `phase-8-activation.md` sections 2 and 3). The email carries a link, never a code. Before the first batch: SMTP and its bounce webhook parser registered (so bounces and complaints feed the suppression list); settings `platform.support_email` (and optionally `platform.support_whatsapp`), `identity.activation_job_seekers_not_before`; `.env` `JOB_SEEKER_ACTIVATION_URL`, `COMPANY_ACTIVATION_URL`, `JOB_SEEKER_SIGN_IN_URL`. Staff test first: `php artisan identity:send-activation-emails --audience=job_seeker --emails=<file> --write`. Then company owners (`--audience=company`), then job seekers: a pilot (`--pilot --limit=200`), then `--limit=500` a day; every run is a dry run unless `--write`; a run stops by itself when the previous batch bounced above 2% or drew complaints above 0.1%. One reminder after 7 days: `--reminders`. Progress at any time: `php artisan identity:activation-report` (or `GET admin/activation/report`, page D29, and the card on D2); the account fixes of the support team are `PUT admin/users/{id}/email`, `POST admin/users/{id}/reset-two-factor` and the three resend actions (permission `users.manage`, which `identity:sync-permissions` gives to `super_admin`).

## 4. Processes

- [ ] Scheduler: the `jobportal` crontab has `* * * * * cd /usr/local/src/jobportal && /usr/bin/php artisan schedule:run >> /dev/null 2>&1`. Set the admin setting `platform.operations_email` (alerts when a task is late or failing) and point an outside uptime monitor at `GET /api/v1/health/scheduler` (200 while the every-minute heartbeat is under 5 minutes old, 503 otherwise).
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
- [ ] Sign in as the admin with the second factor; sign in as a company member and a job seeker. A company admin enrols the authenticator at the first sign-in (second factor is mandatory for company admins); a lone company admin who loses the device is reset by `php artisan identity:reset-user-two-factor <email>`.
- [ ] Trigger one queued action (for example an application email) and see it processed by the worker, not in the request.
- [ ] Hit a throttled endpoint past its limit and see 429 (proves the Redis limiter).
- [ ] Send a test email; open the unsubscribe and confirm links.
- [ ] `php artisan schedule:list` shows the nightly tasks (among them `company:purge-name-change-documents` at 03:30, which deletes the proof of a name change 90 days after its decision); the next morning check the log for failed erasure steps (`identity:reconcile-erasure`) and failed jobs (`php artisan queue:failed`).

## 7. Not code, but blocking

- [ ] Legal review answer (`docs/legal-review.md`); an admin then decides the setting `job_seeker.candidate_sensitive_filters_enabled` (off by default).
- [ ] `docs/analysis/legacy-security-report.md` (S1 to S3, critical) delivered to the maintainers of the running karir.pkp.
- [ ] Front-end origin and the invitation page URL given by the user.
- [ ] Client IP behind the Nuxt proxy decided (`docs/design/frontend.md`, section 5.2, open points): without a trusted proxy every visitor shares the Nuxt server's IP in rate limits, audit and consent records.
- [x] Brand name decided 2026-10-15, changed 2026-10-16: **ONESIS ERP** (One Solutions Integrated System) and **ONESIS Career** (job seeker site), `docs/design/naming.md`.
- [ ] `APP_NAME="ONESIS ERP"` in the production `.env`; the problem `type` renamed to `urn:onesis:error:<code>` (coding session, `naming.md` section 1) before go-live.
- [ ] Infrastructure names changed from `jobportal` to `onesis` together with separating the live site from the working folder (`naming.md` section 3).
- [ ] Contact details for the public site (email, phone, address, WhatsApp if any) given by the user (`docs/design/frontend.md` 11.6).
- [ ] Not blocking go-live, start early: Meta Business verification, a WhatsApp Business number and approval of the templates listed in
  `docs/design/phase-8-whatsapp.md` section 3 (`id` and `en`), for the WhatsApp notices coded after go-live (#33).
- [ ] Admin roles other than `super_admin` created and given (`identity:create-role`, `identity:set-admin-role`, `docs/design/phase-8-admin-console.md`); at least two active `super_admin` accounts so one can recover the other.
