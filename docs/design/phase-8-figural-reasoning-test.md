# Phase 8: figural reasoning test in the system library (coded 2026-11-04)

Asked by the user on 2026-11-04: figural questions for a test battery, "in the LPTUI format", as **real content, not demo data**, with mechanisms taken from any source. Builds on `phase-8-question-images.md` (pictures on questions and options).

## Decisions

- 2026-11-04, D1 (user): **a library skill test**, not a psychological battery: batteries are written by partner psychologists (`phase-8-disc-profile.md` D1), so the platform's own content lives in the system library. It has no norm table and no psychologist signature; scoring is the usual skill-test score (points, percentage, band).
- 2026-11-04, D2 (user): **40 items, four subtests of ten**: odd one out, series, analogies, 3 by 3 matrices, from easy to hard in each subtest.
- 2026-11-04, D3 (user, "format LPTUI"): the layout of the printed example in `phase-8-question-images.md` (each subtest has its own instructions and time, five pictures A to E side by side with the letter under each). Items and figures are **original**; no item, figure or norm of LPTUI or of any published test is copied (they are copyrighted and confidential under the HIMPSI code). The mechanisms (odd one out, series, analogy, progressive matrices with Latin squares, overlay and XOR) are the general ones of non-verbal reasoning tests.
- 2026-11-04, D4 (this design): **installed by an Artisan command**, `php artisan assessment:install-figural-test --admin=<id>`, in the name of an admin holding `assessment_library.manage`, through the authoring actions (`CreateTest`, `AddSection`, `UploadQuestionImage`, `AddQuestion`, `PublishTest`), so every library rule applies. Once only (409 `library-test-exists` while a library test with the title is not archived). A failed run leaves a draft that the admin deletes.
- 2026-11-04, D5 (this design): **one sample item** (the first item of subtest 1, shown in the practice and the catalogue, not scored), because a test has at most one sample per question type; the other subtests explain their rule with a worked example in words in the section instructions.
- 2026-11-04, D6 (this design): figures are **drawn by GD** at three times the size and shrunk (`GdFigureCanvas`), cells of 160 pixels; the widest stem (a series) is 896 pixels. No font file is needed (the question mark is drawn).

## Not done

- **Psychometric data:** item difficulty, discrimination, reliability and norms are unknown until a pilot (a few hundred candidates). The band of a skill test is a fixed percentage band, not a norm; a psychologist reviews the item statistics before the test is used for decisions. Easy-to-hard order follows the design, not data.
- **Accessibility:** the alternative texts are neutral (`phase-8-question-images.md` D4), so the test cannot be taken with a screen reader.
- **Editable figures:** the items are PHP data today; `phase-8-figure-editor.md` D6 stores them as figure descriptions so a copy can be revised in the editor (the installer changes before it is committed).

## Structure

| Subtest | Prompt | Time | Stem picture |
|---|---|---|---|
| Gambar yang Berbeda | Pilihlah satu gambar yang berbeda dari gambar lain. | 4 min | none |
| Deret Gambar | Gambar mana yang tepat untuk mengisi kotak bertanda tanya pada deret di atas? | 5 min | four frames and a question cell |
| Analogi Gambar | Gambar mana yang berhubungan dengan gambar ketiga seperti gambar kedua berhubungan dengan gambar pertama? | 5 min | A → B, C → ? |
| Matriks Gambar | Gambar mana yang tepat untuk mengisi kotak kanan bawah? | 7 min | 3 by 3, last cell a question |

Each item is a `single_choice` question worth 1 point, options A to E with a picture each. The rule of every item is in `FiguralReasoningContent` next to it (and in the table below); it is never shown to a candidate.

## Keys and rules

| # | Odd one out | Series | Analogy | Matrix |
|---|---|---|---|---|
| 1 | C: shape | B: arrow turns 45° | C: large → small | E: size grows per column |
| 2 | E: count | D: one dot more | A: empty → filled | C: count 1, 2, 3 per row |
| 3 | B: mirror image | A: one side more | E: quarter turn | A: Latin square of shapes, fill per row |
| 4 | D: dots = sides | E: shrink and alternate fill | B: count doubles | D: arrow quarter turn per column, rows offset 45° |
| 5 | A: inner = outer shape | C: two dots move opposite ways | D: inner and outer swap | B: overlay of the first two |
| 6 | E: angle of the hands | A: two hands, two speeds | D: mirror left to right | B: XOR of the first two |
| 7 | D: dot on the diagonal | E: count up, shapes alternate | B: one side and one dot more | D: two orthogonal Latin squares (shape, count) |
| 8 | A: symmetry | B: cell moves three along the edge | A: middle figure removed | E: dot clockwise, hollow opposite |
| 9 | C: strokes = dots | C: left gains, right loses | E: half turn and fill inverted | A: third = sum of the first two |
| 10 | B: mirror image (dot beside arrow) | D: arrow turns 135°, fill alternates | C: mirror of a grid | C: shape, count and fill Latin squares |

## Code

- `Src\Assessment\Application\Library\Figural`: `FiguralReasoningContent` (the items, as element lists on a 100 by 100 grid), `FiguralItem`, `FiguralSection`, `FiguralLayout`, the port `FigureRenderer`, the use case `InstallFiguralReasoningTest`.
- `Src\Assessment\Infrastructure\Figures`: `GdFigureRenderer`, `GdFigureCanvas`.
- `Src\Assessment\Interface\Console\InstallFiguralReasoningTestCommand`.
- Tests: `tests/Unit/Assessment/Application/FiguralReasoningContentTest.php`, `tests/Feature/Assessment/InstallFiguralReasoningTestTest.php`.
- The demo test `DemoFiguralTest` ("contoh fiktif", three items) stays as it is.
