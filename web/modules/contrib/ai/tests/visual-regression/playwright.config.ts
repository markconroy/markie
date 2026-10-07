import { defineConfig, devices } from '@playwright/test';

/**
 * Visual regression tests for the AI module's built UI widgets.
 *
 * Point VR_BASE_URL at a running Drupal site that has the `ai` and `ai_test`
 * modules enabled (the ai_test.visual_regression_form route must be reachable
 * anonymously). See ../../docs/developers/visual_regression_testing.md.
 */
const baseURL = process.env.VR_BASE_URL || 'http://127.0.0.1:8080';

export default defineConfig({
  testDir: './tests',
  // Baselines are organised per project (gin-light / gin-dark) under a single
  // reviewable folder. No {platform} suffix: baselines are generated in the
  // pinned Playwright container in CI so they stay deterministic.
  snapshotPathTemplate: 'baselines/{projectName}/{arg}{ext}',
  outputDir: 'test-results',
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: 0,
  reporter: [
    ['list'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
  ],
  expect: {
    toHaveScreenshot: {
      // Up to ~0.1% of pixels may differ (issue: "0.1% pixel difference").
      maxDiffPixelRatio: 0.001,
      // Per-pixel color tolerance, 0-1 (issue: "5% fuzz tolerance").
      threshold: 0.05,
      // Freeze CSS animations/transitions and hide the text caret so the
      // capture is deterministic.
      animations: 'disabled',
      caret: 'hide',
      scale: 'css',
    },
  },
  use: {
    baseURL,
    viewport: { width: 1280, height: 1024 },
    deviceScaleFactor: 1,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'gin-light',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 1024 },
        colorScheme: 'light',
      },
    },
    {
      name: 'gin-dark',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 1024 },
        colorScheme: 'dark',
      },
    },
  ],
});
