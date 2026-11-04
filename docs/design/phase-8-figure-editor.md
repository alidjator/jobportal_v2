# Phase 8: figure editor and rule generator (designed 2026-11-04, not coded)

Asked by the user on 2026-11-04: authors make figural items in the app, without a drawing program and without code. Today a picture is drawn elsewhere and uploaded (`phase-8-question-images.md`); the 40 items of `phase-8-figural-reasoning-test.md` exist only as PHP data. This design puts the figure description, the server renderer and a rule generator behind the question editors.

## Decisions

- 2026-11-04, D1 (user): in **Console** (library and Portal Mitra, the shared `TestEditorPage`) **and Workspace** (company skill tests, `AssessmentQuestionForm`); one shared dialog in `packages/ui`. [Screens](#screens)
- 2026-11-04, D2 (user): **the server draws**: the editor sends a figure description (JSON) and the API renders it with `GdFigureRenderer`, the renderer of the 40 items; previews come from the API. [Endpoints](#endpoints)
- 2026-11-04, D3 (user): **elements, layouts and a rule generator**: the author builds frames from elements, or picks a rule that fills the stem, the answer and four distractors, then edits any frame. [Rules](#rule-catalogue)
- 2026-11-04, D4 (user): **the description is stored** with the question, so a figure opens again in the editor while the test is editable, and `CloneTest` copies it. [Schema](#schema)
- 2026-11-04, D5 (user): the first catalogue holds **every rule the 40 items use**. [Rules](#rule-catalogue)
- 2026-11-04, D6 (user): the **40 installed items are stored as figures too**: `InstallFiguralReasoningTest` saves their descriptions, so a clone can be revised in the editor after the item analysis (`phase-8-item-analysis.md`). The installer changes before it is committed.
- 2026-11-04, D7 (user): the generator puts the key at a **random position**, the author may move it; the editor warns when one letter is the key of more than 30% of a section's items.
- 2026-11-04, D8 (this design): the preview is **server-rendered and debounced** (300 ms), not a second geometry in the browser: one renderer, no drift. Elements are placed by clicking a 10-unit snap grid and edited in a property panel.
- 2026-11-04, D9 (this design): the element format moves from positional PHP arrays to **keyed, versioned JSON** (`v: 1`) with a domain value object, used by the content class, the renderer and the API alike.
- 2026-11-04, D10 (this design): a figure belongs to **`single_choice` questions** (five options A to E); the figure's key and the question's right option must agree.

## Figure format

```json
{
  "v": 1,
  "layout": "series",
  "stem": [[{"type": "arrow", "x": 50, "y": 50, "length": 56, "rotation": 0}], "..."],
  "options": [["..."], ["..."], ["..."], ["..."], ["..."]],
  "key": 1,
  "rule": {"key": "series.rotate", "params": {"step": 45}}
}
```

- `layout`: `options_only` (odd one out, no stem), `series` (4 stem frames), `analogy` (3), `matrix` (8). `options`: exactly 5 frames. `key`: 0 to 4. `rule`: the generator rule and parameters that made it (null when drawn by hand; kept so the editor can regenerate).
- Elements, on a 100 by 100 grid, angles clockwise from up, as in `FiguralReasoningContent`:
  `shape` (`kind` circle, triangle, square, diamond, pentagon, hexagon, heptagon, octagon, star; `x`, `y`, `size` 4–96, `fill` none, solid, grey; `rotation`), `dot` and `ring` (`x`, `y`, `radius` 2–20), `line` and `box` (`x1`, `y1`, `x2`, `y2`), `grid` (`cells` 0–8), `arrow` (`x`, `y`, `length` 10–90, `rotation`), `flag` (`x`, `y`, `size`, `rotation`, `mirrored`, `fill`).
- Limits: coordinates 0 to 100, at most 30 elements per frame, rotation any number taken modulo 360. A bad description is 422 `invalid-figure` with `errors` naming the path (`options.2.4.size`).
- Domain: `Src\Assessment\Domain\Figure\FigureSpec`, `FigureFrame`, `FigureElement` (validation, `toArray`/`fromArray`, a canonical form for comparing frames). `FigureRenderer` takes them instead of arrays; `FiguralReasoningContent` builds them.

## Schema

- **`assessment_question_figures`:** `id`, `test_id` (cascade), `question_id` (nullable until the question is saved, unique, cascade), `spec` (jsonb, the format above), `timestamps`. The images it renders are ordinary `assessment_question_images` rows (the stem picture as the question's image, one per option), so the runner, the review views and the item analysis need no change.
- An unattached figure follows the image orphan rule (purged after 24 hours with its images, `PurgeOrphanQuestionImages`). Replacing a figure leaves the old figure and its images orphaned. `CloneTest` copies the row with its images; `DeleteQuestion` and `DeleteTest` take it away.

## Endpoints

All in `AuthoringRoutes`, so they exist for the three authors (`company/assessment`, `provider`, `admin/assessment/library`) with the authoring access of today (company admin with the author permission, psychologist, admin with `assessment_library.manage`), under `editableTest` (archived 409 `test-archived`, locked 409 `test-locked`).

| Route | Body | Response |
|---|---|---|
| `POST tests/{id}/figures/preview` | `{frame}` or `{layout, stem}` | `image/png`; nothing stored; rate limit 120 a minute per author |
| `POST tests/{id}/figures/generate` | `{layout, rule, params, base}` (`base`: the starting frame, optional; a default per rule) | `{figure}`: the full description with stem, options, a random key; nothing stored |
| `POST tests/{id}/figures` | `{figure}` | 201 `{figure_id, image_ids, option_image_ids}`: the figure stored unattached and its pictures rendered with neutral alt texts (`Gambar pilihan A`, the stem texts of the 40 items) |
| question `POST`/`PUT` (existing) | adds `figure_id` | the question takes the figure's images; `image_ids` and the option `image_id`s must be the figure's, the right option must be the figure's key, the type `single_choice`: else 422 `figure-mismatch` |
| question resources (author views) | | `figure` (the description) when the question has one |

Errors: 422 `invalid-figure`, 422 `invalid-figure-rule` (unknown rule, bad parameters, or a base frame the rule cannot use, such as a rotation of a circle), 422 `figure-mismatch`, 429 on the preview limit.

## Rule catalogue

`Src\Assessment\Domain\Figure\Rules`: one class per rule, `generate(base, params, random) → FigureSpec`, and its distractor strategies. Every generated item is checked: five different options (canonical form), the answer not equal to any distractor, every frame within the limits; a rule that cannot make four distractors fails with `invalid-figure-rule` instead of repeating one.

| Rule | Layout | Parameters | Distractors (near misses) |
|---|---|---|---|
| `series.rotate` | series | step (degrees) | step skipped, opposite direction, last frame repeated, one step too far |
| `series.count` | series | step | ±1 off, last repeated, other shape with the right count |
| `series.sides` | series | step | ±1 side, a circle |
| `series.size` | series | factor | not shrunk, one step back, other shape |
| `series.alternate_fill` | series | fills | the wrong fill of the right next frame |
| `series.move` | series | path (corners, grid edge), step | step off by one, wrong direction, a cell off the path |
| `series.two_rules` | series | two rules on two elements | one rule applied only, rules swapped |
| `matrix.row_column` | matrix | one attribute per row, one per column | the right row value with the wrong column value and the reverse |
| `matrix.latin` | matrix | attributes (shape, count, fill; two orthogonal at most, a third tied) | each attribute wrong in turn |
| `matrix.overlay` | matrix | strokes | one stroke left out, one added |
| `matrix.xor` | matrix | positions | union, intersection, one position off |
| `matrix.sum` | matrix | counts | ±1, the product, the difference |
| `analogy.transform` | analogy | transform (rotate, mirror, scale, invert fill, double count, swap nested, remove middle, mirror grid), C frame | another transform, the transform half done, C unchanged |
| `odd.violate` | options_only | attribute violated (shape, count, mirror, relation dots/sides, inner/outer, angle, on-line, symmetry), nuisance (rotation, size, position, fill) | not applicable: four frames follow the rule with nuisance variation, one breaks it |

The 40 items are the reference examples: a unit test regenerates each with its rule and parameters and checks the answer equals the stored one.

## Screens

- **Shared dialog `FigureEditorDialog` (`packages/ui`)**, opened by "Buat figur" / "Ubah figur" in the question dialog of a `single_choice` question (Console `BatteryQuestionDialog`, Workspace `AssessmentQuestionForm`), shown only where the author may edit the test (frontend rule 20).
  - Top: layout picker and "Gunakan aturan" (rule picker with its parameters and the base frame; "Buat ulang" regenerates with a new random key).
  - Left: the frame strip: stem cells, then options A to E with the key marked; copy a frame to another, clear.
  - Centre: the selected frame as the server preview on a 10-unit snap grid; a click places the element chosen in the palette; elements listed under it (select, delete, up/down).
  - Right: the properties of the selected element (kind, size, position, rotation in steps of 15° and free, fill, mirrored).
  - Bottom: the whole item preview as the candidate sees it, the key-balance warning (D7), "Simpan figur" (`POST figures`, then the question dialog holds `figure_id` and shows the pictures; the question is saved as today).
- Phone widths: the dialog is a full screen sheet; the strip scrolls.

## Steps

1. API: figure domain and format (D9) with the content class and renderer moved to it; table and repository; preview, generate and store routes; `figure_id` on question save and in the author resources; clone, delete, orphan purge; the rule catalogue; the installer stores figures (D6); `lang/id.json`, error catalog, Scramble cache.
2. `FigureEditorDialog` in `packages/ui` and the Console wiring (library and Portal Mitra).
3. Workspace wiring.
4. Docs, HANDOFF.

## Tests to write

- Format: every element type valid and each limit refused with its path; canonical comparison.
- Rules: each rule generates five different options with the key at the given position; bad parameters refused; the 40 reference items regenerated equal.
- API: the three areas reach the routes with their access; preview returns a PNG and stores nothing; store renders 1 + 5 or 5 pictures; question save with a figure of another test or a wrong key is `figure-mismatch`; a locked test refuses; clone copies the figure and its files; an orphan figure is purged; the installer leaves 41 figures.
- Front end: the dialog builds a description, regenerates, moves the key, warns about the balance; the button is hidden without the author permission.

## Open for the coding session

- G1: the question dialogs keep options as rows with an image id each; check how a whole set of six pictures replaces them in one step.
- G2: the preview rate limit per author key for the three guards.
