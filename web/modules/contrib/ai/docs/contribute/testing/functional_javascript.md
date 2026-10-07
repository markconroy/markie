# Functional JavaScript Testing

Writing Functional JavaScript (FJS) tests is super important for making sure our AI module's UI works perfectly. However, because FJS tests spin up a real browser behind the scenes, they can be a bit heavy and slow.

To keep our CI pipeline running fast and smoothly, we've set up a neat little script (`scripts/filter-functionaljavascript-tests.sh`) to help manage these tests. If you're contributing an FJS test, there are just three quick rules you need to follow so your tests run when you need them to!

## Three Quick Rules for FJS Tests

Whenever you create a new FJS test, always extend our `BaseClassFunctionalJavascriptTests` (in `Drupal\Tests\ai\FunctionalJavascriptTests`) rather than core's `WebDriverTestBase`. The video recording, screenshot and helper methods described below all live on that base class, so a test extending `WebDriverTestBase` directly will silently ignore them.

Then make sure to include these three things:

### 1. Tag Your Test with an Issue Number
Always add an `@group` annotation with the exact drupal.org issue number you're working on.

```php
/**
 * Description of my awesome new FJS test.
 *
 * @group ai
 * @group 1234567
 */
```
**Why do I need to do this?**
Because FJS tests take so long to run, we skip running them on the main branch (like 1.x or 2.x) to save CI resources. Instead, our filtering script looks specifically for tests tagged with the numerical issue ID matching your current issue branch (like `1234567-my-feature`). If you don't add the issue number group, your test will simply be ignored during issue branch tests and will only ever run when we tag a release!

### 2. Turn on Video Recording
Make sure to add this small property to your test class so that Drupal records a video of the test:

```php
protected bool $videoRecording = TRUE;
```
**Why do I need to do this?**
If your test ever fails in Drupal CI, the CI system will actually generate a video file showing exactly what the automated browser was doing. Since we run headless Selenium containers, you won't be able to physically see the browser clicking around on your own screen. Having a video artifact is an absolute lifesaver when you're trying to figure out exactly why the UI broke!

### 3. Set the Screenshot Module Name

If your test lives in a sub-module (anything under `modules/`), set the module name so the recorded videos and screenshots end up in the right folder:

```php
protected string $screenshotModuleName = 'my_sub_module';
```

You only need to skip this if your test belongs to the core AI module (tests directly under the AI module's own `tests/` directory) — the base class already defaults to `ai`.

**Why do I need to do this?**
Videos and screenshots are written to `sites/default/files/simpletest/videos/{screenshotModuleName}/{category}/` and `sites/default/files/simpletest/screenshots/{screenshotModuleName}/{category}/`, where the category defaults to your test class name. Without this, every sub-module's artifacts get dumped into the `ai` folder and are much harder to find in the CI artifacts.

## Taking Screenshots

Video recording covers the whole test run, but sometimes you want a still image of one specific moment — right after an AJAX call settles, or just before an assertion that keeps failing. The base class gives you `takeScreenshot()` for exactly that, and it works independently of `$videoRecording`:

```php
// Auto-numbered: screenshot_1.png, screenshot_2.png, ...
$this->takeScreenshot();

// Or give it a meaningful name (the .png extension is added for you).
$this->takeScreenshot('after_modal_opened');
```

The PNG is written to `sites/default/files/simpletest/screenshots/{screenshotModuleName}/{category}/`, using the same module name and category as the video recordings. If you want to group the images under something other than the test class name, set the category on your test class:

```php
protected string $screenshotCategory = 'field_widget_modal';
```
