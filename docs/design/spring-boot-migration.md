# Backend migration to Spring Boot (decided 2026-10-16, not coded)

After the phase 1 release the back end is moved from Laravel (PHP) to Spring Boot (Java). This document records the direction and the questions that
must be answered before any migration work. It changes no code and no schema. The REST API stays the contract (`architecture.md` section 1), so the
front end is not part of this migration. Plans, billing, payments, orders and invoices stay out of scope.

## Decisions

- 2026-10-16: after the phase 1 release the back end **migrates from Laravel to Spring Boot** (decided by the user; [Direction](#1-direction)).
- 2026-10-16: the migration is **incremental, in steps ordered by the complexity of the features** (refined below to a simple pilot, then by risk and
  value), not a single rewrite (decided by the user;
  [Direction](#1-direction)). The common names are *incremental migration* and the *strangler fig pattern*.
- 2026-10-16: **one simple pilot first, then by risk and value; nginx routes by path; Spring Boot verifies the existing Sanctum tokens; one shared schema with
  migrations in Laravel until the last step** (decided by the user; section 2, questions 1, 3, 4 and 5).
- 2026-10-16: **the migration starts after the ERP talent acquisition go-live; order by a combined complexity score; a shared outbox table carries events
  across the two back ends; Maven and Spring Data JPA** (decided by the user; section 2, questions 2, 6, 7 and 8).
- 2026-10-16: **a feature is frozen while its step runs; the self-hosted edition is released only after the back end is fully Spring Boot** (decided by the user;
  section 2, questions 9 and 10). All questions of section 2 are answered.
- 2026-10-16: the documents call it **backend migration** (Laravel to Spring Boot), never "port": "port" already means the discarded first port of
  karir.pkp and the ports of the architecture (decided by the user; [Direction](#1-direction)).

## 1. Direction

- **Current stack (phase 1):** PHP 8.3, Laravel 13, PostgreSQL 16, Redis. **Target stack (planned):** Spring Boot with Maven and Spring Data JPA; the Java and
  Spring versions are fixed when the migration starts (question 6).
- **Incremental:** a step moves one feature or one bounded context at a time; during the migration both back ends run and the old one is switched off
  when the last step is done. A step is done when the new implementation passes the same API contract as the old one.
- **Order:** a simple pilot first, then by risk and value (question 1); complexity is a combined score (question 2).
- **Helps:** the API is the contract (`/api/v1`, RFC 9457 errors, an OpenAPI document from Scramble) and the code is layered by bounded context
  (Domain, Application, Infrastructure, Interface), so a context maps to a Spring package without changing its behaviour.
- **Costs to plan:** two runtimes side by side, shared authentication and data during the coexistence, and a freeze of the feature being migrated.

## 2. Open questions (one at a time, before the first migration step)

1. ~~Order~~ **answered 2026-10-16: one simple pilot feature first, then by risk and value**, not by complexity alone. The pilot proves the routing, the
   authentication and the contract tests.
2. ~~Complexity measure~~ **answered 2026-10-16: a combined score per context** (endpoints, tables, business rules, outside integrations such as Reverb, Janus,
   Asterisk and email), ordered together with risk and business value.
3. ~~Routing~~ **answered 2026-10-16: nginx routes by path** (each group of `/api/v1/...` routes goes to Laravel or Spring Boot according to the steps done);
   no new component.
4. ~~Authentication~~ **answered 2026-10-16: Spring Boot verifies the existing Sanctum tokens** (it reads the hashed `personal_access_tokens`); users do not sign
   in again and clients do not change.
5. ~~Database ownership~~ **answered 2026-10-16: one shared PostgreSQL schema; the migrations stay in Laravel until the last step**, then move to a Java
   migration tool.
6. ~~Baseline~~ **answered 2026-10-16: Maven and Spring Data JPA (Hibernate)**, on the latest Java LTS and stable Spring Boot when the migration starts (the exact
   versions are fixed then); Flyway for the migrations after the last step.
7. ~~Events and queues across the two back ends~~ **answered 2026-10-16: a shared outbox table** in the shared PostgreSQL schema, written in the same transaction as the
   change; the listener runs in the back end that owns its context. The Laravel queue stays with Laravel until its context moves. The scheduler and the
   private file disk are decided with the first step.
8. ~~The meaning of "phase 1 release"~~ **answered 2026-10-16: the go-live of the ERP talent acquisition** on the PKP production site.
9. ~~The freeze rule~~ **answered 2026-10-16: a feature is frozen while its step runs** (no new behaviour in either back end until the step is done); an urgent bug
   fix is made in Laravel and followed by the new code.
10. ~~Effect on the self-hosted edition~~ **answered 2026-10-16: the self-hosted edition is released only after the back end is fully Spring Boot**, so it never
   packages two runtimes (`self-hosted.md`). SaaS keeps running both during the migration. All questions of this section are answered.
