<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the update hook that adds the AI Disclosure setting.
 *
 * @group ai_translate
 */
#[RunTestsInSeparateProcesses]
#[Group('ai_translate')]
class UpdateDisclosureSettingKernelTest extends KernelTestBase {

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
    ai_translate_update_10005($sandbox);
  }

  /**
   * Tests that a fresh install has the setting on.
   */
  public function testNewInstallHasSettingOn(): void {
    $this->assertTrue($this->config('ai_translate.settings')->get('ai_disclosure_apply'));
  }

  /**
   * Tests that an existing site opts in: a missing key becomes FALSE.
   */
  public function testMissingKeyBecomesFalse(): void {
    $this->config('ai_translate.settings')->clear('ai_disclosure_apply')->save();
    $this->assertNull($this->config('ai_translate.settings')->get('ai_disclosure_apply'));

    $this->runUpdate();

    $this->assertFalse($this->config('ai_translate.settings')->get('ai_disclosure_apply'));
  }

  /**
   * Tests that a set value stays as it is and a rerun is a no-op.
   */
  public function testExistingValueIsUnchangedAndRerunIsNoop(): void {
    foreach ([TRUE, FALSE] as $value) {
      $this->config('ai_translate.settings')->set('ai_disclosure_apply', $value)->save();
      $this->runUpdate();
      $this->runUpdate();
      $this->assertSame($value, $this->config('ai_translate.settings')->get('ai_disclosure_apply'));
    }
  }

}
