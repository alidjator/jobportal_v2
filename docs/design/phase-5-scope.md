# Phase 5 scope (being designed, 2026-10-10)

Phase 5 follows the closed gap analysis (only R8, the skill test, remains, excluded by the user). Each chosen item gets its own `phase-5-*.md` design document before it is coded.

## Decisions

- 2026-10-10: Phase 5 covers three groups: deferred product decisions, extensions of the Phase 4 features, hardening and quality ([Groups](#groups)). Go-live readiness (import rehearsal, cutover, deployment) is not part of Phase 5.
- 2026-10-10: Order of work: hardening and quality first, then the deferred decisions, then the Phase 4 extensions ([Order](#order)).
- 2026-10-10: In scope: all three hardening items; fresh graduate category, immediate clearing of talent pool notes, SQL medians and ranked full-text search; newsletter and company review extensions. Out: applicants living abroad, the training (Moodle) extensions ([Groups](#groups)).

## Groups

**Deferred product decisions** (sources: `decisions.md`, `postgres-migration.md`, `HANDOFF.md`):

- *Out (2026-10-10):* applicants living abroad: profile location is Indonesian only (BPS chain); options recorded in `decisions.md`.
- The computed "fresh graduate" category (deferred in `decisions.md` and `phase-3-matching.md`).
- Immediate (not nightly) clearing of talent pool notes, which needs a trigger in the JobSeeker context.
- Live business intelligence duration medians moved to SQL (`percentile_cont`).
- Ranked full-text search (`tsvector`).

**Extensions of Phase 4 features** (the "Not in this phase" sections of `phase-4-newsletter.md`, `phase-4-training.md`, `phase-4-company-reviews.md`):

- Newsletter: segments, scheduled sends, open and click tracking, issue templates, subscriber import, subscribing from the profile.
- *Out (2026-10-10):* training: enrolling a job seeker in a Moodle course, Moodle accounts, single sign-on, certificates on the profile, per-company recommendations.
- Company reviews: helpfulness votes, rating on the company card, review count in the job search, verified employment, interview experience.

**Hardening and quality:**

- Security review of the API, query and index performance audit, consistency of the API documentation and the error catalog.

## Order

1. Hardening and quality: API security review, performance audit, API documentation consistency.
2. Deferred decisions: fresh graduate category, immediate clearing of talent pool notes, SQL medians, ranked full-text search.
3. Phase 4 extensions: newsletter, company reviews.
