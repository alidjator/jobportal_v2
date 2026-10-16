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
- 2026-10-16: the documents call it **backend migration** (Laravel to Spring Boot), never "port": "port" already means the discarded first port of
  karir.pkp and the ports of the architecture (decided by the user; [Direction](#1-direction)).

## 1. Direction

- **Current stack (phase 1):** PHP 8.3, Laravel 13, PostgreSQL 16, Redis. **Target stack (planned):** Spring Boot; the Java and Spring versions and the
  build tool are open (question 6).
- **Incremental:** a step moves one feature or one bounded context at a time; during the migration both back ends run and the old one is switched off
  when the last step is done. A step is done when the new implementation passes the same API contract as the old one.
- **Order:** a simple pilot first, then by risk and value (question 1); how complexity is measured is open (question 2).
- **Helps:** the API is the contract (`/api/v1`, RFC 9457 errors, an OpenAPI document from Scramble) and the code is layered by bounded context
  (Domain, Application, Infrastructure, Interface), so a context maps to a Spring package without changing its behaviour.
- **Costs to plan:** two runtimes side by side, shared authentication and data during the coexistence, and a freeze of the feature being migrated.

## 2. Open questions (one at a time, before the first migration step)

1. ~~Order~~ **answered 2026-10-16: one simple pilot feature first, then by risk and value**, not by complexity alone. The pilot proves the routing, the
   authentication and the contract tests.
2. **Complexity measure:** how it is judged (endpoints, tables, business rules, integrations such as Reverb, Janus, Asterisk)?
3. ~~Routing~~ **answered 2026-10-16: nginx routes by path** (each group of `/api/v1/...` routes goes to Laravel or Spring Boot according to the steps done);
   no new component.
4. ~~Authentication~~ **answered 2026-10-16: Spring Boot verifies the existing Sanctum tokens** (it reads the hashed `personal_access_tokens`); users do not sign
   in again and clients do not change.
5. ~~Database ownership~~ **answered 2026-10-16: one shared PostgreSQL schema; the migrations stay in Laravel until the last step**, then move to a Java
   migration tool.
6. **Baseline:** Java version, Spring Boot version, build tool (Maven or Gradle), persistence library.
7. **Queues, scheduler, events and files** shared during the coexistence (database queue, the three queue names, domain events, the private file disk).
8. **The meaning of "phase 1 release"** (the ERP talent acquisition go-live, or the first customer release) and the freeze rule for features in a step.
9. **Effect on the self-hosted edition and on module entitlements** (`self-hosted.md`): one release carries two runtimes while both exist; the licence
   file and the upgrade steps must work with both.
