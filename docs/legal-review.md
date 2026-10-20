# Legal review brief: personal data, selection criteria and psychological tests

For the legal adviser of **ONESIS ERP** (One Solutions Integrated System) and its job seeker site **ONESIS Career**, operated in Indonesia. The ERP's
first module is talent acquisition (jobs, applications, interviews, psychological tests, offers); the job seeker site is public. The brief lists what the
system does, or is designed to do, with sensitive personal data and what we need answered. It is a factual inventory, not legal advice, and the legal
references in the questions are unverified suggestions of where to look. Written 2026-10-09, brought up to date 2026-10-21 (not sent yet).

## 0. Priority of the answers

The questions keep their numbers (the design documents refer to them). "Built" means the behaviour exists in the code, switched off or at a safe default
where the question is open; "designed" means it is planned and waits for the answer before it is built.

| Priority | Questions | Why | State |
|---|---|---|---|
| **A. Before go-live** | 1 to 7, 10, 23 to 25 | the terms and the privacy policy must be final; the sensitive filters, the age limits and the CV details need a ruling (they stay off or at their defaults until then); the recordings and the operator's general duties (registration, data protection officer, breach notice, roles, where the data is kept) must be settled | built; 24, 25 are duties of the operator |
| **B. Blocks building a feature** | 12, 13, 15, 20, 21 | the online offer acceptance, restricted candidates, the applicant data retention per company, candidates entered from outside the platform and pre-employment checks are not built until answered | designed |
| **C. Before the first psychology partner or in-house psychologist** | 11, 17, 19 | automatic reports, raw data retention and in-house signers; psychological tests are switched off until then | 11, 17 built (auto issue off); 19 designed |
| **D. Policy text, or after go-live** | 8, 9, 14, 16, 18, 22 | the answers change the policy text or a later feature; the system works with the present behaviour meanwhile | 8, 9, 14 built; 16, 22 designed; 18 a plan |

## 1. What the system does

| Area | Behaviour (today, or designed where marked) |
|---|---|
| Data collected | A job seeker may give birth date, gender, marital status, religion and nationality (all optional), and a photo. The profile owner and the companies the person applied to can see them. |
| Candidate search (R9) | A company member searches job seekers who opted in. A search can **filter** by religion, nationality, age range and gender. The values are never shown to the company in the search; they only narrow the results. **Switched off by default** (setting `job_seeker.candidate_sensitive_filters_enabled`, false until this review clears it): a search that uses one is refused with 422 `filter-not-available`. |
| Account age limit | A job seeker whose birth date gives an age outside the minimum (default 15) and maximum (default 60) is refused. Both limits are admin settings (minimum 13 to 30, maximum 40 to 100; 100 switches the upper limit off). |
| CV generator | A job seeker may print photo, birth date, gender, religion, marital status and nationality on their own generated CV. All are off unless the job seeker ticks them for that CV. The CV can be attached to an application. Expected salary, street address and WhatsApp number are never printed. |
| Matching and alerts | Job recommendations, candidate recommendations, the match percentage and job alerts use **no** sensitive attribute: only skills, location and work mode, experience, education, salary and role. |
| Profile views | A company member opening a candidate profile or resume is logged (company, member, candidate, time); the candidate sees which companies looked. Contact data stays hidden until the candidate applies. |
| Job views | Planned (dashboards): each view of a job is logged with the account id, or a daily-salted hash of IP and user agent for a visitor who is not logged in, for 90 days; afterwards only a daily count per job remains. Companies see the counts, never the viewers. |
| Application source and match score | Each application records where the applicant came from (search, recommendation, alert, direct link, invitation, shared link or entered by the company) and the match percentage at the time of applying. Both are used only in aggregate analytics for the platform and the company admin (groups under 5 are hidden); no endpoint returns them for a person. |
| Company reviews | A job seeker who applied to a company may review it; an admin approves it first, and the author's **name is always shown** with a published review. A company may reply and may report a review. When the account is erased, the text of a published review is deleted and the ratings stay. |
| Talent pool | A company member may save a candidate who can be found (opted in) into the company's talent pool with a category and a **free-text note of up to 500 characters**, seen by every member of that company only, never by the candidate. The entry and its note are hidden while the candidate cannot be found; the note is deleted by a nightly job after that (the entry stays), and the entry and note are deleted when the account is anonymised or a member removes the entry. |
| Erasure | Account deletion anonymises the account and erases the profile, resumes, alerts, invitations, views and CV records. |
| Interview and video-answer recordings (Q23) | A company may mark a video or voice interview as recorded. The candidate consents or refuses when confirming the schedule and may withdraw until recording starts; refusing does not cancel the interview. A team member starts the recording by hand, every participant sees an indicator, and a voice call plays "this call is recorded" to the candidate. A test with video-answer questions needs a separate recording consent, and the candidate may decline the test. Recordings are kept on the platform's private storage, streamed only to the company members who may see the application, and deleted 90 days after the interview or the test (admin settings), earlier when the application is anonymised or a team member deletes them. |
| Company team activity (Q10) | A company admin sees each member's activity, including the IP address and browser of their sign-ins, and one activity log of the whole company that can be exported as CSV; kept 12 months. |
| Psychological tests (Q11, Q17 to Q19) | Licensed psychologists (STR and SILP) of a partner institution, or the customer's own in-house psychologists (designed), build test batteries and sign the reports; a company uses a battery only when the platform switches psychological tests on for it. The candidate consents before the test and gets a plain-language explanation. A provider may be allowed to issue reports automatically from its norms (off by default until question 11 is answered). |
| Offers (Q12) | Designed: the job seeker accepts an offer letter online with a checkbox and their password; the time, IP address, browser and a hash of the letter are kept as evidence. |
| Restricted candidates (Q13) | Designed: a company may mark a job seeker as restricted for a period with a reason; their applications are flagged and they are hidden from the company's search, without being told. |
| Data download (Q14) | A job seeker can download their data; what companies and psychologists wrote about them is left out. |
| Applicant data retention (Q15) | Designed: a final application is anonymised on the company's side after a period the company chooses (6 to 60 months, default 24). |
| Reported chats (Q16) | Designed: a reported conversation keeps the reported message and up to 10 earlier ones for platform admins, deleted 180 days after the decision. |
| CVs from outside the platform (Q20) | Designed: a company enters a person's name and email and may upload the CV it received by email, at a walk-in or through a referral; the person is invited to sign up and apply; the CV is seen by the team only and deleted after 30 days without a response. |
| Pre-employment checks (Q21) | Designed: medical check-up, background check, police record and reference checks per applicant, each with the candidate's consent in the platform; the medical result is a category only (fit, fit with notes, unfit); outcomes are seen by a dedicated permission only and every read is audited; medical outcomes and check files are deleted 90 days after the application ends. |
| WhatsApp notices (Q22) | Designed, after go-live: job seekers who opt in and confirm their number with a WhatsApp code get interview, test, offer and check reminders by WhatsApp, through the official Meta API or, where an installation chooses it, an unofficial gateway. No message text or number is kept in the log. |

## 2. Questions

1. **Discrimination in recruitment.** Do the filters by religion, gender and age in the candidate search conflict with the prohibition of discrimination in employment (for example Law 13/2003, Articles 5 and 6, as amended)? If so, is the platform liable for offering the tool, or only the company that uses it? Does the answer differ for nationality (a work permit rule can be a legitimate criterion)?
2. **Personal data classes.** As we read it, the list of specific personal data in the Personal Data Protection Law (Law 27/2022, Article 4(2)) names health, biometric, genetic, criminal record, children's and personal financial data, and other data set by law, but not religion or marital status (unlike the GDPR). Is that right, or does another law make them specific personal data? If they are general personal data, does their risk of discrimination still call for more than the present optional field with the general terms (a separate explicit consent, a notice of who sees them)? Job seekers under 18 are covered by question 3.
3. **Age limit of accounts.** Is refusing job seekers above 60 (or any configured limit) lawful when it limits the account, not one job? Which minimum age is right (15 now)? The data of a person under 18 is children's data, a class of specific personal data (UU 27/2022 Article 4(2)), whose processing needs the consent of a parent or guardian (Article 25): must the platform get that consent at sign-up, and how must it be checked, or should the minimum be 18? Do the child labour rules (Law 13/2003, Articles 68 to 75) limit which jobs a job seeker under 18 may see or apply to, and does the platform or the company answer for that? (Updated 2026-10-21.)
4. **Personal details on a CV.** May the platform offer these fields on a generated CV when the job seeker chooses them, given that common Indonesian CVs carry them? Should the platform add a warning, or hide some of them?
5. **Protected attributes as search results.** Companies cannot see these values in the search, but they can infer them by filtering repeatedly. Is that a concern if the filters are later switched on?
6. **Terms and privacy policy.** What must the documents say about the candidate search (opt-in, view log, what the company sees), the CV generator and the processing for matching? Is a data-processing agreement with companies needed?
7. **Records and retention.** How long may the view log and the CV generation records be kept, and what may the platform keep after an account is anonymised?
8. **Company reviews.** Who answers for a defamatory review (the author, the platform as the host)? What notice-and-takedown process should the terms promise, and is showing the author's real name lawful and wise? How long may a rejected review or a report be kept?
9. **Talent pool notes.** Is a company's private note about a candidate personal data that the candidate may ask to see or have corrected, and what must the privacy policy say about it? Should the platform refuse notes about sensitive attributes or add a warning next to the field?
10. **Member activity seen by the company (added 2026-10-13).** A company admin sees the activity of each team member, including the IP address and browser of their sign-ins (`phase-8-company-permissions.md`). What must the privacy policy and the terms for companies say about it, and may it be kept for the 12 months of the audit log? Added 2026-10-14 (`phase-8-company-admin.md`): the
    company admin also sees one activity log of the whole company (sign-ins, CV and report downloads, exports) and may export it as CSV.
11. **Psychological reports issued automatically (added 2026-10-14).** A partner provider may let the system issue a psychological test report from
    its norms at the moment the candidate submits, under the name, STR and SILP of a responsible psychologist who reviews it afterwards and may
    revise it (`phase-6-assessments.md`, Part 7). Does this meet Law 23/2022 (Articles 26 and 33: psychological measurement by a psychologist; Article 44:
    explanation to the client), or must a psychologist review every report before the company sees it? Does the answer differ by test (intelligence,
    personality, work attitude)? Added 2026-10-14 (`phase-8-provider-tools.md`): may an automatically issued report carry a hiring recommendation
    (recommended, consider, not yet) derived from rules set by the psychologist, and is it right to leave that recommendation out of the
    candidate's explanation? May the norm group use the candidate's age and education (shown to the psychologist, never to the company)?
12. **Accepting a job offer online (added 2026-10-14).** A job seeker accepts an offer letter on the platform with a checkbox and their account
    password; the time, IP address, browser and the SHA-256 of the letter are kept in a separate evidence document (`phase-8-offer-letters.md`).
    Is this enough as an electronic signature under UU ITE and PP 71/2019 for an offer letter, or should a certified e-signature (PSrE) or e-meterai
    be offered? May the platform keep this evidence after the applicant's data is anonymised?
13. **Restricted candidates (added 2026-10-14).** A company may mark a job seeker as restricted (a blacklist with a reason and a period), which flags
    their applications and hides them from the company's candidate search, without telling them (`phase-8-rejections.md`). Is this lawful under the
    Personal Data Protection Law and the non-discrimination rules? Must the person be told or be able to see it on a data access request, and which
    reasons must be refused?
14. **Personal data export boundary (added 2026-10-14).** A job seeker can download their data (`phase-8-jobseeker-privacy.md`), which leaves out
    what companies and psychologists wrote about them: team notes and ratings, hiring managers' review reasons, interview scorecards, rejection and withdrawal notes of the team,
    talent pool notes, restriction reasons, the company's psychological report text and the psychologist's review notes. Do UU 27/2022 Articles 7
    and 13 require any of these in the copy (with question 9 and 13), and in what form (for example a summary instead of the full text)? The coded export
    (2026-10-15) also leaves out, and the question covers: interview recordings and call logs (the candidate's voice or image), the raw answers and scores of
    tests (they carry the provider's answer keys), the companies' replies to the person's reviews, and in-app notifications.
15. **Applicant data retention per company (added 2026-10-14).** A final application (rejected, withdrawn, or still open on a closed job) is
    anonymised on the company's side after a period the company chooses, 6 to 60 months, default 24 (`phase-8-company-admin.md`); hires and
    applications held in the talent pool are left out, and the stage dates, reason codes and counts stay. Is 24 months a sound default under UU
    27/2022 (purpose limitation and retention), may the company choose the period, and is a shorter maximum required?
16. **Reported chat messages read by platform admins (added 2026-10-15).** When a job seeker or a company reports a conversation, the report keeps
    the reported message and up to 10 earlier messages (`phase-8-platform-admin.md`), which platform admins read to decide; the excerpt is
    deleted 180 days after the decision. Is this covered by the terms and privacy notice as written, and is 180 days a sound period?
17. **Raw psychological test data and account erasure (added 2026-10-15).** When a candidate erases their account, the answers, drawings and the
    stored report PDF of their psychological tests are deleted today (`phase-6-assessments.md`; `phase-8-provider-institutions.md`). How long must
    a partner institution keep the raw data of an examination under Law 23/2022 and the HIMPSI code of ethics, and does that duty override the
    candidate's erasure request (UU 27/2022)? If it does, the data would move to a locked institution archive instead.
18. **Psychological results after hiring (added 2026-10-15).** A candidate consents to a psychological test for selection. When a hire moves to the
    employee record of the ONESIS ERP (`erp-direction.md` section 3), the plan is **not** to carry the report or the results over
    (`phase-6-assessments.md`, "ERP later"). May the employer keep using the selection report for employment purposes (placement, promotion) under
    Law 23/2022 and UU 27/2022 (purpose limitation), and if so, what consent from the employee and what retention apply?
19. **In-house psychologist signing for the employer's own applicants (added 2026-10-16).** A customer may register its own psychologist (an employee, with
    STR and SILP) as the signer of psychological reports for that customer's applicants (`phase-8-provider-institutions.md` section 8). The report would
    state that the signer is an in-house psychologist. Does Law 23/2022 (and the professional code of ethics) allow an employee to sign selection
    reports for the employer's own applicants, what conflict-of-interest safeguards are required, and must the candidate be told?
20. **CVs received outside the platform (added 2026-10-19).** A company may enter a person who sent a CV by email, came to a walk-in interview or
    was referred: the team types the name and email and may upload the CV it received; the person gets an email to sign up and apply; the CV is
    seen by the team only and deleted after 30 days when the person does not respond (`phase-8-manual-candidates.md`). What is the lawful basis
    under UU 27/2022 for keeping and reading the CV before the person agrees on the platform (the person's own request to be considered,
    Article 20(2)(b)?), is 30 days acceptable, and must the email name the source of the data (the information duty of the controller; we have not found the right article)? For a referral the
    data comes from a third party: is the referrer's sharing enough, or may the team only send the invitation without the CV?
21. **Medical check-up and background checks (added 2026-10-19).** Before hiring, a company may record a medical check-up, a background check, a
    police record (SKCK) and reference checks per applicant (`phase-8-pre-employment-checks.md`). The system stores only a category for the medical
    result (fit, fit with notes, unfit), asks the candidate's consent in the platform for each check, shows the outcome to members with a
    dedicated permission only, audits every read, and deletes medical outcomes and check files 90 days after the application ends. Health data
    and criminal records are specific personal data (UU 27/2022 Article 4(2)): is this consent and minimisation enough, may "unfit" be recorded at
    all or only "not recommended", may background and reference findings be kept as files, and may an internal tenant carry the outcome into the
    employee record?
22. **WhatsApp notices through an unofficial gateway (added 2026-10-19).** Job seekers who opt in get interview, test and offer reminders by
    WhatsApp (`phase-8-whatsapp.md`). An installation may send them through the official WhatsApp Business API (Meta) or through an unofficial
    gateway that automates WhatsApp Web, which breaks WhatsApp's terms and runs the session at a third party. Under UU 27/2022, what agreement is
    needed with each sender as a data processor, is an unofficial gateway acceptable at all for candidates' data, and what must the privacy policy
    say about WhatsApp (Meta as a processor, transfer outside Indonesia, Article 56)?
23. **Interview and video-answer recordings (added 2026-10-21).** Video and voice interviews and video answers in tests may be recorded, with the
    candidate's explicit consent given in the platform before recording starts (section 1). Is this consent, given when confirming the schedule
    and withdrawable until recording starts, a sound basis under UU 27/2022? Are a recorded voice and image biometric data (Article 4(2)) when they
    are only stored and played back, never used to identify a person? Is 90 days a sound period, and who may play a recording (every member who
    may see the application, or only the interview panel)? May a company reject a candidate for refusing a recording (today a refusal does not
    cancel the interview, but a declined test ends that step)? Must the candidate get a copy on request (with question 14)?
24. **General duties of the operator (added 2026-10-21).** Before go-live: must the platform register as a private electronic system operator
    (PSE Lingkup Privat, PP 71/2019 and the ministry's regulation on private operators), and under which legal entity? Must it appoint a data
    protection officer (UU 27/2022 Article 53), given that it processes specific personal data at scale (pre-employment medical outcomes,
    psychological tests, children's data)? What does the notice of a data breach within 3 x 24 hours (Article 46) require of us in practice: whom
    we tell (the people concerned, the authority), what the notice must say, and what records we must keep?
25. **Roles and where the data is kept (added 2026-10-21).** Who is the controller of each kind of data: the platform for the job seeker's account
    and profile, the company for its applications, notes, scorecards and checks, the partner institution for psychological tests, or are some of
    them joint controllers? Which agreements follow from that (the terms for companies, a data-processing agreement, see question 6, and the
    agreement with partner institutions)? The platform runs on servers the operator manages itself; email, the SIP trunk for voice calls and
    WhatsApp (question 22) go through outside providers, some possibly outside Indonesia. Does PP 71/2019 require a private operator to keep the
    data in Indonesia, and what does Article 56 require for each provider abroad?

## 3. What we can change after the answers

- Switch the four filters on for everyone, or remove them in code (one setting, `job_seeker.candidate_sensitive_filters_enabled`).
- Change the account age limits (admin settings `job_seeker.min_age`, `job_seeker.max_age`), or remove the upper limit (set it to 100).
- Q3: raise the minimum age to 18, or add a parent's or guardian's consent step for job seekers under 18.
- Remove any of the six optional CV details (`CvInclusion`), or add a warning.
- Change what the terms and the privacy policy say (versioned legal documents managed by an admin).
- Q10: shorten the 12 months of the activity log, or hide IP address and browser from company admins.
- Q11: keep automatic reports off for good (a per-provider switch, `auto_issue_allowed`), allow them per test kind, or drop the recommendation.
- Q12: keep the checkbox and password, or add a certified e-signature (PSrE) or e-meterai step; drop the evidence at anonymisation.
- Q13: tell the person, show the restriction in the data download, refuse reasons from a fixed list, or drop the feature.
- Q14: add items (or a summary of them) to the data download.
- Q15 and Q16: change the default and the limits of the periods (settings).
- Q17: keep the raw data in a locked institution archive instead of deleting it on erasure.
- Q18: keep the rule that reports never move to the employee record, or add a new consent step for the employee.
- Q19: add safeguards (a second signer, a notice to the candidate) or allow partner psychologists only.
- Q20: drop the CV upload (invitation by email only), shorten the 30 days, or name the source in the email.
- Q21: record "not recommended" instead of "unfit", forbid files for background and reference checks, shorten the 90 days, or keep outcomes out of the employee record.
- Q22: remove the unofficial adapter, or require a data-processing agreement before an installation may switch it on.
- Q23: shorten the 90 days (`interview.recording_retention_days`, `assessment.video_retention_days`), limit playback to the interview panel, or switch recording off.
- Q24 and Q25: register the platform, name a data protection officer and the breach procedure in the privacy policy, and add the controller and processor terms to the agreements with companies and partner institutions.
