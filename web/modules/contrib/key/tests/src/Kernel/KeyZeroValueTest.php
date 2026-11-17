<?php

declare(strict_types=1);

namespace Drupal\Tests\key\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\key\Entity\Key;
use Drupal\key\Entity\KeyConfigOverride;
use Drupal\key\Plugin\KeyPluginFormInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that a key value of '0' is treated as a value, not as missing.
 *
 * @group key
 */
#[Group('key')]
#[RunTestsInSeparateProcesses]
final class KeyZeroValueTest extends KernelTestBase {

  /**
   * The environment variable used by the test.
   */
  private const ENV_VARIABLE = 'KEY_TEST_ZERO_VALUE';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'key',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    putenv(self::ENV_VARIABLE . '=0');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    putenv(self::ENV_VARIABLE);
    parent::tearDown();
  }

  /**
   * Tests that the Environment provider returns and overrides with '0'.
   */
  public function testEnvironmentProviderZero(): void {
    $key = Key::create([
      'id' => 'env_zero',
      'label' => 'Environment zero',
      'key_type' => 'authentication',
      'key_provider' => 'env',
      'key_provider_settings' => [
        'env_variable' => self::ENV_VARIABLE,
        'base64_encoded' => FALSE,
        'strip_line_breaks' => TRUE,
      ],
      'key_input' => 'none',
    ]);
    $key->save();

    $this->assertSame('0', $key->getKeyValue());

    $form = [];
    $form_state = (new FormState())->setValues(['env_variable' => self::ENV_VARIABLE]);
    $provider = $key->getKeyProvider();
    $this->assertInstanceOf(KeyPluginFormInterface::class, $provider);
    $provider->validateConfigurationForm($form, $form_state);
    $this->assertSame([], $form_state->getErrors());

    $this->config('system.performance')->set('css.preprocess', TRUE)->save();
    KeyConfigOverride::create([
      'id' => 'css_preprocess',
      'label' => 'CSS preprocess',
      'config_type' => 'system.simple',
      'config_name' => 'system.performance',
      'config_item' => 'css.preprocess',
      'key_id' => 'env_zero',
    ])->save();
    $this->container->get('kernel')->rebuildContainer();

    $this->assertFalse((bool) \Drupal::config('system.performance')->get('css.preprocess'));
  }

  /**
   * Tests that the State provider form accepts a stored zero.
   */
  public function testStateProviderValidationZero(): void {
    \Drupal::state()->set('key_test_zero', 0);
    $key = Key::create([
      'id' => 'state_zero',
      'label' => 'State zero',
      'key_type' => 'authentication',
      'key_provider' => 'state',
      'key_provider_settings' => [
        'state_key' => 'key_test_zero',
      ],
      'key_input' => 'none',
    ]);

    $provider = $key->getKeyProvider();
    $this->assertInstanceOf(KeyPluginFormInterface::class, $provider);

    $form = [];
    $form_state = (new FormState())->setValues(['state_key' => 'key_test_zero']);
    $provider->validateConfigurationForm($form, $form_state);
    $this->assertSame([], $form_state->getErrors());

    $form_state = (new FormState())->setValues(['state_key' => 'key_test_missing']);
    $provider->validateConfigurationForm($form, $form_state);
    $this->assertArrayHasKey('state_key', $form_state->getErrors());
  }

}
