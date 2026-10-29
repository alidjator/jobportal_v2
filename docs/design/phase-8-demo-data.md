# Phase 8: demo data for the development domains

Work the front end asks for (`frontend.md` section 13.1 point 1, step 0). Designed and coded on 2026-10-21.

## Decisions

- 2026-10-21: one artisan command, `demo:seed`, in a new operational module `src/DemoData` (no API); refused when `APP_ENV=production` ([detail](#command)).
- 2026-10-21: the data is written through the use cases the API runs, with admin and member actors, not by direct table writes; no new exception to the
  code standard ([detail](#how-it-writes)).
- 2026-10-21: one random password per run for every demo account, printed once at the end of the output, never stored in docs, logs or `.env` ([detail](#command)).
- 2026-10-21: the run is refused when a demo account already exists; to seed again: `migrate:fresh`, `db:seed`, `demo:seed` ([detail](#command)).
- 2026-10-21: both demo companies have `recruitment.requisition_required` overridden to off (by the demo admin), so Workspace posts jobs without the
  ERP requisition flow, which is tried with the ERP pages (step 7); the override can be reset in Console ([detail](#contents)).
- 2026-10-21: a demo platform admin (`super_admin`) for Console is created too; it enrols its second factor at the first sign-in ([detail](#contents)).
- 2026-10-21: the contents as proposed: a group with two companies (one verified, one with documents waiting), one account per preset, 8 job seekers,
  7 jobs, 14 applications at every stage, emails on `@demo.onesis.test` ([detail](#contents)).
- 2026-10-28: the demo workforce (approval flows, manpower plans, requisitions and their approval paths) is written by `DemoWorkforce` at the end of `demo:seed` and alone as `php artisan demo:seed-workforce` ([detail](#workforce)).
- 2026-10-28: the demo screening presets and the demo campaigns (with the job seekers who signed up through them) are written by `DemoPresets` and `DemoCampaigns` inside `demo:seed` and alone as `demo:seed-presets` and `demo:seed-campaigns` ([detail](#screening-presets-and-campaigns)).
- 2026-10-29: the work that waits in the lists "Wawancara saya", "Review kandidat" and "Penilaian tes saya" is written by `DemoMyWork` inside `demo:seed`, through the use cases of the members and the candidates; the assessor of the UI/UX test is the recruiter, because a hiring manager does not see an application that has not reached the boundary of the job ([detail](#my-work)).

## Command

`php artisan demo:seed` (after `migrate` and `db:seed`, and `identity:sync-permissions` so the `super_admin` role exists). It prints a table of the
accounts (application, role, name, email) and then the one password, made with 20 random letters and digits. The password is only in the output: keep
it in a password manager. A second run stops at once with `demo-data-exists` and changes nothing. A run that fails halfway leaves what it wrote, so the
database is rebuilt as for a second run.

## How it writes

`src/DemoData` drives the use cases of the other contexts as a client does (like a controller, from outside the context): `CreateAdmin`, `CreateGroup`,
`CreateCompany`, `AcceptInvitation`, `InviteMember`, `SetGroupRole`, `CreateLegalEntity`, `LinkTenant`, the document submit and approve use cases,
`CreateJob`, `ReplaceJobAssignees`, `PublishJob`, `ApproveJob`, `RegisterJobSeeker`, `VerifyEmail`, the profile use cases (`UpdateMyProfile`, `AddMyExperience`, `ReplaceMySkills`, `ReplaceMyLanguages`, `ReplaceMyLinks`, `UploadMyResume`), `ApplyToJob`,
`ProcessApplication`, `RespondToOffer`, `WithdrawMyApplication` and `CloseJob`. The reference ids come from the reference use cases (the first active
items, the first city of the first province). So the history, events, audit and analytics records are those of real use.

For the run only, the command swaps four ports in its own process (`DemoDataServiceProvider::useDemoAdapters`): the invitation and verification-code
senders keep the token and the code in memory (`InMemoryDemoInbox`), the invitation link is the plain token (no `COMPANY_INVITATION_URL` needed),
and the registration limit per IP is off. Other emails the use cases send go to the `.test` addresses through the configured mailer (`log` on the
development site). Each session that an acceptance or a registration opens is ended. A company admin gets no session from the acceptance but the
second-factor challenge (a company admin always enrols one, at the first real sign-in), so the run finds the account through `ListAdminUsers`.

## Contents

| What | Detail |
|---|---|
| Platform admin | `admin@demo.onesis.test`, `super_admin` |
| Group | "Grup Demo Nusantara", two legal entities, one per company; both companies have `recruitment.requisition_required` overridden to off |
| PT Demo Sinar Teknologi | verified (its required documents approved); company admin (also a group member with every group permission), recruiter, hiring manager, interviewer (`*.sinar@demo.onesis.test`) |
| PT Demo Arunika Ritel | company admin only (`admin.arunika@demo.onesis.test`); required documents submitted and waiting for review (Console D5) |
| Provider and psychologists | "Biro Psikologi Demo Insight" (code `DEMO`) with a lead and a member psychologist (`psikolog.*@demo.onesis.test`), licences valid five years; the second factor is enrolled at the first sign-in; no battery yet (`DemoProvider`) |
| Job seekers | 8, verified email, profile with phone, location, one past job, three skills, two languages, a LinkedIn link (and a GitHub link for every second one) and one PDF resume (`<first>.<last>@demo.onesis.test`) |
| Jobs | Sinar: Backend Developer, UI/UX Designer, Data Analyst (active), Mobile Developer (draft), Customer Support Officer (closed); Arunika: Store Supervisor, Merchandiser (active at once: a tenant of a group counts as verified, `erp-governance.md` 1.2) |
| Second factor | the group asks every member of its tenants for one, so every Workspace account enrols TOTP at its first sign-in, like the platform admin |
| Interview kits | 6 in the library of the verified company (backend, design, data, support, structured behavioural, hiring manager's closing), Indonesian, with guidance, weights and score anchors; set as the default kit of the interview stage of the backend, mobile, UI/UX, data and support jobs, so the scheduled interviews copy them (`DemoInterviewKits`) |
| Screening questions | 11 in the library of the verified company (number, yes/no, single and multiple choice, text); each Sinar job uses 3 to 5 (the default limit) of them, with required, must-have and ideal answers, and every application carries answers (some fail a must-have on purpose); the questions are set before the applications because a job's questions lock once it has applications (`DemoScreening`, `DemoScreeningContent`) |
| Psychological batteries | 3 published short batteries of the demo provider (6 with `demo:seed --full-batteries`, which adds several minutes), written by its lead psychologist and granted to the verified company (its `assessment.psychological_enabled` is on): Professional Talent Assessment, Leadership Competency Profile and Operational Readiness Test, each in a short version (a few items per subtest) and, with the option, a full one with the benchmark's size (570 questions and 150 minutes, 570 and 150, 420 and 90; `benchmark-teleinterview-assessment.md` 3.3). Subtests of the IST kind (sentence completion, odd one out, analogies, similarities, arithmetic, number series, logic), Kraepelin speed columns (10 columns short, 20 full, not counted as questions), a checking subtest, and work-attitude or leadership Likert scales; each with a five-band norm table per scale, a report template, an explanation template and a recommendation rule. The items are original and generated from word, number and behaviour banks, not copied from any published test; repeated words and sentence patterns are expected in the full versions (`DemoBatteries`, `DemoBatteryContent`, `DemoPsychometricBanks`) |
| Assessment library | 6 published skill tests of the system library written by the platform admin (numeric reasoning, reading comprehension, business English, spreadsheet basics, digital literacy, work ethics), auto-scored, for every company to send (`DemoLibrary`, `DemoLibraryContent`) |
| Assessment tests | 4 published skill tests of the verified company (backend, data, customer support, UI/UX), auto-scored, Indonesian; each is the default test of a job's assessment stage (5 jobs), with a 70 % advance rule to the interview stage for backend and data; no application enters the assessment stage, so no invitation is sent (`DemoAssessments`, `DemoAssessmentContent`) |
| Applications | 14: applied 5, screening 3, interview 2 (online, with the interviewer, from three days ahead), offer 1, hired 1 (an accepted offer), rejected 1, withdrawn 1 (by the job seeker) |
| Organisation structure | 5 unit types (Direktorat, Divisi, Departemen, Cabang atau toko, Seksi), 9 grades (G1 to G9), and **6 legal entities** with their cost centres, unit trees and positions: the two companies' own (Sinar: 24 units and 33 positions, with the titles of the demo jobs; Arunika: 15 units and 23 positions, stores under regional departments) and four more made by the group admin (a listed holding, a logistics company, a financing company, an education foundation: 10 to 15 units and 12 to 21 positions each). Written by `DemoStructure` through the structure use cases, from the content of `DemoStructureContent` (92 units and 125 positions in all). |

Names and texts are fictitious. The texts the front end shows (job descriptions, headlines, the offer note) are Indonesian, as real postings are;
code, comments and documents stay English.

## Organisation structure

`demo:seed` also writes the structure at the end of its organisation step. The same code runs alone as `php artisan demo:seed-structure`, for a database that already has the demo group: it signs in as the group admin of the
verified company (`admin.sinar@demo.onesis.test`, who holds every group permission), finds the two companies' legal entities by their legal names, makes the four others when missing, and creates every unit type, grade,
cost centre, unit and position that does not exist yet by its code. It can therefore run again and only adds what is missing; it stops with `demo-structure-needs-demo-data` when the demo group is not there, and is refused in
production. A unit tree is written parent first, and a position after the position it reports to, because the content lists them in that order. No migration.

## Workforce

`demo:seed` ends with the workforce (`DemoWorkforce`, from the content of `DemoWorkforceContent`); the same code runs alone as `php artisan demo:seed-workforce` after `demo:seed-structure`. It does nothing when the group already has an approval flow (the first thing it writes), stops with
`demo-workforce-needs-structure` or `demo-structure-needs-demo-data` when what it needs is missing, and is refused in production. Everything goes through the use cases of the API, as the people of the demo act: Rina (company admin of the verified
tenant, requester of its plans, holds every group permission), Hendra (hiring manager preset, requester of most requisitions), Sari and Maya (hiring managers of them), Agus (company admin of the second tenant). No migration.

- **Roles and flows.** Hendra gets the group roles `group.workforce.view` and `.manage` (so the step of that role has a holder besides the requester). The group has one default flow for plans, requisitions and amendments: one step, the holders of
  `group.workforce.manage`; the second tenant uses it. The verified tenant has its own: plan, the hiring manager Hendra and, for 5 or more additions, the group role; requisition, the hiring manager, then Rina for 3 or more hires, then the group
  role for a highest salary of 20 million or more; amendment, the hiring manager and Rina for 3 or more hires. Hendra delegates to Sari for 14 days from the run.
- **Plans.** Verified tenant, fiscal year of the run: version 1 of 10 lines approved in both steps (17 additions), version 2 (a revision, 11 lines) submitted and waiting for the first step; the next year a draft of 4 lines (a draft someone already started by hand for that year gets these lines). Second tenant: the plan
  of the year of 5 lines approved through the group flow, and the draft of the next year made on its behalf by the group (`PlanSide::Group`). The additions are spread over the months, every other month from February.
- **Requisitions (13).** Verified tenant (10): approved with a job opened of one of two hires; waiting at the hiring manager; unplanned and above the budget, past the first step and waiting for the group role; a contract replacement approved,
  one job opened and then closed; three hires approved in two steps and an amendment for a fourth waiting for Rina; rejected; a draft; returned for revision; cancelled; written by Rina and approved by Hendra. Second tenant (3): approved with
  two vacancies opened, waiting for the group role, and a draft. The target dates are 30 to 80 days from the run, kept inside the fiscal year of the run, so the plan lines are the quotas.
- **Output.** The command prints what it wrote (flows, plans, requisitions, approvals, jobs). Draft jobs made from a requisition are real draft jobs of the two companies.

## Screening presets and campaigns

Both run inside `demo:seed` (the presets after the screening library of the verified company, the campaigns before the job seekers) and alone as `php artisan demo:seed-presets` and `php artisan demo:seed-campaigns` for a database that has the demo
group. Both skip what exists (a question by its text, a preset by its name, a campaign by its code, an account by its email), stop with a `demo-...-need-demo-data` code when the demo accounts are missing, and are refused in production. No migration.

- **Presets (9).** The company admin of each company saves them through `CreateScreeningPreset` from the library, making the few questions the library lacks (`domicile`, `education`, `english`, `leadership` for the verified company; a small library of
  five for the second). Verified company (7): backend engineer (middle), graduate engineer (junior), product designer, data analyst, customer service and operations (junior), managers and heads of division (senior) and a short one for every
  position; second company (2): store staff (junior) and store supervisors and managers (middle). Each keeps to 5 questions, the default `recruitment.max_screening_questions`, with required and must-have flags and the ideal answers of the must-haves.
  Nothing applies a preset to a job, so the jobs of the demo keep their own questions.
- **Campaigns (8).** The platform admin makes them through `CreateCampaign`: platform ones (a job fair, a government programme that has ended, a training institution without an end date, a webinar switched off) and company ones (a campus day and a
  community of the verified company, a job board and a social media campaign that has not started of the second). The periods are counted in days from the run, so one of each state always exists.
- **Results.** The results of a campaign count the accounts that signed up with its code, their applicants and their applications. Six of the eight job seekers of `demo:seed` sign up through a running campaign, so their applications count; 31 more job seekers
  (no profile, no application) sign up through the five running campaigns, as a visitor does with the link (`RegisterJobSeeker`), and get one random password, printed once by `demo:seed-campaigns`. A campaign that is not running takes no sign-up, so the
  ended one has no results. The sign-ups all carry the day of the run, so the daily series is one day.

## My work

`DemoMyWork` runs inside `demo:seed`, after the applications, on the verified company (content in `DemoMyWorkContent`; applications are named `"{job key}:{seeker}"`). It writes nothing directly: members and candidates use the same use cases as the API. No migration, no command of its own. A refusal names its step (`The demo work, answer of interviewer to backend:6, was refused: ...`).

- **Wawancara saya.** The five interviews of the demo get their panel (backend:2 adds the hiring manager, data:1 the recruiter, data:5 the interviewer) and six answers through `AnswerInterviewRsvp`: the interviewer accepts backend:2 and backend:7 and declines backend:6 with a note, the hiring manager accepts backend:2 (note) and data:5, the recruiter accepts data:1. The result is accepted 3, declined 1, pending 1 for the main interviewers and accepted 2, pending 1 for the extra members.
- **Review kandidat.** The recruiter asks the hiring manager for two shortlists through `CreateReviewRequest` (backend: 2 applications; data analyst: 3), and the hiring manager answers three of the five: advance 2, maybe 1 (with a reason). The other two stay awaited.
- **Penilaian tes saya.** The UI/UX test is the default test of its job and has a case study (long text). Three candidates (seekers 3, 4, 5) are invited when they reach the assessment stage; seeker 3 takes it with complete answers and an assessor scores the case study (`scored`), seeker 4 takes it with weaker answers and waits for the score (`pending_scoring`), seeker 5 has not started. The candidates start the test with `StartAttempt` and carry its session token as the device does (`AttemptSessionToken` is set by the seeder, as the HTTP middleware does).
- **Assessor (decided 2026-10-29).** The assessor of the default test is the **recruiter**, not the hiring manager: a hiring manager bound on a job sees only the applications that reached its boundary or were sent to them (`HiringManagerScope`), so scoring an application that was not sent is refused with `Application not found`. A member who is not assigned to the job (the interviewer preset) cannot be an assessor either (`invalid-assessor`). To give the hiring manager scoring work, the UI/UX application must first be put in a review request.
- **Data Analyst must-haves.** Two applications fail them on purpose: seeker 7 (will not work onsite) and seeker 5 (SQL "Dasar", which also holds an interview, added for the interview lists).
