# Benchmark notes, round 2

Round 1: `benchmark-notes.md` (2026-10-03, kept unchanged as the record of that round). Round 2 started on 2026-10-11.

**Goals, in this order** (set by the user, 2026-10-11):

1. Re-verify the round-1 facts that were read only through web-search summaries.
2. Cover areas that round 1 did not: job seeker profile and onboarding, notifications, admin and moderation,
   trust and safety, company verification, account and privacy.
3. Compare what Phases 0 to 4 built with the benchmarks (`gap-analysis-round-2.md`).

**Platforms:** the five of round 1 (Glints, JobStreet, LinkedIn, karir.com, loker.id) plus Kalibrr, Indeed, Dealls, Jobs.id and TopKarir.
Plan, billing, pricing and paid tiers stay out of scope and are not compared.

## Verification levels

| Level | Meaning |
|---|---|
| **direct** | Official page read in full, fetched as plain HTML. |
| **rendered** | Official page read in full through a headless browser, because the content loads with JavaScript or the plain fetch was blocked. |
| **archive** | Official page read in full from a web.archive.org snapshot; the snapshot date is given. |
| **search-summary** | Seen only in web-search summaries of official pages; not read directly. |
| **unverified** | Not confirmed from an official source. |

Each fact keeps its source URL. Absence from these notes does not mean a platform lacks a feature.

## 1. Re-verification of round-1 facts

### JobStreet (SEEK), employer side

Source: the Indonesian employer help centre, `https://help.id.employer.seek.com/id/s/article/<slug>?language=en_US`. Level: **rendered**,
2026-10-11 (Salesforce site; plain fetch returns an empty shell). Article slugs come from the web.archive.org index; 86 articles were read,
pricing, invoice and payment articles were skipped. The "Updated" date of each article is given in brackets.

**Round-1 facts, now confirmed or corrected:**

| Round-1 fact (search-summary) | Round 2 | Article |
|---|---|---|
| Screening questions: recommended from the job, select up to 5 | **Corrected.** SEEK recommends up to 5 from the job ad; the library covers right to work, salary expectations, licences and certifications, experience and skills, education. Up to **10** questions or preferences per ad (older articles say 8). Questions and answers **cannot be changed after posting** ("fair application process"). | `Can-I-add-specific-questions-...` (15/9/2026), `Can-I-edit-my-ad-once-it-s-live-ASIA` (30/10/2025) |
| Status folders pre-screen, shortlist, interview, offer, accept, not suitable; bulk email and move | **Confirmed.** Bulk select: current page (20) or whole list; bulk email with a `[firstname]` placeholder and an option to allow candidate replies. | `All-You-Need-to-Know-About-Candidate-Management` (19/8/2026), `How-do-I-bulk-email-candidates` (4/10/2023) |
| "Not suitable" notified after 48 hours | **Confirmed.** Also: must-have questions, a must-have filter on by default, and opt-in **auto-reject** that moves non-matching applicants to Not Suitable (notified after 48 hours). | same, plus `Can-I-add-specific-questions-...` |
| Talent Search: Basic / Premium | **Confirmed.** Basic is free: keyword search plus filters (location, salary, experience, skills), profile card (work history, location, salary expectation, verified credentials such as right to work), "send a personalised message", "Invite to apply" (message with a job link). Premium (paid) adds contact details; a downloaded profile is visible to every user of the company for 30 days. | `All-you-need-to-know-about-Basic-Talent-Search-ID` (18/2/2026), `...Premium-Talent-Search-ID` (18/2/2026) |
| Candidate Matches | **Confirmed, with detail.** Up to 100 AI-matched profiles per live ad; send the job ad or a personalised message; an invitation allowance per ad; access ends when the ad expires; "More like this / Less like this" feedback; "Likely Interested" label. | `All-you-need-to-know-about-Candidate-Matches-ID` (20/7/2026) |

**New employer facts (not in round 1):**

- **Applicant review:** search applicants by skills, career history and education, not only name; sort by high-fit (AI), matching answers, highest rating,
  newest; candidate card shows location, education, verified credentials (SEEK Pass), screening answers, matched skills, **previous applications to the
  company**, a "high-fit" label with an AI summary. (Candidate-Management, 19/8/2026; `How-do-I-filter-or-manage-my-candidates-ID`, 20/7/2026)
- **Rating and notes:** star rating per application; notes and **documents attached to an application**; visible to every user of the company account
  who can access the job, never to other companies. (same)
- **AI pre-screen:** the employer requests it per candidate; the candidate answers role questions by voice or text within 7 days; the employer gets the
  written answers only; it does not score or decide. (Candidate-Management)
- **Reference checks:** requested inside the flow; referees fill a standard 14-question form (ratings and free text); the employer downloads a PDF;
  the candidate sees the status, not the answers. (`What-are-Reference-Checks-ASIA`, 26/8/2026)
- **Team:** unlimited users at no cost; up to **4 administrators**; permissions: post and manage all ads and applicants, Analytics reports, Candidate
  Matches; **per-ad access** ("Team Access") with its own notification preference per user; one **primary contact** (an admin); a user's email
  cannot be changed (add a new user, remove the old). (`How-do-I-add-new-users-...` 14/7/2025, `How-do-I-assign-specific-job-ad-access-...`
  4/10/2023, `How-do-I-update-the-primary-contact-...` 24/2/2025, `Can-I-change-my-email-address-asia` 24/9/2023)
- **Application notifications to the employer:** every application by email, or a daily or weekly summary, chosen per ad. (`Where-will-my-applications-be-sent-ID`)
- **Job ad lifecycle:** live 30 days; can be expired early (candidates stay manageable); **no pause**; edit at any time except the screening
  questions and the application method; one location and one classification per ad (copy the ad for more); copy from a previous ad;
  desktop and mobile preview; "hide salary" toggle (a pay range is still required for search filters); "advertise privately" hides the company
  name, reviews and branding; links and attachments are not allowed in ads or Talent Search messages; expired ads and their applications stay
  accessible. (several articles, 2023 to 2026)
- **Moderation of job ads:** the first ad of every new employer is reviewed with a **business verification** (Indonesia: tax number, NPWP/TIN)
  before it goes live; later ads can be held as **Pending** for high-risk country names, discriminatory or sexual wording, or suspected fraud.
  (`All-You-Need-to-Know-About-Business-Verification-ID` 19/1/2024, `Why-is-my-job-ad-Pending-ID` 7/11/2024, `When-will-my-ad-go-live-ASIA`)
- **Notifications SEEK sends candidates:** "not suitable" (48 hours later), "your application has been viewed" (when the employer interacts with it),
  "job ad has expired". (`What-automatic-notifications-do-my-candidates-receive-asia`, 21/12/2023)
- **Employer account security:** adaptive multi-factor authentication (SMS, authenticator app, biometrics) on unusual sign-ins; lock-out for
  30 minutes after two wrong passwords; reset link valid 24 hours; email confirmation before the first ad. (2024 to 2026 articles)
- **Company profile:** about page (logo, cover, website, industry, size, location, 2 to 8 specialties, overview of 700 characters, mission,
  featured item, awards) and "life and culture" page (values, 2 to 8 perks, gallery); eligibility: 10 ads in 12 months or 2 active ads; company
  name change only through support. (`Content-Guidelines-ID` 23/2/2024, `How-can-hirers-edit-Company-Profiles-on-SEEK-ID` 7/5/2026)
- **Company reviews:** by past and present employees and candidates; anonymous; guidelines (respectful, relevant, balanced, anonymous, authentic,
  non-commercial, English or Indonesian); three-stage moderation (automated screening, report by any user, human re-moderation); companies
  **cannot edit or delete** reviews, may reply and **flag once** per review, with a daily flag limit; "grow reviews" email template.
  (`Company-reviews-community-guidelines-employer-asia` 12/3/2024, `How-do-I-best-manage-Company-Reviews-...` 22/10/2025, `Why-can-t-I-report-a-review-ID` 22/10/2025)
- **Analytics:** per ad funnel (impressions, clicks, apply starts, apply completes), performance compared with similar ads (title and location) as
  low/normal/high, a prediction of applications at 7 and 14 days, tips (reveal salary, specify location); account report with CSV export, filters by
  user and classification; "Hiring Outcomes" from the employer marking the hire as Accepted. (`All-You-Need-to-Know-About-Hirer-Analytics` 5/8/2026,
  `How-to-get-the-most-out-of-SEEK-Analytics` 22/9/2026, `How-to-understand-and-manage-my-ad-performance` 4/11/2024)
- Out of scope (billing or paid tiers): ad types and prices, Urgently Hiring tag, credits, invitation allowances per ad tier, Premium Talent Search.

### Glints

The help centre page (`help.glints.com`) answers browsers with a Cloudflare check (HTTP 403); it was not bypassed. The same Zendesk help centre
serves its public read API (`https://support-glints.zendesk.com/api/v2/help_center/{en-us,id-id}/articles.json`, 158 and 153 articles), which
returns the current article bodies. Level: **direct** (official API, read 2026-10-11); the article "updated" date is given in brackets. Older
snapshots on web.archive.org were read first; where they differ, the current article wins and the difference is noted. Billing, credit,
subscription and event articles were skipped.

| Round-1 fact | Round 2 |
|---|---|
| Chat to Apply; recruiter online status; new chat notifications | **Confirmed, with detail.** In the app the candidate answers the "Hiring Manager Questions", then the chat opens; some jobs offer **One Tap Apply** (no questions, profile must be complete, an intro message is required). The website uses "Easy Apply" and sends the default CV without asking. Attachments png/jpeg/jpg/pdf up to 5 MB, "Share CV" and "Request CV", template and intro messages, **read receipts** (eye icon / double tick), push and in-app notifications; no group chat, no export; the employer cannot edit or delete messages; a chat with **no message for 30 days is hidden** (the candidate restarts it from "Applied"; only the last lines remain). **Online status was not found.** (2025-03 to 2026-10) |
| Application tracking (app + website) | **Confirmed.** Website "My Application" with filter by status and sort; **applications older than 90 days are no longer shown**; the employer sees applications of the **last 3 months** only. (8017338892441 2026-10-07, 43913001830297 2025-03-14) |
| One-tap quick apply | **Confirmed** ("One Tap Apply", app). (43247860658841, 2025-06-25) |
| Filters education, experience, location; salary on listing | **Confirmed.** App: "Most Relevant", "Newly Added", "Remote/WFH", "Fresh Graduate", up to 3 location and 5 job-field preferences, and **"Nearby"** (jobs within a radius of a pinned location). Salary range is suggested from **government wage regulation** in the job form. (38954479905305 2026-10-08, 50220080753049, 4409549099673 2026-10-09) |
| Application stages Applied, Connected, Skill Test, Interviewing, Offer Sent, Hired, Not Suitable (+ Chat Initiated) | **Corrected.** Employer stages: **Chat Initiated** (new application lands here; the article on Manage Candidates also calls it "Processed"), **In Communication** (set automatically when either side replies; the candidate sees "Connected with HRD"), **Skill Test** (optional), **Interviewing**, **Offer Sent** ("Offered Received"), **Hired**, **Not Suitable**; the candidate also sees **"Stop Receiving Application"** when the job is closed (chat stays open). The employer is warned that a move to **Interview** or **Rejected** emails the candidate. (21693483071385, 21692666837017 2026-10-09, 4409549228953 2025-10-30) |
| Bulk status change (same status only) | **Confirmed** ("Bulk option", same target status only). (4416868004889, 2025-10-30) |
| Rejection flow | **Confirmed, with detail.** On "Not Suitable" the employer picks a reason; a per-employer **rejection method**: one unified message, a custom message per reason, or no message; it becomes the default and can be changed in Account Settings; the message is delivered in the chat. (32883685196441, 2025-10-30) |
| Search applicants by name and status; filters; candidate matches; CV and profile view | **Confirmed.** Search by name within a status; filters; sort by date; profile summary pop-up; resume section, otherwise "Request CV"; previewed applicants turn grey; a reminder lowers the job's visibility when applications are left unprocessed. Candidate recommendations ("AI Matches", formerly Candidate Matches; "CV Finder" for database search) are paid. Since 2024-12 and 2026-03, **free accounts may shortlist (chat or change status) 8 candidates per day** across the company ("Profile Access Limit"); moving "Chat Initiated" to "Rejected" is free. (4416868004889, 4416890500633, 41883316703385 2026-10-09, 51996664302617 2026-03-02) |
| Report inappropriate candidate behaviour | **Confirmed** ("Report Applicant", reason plus mandatory details, investigated). A candidate cannot block a recruiter in the chat; they report the job (see below). (20375381373977 2025-10-30, 20365078001177 2025-04-24) |
| Job post fields: title, role, work address, requirements (gender, age, skills, education, experience); screening questions (paid tier) | **Confirmed.** Steps: Basic Info, Requirements (gender, age, skills, education, experience; work details; salary and benefits), Job Description (AI draft), Screening Questions (7 standard; 3 custom ones and must-have location/education, mandatory photo and CV, hide salary are paid "VIP"); preview. **After posting, only requirements, work days/shifts, salary and benefits and the description can be edited**; title, role, address, skills, commitment type, work arrangement and screening questions are locked (deactivate and post again). (4409549099673, 49161758040729, 2026-10-09) |
| Employer dashboard active/inactive jobs | **Confirmed.** Deactivate and reactivate; a deactivated job takes no applications but its URL still opens; **jobs cannot be deleted**; optional syndication to aggregator partners (changes propagate within 48 hours). (4409549308569, 8280196878361, 2026-10-09) |

**New facts:**

- **Employer registration and company verification.** Sign-up with name, phone, email, password, email link, then company creation; if the company
  already exists the user must be added by its administrator; WhatsApp number verification. Indonesian companies need NPWP, NIB, SIUP (foreign:
  UEN, ACRA); **brokerage and businesses selling illegal drugs or sexual services are refused**. Two verification paths (2026-10-09): **company
  legal documents** (verified, badge shown on the dashboard, job page and company page, up to 5 active jobs) or **proof of ownership** (personal
  NPWP/NIB plus photos of the business; verified but **no badge**, up to 3 active jobs). Review within 2 working days; jobs posted before
  verification stay **"In Review"**; a failed check can be redone. Company name, brand, location and size are locked after saving (change through
  support with documents). (4409556149913, 4409549200537, 43869147799065, 4409549254169, 32199842100633)
- **Team roles:** Administrator (own jobs plus jobs they are added to, change roles, add users), Recruiter (own and added jobs only), Master Admin
  (all jobs, paid plan). Joining an existing company is by request approved by email. Per-job access is "People to Notify". (4409549163033, 19150208706841)
- **Employer sign-in:** 6-digit email code on a new device or after 14 days signed out; WhatsApp OTP optional. Employer account deletion only by
  email request (2 working days). (11250200933273, 4409549240473)
- **Job seeker account:** change email with a verification link to the new address; **self-service deletion** (type "DELETE", give a reason;
  permanent; extra verification possible); **notification preferences** on the website (application updates, job recommendations), plus an email
  digest of unread chat messages from the last 24 hours. (38997216677657, 8251547613209, 6811778610585)
- **Withdrawal:** "you cannot make changes or withdraw applications that you have submitted"; the candidate tells the recruiter in the chat.
  (8251387914521, 2026-09-25)
- **Profile and CV:** headline from the latest job, salary expectation, portfolio and social links; **CV generated from the profile** in an
  ATS-friendly PDF, can be set as the default CV for applying. (49175868569369, 2026-10-07)
- **Trust and safety:** "Report Job" from the job page (reason, optional details and evidence); reports are investigated and the reporter is
  contacted by WhatsApp or email; confirmed fraud deactivates the job and **may suspend the company**; guidance on reporting to the police.
  Fake-job signs for candidates. Glints never charges candidates; job-post rules forbid discriminatory language, **withholding diplomas or personal
  documents**, and misleading salaries. Badges shown to candidates: "Verified Company" and paid "Premium Employer". (50361749943833 2026-10-08,
  28978548427161, 24519751877017, 21305005250329 2026-10-09)

### karir.com

Sources: `www.karir.com` home, `/info/faq-seeker`, `/info/faq-employer`, `/info/privacy-policy`, `/info/terms-conditions`, `/employers`,
`/employers/job-posting`, `/employers/erika`, `/employers/registration`, `/interest-test`, `/english-test`. Level: **rendered**, 2026-10-11.

| Round-1 fact | Round 2 |
|---|---|
| Profile + CV generator (1-click CV), profile matching, preferences + job alert, application status tracking (home page summary) | **Confirmed** on the home page itself (rendered): "Profil & CV Generator ... CV 1x klik", "Status Lamaran", "Preferensi & Job Alert", "Profile Matching"; the home page also shows a **profile match percentage** ("persentase match profil"). The footer adds "Lowongan disimpan" (saved jobs), "Perusahaan disubscribe" (**followed companies**), "Rekomendasi Lowongan", "Test Kemampuan". |
| Interview schedule and recruiter messages in one place | **Not confirmed**: not on the current home page; stays unverified. |
| Resume visible to all companies by default, contact only to "prospects" | **Confirmed** (FAQ). The privacy policy adds: a job seeker can **block specific companies** from seeing the resume; on apply, first name and phone are shown to the company; in search results they are hidden until the company emails the candidate through the resume link. |
| Online test modules | **Confirmed, with detail:** interest test (built with the psychology faculty of Atma Jaya, result shown on the profile as 3 matching job types), English test (15 minutes), communication-style test, logic test (ERIKA page). |

**New facts:**

- Employer registration form: name, position, phone, email, password (`/employers/registration`). The employer FAQ says job posting currently
  goes through a business consultant.
- Employer terms (`/info/terms-conditions`): employers must send company licence documents (SIUP, NPWP, SKDP/SKDU, TDP and others); every ad needs
  karir.com approval before it goes live; ads live 30 days; ads may not contain pornography or content on religion, race or ethnicity that spreads
  hatred, or misleading information; **employers may not charge candidates any fee** (transport, training, uniforms and the like); karir.com may
  refuse service, suspend ads and revoke membership.
- Job seeker terms: karir.com may edit resumes and block accounts that break its rules; users may not post false, hateful or SARA-discriminatory content.
- ERIKA (separate ATS product): recruitment page, employee data bank, application tracking, onboarding, CV parsing; jobs posted there also
  appear on karir.com. Paid service, noted only as context.

### loker.id

The live site (`www.loker.id`, `legacy.loker.id`) answers every client, headless browser included, with a Cloudflare check (HTTP 403); it was not
bypassed. Sources, level **archive**: `/pusat-bantuan` (snapshot 2026-09-07), `/pasang-iklan-lowongan` (2025-08-13), `/job-alerts` (2025-08-13).
The `/fitur/*` pages have no snapshot and stay **search-summary** (`/fitur/talent-search`, `/fitur/export-csv`, `/fitur`, read 2026-10-11).

| Round-1 fact (search-summary) | Round 2 |
|---|---|
| Quick apply ("lamar cepat") | **Search-summary** still (home page wording). The help centre (archive) only states the steps: register, activate by email, complete the profile, write the online resume, apply; a **complete profile is required before the online resume**, and the resume before applying. |
| Email job alerts by keyword, category/interest, education, location | **Confirmed** (archive, `/job-alerts` and `/pusat-bantuan`). |
| Post job; applications in a dashboard | **Confirmed** (archive). Free posting needs a complete company profile first. |
| Talent Search + Job Invitation | **Confirmed** (archive): "Cari Resume Kandidat Potensial", offer a job from the online resume; the company must have a live job first. Filters (position, skills, location, experience, education) and the three-step flow (criteria, browse and save, invite with a personal message) stay **search-summary** (`/fitur/talent-search`). |
| Job Application menu: download resume, message candidate; ATS | **Confirmed** (archive). The feature list adds: applications grouped by stage (phase), per-candidate history of the stages passed, **mail templates for the onsite-interview invitation and the rejection**, candidate recommendation, resume download as PDF, interactive Q&A with chosen candidates, **Job Offer menu with the status of each offer**, talent pool, statistics, email notifications, company profile page. |
| Screening questions (auto filter); Talent Pool; CSV export | Talent pool **confirmed** (archive). Screening questions and CSV export stay **search-summary**: the export holds the applicant profile, screening answers and application status; per job, a range or all applicants. |

**New facts (archive, help centre):**

- **Job ad verification by an admin before publishing.** Refusal reasons: incomplete company profile, company profile that cannot be verified,
  incomplete form, excessive promotion, duplicate ad (delete the old one to republish), suspected job scam.
- Account: registration free, email activation (resend link; Facebook sign-in skips it); password at least 8 characters; change password under
  "Pengaturan Akun". Profile photo at most 300 KB, at least 150×150 px.
- Support: email, phone and WhatsApp, daily 08:00 to 17:00 WIB.

## 2. Areas not covered in round 1

Section 1 already holds the new facts found while re-verifying (JobStreet employer, Glints, karir.com, loker.id). This section adds the
platforms and articles read only for the new areas, then sums up per area in [2.9](#29-summary-per-area).

### 2.1 LinkedIn

Source: `https://www.linkedin.com/help/linkedin/answer/<id>`. Level: **direct**, 2026-10-11.

- **Apply:** Easy Apply or an external Apply, set by the employer. **An application cannot be edited or withdrawn** after submission; the
  candidate contacts the poster by InMail. (a512388)
- **Open to Work:** visibility "All LinkedIn members" (adds the #OpenToWork photo frame), "Recruiters only", or "Visible only to you" (used for
  recommendations only); preferences: job titles, location types, locations, start date, employment types. LinkedIn **hides the signal from
  Recruiter users at the member's current company** (the profile entry marked "I am currently working here"), without guaranteeing it. (a507508)
- **Job post verification badge** (pilot): the company page is verified and the poster has verified working there; the badge cannot be bought.
  (a1492056) Company page verification is requested by a super admin by proving ownership of the website. (a7174627, search-summary)
- **Job post review:** new job posts may be selected for review (random or flagged by an automated language check; a human decides); usually
  within one business day; **while in review the post is hidden and accepts no applicants**; approval or closure is emailed; a closed post can be
  **appealed for up to six months**. (a520650)
- **Enforcement:** policy-breaking jobs are removed with an email; repeated violations suspend the member's job posting (all open jobs closed) or
  posting to the company page; egregious ones such as scams can restrict posting permanently after one violation. (a1358799)
- **Discrimination policy:** no discrimination on age, gender, gender identity, religion, ethnicity, race, national origin, disability, sexual
  orientation or any legally protected ground; any member can report; reviewed and removed. (a1335725)
- **Report a job:** from the job page, categories: spam or scam (scam/phishing/malware, promotional, illegal goods), discriminatory or offensive,
  broken or incorrect (closed, wrong company or location, formatting, does not belong). Teams "review, correct, and/or remove". (a513290)
- **Applicant sharing:** a job posted from a personal account is visible to the poster only; applicants can be forwarded to 1st-degree
  connections at the company, who cannot rate or add notes. (a516725)
- **Account closure:** permanently deletes the profile, messages, connections, recommendations; **can be reopened within 14 days** (some
  items are lost); data deletion per the privacy policy. **Data download:** a partial archive by email within minutes, the full one within
  24 hours, available 72 hours. (a1379064; a1339364 search-summary)

### 2.2 JobStreet (SEEK), job seeker side

Source: `https://help.id.jobstreet.com/id/s/article/<slug>?language=en_US`. Level: **rendered**, 2026-10-11; 100 distinct articles read
(dates 2026-03 to 2026-08 unless noted). This also re-verifies the round-1 job seeker facts:

| Round-1 fact (search-summary) | Round 2 |
|---|---|
| Saved jobs | **Confirmed.** Save from results or the ad; private notes per saved job; a saved job moves to Applied after applying. (`How-do-I-save-a-job-JS`) |
| Saved searches; LiNa job alerts up to 3 profiles (location, specialization, level, experience, work type) | **Corrected.** Up to **10 saved searches**; a job alert is a toggle on each saved search (on by default), at most **20 jobs per email**, daily when there are new matches, but **only monthly when the user has been inactive**; a saved search cannot be edited (save a new one). Criteria are any search: keywords with Boolean operators and exact phrases, classifications, location with radius (default 50 km), work type, salary range. (`How-do-I-save-a-search-ID`, `What-are-Jobstreet-Job-Alerts-JS`, `Help-Why-have-I-stopped-...`) |
| Application status tracking | **Confirmed.** "Applied jobs" keeps applications **90 days** (another article says three months); shows whether the employer viewed the application, or "Viewed status unavailable" when the employer uses an external system; removing a job from the list does not withdraw it. Emails: submitted, **viewed**, "unlikely to progress" (not suitable), **job no longer advertised**. (`Received-an-update-...`, `What-does-Viewed-status-unavailable-mean`, `How-do-I-withdraw-...`) |
| Salary reports, company information | **Confirmed, with detail:** "Salary Insights" tab on company profiles, from job-ad salaries of the past 24 months (visible ranges plus industry averages, outliers removed); salary filter works even when the ad hides the salary (a range is mandatory for employers). (`Salary-Insights-on-Company-Profiles`, `How-can-I-find-the-salary-...`) |

**New facts:**

- **Withdrawal:** not possible on JobStreet; the candidate contacts the employer. Applications are a snapshot: later profile edits do not
  change a submitted application. (`How-do-I-withdraw-or-cancel-my-application-JS`, `Will-the-employer-see-changes-...`)
- **Apply flow:** choose documents (resume from the profile, upload, or none; cover letter written or uploaded, the written one pre-fills next
  time), answer employer questions, update the profile (sent with the application), review and submit. External "Apply" counts as applied
  without knowing the outcome. Resume files .doc/.docx/.pdf/.txt/.rtf up to 5 MB, **up to 10 resumes with one default**; "Create resume" from
  the profile. (`How-do-I-apply-for-a-job-on-Jobstreet-ID`, `How-do-I-add-edit-delete-a-resume-ID`)
- **Profile visibility, four levels:** **Public** (searchable by anyone through "People Search", basic data to signed-out users; full profile and
  contact to signed-in employers), **Standard** (employers can view profile and resume and contact directly or through JobStreet), **Limited**
  (profile but not resume; contact only through JobStreet), **Hidden** (only employers the candidate applies to). The default resume is the
  one employers see in Talent Search. **No hiding from a specific employer.** A separate **"Show my approachability"** signal (open to
  opportunities). Profile activity shows search appearances and views. (`Which-visibility-option-should-I-choose-ID` 3/6/2026,
  `Who-can-view-my-Jobstreet-Profile-ID`, `Can-I-hide-my-profile-from-my-current-employer-ID`)
- **Profile sections:** personal summary, career history (at least one role cannot be deleted), education, licences and certifications, skills
  (suggested from the history), languages, resumes, "About your next role" (work type, location, salary expectation, right to work);
  mononym names entered with a hyphen; profile photo JPG/PNG at least 256×256, **screened for inappropriate content**. Profile data drives
  recommendations (profile, behaviour, similar seekers, the ad). (several 2026 articles)
- **Matching signals to the candidate:** "strong applicant" and "very strong applicant" badges on a job, only to the candidate, from the
  default resume, the ad and the employer's past hiring; employers do not see it. (`What-is-the-You-might-be-a-strong-applicant-badge-ID` 11/8/2026)
- **"Show strong interest":** up to 3 applications per month (resets monthly, no carry-over, irreversible), visible to the employer; requires
  identity verification. (`Show-strong-interest-in-the-jobs-you-really-want` 10/6/2026)
- **Verification (SEEK Pass):** identity, right to work, licences, education, work history; only the verified status is shared, documents
  deleted after verification. JobStreet may **require right-to-work or identity verification before applying** when a user applies to many jobs
  quickly. (`Why-am-I-being-asked-to-verify-my-right-to-work-...` 10/6/2026, `What-is-SEEK-Pass-ASIA`)
- **Sensitive data alert:** automated scanning of uploaded documents (text and images) for identity, financial and health documents, with an
  alert to the candidate; guidance lists religion, ethnicity, sexual orientation, political views, criminal record as sensitive.
  (`Detecting-sensitive-information-...` 9/6/2026, `Sensitive-information-what-you-need-to-know-ID`)
- **Sign-in:** **passwordless**, a 6-digit email code valid 30 minutes (a new request cancels the previous one); social sign-in (Facebook,
  Google, Apple with hidden email); lock-out with an unblock email after repeated failures. Change email with a 6-digit code sent to the new
  address. Several accounts allowed (unique email), **no merge**. (`How-do-I-sign-in-to-my-account` 5/5/2026, others)
- **Notifications:** settings per topic with on/off and "Customise"; unsubscribe link in every email; account and security emails always sent. (`How-do-I-unsubscribe-...`)
- **Account deletion:** self-service in Settings (web and app), permanent; does not delete the SEEK Pass account; employers may still hold data
  already sent to them. (`How-do-I-close-my-account-JS` 11/3/2026)
- **Trust and safety:** "Report this job ad" on every ad (or contact form "Report a suspicious ad"); fraud signs (upfront fees, bank details, ID
  copies before an offer, non-work personal data such as marital status); advice to contact the bank and police. Company reviews: only for
  companies in the reviewer's career history, moderated before publishing, anonymous, a new review overwrites the old one, a reviewer can flag
  their own review to remove it; a company page needs a minimum number of reviews. (`How-do-I-report-a-suspicious-or-offensive-ad-JS`,
  `How-do-I-recognise-a-fraudulent-job-ad-ASIA`, `How-do-I-submit-a-review-JS`, `Can-I-edit-delete-my-review-JS`, `Where-is-my-review-ID`)
- **Reference checks (candidate side):** the candidate nominates 2 referees, tracks status, never sees the answers. (`What-are-Reference-Checks-Candidate-ASIA`)

### 2.9 Summary per area

Practices seen on at least one platform in sections 1 to 3. JS = JobStreet, LI = LinkedIn, GL = Glints, IN = Indeed, KA = Kalibrr,
KC = karir.com, LK = loker.id, DE = Dealls.

**Job seeker profile and onboarding**

| Practice | Seen on |
|---|---|
| Email verification at sign-up; mobile verification (SMS/WhatsApp code) at sign-up or before applying | all; mobile: KA, IN (required to apply) |
| Social sign-in (Google, Apple, Facebook) | JS, IN, LK |
| Passwordless sign-in by email code, or passkeys | JS (code only), IN (passkeys, code fallback) |
| Profile completeness prompts (checklist, stars, match percentage) | KA, GL, KC |
| CV generated from the profile | GL (ATS-friendly PDF), JS (Word), KC |
| Several resumes with a default | JS (up to 10); one file only: IN, KA |
| Job preferences (titles, locations, work type, minimum pay, remote, relocation, start date) | JS, IN, LI, GL |
| "Open to work" / approachability / "ready to work" signal separate from visibility | LI, JS, IN |
| Application is a snapshot (later profile edits not sent) | JS; opposite: KA (employers see the updated profile) |

**Notifications**

| Practice | Seen on |
|---|---|
| Notification preferences per topic and channel (email, SMS, push) | JS, IN, GL |
| Candidate told when the application is viewed | JS, LI, IN, KA |
| Candidate told on stage change, not only on rejection | KA, IN, GL (interview and rejection only) |
| Rejection notice delayed (48 hours) | JS |
| "Job no longer advertised / closed" notice to applicants | JS, IN, GL |
| Interview reminders (3 days, 1 day, same day) | KA |
| Employer gets every application or a daily/weekly digest, per job | JS |
| Job alert frequency reduced for inactive users | JS |

**Admin and moderation**

| Practice | Seen on |
|---|---|
| Review before publishing: a new employer's first post, every post, or a sample | JS (first ad), LK, KC, KA (every post and edit), GL (until verified), LI (sampled) |
| Automated wording checks (discrimination, sexual content, high-risk countries) holding a post as pending | JS, LI |
| Rejection reason shown to the employer; appeal | KA (reason), LI (appeal up to 6 months) |
| Graduated enforcement: remove job, suspend posting, restrict permanently | LI, GL (suspend company), KC |
| Messaging frozen while an employer is under review | IN |
| Profile photo moderation | JS, IN |
| Resume hidden for guideline breaches, with appeal | IN |

**Trust and safety**

| Practice | Seen on |
|---|---|
| "Report job" with reasons (scam, discriminatory, inaccurate, advertisement) | all |
| Report a candidate / a message as spam | GL (report applicant), IN (report spam) |
| Block specific employers from finding the profile | IN (up to 100), KA (hide status from named companies), KC (block companies); not possible: JS |
| Contact alias instead of the real email | IN |
| No fees from candidates, stated as a rule for employers | GL, KC, DE |
| Scam education pages; police-report guidance | GL, JS, IN, LI |
| Sensitive data: detection in uploads, or removal of fields (ID, birth date, gender, ethnicity) from the built resume | JS (detection), IN (removal) |
| Identity or right-to-work verification before applying when abuse is suspected | JS |
| Rate limits on reports (daily flag limit; one flag per review) | JS |

**Company verification**

| Practice | Seen on |
|---|---|
| Legal documents (NPWP, NIB, SIUP) before the first job goes live | JS (NPWP), GL, KC, LK |
| Verified badge on company page and job | GL, LI (pilot), KA |
| Lighter path without company documents, with fewer rights and no badge | GL (3 active jobs instead of 5) |
| Company name and key fields locked after saving (change through support) | JS, GL |
| Refused business types (brokerage, illegal goods) | GL |

**Account and privacy**

| Practice | Seen on |
|---|---|
| Self-service account deletion | JS, GL (job seeker), KA (recruiter); by request: IN (data request form), GL (employer) |
| Grace period to reopen a closed account | LI (14 days) |
| Data download (export) | LI, IN |
| Profile visibility levels | JS (4 levels), IN (2), KA (always visible) |
| Withdraw an application | IN, KA; **not possible**: JS, LI, GL |
| Employer roles and per-job access | JS, GL, KA, LI (Recruiter) |
| Multi-factor authentication for employers | JS (adaptive), GL (new device), IN (mandatory) |

## 3. Additional platforms

### 3.1 Indeed

The help centre page (`support.indeed.com`) answers browsers with a Cloudflare check; the same Zendesk help centre serves its public read
API (`https://indeed.zendesk.com/api/v2/help_center/{en-us,id}/articles.json`, 78 and 60 articles, job seeker side only). Level: **direct**
(official API, read 2026-10-11). Employer help is a separate site and was not read. Article ids and "updated" dates are given.

- **Two job types:** "Apply on Indeed" (application, draft, messages, status tracking and confirmation email on Indeed) and "Apply on the company
  site" (redirect to the employer's ATS; no status from Indeed). (115002786323, 2026-08-21)
- **Apply flow:** phone verification may be required (6-digit code by SMS, call or WhatsApp, valid 10 minutes; required to create an account and
  to apply; virtual numbers refused); apply with the profile or one resume file; **employer questions must be answered**; optional supporting
  documents; **save as draft and continue within 14 days**; on-screen and email confirmation; **an application cannot be edited**. Optional extra
  steps after applying: employer "AI Recruiter Questions" (text, audio or video; the candidate may switch to text) and an optional **bot check**
  (government ID and selfie through a third party; Indeed and the employer never see the documents). (204652920, 31239896015245, 32004295623437,
  42787723284749, 42975377990797)
- **Application statuses ("My jobs" > Applied):** set by the candidate: *No longer interested, Interviewing, Offer received, Hired*; set by the
  employer: *Application viewed, Employer reached out, Not selected by employer* (and Interviewing, Offer received, Hired); set by Indeed:
  *Applied, Employer reviewing applications, **Response seems unlikely*** (employer has hired enough or **no activity for two weeks**), *Job
  closed*. **Withdraw application** is possible for jobs posted on Indeed (may not be able to re-apply; optional confidential reason survey not
  shared with the employer). Archive (auto-removed after 6 months); the list keeps 6 months; older history through a data request. (4412589551757,
  2026-07-24; 14164824787725)
- **Interviews tab:** interviews scheduled on Indeed appear automatically with live labels (starts in 4 hours, canceled by employer); the candidate
  can **join, reschedule or cancel** when the employer allows it; device test; past 6 months kept. (13267533394445, 2026-08-21)
- **Saved jobs** (removed after 6 months); **job alerts** created from a search with an email, edit title, location and **frequency**, pause
  with a toggle, unsubscribe per alert. (14087165677837, 204488890)
- **Profile visibility:** default **"Employers can't find you"**; "Employers can find you" lets employers view parts of the profile, recommend jobs
  and message through Indeed (only after **30 days of activity**). **Block up to 100 named employers** from finding the profile (not guaranteed;
  an application still shares the profile). Preferences: job titles (up to 10), job types, schedule, minimum pay (hidden from employers), relocation,
  remote, commute; "Hide jobs with these details"; **"Ready to work"** toggle (can start immediately). Employment eligibility (work
  authorization) is visible to employers. (204524164, 39751985005709 2026-08-26, 29271461896077, 32603395892109, 30135634166797, 40356306296589)
- **Contact privacy:** the candidate's email is replaced by an **auto-generated alias** (`…@indeedemail.com`) when applying; the phone number goes
  only to employers the candidate applies or responds to. (360037819631, 30135634166797)
- **Sensitive data removed from the built resume (2026-04-08):** citizenship, ethnicity, disability, ID number, date of birth, gender can no
  longer be stored or shared through the Indeed Resume. Demographic data is collected only through an **optional, confidential survey**
  (US, UK, Ireland, France), never shown to employers individually, used for fairness evaluation, deletable. (44889954769037, 360059972312)
- **Resume:** one uploaded file at a time (converted to PDF), "Sync to profile", share link, profile language; profile photos only in the US app,
  **moderated** (no IDs, addresses, links, QR codes, ads, sexual or violent content) and not shown to employers. A resume that breaks the guidelines
  is **hidden**; the candidate appeals with an ID, reviewed within 24 hours. (11314976176141, 28827071763981, 27517857907853, 25810344131597, 37740079842189)
- **Messages:** read receipts and **online status**, both opt-in in communication settings; archive or delete a whole conversation (irreversible);
  **Report Spam** (reason, the sender's job may be removed, future messages go to spam); messaging is **frozen while the employer account is under
  moderation**. (40356306296589 2026-09-22, 360040122551, 39525563214349)
- **Communication settings:** per channel and per topic: job and company alerts, resume contacts from employers, application and account status,
  recommended jobs, career guidance, surveys; SMS for recruiter invites and interview updates. (39525563214349)
- **Account:** minimum age 16 (18 in the EEA, UK and others), stable personal email and phone (no disposable email or burner number); Google or
  Apple sign-in; **passkeys** (become the default; email code still possible); 2FA on unusual sign-ins and **mandatory for employer accounts**;
  change email with a code to the new address. **Close account** is separate from **data deletion**, which goes through a Personal Data Request
  form (verification, about a month; copy, deletion or both). (360039056312 2026-09-28, 35932737328397, 6501388107021, 39462136433677, 10652026126605)
- **Trust and safety:** "Report job" from the job page or My jobs, categories *offensive or discriminatory, fake job, inaccurate, advertisement,
  other*; the job may be removed or edited; no feedback to the reporter. Scam guidance (fees, check and reshipping scams, ID requests); a
  page on **signs of labour exploitation** (pay below minimum wage, recruitment fees, ID documents withheld). (360028156452 2026-07-16, 38886273141389, 8173478443149)
- **Company reviews:** one review per person per company, only for current or former employers, anonymous (job title, status and location
  required), moderated before publishing, cannot be edited (a new review replaces it), can be deleted by the author; anyone can report a review,
  handled within 1–2 business days without feedback; a company representative may claim the page. (360046789111, 360026912831)

### 3.2 Kalibrr

Sources: `www.kalibrr.com/id-ID/employers`, `/jobseeker-guide` (**rendered**), and the help centre through its public Zendesk API
(`https://kalibrr-support.zendesk.com/api/v2/help_center/en-us/articles.json`, 202 articles, Indonesian and English; the help pages
themselves answer 403). Level: **direct**, 2026-10-11. Billing and package articles were skipped.

- **Job post and curation:** required minimum qualifications and description; optional custom questions and assessments; **public** (job board,
  shared to social media and syndicated to LinkedIn, Google and Indeed) or **private** (link only: confidential hiring, job fairs, external
  sourcing). Every public post and **every edit** goes through curation (automatic, or manual review within about 4 working hours / 12 business
  hours) against "consistent, correct, complete, clear, concise"; a rejected post shows the reason ("Show Reason"). **Links in job posts are
  not allowed** (an apply-redirect option instead). Default duration 2 months, adjustable deadline. (360016120311 2025-06-30, 360016125871,
  21661341539097, 21661059445785, 360016121851, 23033154446873, 360016121991)
- **Pipeline (ATS):** default states Applications, Shortlist, For Interview, Offered, Hired, Archived (No Show, No Response, Rejected, Withdrew);
  **custom states per job** sit between For Interview and Offered (no notification to the candidate; useful as a "pooling" state); moving to a
  default state **emails the candidate** (also app and SMS); move one or many candidates at once; filters by qualification in any state;
  "Applications In Progress" for incomplete applications. (360015904852, 21666575879065, 21383298302489, 22628880564761, 22629140629913)
- **Interview scheduler** in the For Interview state: date, address (company address by default), recruiter contact (shown only after the
  candidate confirms), time slots, interviewers (notified), message; the candidate confirms, declines or **asks to reschedule**; reminders by
  email and SMS **3 days, 1 day and on the day**; candidates marked **No Show** get their account flagged and are asked for a reason.
  (360016127091 2024-06-09, 360016125671)
- **Candidate profile for the employer:** work history, education, skills, summary, assessment results (4 levels: Outstanding, Good,
  Satisfactory, Needs Improvement), custom-question answers; **notes private or shared with the team, with @mentions** that email the colleague;
  "Forward Profile" to people without access; CSV export of applicants with custom-question answers; profile download. (360015904872 2025-04-07,
  21383510835353, 22630619687321)
- **Custom question types:** document upload, short text, long text, checkbox, **voice recording**; no limit on count. (360016126451 2026-07-28)
- **Messaging:** message templates with placeholders (candidate first name) and **automated triggers** (for example, an acknowledgement when a
  candidate applies; one job per template); candidate messages only after the employer accepts (shortlists) the application; delivery by inbox,
  email, app and SMS (verified mobile). **Response rate** per job (applications shortlisted or archived ÷ received) is **shown to candidates on
  the job post**. (360016127771, 360015906892, 360016102432 2026-09-26)
- **Sourcing:** Resume Search pools (all, applied, archived, pending invites, recommended, saved), filters "not invited / not applied / not
  archived", invite to apply one or many (paid, monthly quota); **Leads** per job: recommended candidates and **candidates who viewed or saved the
  job**. (360015901612, 360016126691)
- **Reports:** overview funnel, **candidate experience** (satisfaction score and feedback), job post performance with conversion rates,
  **hiring speed** per stage, **origins** (source channel and location), **team productivity** per recruiter. (360015909532 2025-04-07)
- **Team:** one company account, several recruiter accounts; roles **Administrator**, **Job Manager** (creates jobs, manages assigned
  pipelines), **Interviewer** (schedules interviews and writes notes on assigned jobs); per-job user access; invitation link valid 24 hours;
  company-domain email recommended. (360016125071, 21348473302937 2026-09-21, 21661930361625)
- **Company verification:** a "verified" badge after sending business registration documents (Philippines: SEC, mayor's permit, DTI;
  recruitment licence for agencies); for Indonesia the article ties the badge to a meeting and a paid subscription, with a company-domain email
  and an active website. (360015907512 2025-06-30, 27688375708057)
- **Job seeker side:** profile is the online resume and **visible to all employers**; one uploaded resume file, seen only by employers the
  candidate applied to; later profile edits are visible to employers on earlier applications; email and **mobile verification** required at
  sign-up; **hide the "actively looking" status from named companies** ("Sourcing Visibility"); "Who viewed my profile" (unlocked by a
  referral); job match analysis on each job; recommended jobs from profile and preferences; skills tests are part of some applications (cannot
  be retaken); application statuses: *incomplete, sent, seen by recruiter, being reviewed, invited for interview, for interview, you requested a
  reschedule, you declined the interview, interview completed, flagged as no-show/no response, rejected, declined the offer / withdrew*; the
  candidate can **withdraw**; invitations can be accepted (becomes an application) or declined (employer notified); re-applying to the same job
  depends on the company. Auto sign-out after 20 minutes idle. Recruiter account deactivation is self-service and deletes data. (360016052772
  2026-09-16, 360039393071 2026-08-05, 360016052712, 360054954212, 360016102532, 360016102472, 360016316551, 360016315971, 360041316831, 360015905572)

### 3.3 Dealls

Sources: `dealls.com/about`, `/pasang-loker-gratis`, `/terms-and-condition`, `/privacy-policies` (**rendered**, 2026-10-11). No public help
centre was found. Facts below are the platform's own statements; marketing claims are marked as such.

- **Job seeker (about page, marketing wording):** "Apply Jobs with Priority Slot" (the platform matches the profile and places the application
  first for recruiters), "Full Transparency" (live updates on the application status), "Get Approached by Recruiters" (profile shown to
  recruiters), a **profile rank from resume screening** (a user quote mentions "Top 10%"), free one-to-one **mentoring** with booked
  sessions, an **AI CV Analyzer**. Dealls states it is an ATS where companies publish jobs directly, not a recruitment agency.
- **Employer (feature page, marketing wording):** free job posting, AI CV screening, advanced filtering, talent search, recruitment analysis
  reports, **automated emails and take-home tests**; the ATS is sold together with an HRIS ("KantorKu").
- **Interview scheduling (terms and privacy notice):** optional **Google Calendar integration**; after the candidate **picks a slot**, the
  event with a Google Meet link is created, updated or cancelled automatically; calendar data is not used for marketing or AI training.
- **Data subject rights (privacy notice, Indonesian personal data law):** correct data, withdraw consent (may end the account), access and
  copy, **erasure**, restriction, objection, portability, complaint; data kept only as long as needed, then deleted or anonymised; where Dealls
  processes data for a client (the employer), requests are coordinated with that client as controller.
- Job listings carry a standing warning that companies on Dealls never ask for personal data, bank details or fees when applying (seen in
  round 2 search results on job pages; **search-summary**).

### 3.4 Jobs.id

`jobs.id` and `www.jobs.id` now **redirect (HTTP 302) to `karir.com`** (checked 2026-10-11); both belong to PT Qerja Manfaat Bangsa. There is
no separate Jobs.id product to compare; karir.com (section 1) covers it.

### 3.5 TopKarir

`topkarir.com` resolves but **does not answer** (connection timeout, 2026-10-11). The latest web.archive.org snapshot (2026-06-09) shows a
job board with internships, scholarships, training ("TopEdu"), entrepreneurship and career tips, with promotion banners dated 2021. The help
or feature pages have no snapshot. **Nothing verifiable to compare**; TopKarir is left out of the gap analysis.
