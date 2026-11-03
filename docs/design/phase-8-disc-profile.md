# Phase 8: the most/least choice and the DISC profile (#47, designed 2026-11-02, step 1 of the API coded 2026-11-03)

Chosen by the user on 2026-11-02 ("DISC penuh"): a battery of the DISC family (the questionnaire in which the candidate marks, in each group of statements, the one that is **most** like them and the one that is **least** like them, scored on four dimensions with three graphs) can be built in Portal Mitra and taken in Career. Extends `phase-6-assessments.md` (questions, scales, norms, attempts), `phase-8-provider-tools.md` (norms by group, report, recommendation, automatic issue) and `phase-8-provider-institutions.md`. D1, D4 and D5 were decided by the user on 2026-11-02 (D1 settled the same day: the partners fill the catalogue like for any psychometric battery). The coding of the mechanism can start; no content is awaited from PKP.

**Read from the code (2026-11-02):** `forced_choice` takes **one** pick per question (`AnswerGrader::scaleOf` is the scale of the chosen statement); `ScoreAttempt` adds one score to one scale per answer; a norm row maps the raw count of **one scale** to a standard score and a band, and a raw score may be negative (`raw_min` and `raw_max` run from -100000 to 100000); `QuestionRepository::scalesOfTest` collects the scales the norms and the report template may name; the report template knows `{{scale:<scale>:raw|standard|band}}`; signing needs a norm row for every scale of the result. Nothing of DISC exists (no battery, no items, no seed).

## Decisions (proposed)

- **D1 (decided by the user, 2026-11-02, settled the same day): the same as every other psychometric method.** The "bank" is the platform's **catalogue of batteries**, and the **partners fill it**: a DISC battery is written, published and owned by a partner institution in Portal Mitra, granted to companies by the platform admin (`assessment_company_batteries`), and its reports are numbered and signed under that institution, exactly like a personality, intelligence or speed battery. The platform ships the mechanism (the question type, the scoring, the graphs) and no instrument content of its own; it does not invent statements or norms, and each partner answers for the right to use what it enters and for its validation. Several partners may each offer a DISC battery. Demo data holds a small battery of obviously fictitious statements, to try the whole path. A bulk entry for the 24 groups (a CSV of group, statement and dimension) is a later convenience if the partners ask; the editor takes one question at a time. (Two earlier readings of the day were withdrawn: a platform item bank under a first-party provider, and a shared library of contributed items.)
- **D2: a new question type `most_least`.** Three to six statements (four is the classic group); every statement has a **dimension** (its `scale_key`, for example `d`, `i`, `s`, `c`); the candidate picks exactly one statement as the most like them and a different one as the least like them. No `score_value`, no `points` (the question adds nothing to a total).
- **D3: scoring by counts, three graphs.** Each answer adds 1 to the raw score `<dimension>_most` of the statement picked as most and 1 to `<dimension>_least` of the one picked as least. After the last answer `<dimension>_change` is `most - least` (from -N to N for N groups). These twelve keys (four dimensions times three) are the scales of the attempt, the scales an author writes **norm rows** for (the existing table, a negative raw range for the change), and the scales the report template names. The bare dimension (`d`) is not a scale of a `most_least` battery. A neutral statement (no dimension) is not offered: an author who needs one gives it a dimension of its own, which then needs a norm row like the others.
- **D4 (decided by the user, 2026-11-02): the three graphs are called by what they say** (Paling, Paling tidak, Perubahan), not by a product's names; the wording is in the translations, so a provider may use its own in the report text.
- **D5 (decided by the user, 2026-11-02): charts.** For a battery with `most_least` questions the review in Portal Mitra and the PDF show the standard scores of each graph as a line over the four dimensions (an inline SVG in the PDF, which dompdf draws; a chart component on the screen), beside the table. The report text gets `{{profile:most}}`, `{{profile:least}}` and `{{profile:change}}`: the dimensions whose standard score in that graph reaches the battery's high mark, highest first, as letters (`DI`), `-` when none. The high mark is the standard score of the first row whose band the author calls high; until the norm rows say it, 55 (the recommended threshold of the recommendation rule's default). Named patterns (a library of profile descriptions) are not built: the author writes them as the norm explanations per dimension and graph.
- **D6: the rest of the machinery is unchanged.** Norms by age and education group, the recommendation rule (key scales may be the derived keys), automatic issue (it needs a norm row for every derived scale), the register, the verification and the company's comparison read the derived scales like any scale.

## Schema

One migration: extend the `type` check of `assessment_questions` (`most_least`). No new table or column: the answer keeps `option_positions` as `[most, least]` (the column is already a list), and the statements are `assessment_question_options` with their `scale_key`.

## Rules and API

- `QuestionType::MostLeast` (graded without a person; `hasChoices`; psychological batteries only). `QuestionRules`: options 3 to 6, every option has a `scale_key` (1 to 20 characters, lower case letters and digits), no `score_value`, no `is_correct`; 422 `invalid-question` otherwise. A battery must give all its `most_least` questions the **same set of dimensions** and at least two (422 `dimensions-differ`, `test-incomplete` when publishing).
- Answering: `PUT me/assessment/invitations/{id}/attempt/questions/{questionId}/answer` takes `option_positions: [most, least]`; two different positions inside the options of the question, both needed (422 `invalid-answer`). Marking only one is a saved draft the server does not count.
- Scoring: `AnswerGrader::contributions(question, answer)` returns `scale => amount` (1 for the most statement's `_most`, 1 for the least statement's `_least`); `ScoreAttempt` adds the contributions of every answer, then adds the `_change` of each dimension. The answer's own `score` is 0 and the question's `points` 0.
- `QuestionRepository::scalesOfTest` returns the twelve derived keys for such a battery, and `NormRules` accepts them. The report renderer knows the `profile:` placeholders; the review and the result carry the `scales[]` as today (the charts read them).

## Screens

- **Career test runner:** a `most_least` question is a table of its statements with two columns, "Paling" and "Paling tidak", one radio each; picking a statement as the most clears it from the least and the other way round; keyboard and screen-reader labels per cell. The section navigation, the timer and the integrity signals are unchanged.
- **Portal Mitra, battery editor:** the question dialog lists `most_least` among the psychological types; its options are statements, each with a dimension; the editor warns when the groups do not share the same dimensions. The norms tab lists the twelve scales (it already lists the scales of the test).
- **Portal Mitra review and the PDF:** the three charts of D5 beside the scale table.
- **Company and candidate:** nothing new: the company reads the signed report (and its PDF) as today; the candidate reads the explanation, which comes from the norm explanations.

## Not taken

An item bank, named profile patterns, graphs plotted against a published norm group of another publisher, adaptive selection, a score for the consistency of the answers (a later part if a provider asks).

## Tests

The type rules (statement count, dimensions, same set across questions), answering (two positions, same position refused, one missing), the scoring (a known set of answers gives the known twelve raw scores and the right `_change`), norms accept negative ranges and refuse the bare dimension, signing needs a norm row for each derived scale, the report placeholders, an automatic issue of a DISC battery, and the Career runner and editor (Vitest of the helpers).

## Coded 2026-11-03: step 1, the API (migration `000407`)

`QuestionType::MostLeast`; `Domain\MostLeast` (the dimension pattern, the twelve keys, the contributions of an answer, `withChanges`, the high mark and the profile letters); the rules in `QuestionRules` and `AnswerRules`;
`ScoreAttempt` adds the contributions and the changes; `QuestionRepository::scalesOfTest` returns the derived keys and `mostLeastDimensions`; `PublishTest` checks the set of dimensions; `ReportTemplateRules` and
`ReportRenderer` know `{{profile:most|least|change}}`. Tests: `tests/Feature/Assessment/MostLeastBatteryTest.php`. Interpretations to confirm:

1. **Both positions are required** to save an answer (422 `invalid-answer` for one, two equal, or out of range); a half-made choice lives in the runner until both are marked. The design line "marking only one is a saved draft" is read that way.
2. **The high mark** is the lowest standard score among the norm rows of the battery whose band is "high" or "tinggi" (any case); without such a row, 55. It is one mark for the battery, read from all its norm rows.
3. **`dimensions-differ`** is not a separate code: the check is a problem in the `test-incomplete` list when publishing ("the most/least questions must all use the same set of at least two dimensions"); the editor warning
   is for the front end step.
4. **A dimension** is 1 to 20 lowercase letters or digits, with no underscore (the underscore separates it from the graph in the scale key).
5. A group whose statements share a dimension is allowed (picking both as most and least cancels out); only the set of dimensions of the battery is checked.

## Coding order

1. API: the type and its rules, the migration, the answer rule, the scoring and the derived scales, the placeholders and the publish check.
2. Portal Mitra: the question dialog and the editor warning.
3. Career: the runner component.
4. Charts: the review and the PDF.
5. Demo data: a small fictitious battery, to try the whole path.
6. Optional, when the partners ask: a bulk entry of the groups (CSV) in the battery editor.
