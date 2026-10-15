# Error codes

Every error is an RFC 9457 problem document (`application/problem+json`) with `type`, `title`, `status` and `detail`. The `type` is `urn:jobportal:error:<code>`: a client decides what to do from the code, never from the text of `detail`, which may change. A `422` with `validation-failed` also carries `errors`, a map of field to messages; some business rules (`422`) carry the same member. A `429` carries a `Retry-After` header. The list is generated from the source (`php artisan api:error-catalog`).

| Code | Status | Raised in | Message |
|---|---|---|---|
| `account-deactivated` | 403 | Identity | This account has been deactivated. |
| `account-not-active` | 409 | Identity | Only an active account can be deleted. |
| `account-suspended` | 403 | Identity | This account has been suspended. |
| `admin-not-active` | 409 | Identity | Only an active administrator can be suspended. |
| `admin-not-suspended` | 409 | Identity | Only a suspended administrator can be reactivated. |
| `admin-only` | 403 | Dashboard, JobSeeker, Newsletter, Review | Only an admin has this dashboard. |
| `alert-limit` | 422 | JobAlert |  |
| `already-applied` | 409, 422 | JobInvitation, Recruitment | This candidate already applied to the job. |
| `already-in-stage` | 409 | Recruitment | The application is already in this stage. |
| `already-in-talent-pool` | 409 | TalentPool | This candidate is already in your talent pool. |
| `already-invited` | 409 | JobInvitation | This candidate was already invited to the job. |
| `already-reported` | 409 | Review | You already reported this review. |
| `already-reviewed` | 409 | Review | You already reviewed this company. |
| `application-final` | 409 | Recruitment | This application is final, so its ratings can no longer change. |
| `application-limit-reached` | 429 | Recruitment | You have reached the daily limit of applications. Try again tomorrow. |
| `application-not-withdrawable` | 409 | Recruitment | This application can no longer be withdrawn. |
| `applications-unavailable` | 409 | Recruitment | This company cannot take applications right now. |
| `assessment-required` | 422 | Recruitment |  |
| `attempt-closed` | 409 | Assessment | This test is closed: it was submitted, ran out of time or was cancelled. |
| `attempt-moved` | 409 | Assessment | This test is open on another device now. Start it again here to take it back. |
| `attempt-not-started` | 409 | Assessment | This test was not started. |
| `attempt-not-submitted` | 409 | Assessment | The candidate has not submitted this test yet. |
| `attempt-session-required` | 409 | Assessment | Send the attempt session you got when the test started in the header X-Attempt-Session. |
| `auto-issue-not-allowed` | 422 | Assessment | The provider is not allowed to issue reports automatically. A platform admin allows it after review. |
| `avatar-not-found` | 404 | Identity, JobSeeker | There is no profile picture. |
| `battery-not-published` | 409 | Assessment | Only a published battery can be granted. |
| `body-too-long` | 422 | Messaging |  |
| `call-attempts-used` | 409 | Recruitment |  |
| `call-in-progress` | 409 | Recruitment | The candidate is already being called. |
| `campaign-code-taken` | 409 | Campaign | This campaign code is already used. |
| `campaign-has-registrations` | 409 | Campaign | Someone registered with this campaign; switch it off instead. |
| `campaign-link-unavailable` | 503 | Campaign | The registration link is not configured. |
| `campaign-period-invalid` | 422 | Campaign | The campaign cannot end before it starts. |
| `candidate-not-waiting` | 409 | Recruitment | The candidate is not waiting in the lobby. |
| `candidate-view-limit` | 429 | JobSeeker | You opened as many candidate profiles as you may today. Try again tomorrow. |
| `cannot-remove-self` | 409 | Company | You cannot remove yourself; another company admin can. |
| `cannot-reset-self` | 409 | Company | You cannot reset your own second factor here. |
| `cannot-sign-reports` | 403 | Assessment | Your STR or SILP is not valid today, so you cannot review reports. |
| `cannot-suspend-self` | 409 | Company | You cannot suspend yourself. |
| `category-limit` | 422 | TalentPool |  |
| `category-name-invalid` | 422 | TalentPool |  |
| `category-name-taken` | 409 | TalentPool | Your company already has a category with this name. |
| `channel-required` | 422 | JobAlert | Turn on at least one way to be told: email or in-app. |
| `chunk-out-of-order` | 409 | Assessment | Send the chunks in order. |
| `column-already-submitted` | 409 | Assessment | This column was already handed in. |
| `company-admin-required` | 403 | Company | Only a company admin can manage the team. |
| `company-already-suspended` | 409 | Company | The company is already suspended. |
| `company-not-found` | 404 | Company | Company not found. |
| `company-not-suspended` | 409 | Company | The company is not suspended. |
| `company-permission-required` | 403 | Shared |  |
| `company-suspended` | 403 | Assessment, Campaign, Company, Dashboard, Job, JobInvitation, JobSeeker, Messaging, Recruitment, Review, TalentPool | This company is suspended. |
| `consent-required` | 422 | Assessment | Agree to the terms of the test to start it. |
| `conversation-closed` | 409 | Messaging | This conversation is closed. The company must reopen it first. |
| `conversation-exists` | 409 | Messaging | This company already has a conversation with this job seeker, started by another member. |
| `criteria-required` | 422 | JobAlert | An alert needs at least one filter, such as a keyword, a role or a place. |
| `cv-limit` | 429 | JobSeeker | You have had too many CVs generated this hour. Try again later. |
| `cv-template-required` | 409 | JobSeeker | The default CV template must be switched on. |
| `daily-limit` | 429 | Messaging | Your company reached its daily limit of chat messages. Try again tomorrow. |
| `data-export-limit` | 429 | JobSeeker | You can ask for a copy of your data once a day. |
| `data-export-running` | 409 | JobSeeker | A copy of your data is already being prepared. |
| `default-stage-cannot-be-deleted` | 409 | Recruitment | A default stage cannot be deleted. |
| `delete-window-over` | 409 | Messaging |  |
| `deletion-already-requested` | 409 | Identity | The deletion of this account was already requested. |
| `deletion-not-requested` | 409 | Identity | No deletion of this account is pending. |
| `document-approved` | 409 | Company | An approved document cannot be deleted; upload a new file to replace it. |
| `document-changed` | 409 | Company | The company replaced this document after you opened it. Reload and review the latest file. |
| `document-not-pending` | 409 | Company | Only a pending document can be reviewed. |
| `document-type-inactive` | 422 | Company | This document type is not accepted at the moment. |
| `education-limit-reached` | 409 | JobSeeker |  |
| `email-already-registered` | 409 | Assessment, Company, Identity | An account with this email already exists. |
| `email-already-verified` | 409 | Identity | The email address is already verified. |
| `email-domain-not-allowed` | 422 | Company | This company only invites email addresses of its allowed domains. |
| `email-not-verified` | 403, 409 | Identity, Training | Verify your email address before continuing. |
| `empty-message` | 422 | Messaging | A message needs a text or at least one file. |
| `experience-limit-reached` | 409 | JobSeeker |  |
| `explanation-required` | 422 | Assessment | Write the explanation for the candidate. |
| `export-too-large` | 422 | Recruitment |  |
| `faq-category-not-empty` | 409 | Content | The category still has questions. Move or delete them first, or deactivate the category. |
| `faq-category-not-found` | 422 | Content | The category does not exist. |
| `file-too-large` | 422 | Assessment, Company, Identity, JobSeeker, Messaging | A chunk is larger than the allowed 5 MB. |
| `filter-not-available` | 422 | JobSeeker | The religion, nationality, age and gender filters are not available. |
| `forbidden` | 403 | Assessment, Company, Job, Platform | You are not allowed to perform this action. |
| `google-email-not-verified` | 401 | Identity | Google has not verified this email address. |
| `google-sign-in-unavailable` | 401, 503 | Identity | This account cannot sign in with Google. |
| `image-not-found` | 404 | Company | Image not found. |
| `internal-error` | 500 | Platform | An unexpected error occurred. |
| `interview-edit-denied` | 403 | Recruitment | Only the member who scheduled the interview, its interviewer or a company admin can change its questions. |
| `interview-not-active` | 409 | Recruitment | This interview is no longer active. |
| `interview-not-confirmed` | 409 | Recruitment | Only a confirmed interview can be joined. |
| `interview-not-pending` | 409 | Recruitment | This interview is not waiting for your answer. |
| `invalid-answer` | 422 | Assessment, Recruitment | The open section has no questions. |
| `invalid-answers` | 422 | Recruitment | Some answers are missing or invalid. |
| `invalid-application` | 422 | Recruitment | Choose one of your own resumes or a CV to generate. |
| `invalid-applications` | 422 | Recruitment |  |
| `invalid-assessment-input` | 422 | Assessment, Recruitment |  |
| `invalid-assessment-schedule` | 422 | Assessment |  |
| `invalid-assessment-test` | 422 | Assessment |  |
| `invalid-assessor` | 422 | Assessment |  |
| `invalid-assignees` | 422 | Job | A job needs at least one assignee. |
| `invalid-attachment` | 422 | Assessment, Messaging | The file must be a PDF, a JPEG or a PNG. |
| `invalid-column` | 422 | Assessment |  |
| `invalid-credentials` | 401 | Identity | The email or password is incorrect. |
| `invalid-criteria` | 422 | JobAlert | The salary period must be a positive whole number. |
| `invalid-cursor` | 422 | Messaging, Notification, Platform, Recruitment | The cursor is not valid. Start again without a cursor. |
| `invalid-cv` | 422 | JobSeeker | The language of a CV is id or en. |
| `invalid-document` | 422 | Company | The file must be a PDF or a JPEG, PNG or WebP image. |
| `invalid-education` | 422 | JobSeeker | The education level does not exist or is not active. |
| `invalid-email-domain` | 422 | Company | Each domain must look like example.com. |
| `invalid-email-template` | 422 | Platform | The email template is not valid. |
| `invalid-experience` | 422 | JobSeeker | The job type does not exist or is not active. |
| `invalid-google-token` | 401 | Identity | The Google token is not valid. |
| `invalid-ideal-answer` | 422 | Recruitment |  |
| `invalid-image` | 422 | Shared | The file is not a usable JPEG, PNG or WebP image. |
| `invalid-integrity-event` | 422 | Assessment | This signal is noticed by the server, not reported. |
| `invalid-interview` | 422 | Recruitment | The proposed time must be in the future. |
| `invalid-interviewer` | 422 | Recruitment |  |
| `invalid-job` | 422 | Job | The job is not valid. |
| `invalid-job-questions` | 422 | Recruitment | The questions of the job are not valid. |
| `invalid-kit` | 422 | Recruitment |  |
| `invalid-languages` | 422 | JobSeeker | The languages are not valid. |
| `invalid-links` | 422 | JobSeeker | The links are not valid. |
| `invalid-location` | 422 | Company, JobSeeker | The location is not a matching chain: each level must belong to the one above it, and a level needs the levels above it. |
| `invalid-name` | 422 | JobAlert |  |
| `invalid-navigation` | 422 | Assessment |  |
| `invalid-norms` | 422 | Assessment |  |
| `invalid-note` | 422 | Recruitment |  |
| `invalid-offer` | 422 | Recruitment |  |
| `invalid-offer-input` | 422 | Recruitment |  |
| `invalid-order` | 422 | Assessment | The list must hold every question of the section exactly once. |
| `invalid-password` | 422 | Company, Identity | The password is not correct. |
| `invalid-phone` | 422 | Recruitment |  |
| `invalid-profile` | 422 | JobSeeker |  |
| `invalid-psychologist` | 422 | Assessment |  |
| `invalid-question` | 422 | Assessment |  |
| `invalid-range` | 422 | Dashboard | Only days up to yesterday can be summarised. |
| `invalid-rating` | 422 | Recruitment | Rate from 1 to 5 stars. |
| `invalid-reference` | 422 | Job | Some of the values do not exist or are not active. |
| `invalid-rejection` | 422 | Recruitment |  |
| `invalid-report` | 422 | Assessment | The report text cannot be empty. |
| `invalid-report-template` | 422 | Assessment |  |
| `invalid-resume` | 422 | JobSeeker |  |
| `invalid-role-name` | 422 | Identity | A role name is 3 to 50 lowercase letters, digits or underscores, starting with a letter. |
| `invalid-schedule` | 422 | Recruitment |  |
| `invalid-score` | 422 | Assessment |  |
| `invalid-scorecard` | 422 | Recruitment |  |
| `invalid-screening-question` | 422 | Recruitment | Every option id must be a different option of this question. |
| `invalid-section` | 422 | Assessment | The format of a section cannot be changed. Add a new section instead. |
| `invalid-setting-value` | 422 | Platform, Shared | Some values are not valid. |
| `invalid-skills` | 422 | JobSeeker | The skills are not valid. |
| `invalid-slug` | 422 | Company | The slug must be 3 to 60 characters: lower case letters, digits and single hyphens, not starting or ending with a hyphen. |
| `invalid-stage` | 422 | Recruitment | An application cannot be moved to this stage. |
| `invalid-stage-order` | 422 | Recruitment |  |
| `invalid-test` | 422 | Assessment |  |
| `invalid-two-factor-code` | 401, 422 | Identity | The verification code is incorrect. |
| `invalid-verification-code` | 422 | Identity | The verification code is incorrect. |
| `invalid-video` | 422 | Assessment | No part of the recording was sent. |
| `invalid-webhook-credentials` | 401 | Recruitment | The webhook credentials are wrong. |
| `invalid-withdrawal` | 422 | Recruitment |  |
| `invitation-closed` | 409 | JobInvitation | This invitation expired because the job is not live. |
| `invitation-cooldown` | 429 | Assessment, Company | Wait a moment before sending another invitation to this email. |
| `invitation-in-progress` | 409 | Assessment | The candidate already started the test. Cancel it before sending another. |
| `invitation-limit` | 429 | JobInvitation | Your company reached its daily limit of job invitations. Try again tomorrow. |
| `invitation-link-unavailable` | 503 | Assessment, Company | Invitations cannot be sent because the invitation link is not configured. |
| `invitation-not-active` | 409 | Assessment | Only an active test invitation can be cancelled. |
| `invitation-not-found` | 404 | Assessment, Company | Invitation not found. |
| `invitation-not-invited` | 409 | Assessment | Only a test that was not started can expire. |
| `invitation-not-open` | 409 | Assessment | This test cannot be started now: it is cancelled, expired, already started, or not open yet. |
| `invitation-not-started` | 409 | Assessment | Only a test that was started can be submitted. |
| `job-deadline-passed` | 409 | Job | The deadline of this job has passed. |
| `job-incomplete` | 422 | Job | The job is not complete. |
| `job-locked-after-applications` | 409 | Job |  |
| `job-not-active` | 409 | Job | Only a live job can be closed. |
| `job-not-draft` | 409 | Job | Only a draft can be deleted; close the job instead. |
| `job-not-editable` | 409 | Job, Recruitment | A job in this status cannot be edited. |
| `job-not-live` | 422 | JobInvitation, JobSeeker | You can only invite candidates to a job that is live. |
| `job-not-pending-review` | 409 | Job | Only a job waiting for review can be decided. |
| `job-not-publishable` | 409 | Job | Only a draft or a rejected job can be published. |
| `job-not-reopenable` | 409 | Job | Only a closed or an expired job can be reopened. |
| `job-not-taken-down` | 409 | Job | Only a job that was taken down can be restored. |
| `job-questions-locked` | 409 | Recruitment | The job has applications, so its questions can no longer change. |
| `job-seeker-only` | 403 | Assessment, Dashboard, Job, JobAlert, JobInvitation, JobSeeker, Messaging, Recruitment, Review, Training | Only a job seeker can take a test. |
| `job-taken-down` | 409 | Assessment, Recruitment | This job was taken down, so its applications can no longer be processed. |
| `kit-limit-reached` | 409 | Recruitment | The company has reached its limit of interview kits. |
| `kit-name-taken` | 409 | Recruitment | Another kit of this company already has this name. |
| `last-company-admin` | 409 | Company | A company needs at least one company admin. |
| `last-section` | 409 | Assessment | This is the last section: submit the test to finish it. |
| `last-super-admin` | 409 | Identity | This is the only active super_admin; give another administrator that role first. |
| `legal-document-incomplete` | 422 | Content | The title and the body must be filled in every language before publishing. |
| `legal-document-missing` | 422 | Identity | Every document in force must be accepted together. |
| `legal-document-not-active` | 422 | Identity | A document that is not in force was sent. |
| `legal-document-not-draft` | 409 | Content |  |
| `legal-document-outdated` | 409 | Identity | A newer version was published. Read it and accept again. |
| `legal-document-version-taken` | 409 | Content | Another version with this number was created at the same time. Try again. |
| `location-parent-required` | 422 | ReferenceData |  |
| `member-is-company-admin` | 409 | Company | A company admin holds every permission. |
| `member-is-owner` | 409 | Company | The owner of the company must hand the ownership over before deleting the account. |
| `membership-suspended` | 403 | Company | Your access to the company was suspended by a company admin. |
| `method-not-allowed` | 405 | Platform | The HTTP method is not allowed for this resource. |
| `newsletter-issue-not-draft` | 409 | Newsletter | Only a draft can be deleted. |
| `newsletter-no-recipients` | 409 | Newsletter | There is nobody to send this issue to. |
| `newsletter-token-invalid` | 422 | Newsletter | This confirmation link is not valid or has expired. |
| `newsletter-unavailable` | 503 | Newsletter | The newsletter is not available. |
| `no-active-interview` | 409 | Recruitment | This application has no active interview. |
| `no-active-offer` | 409 | Recruitment | This application has no pending offer. |
| `no-active-recording` | 409 | Recruitment | No recording is running. |
| `no-live-call` | 409 | Recruitment | There is no call in progress. |
| `no-phone-number` | 409 | Recruitment |  |
| `no-proposal` | 409 | Recruitment | There is no proposed time to accept. |
| `no-recording-consent` | 409 | Recruitment | You have not given consent to record this interview. |
| `not-a-company-member` | 403 | Assessment, Campaign, Company, Dashboard, Identity, Job, JobInvitation, JobSeeker, Messaging, Recruitment, Review, TalentPool | Only a company member can do this. |
| `not-a-participant` | 403 | Messaging | Only a job seeker or a company member can use chat. |
| `not-a-psychologist` | 403 | Assessment | Only a psychologist can review reports. |
| `not-a-video-interview` | 409 | Recruitment | This interview is not held in the platform. |
| `not-a-voice-interview` | 409 | Recruitment | This interview is not held by phone. |
| `not-an-assessor` | 403 | Assessment | Only an assessor of this test can score its answers. |
| `not-an-author` | 403 | Assessment | Only a company member with the permission, a psychologist or an administrator can write tests. |
| `not-an-interview-participant` | 403 | Recruitment | You are not on the panel of this interview. |
| `not-found` | 404 | Platform | The requested resource was not found. |
| `not-in-assessment-stage` | 409 | Assessment | Move the application to an assessment stage before sending a test. |
| `not-in-interview-stage` | 409 | Recruitment | Move the application to an interview stage before scheduling an interview. |
| `not-in-offer-stage` | 409 | Recruitment | Move the application to an offer stage before sending an offer. |
| `not-note-author` | 403 | Recruitment | Only the author or a company admin can delete a note. |
| `not-on-panel` | 403 | Recruitment | Only the members of the interview panel can fill a scorecard. |
| `not-your-message` | 403 | Messaging | Only the sender can delete a message. |
| `note-too-long` | 422 | JobInvitation, TalentPool |  |
| `offer-expired` | 409 | Recruitment | This offer has expired. |
| `offer-not-pending` | 409 | Recruitment | This offer is no longer pending. |
| `offer-required` | 422 | Recruitment |  |
| `own-review` | 403 | Review | You cannot report your own review. |
| `owner-required` | 403 | Company | Only the owner of the company can hand the ownership over. |
| `owner-target-not-company-admin` | 409 | Company | The owner must be an active company admin of the company. |
| `page-out-of-range` | 422 | Job |  |
| `password-not-set` | 409 | Identity | This account has no password. Set one through the password reset flow. |
| `password-required` | 422 | Identity | Set a password before deleting the account. |
| `permission-requires-view` | 422 | Company |  |
| `permissions-required` | 422 | Company, Identity | A member needs at least one permission. |
| `profile-incomplete` | 422 | Recruitment | Complete your profile before applying. |
| `proposal-limit` | 409 | Recruitment |  |
| `proposal-pending` | 409 | Recruitment | Your proposed time is waiting for an answer from the company. |
| `provider-code-taken` | 409 | Assessment | A provider with this code already exists. |
| `provider-name-taken` | 409 | Assessment | A provider with this name already exists. |
| `provider-suspended` | 403 | Assessment | The provider of this psychologist is suspended. |
| `psychological-disabled` | 403 | Assessment | Psychological tests are not enabled for this company. |
| `psychologist-account-managed` | 403 | Identity | A psychologist account is closed by an administrator. |
| `psychologist-suspended` | 403 | Assessment | This psychologist account is suspended. |
| `public-account-required` | 403 | Identity | Only a job seeker or a company member has a profile picture. |
| `question-in-use` | 409 | Recruitment | This question is attached to a job or has answers, so it can only be archived. |
| `question-limit-reached` | 409 | Assessment | The section has reached its limit of questions. |
| `question-locked` | 409 | Assessment | This question is closed: the section does not let you go back to it. |
| `reason-too-long` | 422 | JobInvitation |  |
| `recipient-unavailable` | 409 | Messaging | This job seeker no longer has an account that can receive messages. |
| `recommendation-required` | 422 | Recruitment |  |
| `recording-consent-required` | 422 | Recruitment |  |
| `recording-in-progress` | 409 | Recruitment | This interview is already being recorded. |
| `recording-not-allowed` | 409 | Recruitment | This interview may not be recorded: the job seeker has not given their consent. |
| `recording-not-finished` | 409 | Recruitment | This recording is not finished yet. |
| `recording-not-ready` | 409 | Recruitment | This recording has no file. |
| `recording-started` | 409 | Recruitment | The interview was already recorded, so the consent can no longer be withdrawn. Ask the company to delete the recording. |
| `reference-value-taken` | 409 | ReferenceData |  |
| `rejection-note-required` | 422 | Company | A note is required when the reason is "other". |
| `reply-not-pending` | 409 | Review |  |
| `report-not-auto-issued` | 409 | Assessment | Only a report that was issued automatically can be marked reviewed or revised. |
| `report-not-open` | 409 | Review | This report was already handled. |
| `report-not-signed` | 409 | Assessment | A psychologist has not signed the report of this test yet. |
| `report-signed` | 409 | Assessment | This report is signed and cannot be changed. |
| `resume-limit-reached` | 409 | JobSeeker |  |
| `retake-limit` | 409 | Assessment | You have used every recording this question allows. |
| `review-not-editable` | 409 | Review | A review that was taken down cannot be edited. |
| `review-not-pending` | 409 | Review |  |
| `review-not-published` | 409 | Review |  |
| `review-requires-application` | 403 | Review | Only a job seeker who applied to this company can review it. |
| `role-already-exists` | 409 | Identity |  |
| `role-in-use` | 409 | Identity |  |
| `role-protected` | 409 | Identity | The super_admin role holds every permission and cannot be edited. |
| `role-unchanged` | 409 | Identity |  |
| `salary-period-required` | 422 | Job, JobAlert | A minimum salary needs the salary period it is meant in. |
| `saved-jobs-limit` | 422 | Job |  |
| `schedule-required` | 422 | Recruitment |  |
| `scorecards-locked` | 403 | Recruitment | Send your own scorecard first: the other scorecards stay hidden until then. |
| `scoring-locked` | 409 | Assessment | The application has left the assessment stage, so its scores can no longer be changed. |
| `scoring-not-open` | 409 | Recruitment | A scorecard can be filled from the start of a confirmed interview. |
| `scoring-started` | 409 | Recruitment | A panel member already started a scorecard, so the questions can no longer change. |
| `screening-disabled` | 403 | Recruitment | Screening questions are switched off for this company. |
| `screening-limit-reached` | 409 | Recruitment | The job has reached its limit of screening questions. |
| `section-limit-reached` | 409 | Assessment | The test has reached its limit of sections. |
| `setting-not-editable` | 422 | Platform |  |
| `setting-not-overridable` | 422 | Platform | This setting cannot be overridden per company. |
| `slug-reserved` | 422 | Company | This slug is reserved. |
| `slug-taken` | 409 | Company | This slug is already taken. |
| `social-account-conflict` | 409 | Identity | A different Google account is already linked to this account. |
| `stage-limit-reached` | 409 | Recruitment | The company has reached its limit of stages. |
| `stage-name-taken` | 409 | Recruitment | Another stage of this company already has this name. |
| `stage-not-interview` | 422 | Recruitment |  |
| `stage-type-not-allowed` | 422 | Recruitment | Extra stages can only be of the type screening, assessment, interview or offer. |
| `talent-pool-limit` | 422 | TalentPool |  |
| `team-not-connected` | 409 | Recruitment | Join the call from your browser before you dial the candidate. |
| `template-unavailable` | 422 | JobSeeker | This CV template is not available. |
| `terms-not-accepted` | 409 | Identity | You must accept the current terms and privacy policy before continuing. |
| `test-archived` | 409 | Assessment | An archived test cannot be changed. Restore it first. |
| `test-incomplete` | 422 | Assessment | The battery cannot issue reports automatically yet. |
| `test-limit-reached` | 409 | Assessment | The company has reached its limit of skill tests. Archive or delete one first. |
| `test-locked` | 409 | Assessment | This test was already sent to candidates, so it can no longer be changed. Clone it to make a new version. |
| `test-not-draft` | 409 | Assessment | Only a draft can be deleted. Archive the test instead. |
| `test-not-psychological` | 422 | Assessment | Only a psychological battery issues reports. |
| `too-many-applications` | 422 | Recruitment |  |
| `too-many-attachments` | 422 | Messaging |  |
| `too-many-recipients` | 422 | Messaging |  |
| `too-many-requests` | 429 | Assessment, Company, Identity, Job, Platform | Too many requests. Try again later. The `Retry-After` header tells how long to wait. |
| `training-unavailable` | 503 | Training | The training platform is not configured. |
| `transfer-target-not-company-admin` | 409 | Company | The jobs can only go to an active company admin of the company. |
| `two-factor-already-enabled` | 409 | Identity | The second factor is already switched on. |
| `two-factor-challenge-expired` | 401 | Identity | The sign-in has expired. Sign in again. |
| `two-factor-not-enabled` | 409 | Identity | This administrator has no second factor yet. |
| `two-factor-not-started` | 409 | Identity | No enrolment is waiting for a code. Start one first. |
| `two-factor-required` | 409 | Identity | The second factor is required for this account and cannot be switched off. |
| `unauthenticated` | 401 | Platform | Authentication is required. |
| `unknown-industry` | 422 | Company | The industry does not exist or is not active. |
| `unknown-notification-preference` | 422 | Notification | This notification setting does not exist. |
| `unknown-organization-type` | 422 | Company | The organization type does not exist or is not active. |
| `unknown-permission` | 422 | Identity |  |
| `unknown-team-action` | 422 | Company | This is not an action of the team history. |
| `unknown-team-size` | 422 | Company |  |
| `unsupported-language` | 422 | Platform |  |
| `upload-deadline-passed` | 409 | Assessment | The time to send this recording has passed. |
| `validation-failed` | 422 | Platform | The request data is invalid. The `errors` member maps each field to its messages. |
| `verification-code-cooldown` | 429 | Identity | Wait a moment before requesting another code. |
| `verification-code-expired` | 422 | Identity | The verification code has expired or is no longer valid. Request a new one. |
| `verification-code-limit-reached` | 429 | Identity | Too many codes were requested. Try again later. |
| `video-capacity-full` | 409 | Recruitment | Too many video interviews are running right now. Try again in a few minutes. |
| `video-consent-required` | 422 | Assessment |  |
| `video-disabled` | 409 | Recruitment | Interviews in the platform are switched off. |
| `video-expired` | 409 | Assessment | The retention period of this video is over and the file was deleted. |
| `video-not-allowed` | 422 | Assessment |  |
| `video-not-enabled` | 422 | Recruitment |  |
| `video-room-not-open` | 409 | Recruitment | The video room is not open yet. |
| `video-server-unavailable` | 503 | Recruitment | The recording converter is not configured. |
| `video-take-closed` | 409 | Assessment | This recording is closed. |
| `video-too-large` | 422 | Assessment |  |
| `video-window-closed` | 409 | Recruitment |  |
| `voice-capacity-full` | 409 | Recruitment | Too many phone interviews are running right now. Try again in a few minutes. |
| `voice-disabled` | 409 | Recruitment | Phone interviews are switched off. |
| `voice-not-enabled` | 422 | Recruitment |  |
| `voice-quota-used` | 409 | Recruitment | Your company used all its phone interview minutes of this month. |
| `voice-server-unavailable` | 503 | Recruitment | The recording cannot be fetched. |
| `voice-window-closed` | 409 | Recruitment |  |
