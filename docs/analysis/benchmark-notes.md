# Benchmark notes (step 2)

Benchmarks: Glints, JobStreet, LinkedIn, karir.com, loker.id. Plan, billing, and paid tiers are out of scope and not compared.

**Verification levels.** Only facts stated in public sources are recorded. Items marked *search-summary* were read through
web-search summaries of official pages because the pages returned HTTP 403 on direct fetch (JobStreet/SEEK domains, loker.id,
Glints help center). Absence from this list does not mean a platform lacks the feature; it only means it was not verified.

Collected on 2026-10-03.

# Benchmark notes (step 2) — verified from public sources only

## Glints
Sources: glints.com/id/lowongan/chat-untuk-melamar/ (fetched), glints.com/id/lowongan/aplikasi-lowongan-kerja/ (fetched),
help.glints.com articles (403 on direct fetch; content seen only through search summaries).

Job seeker (verified):
- Chat to Apply: chat directly with recruiter; recruiter online status; new chat notifications
- Application progress/status tracking (app + website)
- One-tap quick apply ("Lamar Cepat 1x Tap")
- Filters: education level, work experience, location; salary range shown on listing
- Notifications for new jobs matching criteria

Employer (verified via help.glints.com search summaries):
- Employer dashboard: active/inactive job list
- Manage Candidates: search applicants by name and by status stage, filters matching job criteria, change status, bulk status change (same status only)
- Candidate Matches: recommended candidates per active job
- Glints Chat integrated with Manage Candidates; employer can initiate chat; chat notifications
- Application stages: Applied, Connected, Skill Test, Interviewing, Offer Sent, Hired, Not Suitable (+ "Chat Initiated")
- Rejection flow; report inappropriate candidate behavior
- Job post: title, role, work address, requirements (gender, age, skills, education, experience); up to 3 custom pre-screening questions (tied to a paid tier -> billing, out of scope)
- View candidate CV/resume and profile
- Employer mobile app exists (post job, manage)
Out of scope (billing): Glints VIP.

## JobStreet (SEEK)
Sources: all jobstreet.com and *.employer.seek.com pages returned 403 on direct fetch; facts below come from
web-search summaries of official SEEK/JobStreet pages (NOT read directly) -> mark as "search-summary verified".

Job seeker:
- Job search; profile management/online profile; saved searches; saved jobs ("save to apply later"); job applications with status tracking
- Career advice, explore careers, explore salaries (salary reports), company information
- LiNa job alerts by email: up to 3 alert profiles; criteria = location, specialization, position level, years of experience, full/part time
- Mobile app: daily personalised alerts, access to saved and applied jobs

Employer:
- Screening questions: recommended automatically from job title/role/specialisation/location; select up to 5
- Candidate Management: status folders pre-screen, shortlist, interview, offer, accept, not suitable; bulk actions (email selected, move to status);
  "not suitable" candidates notified after 48 hours
- Talent Search (Basic / Premium tiers -> tiering is billing, out of scope): detailed candidate profiles (work history, education, skills),
  send personalised email / invite candidate to apply, reach candidates who have not seen the ad; SEEK Pass verification
- Candidate Matches (recommendations)

## LinkedIn
Sources (fetched directly, linkedin.com/help): a508716 (after you apply), a511279 (job alerts), a519651 (screening questions), a517545 (post a job);
search summary also cited a512329 (view jobs applied), a511260 (search jobs), a413520, a412523.

Job seeker (verified):
- Easy Apply (or external Apply depending on employer settings); can confirm which resume was submitted
- Job tracker: Applied tab; application updates = "application viewed" and "resume downloaded" notifications (no guarantee of reply)
- Save jobs
- Job alerts: created from a search, daily or weekly, email and/or app notification, max 20, toggle off per alert
- Signal "open to" recruiters (mentioned as related feature)
Employer (verified):
- Post a job (free or promoted); title; AI-drafted description option; "#Hiring" profile frame
- Screening questions with types: background check, education, location, language, certifications, work experience, work authorization, visa status,
  skill expertise, hybrid work, industry experience, custom, urgent hiring need; "must-have qualification" flag
- Rejection settings: auto-archive applicants failing must-haves + automatic rejection email
- Applicant management: filter, sort, rate applicants in one place; direct messaging; applicant update emails via LinkedIn or external site
- Reject applicants flow (Recruiter)

## karir.com
Sources: karir.com/info/faq-seeker (fetched), karir.com/employers (fetched), search summary of karir.com home page.
Job seeker:
- Registration via homepage form + email activation; complete resume required to apply; resume visible to all companies by default, contact info only to "prospects"
- Apply button turns into "applied"; one-click apply without re-filling forms (home page summary)
- Profile + CV generator (1-click CV), profile matching / daily job recommendations, preferences + job alert, application status tracking,
  interview schedule and recruiter messages in one place (home page summary, not FAQ-verified)
Employer:
- Job posting with auto-matching, online test modules, media engagement; unlimited resume search (database CV)
- ERIKA e-recruitment (applicant management, paperless); Customized Recruitment Project (service); employer branding page per employer
- Salary benchmark tool; industry pages. (Midtrans online payment = billing, out of scope)

## loker.id
Sources: loker.id pages 403 on direct fetch; search summaries of official loker.id pages only.
Job seeker:
- "Lamar cepat" quick apply (application goes straight to the company)
- Email job alerts with filter by keyword, category/interest, education, location
Employer:
- Post job online; receive applications in dashboard
- Talent Search (filter position, skills, location, experience, education) + Job Invitation to candidates
- Job Application menu: download resume, message candidate; ATS (manage whole pipeline from one dashboard)
- Screening questions (auto filter before application enters dashboard); Talent Pool (save and rehire); CSV export of applicants
- Free and paid tiers exist (pricing out of scope)
