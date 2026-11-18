<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests who sees the message about a missing default translate provider.
 *
 * @group ai_translate
 *
 * @see _ai_translate_check_default_provider_and_model()
 */
#[Group('ai_translate')]
#[RunTestsInSeparateProcesses]
class DefaultProviderMessageTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'ai'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->container->get('module_installer')->install(['ai_translate']);
    $this->container->get('router.builder')->rebuild();
    $this->container->get('messenger')->deleteAll();

    // Take user 1, which bypasses access checks, out of the way.
    $this->createUser();
  }

  /**
   * Tests that only users who can change the AI settings see the message.
   */
  public function testMessageFollowsSettingsAccess(): void {
    $this->setCurrentUser($this->createUser(['create ai content translation']));
    $this->assertFalse(_ai_translate_check_default_provider_and_model());
    $this->assertEmpty($this->container->get('messenger')->messagesByType('error'));

    $this->setCurrentUser($this->createUser(['administer ai']));
    $this->assertFalse(_ai_translate_check_default_provider_and_model());
    $this->assertCount(1, $this->container->get('messenger')->messagesByType('error'));
  }

  /**
   * Tests that the message can be shown regardless of access, as on install.
   */
  public function testMessageForAll(): void {
    $this->setCurrentUser($this->createUser());
    $this->assertFalse(_ai_translate_check_default_provider_and_model(TRUE));
    $this->assertCount(1, $this->container->get('messenger')->messagesByType('error'));
  }

  /**
   * Tests that there is no message once a provider is set.
   */
  public function testNoMessageWithProvider(): void {
    $this->config('ai.settings')
      ->set('default_providers.translate_text', [
        'provider_id' => 'test_translate_provider',
        'model_id' => 'test_translate_model',
      ])
      ->save();

    $this->setCurrentUser($this->createUser(['administer ai']));
    $this->assertTrue(_ai_translate_check_default_provider_and_model());
    $this->assertEmpty($this->container->get('messenger')->messagesByType('error'));
  }

}
