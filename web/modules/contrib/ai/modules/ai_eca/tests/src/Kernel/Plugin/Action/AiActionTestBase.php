<?php

namespace Drupal\Tests\ai_eca\Kernel\Plugin\Action;

use Drupal\Core\Action\ActionManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Token\TokenInterface;

/**
 * Base class for AI Action tests.
 */
abstract class AiActionTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_eca',
    'ai_test',
    'eca',
    'file',
    'key',
    'system',
    'user',
  ];

  /**
   * The action manager.
   *
   * @var \Drupal\Core\Action\ActionManager|null
   */
  protected ?ActionManager $actionManager;

  /**
   * The token service.
   *
   * @var \Drupal\eca\Token\TokenInterface|null
   */
  protected ?TokenInterface $tokenService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installConfig(static::$modules);

    $this->actionManager = \Drupal::service('plugin.manager.action');
    $this->tokenService = \Drupal::service('eca.token_services');
  }

}
