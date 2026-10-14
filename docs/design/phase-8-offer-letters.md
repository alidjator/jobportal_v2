# Phase 8: offer terms, offer letter and accepted-offer evidence (designed 2026-10-14, not coded)

Gaps found against Mekari Talenta's offering letter (help center articles 34783830257305 and 36986013467033). Extends `phase-2-offers.md`
(Recruitment). Coded in a coding session.

## Decisions

- 2026-10-14: an offer carries **full terms**: employment type, contract length or probation, work mode and location, gross or net salary,
  **benefits** from the company's list (prefilled from the job) and up to 10 **allowance lines** with an amount (decided by the user).
- 2026-10-14: the **system generates the offer letter PDF** from the terms; the company may **replace it with its own PDF** (decided by the user).
- 2026-10-14: accepting needs a **checkbox and the account password** (Google-only accounts confirm through Google); the time, address, browser and
  the document hash are kept as evidence (decided by the user). A certified e-signature (PSrE) is not built now.
- 2026-10-14: the **labour-law checks** on probation and contract length are a **setting**, on or off, because both are needed (decided by the
  user): a platform default with a per-company override.
- 2026-10-14: the **double email** on a move to an offer or interview stage goes: the stage email is skipped when the offer or interview email goes
  out.

## 1. Offer terms

New fields on `recruitment_offers` (nullable for the offers sent before):

| Field | Rule | Prefill from the job |
|---|---|---|
| `employment_type` | `permanent` (PKWTT), `contract` (PKWT), `internship`, `daily` (harian lepas), `freelance`; required | the job type, through a slug mapping decided while coding (no prefill when it does not map) |
| `contract_months` | 1 to 60; required for `contract` and `internship`, refused for the others | none |
| `probation_months` | 0 to 12, optional; see the labour-law checks | none |
| `work_mode` | `onsite`, `hybrid`, `remote`; required | `work_mode` |
| `work_location` | text, at most 255 characters; required unless `remote` | city and province of the job |
| `salary_basis` | `gross` or `net`; required | none |
| `allowances` | at most 10 lines `{name (at most 100 characters), amount (1 to 1,000,000,000 Rupiah per month)}` | none |
| `benefits` | ids from the company's benefits and the platform list (`reference_benefits`), stored as a snapshot of their names | the job's benefits |

`position`, `salary` (monthly base), `start_date`, `note` and `expires_at` stay as they are. The request fields are added to `PUT .../offer` and the
`offer` object of the move request; the front end prefills from `GET company/jobs/{id}` (no new endpoint).

### Labour-law checks (setting)

- Setting `recruitment.offer_labor_rules` (on by default), overridable per company by the platform admin (D4), like the assessment bands.
- **On** (PP 35/2021): probation only for `permanent`, at most 3 months (422 `probation-not-allowed`, `probation-too-long`); `contract` at most 60
  months; `internship` at most 12 months (422 `contract-too-long`).
- **Off:** only the field limits of the table apply. The letter does not mention the rules either way.

## 2. Offer letter PDF

- **Generated** when the offer is sent (and for each revision): dompdf, a Blade view in Recruitment, A4. Content: company logo, name and address;
  the date; the candidate's name; position; employment type with contract length or probation; work mode and location; start date; base salary
  (gross or net) per month; allowances with their total; benefits; the note; the answer deadline; "Diterbitkan oleh {sender name} melalui
  {platform}". Language: the candidate's.
- **Own PDF instead:** the company uploads a PDF first, `POST company/applications/{id}/offer-letter-uploads` (multipart `file`, PDF only checked by
  its content, at most 10 MB), which answers an `upload_id` valid 24 hours; the offer request then takes `letter_upload_id`. The file becomes the
  letter; the terms stay the structured summary on the platform. An unused upload is deleted after 24 hours. Not available in bulk moves.
- **Stored** on the private disk under `recruitment/offers/{offer}/`, with `letter_source` (`generated`, `uploaded`) and `letter_sha256` (of the
  exact file sent). A revision is a new offer with its own letter; earlier letters stay with their offers.
- **Read:** the team through `GET company/applications/{id}/offers/{offerId}/letter.pdf`, the candidate through
  `GET me/applications/{id}/offer/letter.pdf`, as downloads with the headers of the company documents. The offer emails **link** to the platform's
  offer page; the file is not attached (as for assessment attachments).
- Erased with the applicant's anonymisation, as the offers are today.

## 3. Accepting with evidence

- `POST me/applications/{id}/offer/accept` takes `agree: true` (422 `agreement-required`) and `password` (422 `invalid-password`, rate limited);
  a Google-only account sends a fresh Google ID token instead (`google_id_token`, checked like the Google sign-in, same account). The front end
  shows the letter (PDF viewer) above the checkbox "Saya telah membaca dan menyetujui surat penawaran ini".
- **Evidence kept** on the offer: `accepted_at`, `accepted_ip`, `accepted_user_agent`, the `letter_sha256` that was accepted (the hash of the file
  shown; the request carries it, `letter-changed` 409 when it differs from the current letter) and the method (`password`, `google`).
- **Evidence PDF:** a separate one-page "Bukti persetujuan penawaran" is generated on acceptance: offer, candidate, company, the accepted letter's
  file name and SHA-256, time (Jakarta), address and browser, method. Readable by the team and the candidate next to the letter. The letter itself is
  never changed, so its hash stays valid.
- Declining stays as it is (no password).
- Legal note: an electronic acceptance with this evidence is an electronic signature in the sense of UU ITE, but **not a certified** one; a PSrE
  integration (Privy, VIDA, Peruri, Mekari Sign) may come later, priced per signature (billing stays out of scope). Added to `legal-review.md`.

## 4. One email per move

- A move into an `offer` stage that sends an offer: the job seeker gets the offer email (`recruitment.offer_sent`) and the in-app offer
  notification, **not** the stage email `recruitment.application_offer` and its in-app notice.
- The same for `interview`: a move that schedules an interview sends only the interview email; a move with `schedule_later` sends the stage email
  as today (it is the only notice then).
- A move with an offer or interview already active (no new one sent) sends the stage email as today.

## Changes in short

| Piece | Change |
|---|---|
| Migration | `recruitment_offers`: the term columns, `letter_path`, `letter_source`, `letter_sha256`, `accepted_ip`, `accepted_user_agent`, `accepted_letter_sha256`, `acceptance_method`, `evidence_path`; a table for pending letter uploads |
| Setting | `recruitment.offer_labor_rules` with a per-company override |
| API | terms in `PUT .../offer` and the move request; `POST company/applications/{id}/offer-letter-uploads`; `GET .../offers/{offerId}/letter.pdf` and `evidence.pdf`; `GET me/applications/{id}/offer/letter.pdf` and `evidence.pdf`; `agree`, `password` or `google_id_token`, `letter_sha256` on accept |
| Errors | 422 `probation-not-allowed`, `probation-too-long`, `contract-too-long`, `agreement-required`, `invalid-password`; 409 `letter-changed` |
| Notifications | stage email and notice skipped when an offer or interview email goes out |
| Scheduler | delete letter uploads older than 24 hours (daily) |
| Front end | offer dialog with the terms and the upload (C7); the job seeker's offer page with the PDF viewer, checkbox and password |
