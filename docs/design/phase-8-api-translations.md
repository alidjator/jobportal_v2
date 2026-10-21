# Phase 8: Indonesian API text

New API work asked by the front end (`docs/design/frontend.md`, section 10, "Error language"). Designed on 2026-10-13; the translations are coded on
2026-10-21 for front-end step 0 ([coded](#coded-2026-10-21)); the notification catalog is not.

## Decisions

- 2026-10-13: error and validation text is translated in the API; the front end shows the text the API returns and decides by `code` only (decided in
  `frontend.md` 10).
- 2026-10-13: Laravel's Indonesian validation messages come from the community `laravel-lang` packages, published into `lang/` and reviewed
  ([detail](#design)).
- 2026-10-13: the rest of the design agreed as proposed: message templates with parameters translated by the renderer, the coverage check, an
  Indonesian name for every validated field, the session drafts and the user reviews ([detail](#design)).
- 2026-10-13: a notification catalog is generated for the front end, which words in-app notifications itself ([detail](#notification-catalog)).
- 2026-10-21: the translations are coded for front-end step 0; the notification catalog waits for the notification pages (`frontend.md` 11.6) ([coded](#coded-2026-10-21)).
- 2026-10-21 (decision gate): no `laravel-lang` release supports Laravel 13 (publisher 16.8 and 17.x-dev accept illuminate up to ^12), so
  `lang/en/validation.php` is copied from the framework and `lang/id/validation.php` is translated here, without new dependencies ([coded](#coded-2026-10-21)).
- 2026-10-21: the Indonesian text is drafted with one glossary and reviewed by the user in the diff before the commit ([coded](#coded-2026-10-21)).

## Current state (read from the code, 2026-10-13)

- `ResolveRequestLanguage` sets the application locale from `Accept-Language` (then the account `locale`, then `id`), but the repository has no `lang`
  folder, so every text below falls back to English.
- **Validation:** 150 form requests; Laravel's default messages ("The :attribute field is required.") and attribute names taken from the field keys
  (`filter.q`). A few closures in requests write their own message (`SearchJobsRequest`, `ApproveCompanyDocumentRequest`).
- **Problem `detail`:** written in English at about 548 throw sites (`BusinessRuleViolation`, `StateConflict`, `AccessDenied`, `EntityNotFound`,
  `AuthenticationFailed`, `ExternalServiceUnavailable`, `TooManyAttempts`); about 80 of them build the text with values (`"... from 0 to {$points}."`,
  `sprintf`, a variable). One code can carry several messages (`invalid-video` has three), so a translation keyed by code alone is not enough. Some 422
  rules repeat the message in an `errors` member.
- **`title`:** the HTTP status text (`Unprocessable Content`).
- **Not affected:** emails (per-locale templates, `phase-0` email templates), translated master data and content (`TranslatedText`, `architecture.md`
  2.9), and in-app notifications, which carry a `type` and `data` and are worded by the front end.

## Design

Agreed by the user on 2026-10-13.

1. **Validation (package chosen by the user):** `laravel-lang/lang` with its publisher (`laravel-lang/publisher`, `php artisan lang:add id`) as development
   dependencies only; the published `lang/id/validation.php` and `lang/en/validation.php` are committed and reviewed like the rest of the text, so
   production does not need the packages. The version that supports Laravel 13 is checked when coding (decision gate if none does). The
   `attributes` list gives an Indonesian name to every field key the requests validate (`email` "email", `password` "kata sandi", `filter.q` "kata
   kunci"); a test collects the rule keys of every form request and fails when one has no Indonesian name. The closures in requests use `__()` with the
   English text as the key.
2. **Problem `detail`:** the English text stays at the throw site as the key; an exception whose text holds values gets a template with placeholders
   and the values as parameters (`'The score must be a whole number from 0 to :points.'`, `['points' => 5]`). `DomainException` (Shared, framework-free)
   gains the optional parameters; the renderer translates `detail` and the messages in `errors` with Laravel's JSON translations (`lang/id.json`), so the
   domain does not call the translator. A missing translation falls back to the English text.
3. **Coverage check:** `api:error-catalog` already reads the literal messages per code; it also lists every message template, and `--check` fails when
   one has no entry in `lang/id.json`, so a new message cannot ship untranslated.
4. **`title`:** translated with the status texts (a short list in `lang/id.json`).
5. **Wording:** a session drafts the Indonesian text with one glossary (lowongan, pelamar, perusahaan, rekruter, tahap, penilaian), consistent with the
   front end's `packages/i18n`; the user reviews it before it is merged.

## Notification catalog

Decided by the user on 2026-10-13 (`frontend.md` 11.6). In-app notifications carry a `type` and `data` and are worded by the client, but the OpenAPI
document types `data` as a string, so the client has no list of types. A command (`api:notification-catalog`, name settled when coding) reads the
notification types of every context (the `*NotificationType` classes and the assessment and interview types) with their recipients and `data` keys and
writes `docs/api/notification-catalog.md` plus a JSON copy the front end reads; `--check` fails when the file is stale, run by a unit test as for the
error catalog. Each type's `data` keys are declared next to the type so the catalog does not guess them.


## Coded (2026-10-21)

Branch `frontend-step-0-api`; no migration.

- **Templates:** `DomainException` takes `parameters` (`:name` placeholders); `getMessage()` is the filled English text, `template()` the key.
  `ErrorMessage` (Shared/Domain) is one message with its own values, for an `errors` list whose messages do not share them or a helper that takes the
  caller's message; an error raised with one takes its template. `EntityNotFound::of('Job', 7)` is the template `Job not found: :id`. About 170 throw
  sites that built their text with values were turned into templates; the English output is unchanged.
- **Renderer:** `ProblemDetailsRenderer` translates `title`, `detail` and every message of `errors` (also those of a validation error, so a middleware's
  `ValidationException::withMessages` is covered) through Laravel's JSON translations; a text without a translation stays English.
- **Validation:** `lang/en/validation.php` (framework copy), `lang/id/validation.php` (translated, with an Indonesian name for each of the 508 field keys
  the form requests validate); closure rules use `$fail('...')->translate()`. `ValidationAttributesTest` builds every form request on an empty route
  and fails when a field has no Indonesian name.
- **Coverage:** `ErrorCatalog::templates()` reads every message a client can read (literals, constants, `match` and ternary branches, local variables,
  helper methods and their calls in any file, lists, `ErrorMessage`, closure rules, status texts); `php artisan api:error-catalog --check` fails when one
  has no entry in `lang/id.json` (1141 entries), and `--untranslated` lists them. No message is unresolved.
- **Tests stay English:** the test client sends `Accept-Language: en` by default (Symfony's request default), so existing assertions keep the English
  text; `ProblemDetailsTranslationTest` asks for `id`.
- **Not translated:** the operator texts of console commands and the activation report (`identity:send-activation-emails`), which stay English.
