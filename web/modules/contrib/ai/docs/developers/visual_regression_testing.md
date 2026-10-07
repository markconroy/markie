# Visual Regression Testing

The AI module ships four compiled front-end bundles:

| Bundle | Source | Drupal library |
| --- | --- | --- |
| MDXEditor | `ui/mdxeditor` | `ai/mdx_editor` |
| JSON Schema editor | `ui/json-schema-editor` | `ai/json_schema_editor` |
| Default tools editor | `ui/default-tools-editor` | `ai/default_tools_editor` |
| AI CKEditor plugin | `modules/ai_ckeditor/js` | (CKEditor 5 plugin) |

These bundles can **build successfully while the rendered widget is broken** —
a runtime React error, a missing CSS import or a bad mount selector will not
fail the build, and PHP unit tests never load the compiled JavaScript. Visual
regression testing closes that gap by rendering each widget in a real browser
and comparing a screenshot against a committed baseline image.

The first three widgets are covered. The AI CKEditor plugin is intentionally
out of scope (it is a CKEditor 5 plugin rather than a standalone widget, and
needs a configured text format to render) — see
[Adding a widget](#adding-a-widget) if you want to extend coverage.

## How it works

The tests live in `tests/visual-regression/` and use
[Playwright](https://playwright.dev/).

1. The `ai_test` module exposes the widgets at a dedicated, **access-free**
   route `/admin/config/ai/test-visual-regression`. Called with the query
   parameters `?vr_widget=<widget>&vr_state=<empty|default>`,
   `\Drupal\ai_test\Form\VisualRegressionForm` renders a **single** widget in
   isolation, wrapped in a fixed-width element carrying a
   `data-vr-target="<widget>-<state>"` attribute. The route has no access check
   so the tests can run as an anonymous user. (The general showcase form at
   `/admin/config/ai/test-form-elements` is a separate route and keeps its
   `administer ai` permission.)
2. The site renders under the **Gin** admin theme (Gin is the default theme on
   the CI site, with `enable_darkmode: auto`), so the widgets are tested in
   their real admin context. The Playwright run uses two projects —
   **`gin-light`** and **`gin-dark`** — which emulate `prefers-color-scheme`;
   Gin's auto dark mode reacts to that and adds `gin--dark-mode` to `<html>`,
   giving separate light and dark baselines.
3. For every widget, in both an **empty** and a **default** (populated) state,
   and under both projects, `tests/widgets.spec.ts`:
   - asserts the built bundle actually mounted (this is what catches a
     "built but broken" widget);
   - asserts Gin's color scheme matches the project (so light and dark
     baselines can't accidentally be identical); then
   - screenshots just the `data-vr-target` wrapper and compares it to the
     baseline in `tests/visual-regression/baselines/<project>/`.
4. Comparison tolerances (`tests/visual-regression/playwright.config.ts`):
   - `maxDiffPixelRatio: 0.001` — at most 0.1% of pixels may differ;
   - `threshold: 0.05` — per-pixel color tolerance (~5% fuzz);
   - animations disabled and the text caret hidden for determinism.

### Why a pinned container

Screenshots are sensitive to font rendering and anti-aliasing, which differ
between operating systems. Baselines are therefore generated **inside the
pinned Playwright Docker image** (`mcr.microsoft.com/playwright:v1.50.0-noble`)
and the same image is used to compare them. This is why baselines must be
generated in CI rather than on a developer's host.

## CI

The `visual_regression` job in `.gitlab-ci.yml`:

- is **`allow_failure: true`** — it warns, it never blocks the pipeline;
- installs a throwaway Drupal site on SQLite, enables `ai` + `ai_test`, adds the
  pinned Gin theme (`GIN_VERSION`) as the default theme with `enable_darkmode:
  auto`, serves it with `drush runserver`, and runs Playwright (both the
  `gin-light` and `gin-dark` projects) against it;
- only ever **compares** against the committed baselines — it never updates
  them. A mismatch (or a widget with no baseline yet) is a warning that a human
  reviews and acts on (see [(Re)generating baselines](#regenerating-baselines));
- **always** saves artifacts: the HTML report (`playwright-report/`), the
  actual/diff images (`test-results/`), and the `baselines/` directory.

Because the AI module only runs its FunctionalJavascript tests on the matching
issue branch and on tags, this job follows the standard pipeline rules instead:
it runs whenever front-end or test code changes, and is otherwise available
manually.

## Reviewing a failure

1. Open the failed `visual_regression` job and download its artifacts.
2. Open `tests/visual-regression/playwright-report/index.html` — for each
   failing case it shows the **expected**, **actual** and **diff** images
   side by side.
3. Decide:
   - **Unintended change** (a real regression): fix the widget or its build.
   - **Intended change** (you changed the widget on purpose): regenerate the
     baseline (below) and commit the new image.

## (Re)generating baselines

**Baselines are always added manually.** The CI job never updates them — it
only compares. When a widget has no baseline yet, or a widget changed on
purpose, the job warns and you decide whether to promote the new screenshot to
a baseline.

Baselines are produced by the CI job, which runs inside the pinned Playwright
container, so they match the environment they are compared in.

**From a CI run:** open the warning job, review the HTML report, and download
the artifacts. Take the new image — the `*-actual.png` for a widget that
changed (under `test-results/`), or the candidate PNG Playwright wrote for a
widget with no baseline yet (under `baselines/<project>/`) — confirm it looks
correct, and commit it to the matching
`tests/visual-regression/baselines/gin-light/` or `.../gin-dark/` folder.

## Running the tests

These tests run in **CI only** (the `visual_regression` job). There is no
supported local run: screenshots are sensitive to host fonts and
anti-aliasing, so a baseline produced on a developer machine would not match
the CI environment. To exercise a change, push it and review the
`visual_regression` job's report and artifacts.

## Adding a widget

1. Render the new widget in isolation from
   `\Drupal\ai_test\Form\VisualRegressionForm::buildForm()` under a new
   `vr_widget` key, in a `data-vr-target="<key>-<state>"` wrapper, for both the
   `empty` and `default` states.
2. Add an entry to the `widgets` array in
   `tests/visual-regression/tests/widgets.spec.ts`, including an
   `assertMounted()` check that proves the bundle ran (e.g. a selector the
   widget only creates once mounted).
3. Generate and commit the new baselines (see above).
