# Phase 7: Tele-interview (form 1 designed 2026-10-11, forms 2 and 3 designed 2026-10-12)

## Decisions

One line per decision; the detail sections below win if they disagree.

- 2026-10-11: all four forms, built in this order: (1) Zoom, Google Meet and Microsoft Teams integration, (2) recorded one-way video, (3) live video inside the platform, (4) voice calls through Asterisk with a licensed SIP trunk and masked numbers (Decided).
- 2026-10-12: form 2 part 1 (authoring, recording consent, decline) coded (see "Form 2 part 1 coded").
- 2026-10-12: form 1 part 4 (deployment files in `deploy/janus/`) coded: one container on the host network, internal webhooks on a loopback-only nginx server (see "Form 1 part 4 coded").
- 2026-10-11: recording is optional per interview and starts only after the candidate's explicit consent, with a retention period and permanent deletion (Decided).
- 2026-10-11: the one-way video is a new question type in the assessment engine (Answers, 1).
- 2026-10-11: **Zoom, Google Meet and Microsoft Teams integration is dropped**; live video runs on a self-hosted Janus WebRTC server and becomes the first form. New order: (1) live video on Janus, (2) one-way video, (3) voice through Asterisk (Answers, 2). This replaces the first line above; answers 3 and 4 lapse.
- 2026-10-11: a video interview has a panel: the main interviewer plus other members who may see the job, at most 4 from the company in all, and the candidate (Form 1 answers, 1). Widened 2026-10-12: the panel applies to every interview format and its table comes with interview kits part 1 (`phase-7-interview-kits.md`).
- 2026-10-11: join tokens are valid from 10 minutes before the start to 30 minutes after the scheduled end, for confirmed interviews only; the candidate waits in a lobby until a team member admits them (Form 1 answers, 2).
- 2026-10-12: recordings are converted by a small converter service in the Janus container that the API calls (and that posts the result to the webhook); attendance is read from display names signed by the API; the interview panel and the company admins may delete a recording by hand (Form 1 part 3 answers).
- 2026-10-11: the recruiter marks an interview as recorded; the candidate consents or refuses when confirming and may withdraw until recording starts; a team member starts recording by hand with an indicator for everyone; refusing does not cancel the interview (Form 1 answers, 3).
- 2026-10-11: recordings are kept 90 days after the interview (admin setting), then deleted permanently by a scheduled task; also deleted by application anonymisation or by hand by the team (Form 1 answers, 4).
- 2026-10-11: Janus runs on the same server as the API, in its own container (Form 1 answers, 5).
- 2026-10-11: the lobby is signalled through a private Reverb channel per interview with id-only events, and REST polling as a fallback (Form 1 answers, 6).
- 2026-10-11: attendance (join and leave per participant) is recorded from Janus event-handler events posted to an internal webhook with a shared secret; nothing marks the interview completed automatically (Form 1 answers, 7).
- 2026-10-11: recordings are converted and mixed inside the Janus container, which then calls an API webhook; a queued job moves the file to the private disk and deletes the raw files (Form 1 answers, 8).
- 2026-10-11: capacity on the shared server: memory and CPU limits on the Janus container, one conversion at a time at low priority, an admin setting for the maximum of video calls at once (409 when full); measure in the trial and add memory before production if needed (Form 1 answers, 9).
- 2026-10-11: the form 1 schema, endpoints and four coding parts are approved; coding starts in a new session with part 1 (Form 1 schema).
- 2026-10-12: video questions are allowed in skill tests only (company and library), not in batteries (Form 2 answers, 1).
- 2026-10-12: video answers are kept 90 days after submission (admin setting `assessment.video_retention_days`), then the file is deleted permanently and the score stays; application anonymisation deletes them earlier (Form 2 answers, 2).
- 2026-10-12: a test with video questions needs a separate recording consent to start; the candidate may decline the invitation (new status `declined`, the team is told in-app) (Form 2 answers, 3).
- 2026-10-12: video files are uploaded in 5 MB chunks and joined on the server; the total size is capped by a setting (default 100 MB) (Form 2 answers, 4).
- 2026-10-12: the last take is the answer; a take the candidate rejects is deleted from the server at once (Form 2 answers, 5).
- 2026-10-12: a practice question runs in the browser only and is never uploaded (Form 2 answers, 6).
- 2026-10-12: the team only streams video answers: no download and no share links (Form 2 answers, 7).
- 2026-10-12: no AI transcription or scoring now (Form 2 answers, 8).
- 2026-10-12: no deadline extension requests; the team re-sends the test after expiry (Form 2 answers, 9).
- 2026-10-12: the length of a take is bounded by server time only (answer time plus a 2-minute upload grace); the file's own duration is not read (Form 2 answers, 10).
- 2026-10-12: the form 2 schema, endpoints and three coding parts are approved; a `video` section keeps a typed duration checked at publish (Form 2 schema).
- 2026-10-12: masking is one-way: the candidate only ever sees the platform's number, never the team member's; the candidate's number stays visible to the company as today. This narrows "both numbers masked" (Form 3 answers, 1).
- 2026-10-12: the number is normalised to E.164 (a leading 0 becomes +62) and the candidate confirms the number to call when confirming a voice interview (it may differ from the profile, for that interview only); no OTP (Form 3 answers, 2).
- 2026-10-12: the candidate takes the call on an ordinary phone (PSTN) only; a candidate who prefers the browser uses a Janus video interview (Form 3 answers, 3).
- 2026-10-12: a call to the platform number plays a recorded message and nothing is routed to the team; the candidate gets an in-app "missed call" notice after an unanswered call (Form 3 answers, 4).
- 2026-10-12: team members join only from the browser (WebRTC to Asterisk); their own numbers are never stored; a panel of at most 4 company participants as for video (Form 3 answers, 5).
- 2026-10-12: Asterisk 22 LTS runs on the same server in its own container with CPU and memory limits, driven by the API through ARI on a private network (Form 3 answers, 6).
- 2026-10-12: voice recording works as for video (consent at confirm, started by hand), and Asterisk plays "this call is recorded" to the candidate when it starts; audio on the private disk, same retention setting, streaming only (Form 3 answers, 7).
- 2026-10-12: the number of call attempts per interview is an admin setting (Form 3 answers, 8).
- 2026-10-12: caps: a maximum length per call and call minutes per company per month, both admin settings; Indonesian numbers (+62) only (Form 3 answers, 9).
- 2026-10-12: a candidate confirming a video interview may give a fallback number; inside the window the team may switch to a voice call to it without rescheduling (Form 3 answers, 10).
- 2026-10-12: the form 3 schema, endpoints and four coding parts are approved, with the setting defaults (3 attempts, 60 minutes per call, 600 minutes per company per month, 5 calls at once), one platform number and the `phrity/websocket` package for the ARI listener; coded after form 1 (Form 3 schema).
- 2026-10-12: form 1 part 1 (scheduling) coded. Answers 1 to 4 on its interpretations: a proposal carries the recording answer, a new video time keeps the flag, `interview.video_enabled` may be overridden per company, and withdrawing a consent notifies the team in-app ("Form 1 part 1 coded"). Interpretation 5 (emails) confirmed as coded.
- 2026-10-12: form 1 part 2 (Janus gateway, rooms, tokens, lobby, closing) coded. Answers: only the interviewer and the panel join, the candidate counts as waiting for 60 seconds after the last join or poll, rooms close when the interview ends its window or is no longer confirmed, and joining is refused (409 `video-disabled`) once video is switched off. Other interpretations in "Form 1 part 2 coded".

Source: `docs/analysis/benchmark-teleinterview-assessment.md` (section 2, practices TI1 to TI10) and `decisions.md` ("assessments reopen R8"). Billing is out of scope.

## Decided (2026-10-11, user)

- **Four forms, in this order:**
  1. Integration with Zoom, Google Meet and Microsoft Teams: a unique meeting link per interview (TI1).
  2. Recorded one-way video (TI7 to TI10).
  3. Live video inside the platform (TI2 to TI4).
  4. Voice calls through Asterisk. Calls reach ordinary phone numbers only through a **licensed SIP trunk provider**; both numbers are masked (TI5).
- **Recording** of calls and video is optional per interview. It needs the candidate's **explicit consent** before it starts (UU PDP), and has a retention period and permanent deletion.

## Answers (2026-10-11, user)

1. **One-way video:** a new question type ("video answer") in the assessment engine of phase 6. It reuses the invitation by stage move, the due time, the reminder, consent, the server timer, assessor scoring and erasure; it adds think time, a re-record limit and video storage.
2. **Video engine:** the user said "saya akan menggunakan janus self hosted untuk engine video conference". Clarified the same day: **Janus replaces the Zoom, Meet and Teams integration**, which is dropped; live video on Janus is built first, then the one-way video, then voice. Consent for recording applies in Janus and recordings are kept on the platform's private disk. A link typed by hand (`online`, `meeting_url`) stays as it is.
3. ~~Lapsed with answer 2.~~ **Meeting creation:** a single schedule creates the meeting in the request, before the transaction; a provider failure is returned to the recruiter at once. A bulk move queues one job per slot; the interview carries a link status `pending`, `ready` or `failed`, and the candidate's email goes out once the link is ready.
4. ~~Lapsed with answer 2.~~ **Recording in an external meeting:** a recording flag per interview; the candidate consents in the platform first, and only then is the meeting created with automatic cloud recording. The recording stays in the provider account, so the platform cannot enforce its retention or deletion; this limit is stated to the company.

## Starting point in v2

- `recruitment_interviews` (`phase-2-interviews.md`): one active schedule per application, `format` `online` or `onsite`, a free `meeting_url` (500 characters), one `interviewer_id`, statuses `pending`, `confirmed`, `declined`, `cancelled`, `completed`; the job seeker confirms, declines or proposes another time (at most 2); bulk slots through `InterviewSlotPlanner`; a reminder 24 hours before; emails to the job seeker only, in-app to the team.
- The assessment engine (`phase-6-assessments.md`) already has invitations sent by a stage move, a due time with expiry and reminder, consent, a server timer, assessor scoring and erasure through application anonymisation.
- Files: one private disk, served only through endpoints that check access or temporary signed URLs (`architecture.md` 2.7). Reverb is configured but not deployed (`architecture.md` 2.13). No SMS or WhatsApp channel.

## Proposed model (to be confirmed point by point)

### Form 1: live video on Janus

- `InterviewFormat` gains `video` (inside the platform); `online` with a typed `meeting_url` and `onsite` stay.
- Janus runs the **VideoRoom** plugin (SFU). The API creates a room per interview through the Janus HTTP API with a random id and pin, and destroys it after the interview; the browser talks to Janus over WSS only with a short-lived join token that an access-checked endpoint issues (Janus `token_auth`, tokens added and removed through the Admin API). The Janus Admin API is never exposed publicly.
- ICE servers (STUN and a TURN server such as coturn, with time-limited credentials) are returned by the same endpoint; the device check (TI2) is a front-end task.
- Recording: Janus writes `.mjr` files per stream; a queued job converts and mixes them (`janus-pp-rec`, `ffmpeg`) into one file on the private disk, served only through an endpoint that checks access; the raw files are then deleted.
- Fallback to a phone call (TI4) comes with the voice form.

### Form 1 answers (2026-10-11, user)

1. **Participants:** a panel. The main interviewer (`interviewer_id`) stays one person; the scheduler may add other company members who may see the job, **at most 4 company participants in all**, plus the candidate. This needs a participant table.
2. **Join window and lobby:** a join token is issued only for a `confirmed` video interview, from **10 minutes before** `starts_at` to **30 minutes after** `ends_at`. The candidate waits in a **lobby** until a company participant admits them (Indeed, Spark Hire).
3. **Recording consent:** the recruiter marks the interview as "recorded" when scheduling. The candidate **consents or refuses when confirming** the schedule and may **withdraw** consent until recording starts. Recording is started by hand by a company participant, and every participant sees an indicator. Refusing recording does not cancel the interview; it is held without recording.
4. **Retention:** **90 days** after the interview (admin setting), then a scheduled task deletes the recording permanently. Application anonymisation deletes it earlier, and the team may delete it by hand.

5. **Infrastructure:** Janus runs on **the same server as the API, in its own container**. Server facts checked 2026-10-11: Docker is installed (the `jobportal` user has no access to its socket), `turnserver` (coturn) is installed on the host but the `coturn` service is inactive, `ffmpeg` and `janus-pp-rec` are not on the host; 8 cores and 7 GB of memory, about 1 GB available.
6. **Lobby signal:** a private Reverb channel per interview; events carry only ids and the lobby state, and the client fetches its join token over REST (architecture review finding 19). REST polling of the lobby state is the fallback when the socket is down.
7. **Attendance:** recorded. Janus posts join and leave events (its HTTP event handler) to an internal webhook guarded by a shared secret; the API stores the join and leave times per participant. Nothing marks an interview completed automatically.

8. **Recording conversion:** inside the Janus container. Its image includes `janus-pp-rec` and `ffmpeg`; when a recording stops, a script in the container converts and mixes the streams into one file on a volume shared with the host, then calls an API webhook. A queued API job moves the file to the private disk and deletes the raw and mixed files from the volume. The API never needs Docker access.

9. **Capacity (finding, decided):** the server is shared with PHP-FPM, PostgreSQL, Redis and the queue workers, and a panel of five sends about 20 streams per room. The Janus container gets memory and CPU limits; conversions run one at a time under `nice`; an admin setting caps the video calls running at once (a join that would open one more room is refused with 409). Load is measured in the trial, and memory is added before production if needed.

### Form 1 schema, endpoints and parts (approved by the user 2026-10-11)

**Schema** (migrations in `Recruitment`):

- `recruitment_interviews` gains `recorded` (boolean, default false), `recording_consent` (`granted`, `refused`, `withdrawn`, null until the candidate answers) with `recording_consent_at`, `video_room_id` (Janus room number, null until the first join), `admitted_at` and `admitted_by`. The CHECK on `format` adds `video`; a `video` interview needs neither `location` nor `meeting_url`.
- `recruitment_interview_participants`: `interview_id`, `user_id` (FK `identity_users`, RESTRICT), unique pair. Holds the extra panel members only (at most 3; the main interviewer stays in `interviewer_id`). Created by interview kits part 1 for every format; form 1 reuses it.
- `recruitment_interview_attendances`: `interview_id`, `user_id`, `role` (`company` or `candidate`), `joined_at`, `left_at`, `janus_session_id`. A log table: no foreign key on `user_id`.
- `recruitment_interview_recordings`: `interview_id`, `status` (`recording`, `processing`, `ready`, `failed`), `started_by`, `started_at`, `stopped_at`, `path` (random name on the private disk), `size_bytes`, `duration_seconds`, `expires_at`. Several per interview (start and stop more than once). Deleting a recording deletes the row and the file.

**Behaviour:**

- The Janus room is created **lazily** at the first join inside the window, with a random pin and secret (encrypted in the database), and destroyed by a scheduled task after the window ends. Each join token is added through the Janus Admin API for the VideoRoom plugin only and removed when the window ends.
- The candidate's join puts them in the lobby (Reverb event to the company participants); a company participant admits; the candidate then receives the token. Once admitted, a rejoin inside the same window skips the lobby.
- Recording starts only when `recorded` is true and `recording_consent` is `granted`; the start locks the consent (a withdrawal after that is refused with 409). Janus records each stream; when it stops, the container converts and calls the webhook; the job stores the file and sets `ready` with `expires_at` = interview end + the retention setting.
- Recordings are served by a streaming endpoint with HTTP range support, to company participants and members who may see the application. Retention task daily; anonymising the applicant deletes recordings and attendance (an `ErasureStep`).
- Notifications follow `phase-2-interviews.md`; the emails say the interview is held in the platform and whether it will be recorded.
- Settings: `interview.video_enabled` (default false), `interview.video_max_concurrent_rooms` (default 3), `interview.recording_retention_days` (default 90). `.env`: `JANUS_HTTP_URL`, `JANUS_ADMIN_URL`, `JANUS_ADMIN_SECRET`, `JANUS_WS_URL`, `JANUS_WEBHOOK_SECRET`, `TURN_URLS`, `TURN_SECRET` (time-limited TURN credentials, coturn `use-auth-secret`).

**Endpoints** (company side under `company/applications/{id}/interview`, job seeker side under `me/applications/{id}/interview`):

| Who | Endpoint | Purpose |
|---|---|---|
| Company | `PUT .../interview` | Gains `format: video`, `participant_ids[]` (at most 3), `recorded` |
| Company | `POST .../video/join` | Token, room, pin, `ws_url`, ICE servers; 409 `video-window-closed`, `video-capacity-full` |
| Company | `GET .../video/lobby`, `POST .../video/admit` | Lobby state, admit the candidate |
| Company | `POST .../video/recording/start`, `.../stop` | 409 `recording-not-allowed`, `recording-in-progress` |
| Company | `GET .../recordings`, `GET .../recordings/{id}/file`, `DELETE .../recordings/{id}` | List, stream, delete |
| Company | `GET .../attendance` | Join and leave times |
| Job seeker | `POST .../confirm` | Gains `recording_consent` (required when `recorded`) |
| Job seeker | `POST .../recording-consent/withdraw` | Until recording starts |
| Job seeker | `POST .../video/join`, `GET .../video/lobby` | Enter the lobby; the token once admitted |
| Janus | `POST /internal/janus/events`, `POST /internal/janus/recordings` | Shared secret; nginx accepts them only from the Docker bridge |

**Coding parts** (one fresh session each):

1. Scheduling (**coded 2026-10-12**): format `video`, panel participants (already there from interview kits part 1), `recorded` and consent (confirm, withdraw), settings, notifications.
2. Janus gateway (an Infrastructure HTTP client behind a port), lazy rooms, join tokens, ICE credentials, the lobby with Reverb events, the capacity cap, room cleanup (**coded 2026-10-12**).
3. Webhooks: attendance, recording start and stop, the conversion webhook and the storing job, recording endpoints, retention, erasure.
4. Deployment files in `deploy/janus/`: Dockerfile or compose with resource limits, Janus configuration (VideoRoom, `token_auth`, HTTP event handler), the conversion script, coturn configuration, the nginx WSS proxy, and the deployment checklist.

### Form 1 part 1 coded (2026-10-12)

Coded: format `video`, `recorded`, the recording answer (confirm, propose, withdraw), the three `interview.*` settings, emails, bulk move. Migration `000125` adds `recorded`, `recording_consent` and `recording_consent_at` to `recruitment_interviews` (the room, `admitted_*` columns and the other tables come with their parts). Interpretations to confirm:

1. **Proposing a time also answers the recording (decided 2026-10-12).** A recorded interview that the job seeker answers by proposing another time carries `recording_consent` (`granted` or `refused`, required): the team accepting the proposal confirms the interview, and without this the job seeker would never be asked.
2. **A new time keeps the flag (decided 2026-10-12).** A request without `recorded` keeps the flag of the video interview it replaces (a first interview is not recorded); a new time is a new interview, so the consent is asked again. Only a video interview can be marked recorded (`invalid-interview`, field `recorded`); the voice form widens this later.
3. **No video before the switch (decided 2026-10-12).** Scheduling a video interview, one by one or in bulk, is refused with 422 `video-not-enabled` while `interview.video_enabled` is off for the company. That setting may be overridden per company, so a trial can start with one company; the other two settings are global.
4. **Withdrawing tells the team (decided 2026-10-12).** `POST me/applications/{id}/interview/recording-consent/withdraw` works while the interview is pending or confirmed and the consent is `granted` (409 `no-recording-consent` otherwise); the interviewer and who scheduled it get the in-app notice `interview.recording_withdrawn` (no email to anyone). The lock after recording starts (409) comes with the recordings in part 3.
5. **Emails (decided 2026-10-12).** A new variable `recording` (`Yes, only if you agree` or `No`) and a line "Recorded" in the scheduled, rescheduled and reminder templates; `format` reads `Video in the platform` and `place` says to open the application to join. Templates an admin already edited keep their text.

### Form 1 part 2 coded (2026-10-12)

Migration `000126`: `recruitment_interviews` gains `video_room_id`, `video_room_pin` and `video_room_secret` (both encrypted), `video_room_closed_at`, `candidate_waiting_at`, `admitted_at` and `admitted_by` (FK `identity_users`, RESTRICT); table `recruitment_interview_video_tokens` (`interview_id`, `user_id`, FK RESTRICT) lists who was given a token. Endpoints: company `POST|GET .../interview/video/join|lobby` and `POST .../video/admit`, job seeker `POST|GET me/applications/{id}/interview/video/join|lobby`. Reverb: private channel `interview-video.{interviewId}`, event `LobbyChanged` with `interview_id` and `state` (`waiting` or `admitted`). Scheduled `recruitment:close-video-rooms` every minute. Decided 2026-10-12: answers 1 to 4 above. Interpretations to confirm:

1. **The API holds its own Janus token.** `token_auth` covers Janus's HTTP API too, so the API sends `JANUS_API_TOKEN` (new `.env` value, `services.janus.api_token`) with every HTTP API call; the Janus start script (part 4) must add it through the Admin API for the VideoRoom plugin each time Janus starts.
2. **Join tokens are derived, not random.** A token is `HMAC-SHA256(app key, interview, room, user)`: the same member gets the same token again on a rejoin, and closing the room removes the tokens of every user listed in `recruitment_interview_video_tokens`, so no token is stored. Each token is added to Janus through the Admin API for the VideoRoom plugin only.
3. **Room id, pin and secret.** The API picks a random room number (retrying when Janus says it exists), a 16-character pin and a 32-character secret. The pin goes to the browsers; the secret only destroys the room.
4. **Waiting.** A job seeker's join answers `200 {status: "waiting"}` without credentials until admitted, then `{status: "ready", credentials}`; the candidate's lobby poll is the heartbeat. Admitting needs a waiting candidate (409 `candidate-not-waiting`) and is harmless twice. Any join after the admission skips the lobby, and an admitted candidate may open the room if they are first.
5. **Capacity and failures.** The count of open rooms is checked under a PostgreSQL advisory lock, so two joins cannot take the last slot; 409 `video-capacity-full`. A room created in the request whose join then fails is destroyed again. A Janus failure is 503 `video-server-unavailable`; a room that cannot be closed stays open for the next run. A room of an interview whose applicant is anonymised (the interview row is deleted) is not found by the closing task; its tokens run out with the window and Janus drops the room at its next restart (part 3's erasure step may destroy it).
6. **Credentials are never cached** (`Cache-Control: no-store, private`) and are valid until the end of the join window; the TURN credentials expire then too.

### Form 1 part 3 coded (2026-10-12)

Migration `000127`: tables `recruitment_interview_attendances` (no foreign key on `user_id`, it is a log) and `recruitment_interview_recordings` (`started_by` FK `identity_users`, RESTRICT; both cascade with the interview). Endpoints: company `POST .../interview/video/recording/start|stop`, `GET company/interviews/{id}/recordings`, `GET .../recordings/{recordingId}/file` (HTTP range, `no-store`, inline), `DELETE .../recordings/{recordingId}`, `GET company/interviews/{id}/attendance`; internal `POST /api/v1/internal/janus/events|recordings` (shared secret `JANUS_WEBHOOK_SECRET` as bearer token or as the basic-auth password, no per-IP throttle). Reverb: event `RecordingChanged` (`interview_id`, `recording`) on `interview-video.{id}`. Scheduled `recruitment:purge-recordings` daily at 03:30. New `.env` values: `JANUS_CONVERTER_URL`, `JANUS_RECORDINGS_DIR`, `JANUS_RECORDINGS_PATH`. Decided 2026-10-12: the converter is called by the API; attendance uses signed display names; the panel and the company admins delete recordings (viewing, streaming, the list and attendance are open to everyone who may see the application). Interpretations to confirm:

1. **Volume layout for part 4.** Janus writes the raw tracks of recording `{id}` to `{JANUS_RECORDINGS_DIR}/raw/{id}` (set with `edit new_rec_dir` before `enable_recording`). The converter receives `POST JANUS_CONVERTER_URL` with `Authorization: Bearer <JANUS_WEBHOOK_SECRET>` and `{recording_id, directory}`, answers 2xx at once, writes `ready/{id}.webm` (or `.mp4`) next to `raw/` and then posts `{recording_id, status: ready|failed, file: "ready/{id}.webm", duration_seconds}` to `/internal/janus/recordings` with the same secret. The API mounts the same volume at `JANUS_RECORDINGS_PATH` read-write (it removes the raw tracks and the converted file after storing), so both containers must write files the API user may delete.
2. **Pipeline.** Stop (or the room closing, which stops a running recording first) marks the recording `processing` and queues `ConvertRecordingWhenStopped` (default queue, 3 tries); the webhook marks the file and queues `StoreRecordingWhenConverted` (the `bulk` queue, it may take minutes), which streams the file to `interview-recordings/{interviewId}/{random}.{ext}` and sets `ready` with `expires_at` = interview end + `interview.recording_retention_days` of the company. A recording that cannot be sent, converted or stored after the last retry, or that is still `processing` six hours after it stopped (daily task), becomes `failed` and has no file; failed rows stay in the list.
3. **Consent lock.** Once a recording row exists, withdrawing the consent is refused with 409 `recording-started` (the job seeker asks the company to delete the recording). Starting needs a confirmed video interview inside its window, `recorded`, consent `granted`, an open room (409 `video-room-not-open`) and no running recording (409 `recording-in-progress`); a failure of Janus rolls the new row back.
4. **Attendance.** `joined` and `leaving` events of the VideoRoom plugin are read; only a `joined` whose display name was signed by the API (`{userId}.{16 hex}`, returned as `display` in the join credentials, which the browser must pass as its display name) counts, so nobody can add themselves. The role is `candidate` when the user owns the application, else `company`. The shape of the Janus event (`session_id`, `timestamp` in microseconds, `event.data.{event,room,display}`) cannot be tested here and is checked against the real server in part 4.
5. **Erasure.** Anonymising the applicant deletes the recording files first (`EraseApplicantData`), then every recording and attendance row of their interviews (also when the interview stays for its scorecard). Not done: a Janus room of such an interview is left to Janus's restart.
6. **Deleting.** Only a finished recording (`ready` or `failed`) can be deleted (409 `recording-not-finished`); the file goes before the row, so a failed deletion can be repeated. A recording of another interview is 404.

### Form 1 part 4 coded (2026-10-12)

Janus is not installed on the server yet, so everything is new, in `deploy/janus/` (read its `README.md` for the install steps): `Dockerfile` (Debian 12 `janus`, `janus-tools`, `ffmpeg`, Python), `docker-compose.yml`, `entrypoint.sh`, `config/*.jcfg`, `converter/converter.py`, `nginx/*`, `setup.sh`; coturn is a second service in the same compose file (UDP and TCP, no TLS: the certificate key is root-only; `turns:` can be added later). Nothing here could be run in development (no Docker access), so the first start is a checklist in the README.

1. **Host network** (interpretation, the design said "Docker bridge"): WebRTC needs a wide UDP range and the public address, so the container uses `network_mode: host`; Janus HTTP, Admin and WebSocket and the converter bind to `127.0.0.1`. The internal webhooks are served by a separate nginx `server` on `127.0.0.1:18089` that exposes only `/api/v1/internal/janus/`; the public server answers 404 for `/api/v1/internal/`. This replaces the bridge-address rule.
2. **One container** for Janus and the converter (decision 8 of the design), limited to 900 MB and 3 CPUs; only 1 GB was free on 2026-10-11, so a conversion competes with the API for memory (one conversion at a time).
3. **Tokens:** `entrypoint.sh` registers `JANUS_API_TOKEN` for the VideoRoom plugin through the Admin API at every start (tokens are in memory).
4. **Conversion layout:** one WebM (VP8/Opus): video tiles side by side (640x360, 15 fps), audio mixed, late joiners delayed by the creation time in the `.mjr` file name. If the name carries no timestamp, tracks start together. Output goes to `ready/{id}.webm`; the working files in `work/{id}` are removed afterwards.
5. **Files are owned by the application user** (the container runs as its uid) so the queue worker can delete them.
6. **Unverified against a real server:** the Janus event shape, the `.mjr` file name pattern, `libjanus_*` plugin and transport file names to disable, and the package names on Debian 12.

### Form 2: one-way video (design started 2026-10-12)

Decided earlier: a new question type of the assessment engine (Answers, 1). Proposed model, to be confirmed point by point:

- A new section format **`video`** holding questions of the new type **`video_answer`**. Each question carries `think_seconds` (30 to 300, or unlimited), `answer_seconds` (30 to 300) and `max_retakes` (0 to 5), as at Spark Hire (TI7). The section has no typed duration: it is worked out from its questions, so the attempt's `ends_at` stays a server deadline. Navigation is forward only.
- The browser records with `MediaRecorder` and uploads; Janus is not involved. The server keeps the clock per question: opening a question starts the think time, a `recording-started` call starts the answer time, and a take must arrive before the answer time plus an upload grace. A limited think time that runs out starts the recording (the browser does it; the server counts from the end of the think time).
- The file type is checked from its content (`video/webm` or `video/mp4`) and stored as uploaded, without transcoding (the API host has no `ffmpeg`); the size is capped by a setting.
- Scoring as for `long_text`: only the assigned assessors give whole points from 0 to the question's points; the result stays `pending_scoring` until every answered video has a score.
- The team watches through a streaming endpoint with HTTP range support, visible to whoever may see the application.
- Notifications, invitation, due time, reminder and expiry are those of the assessment engine.

### Form 2 answers (2026-10-12, user)

1. **Tests:** skill tests only (company tests and the system library). Batteries take no video questions, because video feeds neither the norms nor the psychologist's report. Assessors of the company score them.
2. **Retention:** **90 days** after the attempt is submitted (admin setting `assessment.video_retention_days`, separate from `interview.recording_retention_days`); a scheduled task then deletes the file permanently and keeps the score. Application anonymisation deletes the files earlier.
3. **Refused consent:** a test that holds video questions needs a **separate recording consent** besides the test consent before it starts. The candidate may **decline** the invitation instead: a new invitation status `declined`, which frees the application for another invitation, and an in-app notice to the member who sent the test and its assessors, so the team can schedule a live interview.
4. **Upload:** **chunked**, 5 MB per request, joined on the server; nginx and PHP keep their 12 MB limit. The total per take is capped by a setting (default 100 MB).

5. **Retakes:** the candidate watches the take, then sends it or records again while retakes remain. A rejected take is **deleted from the server at once**. When the retakes run out or the question's time ends, the last take is the answer.
6. **Practice:** one free practice question that checks the camera and microphone and plays back **in the browser only**; nothing is uploaded. The API only supplies its text; the rest is front-end work.
7. **Team access:** **streaming only**, through an endpoint that checks access (members who may see the application). No download and no share links, so the 90-day deletion is not undone by copies.
8. **AI:** none now. Scoring is by human assessors only. AI transcription or scoring would need its own design (provider, consent, human oversight, TI10).

9. **Extensions:** none. As in the engine today, the team re-sends the test after it expires (`PUT /company/assessment/applications/{id}/invitation`).
10. **Length check:** by server time only. The server stamps the start of each take and refuses a chunk that arrives after the answer time plus a **2-minute upload grace**; the browser stops recording on time. The duration inside the file is not read (no `ffprobe` on the host); the size cap still applies.

### Form 2 schema, endpoints and parts (approved by the user 2026-10-12)

**Change to the first sketch:** a `video` section keeps a typed duration like the other formats (an unlimited think time cannot be summed), and publishing checks it is at least the sum of each question's limited think time plus its answer time × (retakes + 1). The section deadline still closes it.

**Schema** (migrations in `Assessment`):

- `assessment_sections.format` adds `video`; `assessment_questions.type` adds `video_answer` (allowed only in a `video` section of a skill test, `video-not-allowed` 422 elsewhere). Questions gain `think_seconds` (null = unlimited, else 30 to 300), `answer_seconds` (30 to 300) and `max_retakes` (0 to 5), required for `video_answer` and null otherwise (CHECK).
- `assessment_invitations.status` adds `declined`, with `declined_at`; the partial unique index (invited or started) is unchanged, so a declined application can take a new invitation.
- `assessment_attempts` gains `video_consented_at`; `assessment_attempt_questions` gains `opened_at` (start of the think time).
- `assessment_video_takes`: `attempt_id`, `question_id`, `take_no`, `status` (`recording`, `complete`, `final`), `recording_started_at`, `upload_deadline_at`, `chunks_received`, `size_bytes`, `mime_type`, `path` (random name on the private disk), `expires_at`, `file_deleted_at`. A rejected take is deleted (row and file). `assessment_answers` gains `video_take_id` for the final take, so assessor scoring works as for `long_text`.
- Chunks are kept under `assessment/video-chunks/{take}/` and joined into `assessment/videos/{attempt}/` when the take completes.

**Candidate endpoints** (`/me/assessment/invitations/{id}/`):

| Endpoint | Purpose |
|---|---|
| `POST start` | Gains `video_consent: true` when the test has video questions (`video-consent-required` 422) |
| `POST decline` | Only while `invited` (`invitation-not-open` 409); in-app `assessment.declined` to the sender and the assessors |
| `POST attempt/questions/{q}/video/takes` | Starts a take; answers the take id, the chunk size and the upload deadline (`retake-limit`, `question-time-over` 409) |
| `PUT .../video/takes/{take}/chunks/{n}` | One chunk of at most 5 MB, in order (`upload-deadline-passed`, `video-too-large` 409/422) |
| `POST .../video/takes/{take}/complete` | Joins the chunks, checks the type from the content (`invalid-video` 422) |
| `POST .../video/takes/{take}/submit`, `DELETE .../video/takes/{take}` | Makes the take the answer, or rejects and deletes it |

When the question's time ends or the attempt is submitted, the last complete take becomes the answer; a question without one counts as unanswered (0 points).

**Team:** `GET /company/assessment/invitations/{id}/videos/{questionId}` streams the final take with HTTP range support (no download header), for whoever may see the application; `GET scoring` and `PUT scoring/questions/{q}` cover video answers as they cover long text. A video question needs at least one assessor, as long text does.

**Settings and tasks:** `assessment.video_retention_days` (90), `assessment.max_video_megabytes` (100). `assessment:purge-expired-videos` (daily) deletes files past `expires_at` and keeps the score; `assessment:purge-stale-video-chunks` (hourly) deletes the chunks of takes left incomplete for more than a day. `EraseAssessmentData` also deletes the video files and takes.

**Coding parts** (one fresh session each):

1. Authoring and invitation: the section format and question type with their rules, the separate video consent, `decline` with its notice, the settings.
2. Recording: the clock per question, takes, chunked upload, joining and type check, retakes, choosing the final take at the end of the question or the submit.
3. Team and retention: streaming, scoring, the purge tasks and erasure.

### Form 2 part 1 coded (2026-10-12)

Migration `000128`: question columns `think_seconds`, `answer_seconds`, `max_retakes` (CHECK: set and in range for `video_answer`, empty for every other type), `assessment_attempts.video_consented_at`, and the check constraints of `assessment_questions.type` (`video_answer`), `assessment_sections.format` (`video`) and `assessment_invitations.status` (`declined`). Settings `assessment.video_retention_days` (90) and `assessment.max_video_megabytes` (100). Tests: `tests/Feature/Assessment/VideoAssessmentTest.php`.

1. **Declining reuses `closed_at`** (interpretation): the design listed a `declined_at` column, but cancelled and expired invitations already stamp `closed_at`, so a declined one does too and no extra column exists.
2. **A video section is forward only by force:** `allow_back` and `allow_jump` are stored false whatever the author sends; `allow_skip` is kept (a question without a take counts as unanswered). A video section takes no `draw_count` (every question is asked).
3. **Error codes:** `video-not-allowed` (422) for a video section in a battery, a video question in a battery or outside a video section, and any other question in a video section; `video-consent-required` (422) when a test with video questions is started without `video_consent: true` (the general `consent` is checked first); declining or starting a declined invitation answers `invitation-not-open` (409). Declining is refused when the due time has passed.
4. **Assessors:** a test with video questions needs at least one assessor to be sent, as long text does (`invalid-assessor`).
5. **Scoring placeholder until part 3:** a video answer is graded by a person, so the grader gives null; the attempt still scores (a video question counts 0 points, no `pending_scoring`) because no take can be recorded until part 2. Do not enable video tests for real candidates before parts 2 and 3.
6. **The candidate sees** `video_consent_required` on the invitation and `video: {think_seconds, answer_seconds, max_retakes}` on each video question of the attempt.
7. **Notice to the team:** `assessment.declined` (in-app only, to the member who sent the test and its assessors) through the new event `InvitationDeclined` and a queued listener.

### Form 3: voice calls through Asterisk (design started 2026-10-12)

Decided earlier: Asterisk; calls reach ordinary phone numbers only through a licensed SIP trunk provider; both numbers masked; recording optional with consent (Decided).

**Facts checked in v2 (2026-10-12):**

- The job seeker's `phone` (`jobseeker_profiles.phone`, 20 characters) is optional, typed as an optional `+` and 8 to 15 digits, **never verified** and not normalised to E.164 (a local `0812...` is accepted). It is required to apply (`ProfileCompleteness`).
- **The company already sees that number**: the application detail (`ApplicantProfileReader`), the applicant export (`ApplicantExportRow`), the opened candidate profile (`GetCandidate`) and the generated CV all carry it. Masking the candidate's number in a call therefore hides nothing the company cannot read today.
- The company's `contact_phone` is on its public profile.

### Form 3 answers (2026-10-12, user)

1. **Masking (gate finding, decided):** **one-way**. The candidate sees only a platform number (a DID of the SIP trunk), never the team member's own number. The candidate's number stays visible to the company in the four places above. Calls still go through the platform so they are logged and can be recorded. This replaces "both numbers masked" in the earlier decision.
2. **Unverified phone (gate finding, decided):** the number is normalised to **E.164** when used (a leading `0` becomes `+62`). For a voice interview the candidate **confirms the number to call** when confirming the schedule, and may give another number for that interview only; only that number is called. No OTP (there is no SMS channel).
3. **Candidate side:** an **ordinary phone call (PSTN) only**, placed by Asterisk through the SIP trunk to the confirmed number. A candidate who prefers the browser is given a Janus video interview instead (the camera may stay off), so there is one browser path only.
4. **Calls back:** a call to the platform number gets a **recorded message** (the number only places calls; wait to be called or check the app); nothing is routed to the team. After an unanswered call the candidate gets an in-app "missed call" notice.
5. **Team side:** **browser only** (WebRTC over WSS to Asterisk). Team members' own numbers are never stored. A panel (at most 4 company participants, as for video) joins the same conference.
6. **Asterisk:** on **the same server in its own container** with CPU and memory limits, version **22 LTS**; the API drives it through **ARI** on a private network; the SIP and RTP ports for the trunk and WSS for browsers are opened.
7. **Recording:** as for video: consent when confirming (withdrawable until recording starts), started by hand by a company participant. When it starts, Asterisk plays an announcement ("this call is recorded") to the candidate. The audio file goes to the private disk, uses the same retention setting (`interview.recording_retention_days`, 90) and is streamed only.
8. **Unanswered calls:** the team may call again inside the join window up to a **number of attempts set by an admin**; each unanswered call sends the candidate an in-app notice. After that the team reschedules.
9. **Caps:** admin settings for the **maximum length of one call** (Asterisk hangs up) and the **call minutes per company per month** (refused with 409 when used up); only Indonesian numbers (`+62`) are dialled. A guard, not billing.
10. **Fallback (TI4):** when confirming a **video** interview the candidate may give an optional **fallback number**. Inside the window the team may switch to a voice call to that number without rescheduling; the recording consent already given applies.

### Form 3 schema, endpoints and parts (approved by the user 2026-10-12)

**Schema** (migrations in `Recruitment`):

- `recruitment_interviews.format` adds `voice`. Interviews gain `candidate_phone` (E.164, set by the candidate at confirmation; required for `voice`, the optional fallback for `video`, null otherwise).
- `recruitment_interview_calls` (the call log): `interview_id`, `started_by`, `status` (`dialing`, `ringing`, `answered`, `no_answer`, `busy`, `failed`, `ended`), `dialed_at`, `answered_at`, `ended_at`, `duration_seconds`, `hangup_cause`, `channel_id`, `bridge_id`. It counts the attempts and the monthly minutes (calendar month in Jakarta time, the company row locked while checking, as `DailyMessageQuota` does).
- `recruitment_interview_recordings` (form 1) gains `media` (`video` or `audio`); `recruitment_interview_attendances` (form 1) also takes the team's joins and leaves from Asterisk events.
- One platform number (`VOICE_CALLER_ID`) shown to every candidate; a pool is not needed because calls back only hear a message.

**Behaviour:**

- The API drives Asterisk through **ARI**: it pushes a temporary WebRTC endpoint per team member through ARI dynamic configuration (removed when the window ends), creates a mixing bridge per interview, dials the candidate through the trunk into the bridge, and records the bridge.
- ARI sends its events over a WebSocket, so a long-running listener (`interview:voice-listen`, under Supervisor) turns them into call status, attendance, recording and missed-call updates. It needs a WebSocket client package (proposed: `phrity/websocket`, a plain synchronous client; added only with your approval).
- Join window, panel, lobby-free (the team is in the bridge before the candidate is dialled), capacity cap through `interview.voice_max_concurrent_calls`.
- Erasure: anonymising the applicant already deletes the interviews; calls and recordings (files included) go with them.

**Settings** (`interview.*`, company overrides where marked): `voice_enabled` (false), `voice_max_call_minutes` (60), `voice_monthly_minutes` (600, per company), `voice_max_attempts` (3, per company), `voice_max_concurrent_calls` (5). `.env`: `ASTERISK_ARI_URL`, `ASTERISK_ARI_USER`, `ASTERISK_ARI_PASSWORD`, `ASTERISK_WSS_URL`, `VOICE_CALLER_ID`. The trunk credentials live only in the Asterisk container's configuration.

**Endpoints:**

| Who | Endpoint | Purpose |
|---|---|---|
| Company | `PUT .../interview` | Gains `format: voice` |
| Company | `POST .../voice/join` | Temporary SIP credentials, `wss_url`, ICE servers; `voice-window-closed` 409 |
| Company | `POST .../voice/call`, `POST .../voice/hangup` | Dial the candidate (also the fallback of a video interview); 409 `call-attempts-used`, `voice-quota-used`, `voice-capacity-full`, `call-in-progress`, `no-phone-number` |
| Company | `GET .../calls` | The call log |
| Company | `.../recording/start`, `.../stop`, `.../recordings` | As in form 1, for audio too |
| Job seeker | `POST .../confirm` | Gains `phone` (required for `voice`) and `fallback_phone` (optional for `video`); `invalid-phone` 422 for a number that is not `+62` |
| Job seeker | `GET .../interview` | Shows the platform number that will call |

**Coding parts** (one fresh session each, after form 1, since they reuse its window, panel, attendance and recordings):

1. Scheduling: format `voice`, the confirmed and fallback numbers, normalisation, the settings.
2. Asterisk gateway and listener: ARI client behind a port, temporary endpoints, bridge, dialling, hangup, attempts, quota, capacity, call log, missed-call notice, attendance.
3. Recording: the announcement, bridge recording, storing, streaming, retention and erasure.
4. Deployment files in `deploy/asterisk/`: container with limits, PJSIP (trunk template, WSS transport), dialplan (the inbound message), the Indonesian prompts, the Supervisor program for the listener, firewall ports and the checklist. The trunk contract and the number come from the user.

## Open questions

Form 1 (live video on Janus):

1. ~~Decided.~~ Who joins the call.
2. ~~Decided.~~ Join window and lobby.
3. ~~Decided.~~ When the candidate consents to recording.
4. ~~Decided.~~ How long recordings are kept.
5. ~~Decided.~~ Where Janus runs.
6. ~~Decided.~~ Lobby signal.
7. ~~Decided.~~ Attendance.
8. ~~Decided.~~ Where recordings are converted.
9. ~~Decided.~~ Memory headroom on the shared server.
10. ~~Approved.~~ Form 1 schema, endpoints and coding parts.

Form 2 (one-way video, an assessment question type):

1. Which tests may hold video questions?
2. How long are video answers kept?
3. What happens when the candidate refuses the recording consent?
4. ~~Decided.~~ Upload.

(1 to 3 decided as well, see "Form 2 answers".)

5. ~~Decided.~~ Retakes.
6. ~~Decided.~~ Practice question.
7. ~~Decided.~~ Team access.
8. ~~Decided.~~ AI.
9. ~~Decided.~~ Extensions.
10. ~~Decided.~~ Length check.
11. ~~Approved.~~ Form 2 schema, endpoints and coding parts.

Form 3 (voice through Asterisk):

1. ~~Decided.~~ Masking.
2. ~~Decided.~~ Unverified phone.
3. to 6. ~~Decided.~~ Candidate side, calls back, team side, Asterisk placement.
7. to 10. ~~Decided.~~ Recording, unanswered calls, caps, fallback.
11. ~~Approved.~~ Form 3 schema, endpoints and coding parts.

All three forms are designed. Coding order: form 1 parts 1 to 4, form 2 parts 1 to 3, form 3 parts 1 to 4. Waiting for the user: a licensed SIP trunk contract and one number (DID), and root setup of the Janus and Asterisk containers.


