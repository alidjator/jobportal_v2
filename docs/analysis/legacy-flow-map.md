# Legacy flow map (karir.pkp web application)

Source of truth for current flows: the **web application** of karir.pkp (`/usr/share/nginx/html/karir.pkp`, live at
`https://karir.satu.solutions`). The legacy REST API (`routes/api.php`) is considered stale and is **not** the reference.
Mapped on 2026-10-03 from the source (`routes/web.php`, `routes/website.php`, `routes/admin.php`, controllers, services,
middleware) and from the live site (login and read-only browsing). Billing, plans, payments and featured/highlight
promotion are intentionally out of scope and not listed.

Production scale seen in the admin dashboard: 136 candidates, 3 companies, 149 jobs (4 active, 145 expired, 0 pending).

## Job seeker

- **Registration**: email + password only (name and username derived from the email prefix). Optional campaign link
  `/{slug}/register` records the registration source (`candidates.campaign_channel`). Email verification when enabled.
  Login by email/password and Google (web uses Socialite; legacy API used Firebase tokens).
- **Profile settings (6 tabs)**: Profile (photo, name, phone, birth date, gender, marital status, religion, expected salary,
  languages, skills, experience level, availability status, available-in date, address: province/city/subdistrict/village/postal code),
  CV/Resume (several PDF files), Experience, Education, Social media, Account (change email, job-alert roles + opt-in,
  profile and CV visibility, change password, close account).
- **Discovery**: public job list with filters (keyword, location, job type, salary range, category); job detail
  (level, education, experience, quota, salary + period, location, dates, category, role, type, tags); save job; autocomplete.
- **Apply**: modal with resume selection and cover letter; requires profile, experience and education to be complete.
  Screening-question inputs are rendered but **their answers are never stored**.
- **After applying**: "Applied Jobs" page shows the stage name (company-defined group); favorites; notifications;
  job-alert list (a notification list, not a configurable alert); messenger with recruiters.
- **Training**: courses pulled from a Moodle instance via web service (16 courses shown).

## Recruiter (company)

- **No self-registration**; accounts are created by an admin. One company is bound to one user (`companies.user_id`).
- **Onboarding**: account-progress steps (company info, founding info, social links, contact). Document verification page exists
  but is **not linked in the menu** (reachable by URL only).
- **Jobs**: create/edit/clone, expire/activate. New jobs become `active` if `job_auto_approved` is on, else `pending`
  for admin approval. Edits to active jobs create a pending copy (`parent_job_id`) when `edited_job_auto_approved` is off.
  Job form: title, tags, work time, category ("scope"), role ("position"), salary (range/custom, period, show salary),
  education, experience, job type, level, vacancies, deadline, description, soft/hard skills, benefits, location (province to subdistrict),
  screening questions, apply destination (internal / external URL / email).
- **Applications**: kanban board with company-defined stages ("application groups"), drag and drop, candidate detail
  with contact info, message and WhatsApp actions. Moving a card sends **no** notification to the candidate.
- **Screening questions**: reusable per company, text + required flag, feature toggle per company.
- **Candidates**: search with filters (skills, experience, education, location, sort), saved candidates with categories, CV-view log.
- **Campaigns**: registration links with QR code and referral code, 7 types (job fair, education institution, government
  institution, training institution, partner, job marketplace, social media), active period; 11 campaigns in production,
  some with legacy slug formats.
- **Messenger**: one-to-one chat with candidates, real-time broadcast, unread count.
- **Public company profile** page.

## Admin

Dashboard counters; companies (status toggle, email verification, profile verification, document review); candidates
(register-via source, export CSV/Excel); jobs (filters, bulk import, clone, pending-edited-jobs approval); applied jobs;
13 master-data modules (categories, roles, industries, professions, skills, tags, benefits, organization types, salary types,
education, experience, team sizes, job types); testimonials; FAQ; settings (preferences, social login, cookies, SEO, pages, custom CSS/JS,
CMS, affiliate, email templates, general, language, country/state/city, theme, SMTP, currency, ad placement, system info).
The admin account used for mapping did not show Users, Roles, Blog, Newsletter or plan/order menus (likely permission-limited).

## Not mapped

Flows that need write actions on production (apply, post a job, send a message); the modal-only campaign detail;
kanban stage names (rendered by JavaScript); admin menus hidden for the account used.
