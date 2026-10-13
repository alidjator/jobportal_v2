# Phase 8: training course detail

New API work asked by the front end (`docs/design/frontend.md`, section 7.4, page P6b `/pelatihan/{course}`). Designed on 2026-10-13, coded after the
legacy import rehearsal together with the front end. Code is not started. The catalog itself is described in `phase-4-training.md`.

## Decisions

- 2026-10-13: add `GET /api/v1/training/courses/{id}` (decided in `frontend.md` 7.4).
- 2026-10-13: design agreed as proposed, including paragraph breaks kept in the summary ([detail](#design)).
- 2026-10-13: hidden Moodle courses (`visible = 0`) are left out of the public list and detail; "my courses" still shows them ([detail](#hidden-moodle-courses)).

## Design

Agreed by the user on 2026-10-13.

- **Source:** the cached catalog (`CachedTrainingCatalog::courses()`, with its stale copy when Moodle is down); the course is found by its Moodle id, so
  the detail adds no Moodle call. An unknown id answers 404 `course-not-found`; Moodle down with no cached copy answers 503 `training-unavailable` as the
  list does.
- **Route:** public, the same limiter (`TrainingServiceProvider::PUBLIC_LIMITER`) and `cache.headers:public;max_age=60;etag` as the list.
- **Body:** the same `CourseResource` as the list (`id`, `name`, `summary`, `image_url`, `starts_at`, `ends_at`, `url`).
- **Front-end path:** `/pelatihan/{id}-{slug}` (`/en/training/{id}-{slug}`), the slug made from the name in the front end; only the id is sent to the
  API, and a path whose slug differs from the current name is redirected (301) to the right one.
- **Summary paragraphs:** the mapper turns the Moodle HTML summary into one line (`plainText()` folds every whitespace run, newlines included). For a
  detail page the paragraph breaks are kept: block ends (`</p>`, `<br>`) become a blank line, other whitespace is folded as now. The list uses the
  same text, so its cards show the breaks too (the front end can cut them).

## Hidden Moodle courses

**Decided by the user on 2026-10-13: option 2.** `Course` carries the Moodle `visible` flag; `ListCourses` and the new detail drop hidden courses (the
detail answers 404 `course-not-found`), `ListMyCourses` keeps them so an enrolled user still finds the course. This also fixes the existing public
list, which showed hidden courses.

The options weighed:

`MoodleTrainingCatalog::courses()` keeps every course that `core_course_get_courses_by_field` returns except the site course. Moodle returns hidden
courses (`visible = 0`) too when the web-service account may see them, so a course an editor hid on Moodle (a draft, an archived course) is listed
publicly today and would get its own indexed page. Options:

1. Keep only `visible = 1` courses in the catalog (list, detail and the enrolled courses alike).
2. Keep only `visible = 1` in the public list and detail; "my courses" still shows a hidden course the user is enrolled in.
3. Leave it as it is.
