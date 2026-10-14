# Benchmark: tele-interview and psychological assessment (2026-10-11)

Requested by the user on 2026-10-11 as two new features: **tele-interview** and **psychological test ("psikotes")**. Scope as answered by the user:

- **Tele-interview** covers four forms: a voice call over VoIP (Asterisk plus a telco), a live video call inside the platform, a recorded one-way video interview, and integration with Zoom, Google Meet and Microsoft Teams.
- **Assessment** covers four forms: tests of psychology and reasoning (not skills), psychometric tests, skill tests, and integration with test vendors.
- **Benchmarks:** the job portals from rounds 1 and 2, video interview vendors, global assessment vendors and Indonesian psychological test providers.

This reopens R8 (skill test), which the user had excluded (`docs/design/decisions.md` section 1: "`assessment` (skill test) is not included now").

Verification levels are the same as in `benchmark-notes-round-2.md`:

| Level | Meaning |
|---|---|
| direct | Official page or official help-centre API read in full |
| rendered | Read through a headless browser |
| search-summary | Only seen through a search-engine summary; treat as unconfirmed |
| old | Official but dated; may no longer hold |

The article ids are Zendesk article ids, with their last update date.

## 1. Sources

| Platform | Source | Level |
|---|---|---|
| Glints | `support-glints.zendesk.com` API | direct |
| Kalibrr | `kalibrr-support.zendesk.com` API | direct |
| Indeed (job seeker) | `indeed.zendesk.com` API | direct |
| Indeed (employer) | `indeed.com/employers/interview` | direct (marketing page; says features vary) |
| LinkedIn | `linkedin.com/help` a1690529 | direct |
| LinkedIn | `business.linkedin.com` hiring integrations | search-summary |
| JobStreet / SEEK | Employer help pages rendered in round 2: none mention video, calls or tests. seekAi from a news report (tribune.net.ph, 2026-09-27). | rendered (absence); search-summary (seekAi) |
| Dealls | Terms rendered in round 2 (Google Calendar access for interview events). Employer page "take home test" from a search. | rendered; search-summary |
| karir.com | Online tests (ERIKA), `benchmark-notes-round-2.md` | rendered |
| Spark Hire | `support.sparkhire.com` API, 253 articles | direct |
| HireVue | Candidate blog page | old (2014) |
| Greenhouse (ATS reference for Zoom, Meet and Teams) | support article 13295386332827 | direct |
| TestGorilla | `support.testgorilla.com` API, 129 articles | direct |
| Mekari Talenta, with LPTUI | `help-center.talenta.co` API, 658 articles | direct |
| Asterisk | docs.asterisk.org, "Configuring Asterisk for WebRTC Clients" | direct |
| UU 23/2022 on psychology education and services | Official copy, PDF from ipkindonesia.or.id, text extracted | direct |
| VoIP licensing in Indonesia | News reports and academic papers | search-summary |
| UU PDP and call recording | News and law-firm articles | search-summary |

**Not found:**

- An official HireVue help centre.
- Willo and myInterview help centres; they did not answer.
- Any major job portal that offers masked phone calls.
- Any Indonesian job portal with a built-in psychological test for employers.

## 2. Tele-interview

### 2.1 What the benchmarks offer

| Form | Glints | Kalibrr | Indeed | LinkedIn | JobStreet | Dealls | Spark Hire | Greenhouse |
|---|---|---|---|---|---|---|---|---|
| Voice call inside the platform | **No.** Phone numbers are exchanged and the call is made outside, by phone, WhatsApp or Telegram (20375465161753, 2025-03-14). | No. The recruiter's phone number is shown to the candidate only after the interview is confirmed (360016127091). | **Yes.** Interview "virtually or over the phone"; if video fails, it falls back to phone. | No | AI voice pre-screening in seekAi (news only) | Not found | No | No |
| Live video inside the platform | No | No | **Yes, Indeed Interview.** No app or link to manage; virtual lobby with messages to waiting candidates; CV beside the video; notes and outcome; device test; SMS reminder with a join link. Built on Daily: the help article suggests "Run a Daily Network Test" (360050564451, 2026-06-08). | No | No | No | **Yes, Live Interview.** Preparation lobby; camera, microphone and connection check; host role that can be handed over; a question panel seen only by the host; timer; screen share; chat and polls; recording saved on "Finish"; guests join through a passcode link (234665268, 2026-10-06). | No |
| Recorded one-way video | No | **Voice answers only:** a custom question can ask the candidate to record their voice (360016318311). | No | No | No | No | **Yes** (see 2.2) | No |
| Zoom, Meet or Teams integration | No (link in chat) | No (address and contact only) | Not needed (built-in) | **Yes.** Video Connect schedules Zoom, Teams or Meet from Recruiter and lets the candidate pick a time from the recruiter's availability. | No | **Google Calendar:** creates, updates and deletes interview events in the user's calendar (terms, rendered). | Calendar connection | **Yes, all three.** Zoom ids are random with a password, never a personal meeting id; the 40-minute limit of a free Zoom host applies; Meet uses one link when all interviews are scheduled at once; Teams needs one link per event and each member enables it (13295386332827). |
| Reminders | Email at Interview and Rejected | **Email and SMS**, 3 days before, 1 day before and on the day | SMS and email | Calendar | Email | Calendar | Email and optional SMS | Calendar |

The Indonesian portals (Glints, Kalibrr, JobStreet, karir.com, loker.id and Dealls) offer **no built-in call or video**. Interviews are held at a physical address or through an external link. Among the portals, only Indeed has built-in video and phone interviews.

### 2.2 One-way video interview (Spark Hire, direct; HireVue, old)

| Setting | Spark Hire | HireVue (2014) |
|---|---|---|
| Invitation | Email with a unique link, optional SMS. Bulk upload of up to 1,000 candidates by CSV. Alternatively, a public invite link (234893307). | Not described |
| Deadline | Set as a date or a number of days from sending. Candidates may **request an extension**; the company approves automatically by rules (maximum number of extensions, and so on) or by hand (4407660677403). | Not described |
| Answer time | Per question, **30 seconds to 5 minutes** (234665208) | Up to 3 minutes |
| Think time | Per question, 30 seconds to 5 minutes or unlimited. A limited think time forces **a single take** (234665248). | 30 seconds |
| Re-records | Per question, **1 to 5 or unlimited** (234665228) | Only practice questions can be redone |
| Candidate flow | Intro video, guidance, consent, and expectations that discourage using AI. Portrait recording on mobile. The candidate may pause and resume with the same link before the deadline (46258017022747). | Automatic device and connection check; practice questions first |
| Review | Rating, scorecards, share links, playback speed, download (several articles) | Not described |
| Integrity | "Proctoring" flags possibly AI-generated answers. Flags never block a submission and never change a score; a reviewer confirms or overrides them (48270908264347). | Not described |
| AI | Transcription, summary and AI scoring. Optional, with human oversight, and the article cites NYC Law 144 notice duties (39488633780379). | Not described |
| Deletion | Permanent deletion, typed "DELETE" to confirm (16043841111579) | Not described |

TestGorilla also offers video questions and "AI interviews" scored by AI from the transcript, with rubrics the recruiter can override (45610232362395, 41160350842523).

### 2.3 Voice calls over VoIP: Asterisk and a telco

**Asterisk (direct).** Browser clients connect through WebRTC:

- Requirements: `chan_pjsip` at version **15.5 or higher**; the modules `res_crypto`, `res_http_websocket` and `res_pjsip_transport_websocket`; `codec_opus` recommended.
- Transport: TLS on the HTTP server (port 8089, `/ws`) and a `wss` transport.
- Endpoint: an endpoint with `webrtc=yes`. This option is shorthand for AVPF, DTLS media encryption (fingerprint verification, `actpass`), ICE, `rtcp_mux` and `media_use_received_transport`.
- Certificates: a trusted certificate (for example Let's Encrypt), with keys of at least 2,048 bits.

**Common pattern (search-summary).** The browser client (SIP.js or JsSIP) connects to Asterisk over WSS. Asterisk bridges the call to another browser, or through a SIP trunk to the telephone network (PSTN). Recording, with MixMonitor or ARI, requires the media to pass through Asterisk. Number masking means calls show a proxy number from a DID pool, so neither side sees the other's real number. It is sold to Indian job portals (edesy.in), but **no large job portal was found that offers it**.

**Regulation (search-summary, needs legal confirmation).**

- **PSTN.** VoIP that reaches the PSTN is telecommunication under UU 36/1999 and PM Kominfo 13/2019, and the operator must be licensed. Komdigi tightened VoIP enforcement in 2025. In practice, calls to or from real phone numbers should go through a **licensed SIP trunk provider**. Browser-to-browser calls that never touch the PSTN avoid this.
- **Recording.** A recorded voice is personal data under UU PDP 27/2022. Recording needs a lawful basis, normally explicit consent for a stated purpose. In 2026 Komdigi stated that recording a person without consent can breach UU PDP.

### 2.4 Practices worth copying

| # | Practice | Seen at |
|---|---|---|
| TI1 | Integration creates a unique meeting link per interview (never a personal room), with a password | Greenhouse, LinkedIn |
| TI2 | Device check (camera, microphone, network) before joining | Indeed, Spark Hire, HireVue |
| TI3 | Lobby and a host who admits or starts; candidate CV beside the call; notes during the call | Indeed, Spark Hire |
| TI4 | Video falls back to phone | Indeed |
| TI5 | Phone numbers stay hidden until needed (after confirmation) | Kalibrr; masking at vendors |
| TI6 | SMS reminder with a join link | Indeed, Kalibrr, Spark Hire |
| TI7 | One-way video: per-question answer time, think time and re-record limit; a deadline with extension requests; pause and resume | Spark Hire, HireVue |
| TI8 | Consent and expectations screen before recording | Spark Hire |
| TI9 | Recordings shared by link with expiry, downloadable, and permanently deletable | Spark Hire |
| TI10 | AI scoring is optional, explained, and never decides alone | Spark Hire, TestGorilla |

## 3. Assessment and psychological tests

### 3.1 Job portals

| Platform | What exists |
|---|---|
| Glints | A "Skill Test" **stage** only. The test happens outside the platform ("the hiring manager will internally assess the candidates or conduct job-specific tests", 21692666837017). It is optional. |
| Kalibrr | **Ready-made skills tests** (built by subject-matter experts), required when the employer attaches them to a job. The candidate may pause and resume. **No retake** (360016316731, 2026-06-14). Results sit on the profile; the candidate can hide each result. The employer sees **4 levels**: Outstanding, Good, Satisfactory, Needs Improvement. The employer **cannot see or change the questions** (23004958791833, 23004399200025). Reports filter by assessment. |
| Indeed | Indeed Assessments: 50+ tests (cognitive, psychometric, language, job skills), mostly 5 to 10 minutes. They can be attached to a job or sent afterwards, and job seekers can take them on their own and add results to the profile. **US English only** (search-summary). |
| LinkedIn | **Skill Assessments discontinued**; badges and data removed in 2024. LinkedIn's reason: hirers value examples of applied skills more (a1690529, direct). Third parties cite fraud. |
| karir.com | Online tests on the job seeker side: an interest test built with Atma Jaya's psychology faculty (result: 3 matching job types on the profile), English (15 minutes), communication style and logic (ERIKA). |
| JobStreet, loker.id | None found |
| Dealls | Automatic email and "take home test" sending (search-summary); no psychological test found |

### 3.2 Global assessment vendors

**TestGorilla (direct)**

- **Structure:** an assessment holds at most **5 tests** plus 10 or 20 custom questions. Library tests last 10 to 30 minutes.
- **Question banks:** each test has a bank of at least 100 questions, of which 12 to 20 are drawn at random. Questions have a maximum exposure and are then retired, and **neither customers nor staff can see** the questions (9027723634331, 30469765739931).
- **Scoring:** percentile by default against a reference group; the raw score is optional (30054006666011).
- **Personality tests:** Enneagram, 16 Types, Big 5 and DISC, with the advice "we do not recommend making a hiring decision based purely on personality" (9028175400347).
- **Anti-cheating:** a webcam snapshot every 15 seconds; tracking of full-screen exits, tab switches, webcam off, developer tools, IP location and paste. Results fall into tiers (No, Some or Major Issues). Candidates are never labelled cheaters; contact the candidate before acting. The timer keeps running when the window closes. **One attempt per email.** An honesty agreement comes before the test (9028797639451, 2026-10-07).
- **Accommodations:** candidates report their language fluency or a condition that affects memory or cognition and get extra allowance, on by default under EEOC and ADA (9028544070427).
- **Reset:** not possible; the candidate must be deleted and invited again.

**Spark Hire Predictive Behavioral Assessment (direct)**

- Behavioural profiles per role, built on 600,000 assessments and 130+ competencies, with a validity and fairness article (32761697218971).
- Invitations work the same way as for interviews.
- Proctoring flags careless or inconsistent answers.

### 3.3 Indonesia

**Mekari Talenta Recruitment with LPTUI (direct)** is the closest local benchmark. Its psychometric add-on is built with the **Institute of Applied Psychology, Universitas Indonesia (LPTUI)**:

- **Three batteries:**
  - Leadership Competency Profile: 570 questions, 150 minutes.
  - Professional Talent Assessment: 570 questions, 150 minutes.
  - Operational Readiness Test: 420 questions, 90 minutes.
- **What they cover:** intelligence (abstract, verbal, numerical), personality and work attitude (accuracy, endurance under pressure, work pace and others).
- **Report:** carries an **official LPTUI report number** and a recommendation.
- **Library page:** each battery has a description, the measured capabilities, one sample question per type and an example report.
- **Candidate flow:** email invitation, then terms, then the sections, then "Submit Summary". The test is **locked after submission** and **auto-submits when time runs out**. The system "is equipped to detect any type of cheating". Candidates must not open other tabs or use two devices (60149003542169).
- **Recruiter flow:** a Psychometric stage with "Move and invite"; start and due date; online or onsite; one or more **assessors**; bulk invitations only within one job (60148920477977, 34775708157209).
- **Other tests:** MBTI as a separate test with statuses Not invited yet, Sent, Submitted and Expired (54018820786969). Custom tests the company builds itself: multiple choice, long text or numeric; a duration; navigation rules (skip, go back, jump); at most 10 measured capabilities (56549296871193).
- **Pricing model:** credit packages (110, 500 and 1,000), not detailed here (billing is out of scope).

**Update 2026-10-14 (direct, Talenta help center; cmlabs news):**

- Sample questions are labelled by subtest codes (CDF, ART, VER for the professional and leadership batteries; CGF, SIK for the operational one),
  which suggests IST-like intelligence subtests next to work-attitude (Kraepelin-like) tests.
- The candidate gets a pop-up after a while **without cursor movement** during the test, and is told not to work from **two devices at once**.
- Every result carries an official, unique LPTUI license or report number that can be validated, and a recommendation.
- Results come out without a visible review step per candidate: LPTUI ran an online selection of **more than 20,000 participants** for a ministry
  in 2025 (built with cmlabs), so reports are most likely issued from the norms under the institute's responsibility, not signed one by one.
- The invitation also takes an attachment (JPG, PNG or PDF, at most 10 MB) that can go with the email; onsite invitations name the office.
- The English feature page still says "Coming soon"; the help center articles were updated in July 2026.

**Common Indonesian test instruments (search-summary):** CFIT and IST (intelligence), Kraepelin (concentration and pace), DISC, PAPI Kostick, EPPS, RMIB, and drawing tests (BAUM, HTP, DAP, Wartegg).

**Law: UU 23/2022 on psychology education and services (direct, official copy)**

- **Pasal 26(1):** "Layanan Psikologi dilaksanakan oleh Psikolog sesuai dengan kewenangannya". A psychologist may be assisted by psychology graduates (26(2)). Services may be direct or **indirect** (26(4)), by an individual or an institution (26(5)).
- **Pasal 33(1)(a) and (2):** psychological measurement ("pengukuran psikologis") is a psychology service. It is defined as "pemeriksaan psikologis dengan menggunakan alat ukur psikologis yang sahih dan andal".
- **Pasal 27:** the client's consent, oral or written, given after being told the benefits and risks.
- **Pasal 37:** a service standard that includes storage of result reports, printed or digital.
- **Pasal 44:** the client has the right to confidentiality (d) and to a correct and clear explanation of the results (f).
- **Pasal 25:** psychologists without the right STR and SILP face administrative sanctions.

**Reading (needs legal confirmation).** Psychological measurement such as intelligence, personality and aptitude tests is a service reserved for licensed psychologists, which may be delivered online. A platform that scores and interprets psychological tests by itself, with no psychologist responsible, falls outside this. The candidate must consent and has the right to an explanation of the result. Skill tests (job knowledge, language, coding) are not psychological measurement. Talenta's model (an accredited university institute signs the report) fits the law. karir.com's interest test was also built with a psychology faculty.

### 3.4 Practices worth copying

| # | Practice | Seen at |
|---|---|---|
| AS1 | Test as an application stage, sent on "move" (automatic or manual), single or bulk within one job | Talenta, Kalibrr, Glints (stage only) |
| AS2 | Start and due time, then expiry; statuses invited, sent, submitted, expired | Talenta, Spark Hire, TestGorilla |
| AS3 | Timed test; the timer keeps running; auto-submit at the end; locked after submission; one attempt | TestGorilla, Talenta, Kalibrr |
| AS4 | Question banks hidden from employers, randomised, with exposure limits | TestGorilla, Kalibrr |
| AS5 | Results as bands (4 levels) or percentiles, not raw pass or fail | Kalibrr, TestGorilla |
| AS6 | Integrity signals (tab switch, full screen, paste, webcam) shown as tiers, never automatic rejection | TestGorilla, Spark Hire, Talenta |
| AS7 | Consent and honesty screen before the test | TestGorilla, Spark Hire, UU 23/2022 Pasal 27 |
| AS8 | Accommodations (extra time) | TestGorilla |
| AS9 | Psychological batteries delivered with a licensed institution that signs the report | Talenta and LPTUI, karir.com and Atma Jaya |
| AS10 | Company-built custom tests (multiple choice, text, numeric) with navigation rules | Talenta, Kalibrr (custom questions) |
| AS11 | Candidate controls whether a profile-level result is visible | Kalibrr |
| AS12 | Personality results are for discussion, not a sole hiring decision | TestGorilla, Talenta (MBTI) |

## 4. Comparison with v2

| Area | v2 today | Gap |
|---|---|---|
| Interview format | `recruitment_interviews.format` online or onsite, with a free `meeting_url` (`phase-2-interviews.md`) | No integration (TI1), no built-in call or video (TI2 to TI4), no one-way video (TI7 to TI9) |
| Reminders | Email the day before; push deferred (`decisions.md`, 2026-10-11); no SMS channel | TI6 needs SMS or WhatsApp (not in the channel list) |
| Contact data | Phone sits on the job seeker and company profiles | TI5 masking needs a telephony provider |
| Stage types | Seven types; **no `assessment` type** (R8 excluded) | AS1 needs a new stage type or an assessment under `screening` |
| Tests | Screening questions at apply time (`phase-1-screening.md`), must-have mark-only | No timed tests, banks, scoring, integrity signals or reports (AS2 to AS12) |
| File storage | Default `local` disk; S3 driver available | Video and voice recordings need large-object storage, retention and deletion |
| Real-time channel | Deferred (Reverb not used) | A live call or video needs signalling (WebRTC through Asterisk WSS or a video provider) |

## 5. Findings for the user (decision gates)

1. **Psychological tests and the law.** Under UU 23/2022 (Pasal 26 and 33), psychological measurement is a service reserved for licensed psychologists, with consent (Pasal 27) and a right to an explanation (Pasal 44). The two benchmarks that follow this (Talenta with LPTUI, karir.com with Atma Jaya) both work with a licensed institution. Building CFIT-like or personality tests that the platform scores and interprets alone would not fit this. This is a reading of the statute, not legal advice.
2. **Telephony through a telco.** Calls that reach real phone numbers need a licensed SIP trunk provider under UU 36/1999 and PM Kominfo 13/2019; this is search-summary level. Browser-to-browser calls through Asterisk do not need a trunk. **Recording** calls or video needs explicit consent under UU PDP.
3. **Benchmark gap.** No Indonesian job portal offers built-in calls, video or psychological tests. Indeed (video and phone) and Talenta (psychometrics, an HRIS and ATS rather than a job portal) are the only mature examples. Neither LinkedIn nor JobStreet has built-in skill tests, and LinkedIn dropped its skill assessments.

**Decided 2026-10-11 (gate 1):** own test engine like Talenta, with skill tests; psychological tests owned and signed by a licensed partner psychologist or institution (`decisions.md`).

**Decided 2026-10-11 (gate 2):** tele-interview order integration, one-way video, live video, voice; voice reaches the PSTN through a licensed SIP trunk with masked numbers; recording optional with consent (`decisions.md`).
