<?php

namespace Drupal\Tests\ai\FunctionalJavascriptTests;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * This is a base class for functional JavaScript tests in the AI module.
 */
abstract class BaseClassFunctionalJavascriptTests extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * We need to fix the automators schema.
   *
   * @var bool
   */
  // phpcs:ignore
  protected $strictConfigSchema = FALSE;

  /**
   * Path to save screenshots.
   *
   * @var string
   */
  protected $screenshotPath = 'sites/default/files/simpletest/screenshots/';

  /**
   * Screenshot category.
   *
   * @var string
   */
  protected $screenshotCategory = '';

  /**
   * The module name for screenshots.
   *
   * @var string
   */
  protected $screenshotModuleName = 'ai';

  /**
   * If counter is used for screenshot filenames.
   *
   * @var int
   */
  protected $screenshotCounter = 1;

  /**
   * Add a screenshot method to capture the current state of the page.
   */
  protected function takeScreenshot($filename = '') {
    // Ensure that the screenshot category is set.
    if (empty($this->screenshotCategory)) {
      // Set the class name as the screenshot category.
      $name = explode('\\', static::class);
      // Use the last part of the class name as the category.
      if (isset($name[0]) && !empty($name[0])) {
        $last = array_pop($name);
        $this->screenshotCategory = $last;
      }
      else {
        $this->screenshotCategory = 'default';
      }
    }
    // Create the directory if it does not exist.
    $directory = $this->screenshotPath . $this->screenshotModuleName . '/' . $this->screenshotCategory;
    if (!file_exists($directory)) {
      mkdir($directory, 0777, TRUE);
    }
    $screenshot = $this->getSession()->getDriver()->getScreenshot();
    // If no filename is provided, use a counter to generate a unique name.
    if (empty($filename)) {
      $filename = 'screenshot_' . $this->screenshotCounter . '.png';
      $this->screenshotCounter++;
    }
    elseif (strpos($filename, '.png') === FALSE) {
      // Ensure the filename ends with .png.
      $filename .= '.png';
    }
    $file_path = $directory . '/' . $filename;
    file_put_contents($file_path, $screenshot);
  }

}
