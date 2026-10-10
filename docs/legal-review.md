# Legal review brief: personal data and selection criteria

For the legal adviser of the job portal (Indonesia). It lists what the system does today with sensitive personal data and what we need answered before release.
It is a factual inventory, not legal advice, and the legal references in the questions are unverified suggestions of where to look. Written 2026-10-09.

## 1. What the system does

| Area | Behaviour today |
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
| Erasure | Account deletion anonymises the account and erases the profile, resumes, alerts, invitations, views and CV records. |

## 2. Questions

1. **Discrimination in recruitment.** Do the filters by religion, gender and age in the candidate search conflict with the prohibition of discrimination in employment (for example Law 13/2003, Articles 5 and 6, as amended)? If so, is the platform liable for offering the tool, or only the company that uses it? Does the answer differ for nationality (a work permit rule can be a legitimate criterion)?
2. **Personal data classes.** Under the Personal Data Protection Law (Law 27/2022), are religion and marital status specific personal data? If so, is the present optional field with the general terms enough, or is a separate explicit consent required?
3. **Age limit of accounts.** Is refusing job seekers above 60 (or any configured limit) lawful when it limits the account, not one job? Which minimum age is right (15 now) and is parental consent or a ban on certain work needed for under 18?
4. **Personal details on a CV.** May the platform offer these fields on a generated CV when the job seeker chooses them, given that common Indonesian CVs carry them? Should the platform add a warning, or hide some of them?
5. **Protected attributes as search results.** Companies cannot see these values in the search, but they can infer them by filtering repeatedly. Is that a concern if the filters are later switched on?
6. **Terms and privacy policy.** What must the documents say about the candidate search (opt-in, view log, what the company sees), the CV generator and the processing for matching? Is a data-processing agreement with companies needed?
7. **Records and retention.** How long may the view log and the CV generation records be kept, and what may the platform keep after an account is anonymised?
8. **Company reviews.** Who answers for a defamatory review (the author, the platform as the host)? What notice-and-takedown process should the terms promise, and is showing the author's real name lawful and wise? How long may a rejected review or a report be kept?

## 3. What we can change after the answers

- Switch the four filters on for everyone, or remove them in code (one setting, `job_seeker.candidate_sensitive_filters_enabled`).
- Change the account age limits (admin settings `job_seeker.min_age`, `job_seeker.max_age`), or remove the upper limit (set it to 100).
- Remove any of the six optional CV details (`CvInclusion`), or add a warning.
- Change what the terms and the privacy policy say (versioned legal documents managed by an admin).
