# Legal review brief: personal data, selection criteria and psychological tests

For the legal adviser of **ONESIS ERP** (One Solutions Integrated System) and its job seeker site **ONESIS Career**, operated in Indonesia. The ERP's
first module is talent acquisition (jobs, applications, interviews, psychological tests, offers); the job seeker site is public. The brief lists what the
system does, or is designed to do, with sensitive personal data and what we need answered. It is a factual inventory, not legal advice, and the legal
references in the questions are unverified suggestions of where to look. Written 2026-10-09, brought up to date 2026-10-16 (not sent yet).

## 0. Priority of the answers

The questions keep their numbers (the design documents refer to them). "Built" means the behaviour exists in the code, switched off or at a safe default
where the question is open; "designed" means it is planned and waits for the answer before it is built.

| Priority | Questions | Why | State |
|---|---|---|---|
| **A. Before go-live** | 1 to 7, 10 | the terms and the privacy policy must be final; the sensitive filters, the age limits and the CV details need a ruling (they stay off or at their defaults until then) | built |
| **B. Blocks building a feature** | 12, 13, 15 | the online offer acceptance, restricted candidates and the applicant data retention per company are not built until answered | designed |
| **C. Before the first psychology partner or in-house psychologist** | 11, 17, 19 | automatic reports, raw data retention and in-house signers; psychological tests are switched off until then | 11, 17 built (auto issue off); 19 designed |
| **D. Policy text, or after go-live** | 8, 9, 14, 16, 18 | the answers change the policy text or a later feature; the system works with the present behaviour meanwhile | 8, 9, 14 built; 16 designed; 18 a plan |

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
| Application source and match score | Each application records where the applicant came from (search, recommendation, alert, direct link or invitation) and the match percentage at the time of applying. Both are used only in aggregate analytics for the platform and the company admin (groups under 5 are hidden); no endpoint returns them for a person. |
| Company reviews | A job seeker who applied to a company may review it; an admin approves it first, and the author's **name is always shown** with a published review. A company may reply and may report a review. When the account is erased, the text of a published review is deleted and the ratings stay. |
| Talent pool | A company member may save a candidate who can be found (opted in) into the company's talent pool with a category and a **free-text note of up to 500 characters**, seen by every member of that company only, never by the candidate. The entry and its note are hidden while the candidate cannot be found; the note is deleted by a nightly job after that (the entry stays), and the entry and note are deleted when the account is anonymised or a member removes the entry. |
| Erasure | Account deletion anonymises the account and erases the profile, resumes, alerts, invitations, views and CV records. |
| Company team activity (Q10) | A company admin sees each member's activity, including the IP address and browser of their sign-ins, and one activity log of the whole company that can be exported as CSV; kept 12 months. |
| Psychological tests (Q11, Q17 to Q19) | Licensed psychologists (STR and SILP) of a partner institution, or the customer's own in-house psychologists (designed), build test batteries and sign the reports; a company uses a battery only when the platform switches psychological tests on for it. The candidate consents before the test and gets a plain-language explanation. A provider may be allowed to issue reports automatically from its norms (off by default until question 11 is answered). |
| Offers (Q12) | Designed: the job seeker accepts an offer letter online with a checkbox and their password; the time, IP address, browser and a hash of the letter are kept as evidence. |
| Restricted candidates (Q13) | Designed: a company may mark a job seeker as restricted for a period with a reason; their applications are flagged and they are hidden from the company's search, without being told. |
| Data download (Q14) | A job seeker can download their data; what companies and psychologists wrote about them is left out. |
| Applicant data retention (Q15) | Designed: a final application is anonymised on the company's side after a period the company chooses (6 to 60 months, default 24). |
| Reported chats (Q16) | Designed: a reported conversation keeps the reported message and up to 10 earlier ones for platform admins, deleted 180 days after the decision. |

## 2. Questions

1. **Discrimination in recruitment.** Do the filters by religion, gender and age in the candidate search conflict with the prohibition of discrimination in employment (for example Law 13/2003, Articles 5 and 6, as amended)? If so, is the platform liable for offering the tool, or only the company that uses it? Does the answer differ for nationality (a work permit rule can be a legitimate criterion)?
2. **Personal data classes.** Under the Personal Data Protection Law (Law 27/2022), are religion and marital status specific personal data? If so, is the present optional field with the general terms enough, or is a separate explicit consent required?
3. **Age limit of accounts.** Is refusing job seekers above 60 (or any configured limit) lawful when it limits the account, not one job? Which minimum age is right (15 now) and is parental consent or a ban on certain work needed for under 18?
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

## 3. What we can change after the answers

- Switch the four filters on for everyone, or remove them in code (one setting, `job_seeker.candidate_sensitive_filters_enabled`).
- Change the account age limits (admin settings `job_seeker.min_age`, `job_seeker.max_age`), or remove the upper limit (set it to 100).
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
