# Phase 8: items from the multi-persona gap analysis and a coding order (proposed 2026-10-20)

A proposal for `phase-8-index.md` (the next free numbers are 35 to 44; the index belongs to the design session, so the rows below are ready to paste there). Each row is one coding stage of its design document. The order follows three facts: the import is **not frozen yet** (anything that changes the import joins the second rehearsal of #34), most of these items are small and independent, and the API coding rule of `decisions.md` section 5 allows only the items the user adds to it. Nothing here is coded.

## Items

| # | Design | Gaps | Parts | Changes the import | Waits for |
|---|---|---|---|---|---|
| 35 | `phase-8-job-copy-in-application.md` | MG-A10 | 2: copy at apply and read; migration and importer | **yes** (`ApplicationsImporter`) | |
| 36 | `phase-8-imported-history-in-reports.md` | MG-B11 | 2: `job_jobs.migrated_at` and importer; report filters and `includes_imported` | **yes** (`JobsImporter`) | `migrated_at` of applications (done, #34 part 4) |
| 37 | `phase-8-company-team-fixes.md` | MG-P03, MG-B03, MG-B09 | 3: one each | no | |
| 38 | `phase-8-admin-queue-cards.md` | MG-C06 | 1 | no | |
| 39 | `phase-8-account-and-public-items.md` | MG-A01, A03, A11, A12, A13, C01 (A14 later) | 4: sitemap and support contact; language and sessions; psychologist password; the A12 test | no | A14 waits for #26 |
| 40 | `phase-8-recruitment-closure.md` | MG-P04, MG-P05 | 3: deletion request; pause and shift; notices | no | the `system` withdrawal (done, MG-A08) |
| 41 | `phase-8-approval-escalation.md` | MG-B10 | 2: counters, setting, task and notices; replacing a filled place | no | |
| 42 | `phase-8-concurrent-edits.md` | MG-B08 | 2: jobs; offers and interviews | no | the front end sends `If-Match` before the setting is turned on |
| 43 | `phase-8-reference-merge.md` | MG-C02 | 3: the port and the log; the targets of each context; the endpoint and slugs | no | |
| 44 | `phase-8-company-support-access.md` | MG-C05 | 2: grants; the read views | no | |

## Waves

1. **Before the second rehearsal** (they change the import, so one rehearsal covers #34 and them): #35 and #36.
2. **Small and independent, can run in parallel sessions** (each in its own contexts): #37, #38 and #39. #37 touches Recruitment, Job and Approval; #38 Company, Review and Messaging; #39 Identity, Job (a read feed) and Company.
3. **Candidate trust and approvals:** #40 (deletion first, then the suspension) and #41.
4. **Larger and less urgent:** #42, #43, #44; the A14 part of #39 after #26.

Waves 2 to 4 do not change the data, so they can follow the final import. Wave 1 cannot: the freeze date should wait for #35 and #36 or they follow a later import.

## What the user decides

- Whether these items join the allowed list of `decisions.md` section 5 (until the front end catches up only #29, #30, #15, #20, #34 and the endpoints the front end asks for may be coded); the gap analysis 5.3 puts the low-effort, high-value ones (#37, #38) inside that rule.
- Whether the freeze waits for #35 and #36.
