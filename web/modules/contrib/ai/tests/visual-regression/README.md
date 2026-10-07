# Visual regression tests

Playwright visual regression tests for the AI module's built front-end widgets:

- **mdxeditor** (`ui/mdxeditor`)
- **json-schema-editor** (`ui/json-schema-editor`)
- **default-tools-editor** (`ui/default-tools-editor`)

These bundles can compile successfully while still rendering a broken widget;
PHP unit tests cannot see that. Each widget is rendered anonymously and in
isolation by the `ai_test` module's dedicated visual-regression route
(`/admin/config/ai/test-visual-regression?vr_widget=…&vr_state=…`) under the
**Gin** admin theme, in both light and dark mode (Playwright `gin-light` and
`gin-dark` projects), and screenshotted against a committed baseline.

## CI only

These tests run in CI (the `visual_regression` job in `.gitlab-ci.yml`), inside
the pinned `mcr.microsoft.com/playwright` container so screenshots are
deterministic. There is no supported local run — a baseline produced on a
developer machine would not match the CI environment's font/anti-aliasing.

Baselines live in `./baselines/gin-light/` and `./baselines/gin-dark/` and are
produced from CI artifacts. See
**`../../docs/developers/visual_regression_testing.md`** for the full guide
(how it works, reviewing failures, updating baselines, adding a widget).
