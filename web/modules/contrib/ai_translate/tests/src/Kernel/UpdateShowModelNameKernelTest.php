<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the update hook that adds the setting to show the model name.
 *
 * @group ai_translate
 */
#[RunTestsInSeparateProcesses]
#[Group('ai_translate')]
class UpdateShowModelNameKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'language',
    'content_translation',
    'ai',
    'ai_translate',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ai_translate']);
    $this->container->get('module_handler')->loadInclude('ai_translate', 'install');
  }

  /**
   * Runs the update hook.
   */
  protected function runUpdate(): void {
    $sandbox = [];
    ai_translate_update_10006($sandbox);
  }

  /**
   * Tests that a fresh install has the setting off.
   */
  public function testNewInstallHasSettingOff(): void {
    $this->assertFalse($this->config('ai_translate.settings')->get('show_model_name'));
  }

  /**
   * Tests that an existing site gets the key: a missing key becomes FALSE.
   */
  public function testMissingKeyBecomesFalse(): void {
    $this->config('ai_translate.settings')->clear('show_model_name')->save();
    $this->assertNull($this->config('ai_translate.settings')->get('show_model_name'));

    $this->runUpdate();

    $this->assertFalse($this->config('ai_translate.settings')->get('show_model_name'));
  }

  /**
   * Tests that a set value stays as it is and a rerun is a no-op.
   */
  public function testExistingValueIsUnchangedAndRerunIsNoop(): void {
    foreach ([TRUE, FALSE] as $value) {
      $this->config('ai_translate.settings')->set('show_model_name', $value)->save();
      $this->runUpdate();
      $this->runUpdate();
      $this->assertSame($value, $this->config('ai_translate.settings')->get('show_model_name'));
    }
  }

}
