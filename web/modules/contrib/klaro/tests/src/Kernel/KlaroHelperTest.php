<?php

namespace Drupal\Tests\klaro\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test KlaroHelper functionality.
 *
 * @group klaro
 */
#[RunTestsInSeparateProcesses]
class KlaroHelperTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['klaro', 'system', 'user'];

  /**
   * The Klaro Helper service.
   *
   * @var \Drupal\klaro\Utility\KlaroHelper
   */
  protected $klaroHelper;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('user_role');
    $this->installConfig(['user']);
    $this->installConfig(['klaro']);
    $this->installEntitySchema('klaro_app');

    // Grant 'use klaro' permission to anonymous users.
    $role = Role::load('anonymous');
    $role->grantPermission('use klaro');
    $role->save();

    $this->klaroHelper = $this->container->get('klaro.helper');
    $this->loggerFactory = $this->container->get('logger.factory');
  }

  /**
   * Test matchKlaroApp with a broken URL that throws InvalidArgumentException.
   *
   * This tests the fix from merge request !131 which adds exception handling
   * for broken URLs in UrlHelper::externalIsLocal().
   */
  public function testMatchKlaroAppWithBrokenUrl() {

    // Enable block_unknown setting to test the blocking behavior.
    $this->config('klaro.settings')
      ->set('block_unknown', TRUE)
      ->set('log_unknown_resources', TRUE)
      ->save();

    // Test with a broken URL that would cause InvalidArgumentException.
    $broken_url = '///C:/Users/laro1/AppData/Local/Temp/mso/01/clip_image001.jpg';

    try {
      $this->klaroHelper->matchKlaroApp($broken_url);
      $this->assertTrue(TRUE, 'Broken URL caught .');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertTrue(FALSE, 'Broken URL not caught .');
    }

  }

}
