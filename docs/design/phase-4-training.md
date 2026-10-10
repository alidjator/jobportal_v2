# Moodle training integration (designed 2026-10-10)

The legacy site shows a "Training" page to job seekers: the catalog of the PKP Moodle (`course.training.pkp.co.id`, 16 courses) and the courses the signed-in job seeker is enrolled in, with progress. It calls the Moodle web service (`core_course_get_courses_by_field`, `core_user_get_users_by_field`, `core_enrol_get_users_courses`) from the web server. The new system keeps both lists and fixes the problems below. Nothing is written to Moodle and nothing is stored in our database.

## Legacy problems that are not carried over

- **S12:** the web service token is sent in the query string of GET requests (it ends up in access logs). The new client sends it in the POST body.
- **Hardcoded email:** the legacy code looks the Moodle user up with a fixed address, so every job seeker sees the "my courses" of one person. The new code uses the email of the signed-in account.
- The site course (Moodle id 1, removed in the legacy view with `unset($courses[0])`) is filtered by id, not by position.
- A failed call returns a JSON error object that the view then treats as a list. The new code raises a typed error (see Failure).

## Decided (2026-10-10, user)

1. **Catalog:** proxy with cache. The API calls Moodle and keeps the result in the application cache for 15 minutes; no new table. When Moodle fails and a cached copy exists, the cached copy is served.
2. **Matching a job seeker to a Moodle account:** by email, **only when the account email is verified**. No Moodle account for that email gives an empty list, not an error.
3. **Failure:** when Moodle cannot be reached (or answers with an error) and there is no cached copy, the response is **503 `training-unavailable`**.

## Proposed (no decision needed unless you disagree)

- **Context:** a new `Training` bounded context (`src/Training`), no tables. Domain: `Course` and `Enrollment` value objects. Application: `ListCourses`, `ListMyCourses`, a port `TrainingCatalog` (`courses()`, `enrollmentsOfEmail()`). Infrastructure: `MoodleTrainingCatalog` (Laravel `Http` client, timeout 5 s, one retry) plus a caching decorator; a fake for tests (`Http::fake`).
- **Settings (env, read through `config/services.php`):** `MOODLE_BASE_URL` (the web service server URL), `MOODLE_TOKEN`, `MOODLE_COURSE_URL` (template with `{id}`, default `https://course.training.pkp.co.id/course/view.php?id={id}`). The token is never logged and never returned. With no URL or token configured the endpoints answer 503 `training-unavailable`.
- **Endpoints**
  - `GET training/courses` (public, job seekers and visitors alike; rate limited like the other public reads): `id`, `name`, `summary` (HTML stripped, plain text), `image_url`, `starts_at`, `ends_at` (ISO 8601 UTC, null when Moodle sends 0), `url`. Search `q` on the name and `page`/`per_page` are applied to the cached list in memory (16 courses today).
  - `GET me/training/courses` (signed-in job seeker, verified email required, else 403 `email-not-verified`): `id`, `name`, `summary`, `image_url`, `progress` (0 to 100 or null), `starts_at`, `ends_at`, `last_access_at`, `url`. Not cached across users (cached 5 minutes per account so refreshing the page does not hit Moodle).
- **Mapping details:** Moodle timestamps are Unix seconds, 0 means "not set". `progress` from Moodle is a float or null. The legacy view printed the summary of the wrong course in "my courses"; the new resource uses each course's own summary.
- **Errors:** any HTTP error, timeout, or Moodle `exception` payload in the body (Moodle answers 200 with `{"exception": ...}`) is turned into `TrainingUnavailable` (503, code `training-unavailable`); the Moodle message is logged without the token, never returned.
- **Tests:** `Http::fake` for success, empty list for an unknown email, unverified email 403, site course filtered out, token in the POST body and absent from the URL, cache hit on the second call, stale copy served when Moodle fails, 503 when it fails with no copy, Moodle `exception` payload handled, `q` filter and pagination.
- **Client:** the training page calls the two endpoints; "open course" links go to the Moodle course page (no single sign-on in this phase).

## Not in this phase

- Enrolling a job seeker in a course, creating Moodle accounts, single sign-on, certificates on the profile, per-company training recommendations.
