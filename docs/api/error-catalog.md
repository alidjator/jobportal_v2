# Error codes

Every error is an RFC 9457 problem document (`application/problem+json`) with `type`, `title`, `status` and `detail`. The `type` is `urn:jobportal:error:<code>`: a client decides what to do from the code, never from the text of `detail`, which may change. A `422` with `validation-failed` also carries `errors`, a map of field to messages; some business rules (`422`) carry the same member. A `429` carries a `Retry-After` header. The list is generated from the source (`php artisan api:error-catalog`).

| Code | Status | Raised in | Message |
|---|---|---|---|
| `account-deactivated` | 403 | Identity | This account has been deactivated. |
| `account-not-active` | 409 | Identity | Only an active account can be deleted. |
| `account-suspended` | 403 | Identity | This account has been suspended. |
| `admin-only` | 403 | Dashboard, JobSeeker, Newsletter, Review | Only an admin has this dashboard. |
| `alert-limit` | 422 | JobAlert |  |
| `already-applied` | 409, 422 | JobInvitation, Recruitment | This candidate already applied to the job. |
| `already-in-stage` | 409 | Recruitment | The application is already in this stage. |
| `already-invited` | 409 | JobInvitation | This candidate was already invited to the job. |
| `already-reported` | 409 | Review | You already reported this review. |
| `already-reviewed` | 409 | Review | You already reviewed this company. |
| `application-final` | 409 | Recruitment | This application is final, so its ratings can no longer change. |
| `application-limit-reached` | 429 | Recruitment | You have reached the daily limit of applications. Try again tomorrow. |
| `application-not-withdrawable` | 409 | Recruitment | This application can no longer be withdrawn. |
| `applications-unavailable` | 409 | Recruitment | This company cannot take applications right now. |
| `avatar-not-found` | 404 | Identity, JobSeeker | There is no profile picture. |
| `body-too-long` | 422 | Messaging |  |
| `campaign-code-taken` | 409 | Campaign | This campaign code is already used. |
| `campaign-has-registrations` | 409 | Campaign | Someone registered with this campaign; switch it off instead. |
| `campaign-link-unavailable` | 503 | Campaign | The registration link is not configured. |
| `campaign-period-invalid` | 422 | Campaign | The campaign cannot end before it starts. |
| `candidate-view-limit` | 429 | JobSeeker | You opened as many candidate profiles as you may today. Try again tomorrow. |
| `channel-required` | 422 | JobAlert | Turn on at least one way to be told: email or in-app. |
| `company-admin-only` | 403 | Dashboard | Only a company admin has the analytics. |
| `company-admin-required` | 403 | Campaign, Company, Job, Recruitment, Review | Only a company admin can manage campaigns. |
| `company-already-suspended` | 409 | Company | The company is already suspended. |
| `company-not-found` | 404 | Company | Company not found. |
| `company-not-suspended` | 409 | Company | The company is not suspended. |
| `company-suspended` | 403 | Campaign, Company, Dashboard, Job, JobInvitation, JobSeeker, Messaging, Recruitment, Review | This company is suspended. |
| `conversation-closed` | 409 | Messaging | This conversation is closed. The company must reopen it first. |
| `conversation-exists` | 409 | Messaging | This company already has a conversation with this job seeker, started by another member. |
| `criteria-required` | 422 | JobAlert | An alert needs at least one filter, such as a keyword, a role or a place. |
| `cv-limit` | 429 | JobSeeker | You have had too many CVs generated this hour. Try again later. |
| `cv-template-required` | 409 | JobSeeker | The default CV template must be switched on. |
| `daily-limit` | 429 | Messaging | Your company reached its daily limit of chat messages. Try again tomorrow. |
| `default-stage-cannot-be-deleted` | 409 | Recruitment | A default stage cannot be deleted. |
| `delete-window-over` | 409 | Messaging |  |
| `deletion-already-requested` | 409 | Identity | The deletion of this account was already requested. |
| `deletion-not-requested` | 409 | Identity | No deletion of this account is pending. |
| `document-approved` | 409 | Company | An approved document cannot be deleted; upload a new file to replace it. |
| `document-changed` | 409 | Company | The company replaced this document after you opened it. Reload and review the latest file. |
| `document-not-pending` | 409 | Company | Only a pending document can be reviewed. |
| `document-type-inactive` | 422 | Company | This document type is not accepted at the moment. |
| `education-limit-reached` | 409 | JobSeeker |  |
| `email-already-registered` | 409 | Company, Identity | An account with this email already exists. |
| `email-already-verified` | 409 | Identity | The email address is already verified. |
| `email-not-verified` | 403, 409 | Identity, Training | Verify your email address before continuing. |
| `empty-message` | 422 | Messaging | A message needs a text or at least one file. |
| `experience-limit-reached` | 409 | JobSeeker |  |
| `faq-category-not-empty` | 409 | Content | The category still has questions. Move or delete them first, or deactivate the category. |
| `faq-category-not-found` | 422 | Content | The category does not exist. |
| `file-too-large` | 422 | Company, Identity, JobSeeker, Messaging |  |
| `filter-not-available` | 422 | JobSeeker | The religion, nationality, age and gender filters are not available. |
| `forbidden` | 403 | Company, Job, Platform | You are not allowed to perform this action. |
| `google-email-not-verified` | 401 | Identity | Google has not verified this email address. |
| `google-sign-in-unavailable` | 503 | Identity | Google sign-in is not available. |
| `image-not-found` | 404 | Company | Image not found. |
| `internal-error` | 500 | Platform | An unexpected error occurred. |
| `interview-not-active` | 409 | Recruitment | This interview is no longer active. |
| `interview-not-confirmed` | 409 | Recruitment | Only a confirmed interview can be marked as completed. |
| `interview-not-pending` | 409 | Recruitment | This interview is not waiting for your answer. |
| `invalid-answer` | 422 | Recruitment |  |
| `invalid-answers` | 422 | Recruitment | Some answers are missing or invalid. |
| `invalid-application` | 422 | Recruitment | Choose one of your own resumes or a CV to generate. |
| `invalid-applications` | 422 | Recruitment |  |
| `invalid-assignees` | 422 | Job | A job needs at least one assignee. |
| `invalid-attachment` | 422 | Messaging |  |
| `invalid-credentials` | 401 | Identity | The email or password is incorrect. |
| `invalid-criteria` | 422 | JobAlert | The salary period must be a positive whole number. |
| `invalid-cursor` | 422 | Messaging, Notification, Platform, Recruitment | The cursor is not valid. Start again without a cursor. |
| `invalid-cv` | 422 | JobSeeker | The language of a CV is id or en. |
| `invalid-document` | 422 | Company | The file must be a PDF or a JPEG, PNG or WebP image. |
| `invalid-education` | 422 | JobSeeker | The education level does not exist or is not active. |
| `invalid-email-template` | 422 | Platform | The email template is not valid. |
| `invalid-experience` | 422 | JobSeeker | The job type does not exist or is not active. |
| `invalid-google-token` | 401 | Identity | The Google token is not valid. |
| `invalid-ideal-answer` | 422 | Recruitment |  |
| `invalid-image` | 422 | Shared | The file is not a usable JPEG, PNG or WebP image. |
| `invalid-interview` | 422 | Recruitment | The proposed time must be in the future. |
| `invalid-interviewer` | 422 | Recruitment |  |
| `invalid-job` | 422 | Job | The job is not valid. |
| `invalid-job-questions` | 422 | Recruitment | The questions of the job are not valid. |
| `invalid-languages` | 422 | JobSeeker | The languages are not valid. |
| `invalid-links` | 422 | JobSeeker | The links are not valid. |
| `invalid-location` | 422 | Company, JobSeeker | The location is not a matching chain: each level must belong to the one above it, and a level needs the levels above it. |
| `invalid-name` | 422 | JobAlert |  |
| `invalid-note` | 422 | Recruitment |  |
| `invalid-offer` | 422 | Recruitment |  |
| `invalid-offer-input` | 422 | Recruitment |  |
| `invalid-password` | 422 | Identity | The password is incorrect. |
| `invalid-profile` | 422 | JobSeeker |  |
| `invalid-range` | 422 | Dashboard | Only days up to yesterday can be summarised. |
| `invalid-rating` | 422 | Recruitment | Rate from 1 to 5 stars. |
| `invalid-reference` | 422 | Job | Some of the values do not exist or are not active. |
| `invalid-rejection` | 422 | Recruitment |  |
| `invalid-resume` | 422 | JobSeeker |  |
| `invalid-schedule` | 422 | Recruitment |  |
| `invalid-screening-question` | 422 | Recruitment | Every option id must be a different option of this question. |
| `invalid-setting-value` | 422 | Shared |  |
| `invalid-skills` | 422 | JobSeeker | The skills are not valid. |
| `invalid-slug` | 422 | Company | The slug must be 3 to 60 characters: lower case letters, digits and single hyphens, not starting or ending with a hyphen. |
| `invalid-stage` | 422 | Recruitment | An application cannot be moved to this stage. |
| `invalid-stage-order` | 422 | Recruitment |  |
| `invalid-verification-code` | 422 | Identity | The verification code is incorrect. |
| `invalid-withdrawal` | 422 | Recruitment |  |
| `invitation-closed` | 409 | JobInvitation | This invitation expired because the job is not live. |
| `invitation-cooldown` | 429 | Company | Wait a moment before sending another invitation to this email. |
| `invitation-limit` | 429 | JobInvitation | Your company reached its daily limit of job invitations. Try again tomorrow. |
| `invitation-link-unavailable` | 503 | Company | Invitations cannot be sent because the invitation link is not configured. |
| `invitation-not-found` | 404 | Company | Invitation not found. |
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
| `job-seeker-only` | 403 | Dashboard, Job, JobAlert, JobInvitation, JobSeeker, Messaging, Recruitment, Review, Training | Only a job seeker has this dashboard. |
| `job-taken-down` | 409 | Recruitment | This job was taken down, so its applications can no longer be processed. |
| `last-company-admin` | 409 | Company | A company needs at least one company admin. |
| `legal-document-incomplete` | 422 | Content | The title and the body must be filled in every language before publishing. |
| `legal-document-missing` | 422 | Identity | Every document in force must be accepted together. |
| `legal-document-not-active` | 422 | Identity | A document that is not in force was sent. |
| `legal-document-not-draft` | 409 | Content |  |
| `legal-document-outdated` | 409 | Identity | A newer version was published. Read it and accept again. |
| `legal-document-version-taken` | 409 | Content | Another version with this number was created at the same time. Try again. |
| `location-parent-required` | 422 | ReferenceData |  |
| `method-not-allowed` | 405 | Platform | The HTTP method is not allowed for this resource. |
| `newsletter-issue-not-draft` | 409 | Newsletter | Only a draft can be deleted. |
| `newsletter-no-recipients` | 409 | Newsletter | There is nobody to send this issue to. |
| `newsletter-token-invalid` | 422 | Newsletter | This confirmation link is not valid or has expired. |
| `newsletter-unavailable` | 503 | Newsletter | The newsletter is not available. |
| `no-active-interview` | 409 | Recruitment | This application has no active interview. |
| `no-active-offer` | 409 | Recruitment | This application has no pending offer. |
| `no-proposal` | 409 | Recruitment | There is no proposed time to accept. |
| `not-a-company-member` | 403 | Campaign, Company, Dashboard, Job, JobInvitation, JobSeeker, Messaging, Recruitment, Review | Only a company member can manage campaigns. |
| `not-a-participant` | 403 | Messaging | Only a job seeker or a company member can use chat. |
| `not-found` | 404 | Platform | The requested resource was not found. |
| `not-in-interview-stage` | 409 | Recruitment | Move the application to an interview stage before scheduling an interview. |
| `not-in-offer-stage` | 409 | Recruitment | Move the application to an offer stage before sending an offer. |
| `not-note-author` | 403 | Recruitment | Only the author or a company admin can delete a note. |
| `not-your-message` | 403 | Messaging | Only the sender can delete a message. |
| `note-too-long` | 422 | JobInvitation |  |
| `offer-expired` | 409 | Recruitment | This offer has expired. |
| `offer-not-pending` | 409 | Recruitment | This offer is no longer pending. |
| `offer-required` | 422 | Recruitment |  |
| `own-review` | 403 | Review | You cannot report your own review. |
| `page-out-of-range` | 422 | Job |  |
| `password-required` | 422 | Identity | Set a password before deleting the account. |
| `profile-incomplete` | 422 | Recruitment | Complete your profile before applying. |
| `proposal-limit` | 409 | Recruitment |  |
| `proposal-pending` | 409 | Recruitment | Your proposed time is waiting for an answer from the company. |
| `public-account-required` | 403 | Identity | Only a job seeker or a company member has a profile picture. |
| `question-in-use` | 409 | Recruitment | This question is attached to a job or has answers, so it can only be archived. |
| `reason-too-long` | 422 | JobInvitation |  |
| `recipient-unavailable` | 409 | Messaging | This job seeker no longer has an account that can receive messages. |
| `reference-value-taken` | 409 | ReferenceData |  |
| `rejection-note-required` | 422 | Company | A note is required when the reason is "other". |
| `reply-not-pending` | 409 | Review |  |
| `report-not-open` | 409 | Review | This report was already handled. |
| `resume-limit-reached` | 409 | JobSeeker |  |
| `review-not-editable` | 409 | Review | A review that was taken down cannot be edited. |
| `review-not-pending` | 409 | Review |  |
| `review-not-published` | 409 | Review |  |
| `review-requires-application` | 403 | Review | Only a job seeker who applied to this company can review it. |
| `salary-period-required` | 422 | Job, JobAlert | A minimum salary needs the salary period it is meant in. |
| `schedule-required` | 422 | Recruitment |  |
| `screening-disabled` | 403 | Recruitment | Screening questions are switched off for this company. |
| `screening-limit-reached` | 409 | Recruitment | The job has reached its limit of screening questions. |
| `setting-not-overridable` | 422 | Platform | This setting cannot be overridden per company. |
| `slug-reserved` | 422 | Company | This slug is reserved. |
| `slug-taken` | 409 | Company | This slug is already taken. |
| `social-account-conflict` | 409 | Identity | A different Google account is already linked to this account. |
| `stage-limit-reached` | 409 | Recruitment | The company has reached its limit of stages. |
| `stage-name-taken` | 409 | Recruitment | Another stage of this company already has this name. |
| `stage-type-not-allowed` | 422 | Recruitment | Extra stages can only be of the type screening, interview or offer. |
| `template-unavailable` | 422 | JobSeeker | This CV template is not available. |
| `terms-not-accepted` | 409 | Identity | You must accept the current terms and privacy policy before continuing. |
| `too-many-applications` | 422 | Recruitment |  |
| `too-many-attachments` | 422 | Messaging |  |
| `too-many-recipients` | 422 | Messaging |  |
| `too-many-requests` | 429 | Company, Identity, Job, Platform | Too many requests. Try again later. The `Retry-After` header tells how long to wait. |
| `training-unavailable` | 503 | Training | The training platform is not configured. |
| `unauthenticated` | 401 | Platform | Authentication is required. |
| `unknown-industry` | 422 | Company | The industry does not exist or is not active. |
| `unknown-notification-preference` | 422 | Notification | This notification setting does not exist. |
| `unknown-organization-type` | 422 | Company | The organization type does not exist or is not active. |
| `unknown-team-size` | 422 | Company |  |
| `unsupported-language` | 422 | Platform |  |
| `validation-failed` | 422 | Platform | The request data is invalid. The `errors` member maps each field to its messages. |
| `verification-code-cooldown` | 429 | Identity | Wait a moment before requesting another code. |
| `verification-code-expired` | 422 | Identity | The verification code has expired or is no longer valid. Request a new one. |
| `verification-code-limit-reached` | 429 | Identity | Too many codes were requested. Try again later. |
