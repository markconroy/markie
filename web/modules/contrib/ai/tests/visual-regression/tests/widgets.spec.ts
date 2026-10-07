import { test, expect, type Page } from '@playwright/test';

/**
 * Visual regression coverage for the four built front-end bundles that can
 * compile successfully while still rendering a broken widget.
 *
 * Each widget is rendered in isolation by the ai_test form
 * (\Drupal\ai_test\Form\VisualRegressionForm) via the ?vr_widget=&vr_state=
 * query parameters, in both an empty and a populated ("default") state. The
 * site renders under the Gin admin theme, and each test runs under two
 * projects — `gin-light` and `gin-dark` — which emulate prefers-color-scheme
 * so Gin's auto dark mode produces light and dark baselines. For each we:
 *
 *   1. Assert the built bundle actually mounted (catches "asset built but
 *      widget broken").
 *   2. Assert Gin's color scheme matches the project.
 *   3. Compare a screenshot of just that widget against a committed baseline.
 *
 * ai_ckeditor is intentionally out of scope here (it is a CKEditor 5 plugin,
 * not a standalone widget, and needs a configured text format to render).
 */

const ROUTE = '/admin/config/ai/test-visual-regression';

interface Widget {
  /** Value passed as ?vr_widget=. */
  key: string;
  /** Human-readable label for the test title. */
  label: string;
  /** Asserts the built bundle mounted; throws if the widget is broken. */
  assertMounted: (page: Page) => Promise<void>;
}

const widgets: Widget[] = [
  {
    key: 'mdxeditor',
    label: 'MDXEditor',
    assertMounted: async (page) => {
      // The bundle ran if it flagged the textarea initialised and inserted its
      // React wrapper in place of the textarea.
      await expect(
        page.locator('textarea[data-mdxeditor][data-mdxeditor-initialized="true"]'),
      ).toHaveCount(1);
      await expect(page.locator('.mdxeditor-wrapper')).toBeVisible();
    },
  },
  {
    key: 'json_schema',
    label: 'JSON schema editor',
    assertMounted: async (page) => {
      // CodeMirror mounts inside the editor container.
      await expect(
        page.locator('[data-ai-json-schema-editor] .cm-editor'),
      ).toBeVisible();
    },
  },
  {
    key: 'default_tools',
    label: 'Default tools editor',
    assertMounted: async (page) => {
      // The bundle creates a root element it renders the React tree into.
      await expect(
        page.locator('[data-ai-agents-default-tools-editor-root]'),
      ).toBeVisible();
    },
  },
];

const states = ['empty', 'default'] as const;

for (const widget of widgets) {
  for (const state of states) {
    test(`${widget.label} – ${state} state`, async ({ page }, testInfo) => {
      await page.goto(`${ROUTE}?vr_widget=${widget.key}&vr_state=${state}`);

      // 1. The widget actually rendered.
      await widget.assertMounted(page);

      // 2. Gin's color scheme matches the project, so the light and dark
      //    baselines can never accidentally be identical.
      const html = page.locator('html');
      if (testInfo.project.name === 'gin-dark') {
        await expect(html).toHaveClass(/gin--dark-mode/);
      }
      else {
        await expect(html).not.toHaveClass(/gin--dark-mode/);
      }

      // 3. Wait for web fonts so text rendering is stable before the snapshot.
      await page.evaluate(async () => {
        await document.fonts.ready;
      });

      // 4. Visual regression: screenshot just the widget wrapper.
      const target = page.locator(`[data-vr-target="${widget.key}-${state}"]`);
      await expect(target).toBeVisible();
      await expect(target).toHaveScreenshot(`${widget.key}-${state}.png`);
    });
  }
}
