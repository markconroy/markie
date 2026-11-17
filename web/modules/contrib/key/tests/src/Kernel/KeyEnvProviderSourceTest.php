<?php

declare(strict_types=1);

namespace Drupal\Tests\key\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\key\Entity\Key;
use Drupal\key\Entity\KeyConfigOverride;
use Drupal\key\KeyInterface;
use Drupal\key\Plugin\KeyPluginFormInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests where the Environment key provider reads its value from.
 *
 * @group key
 */
#[Group('key')]
#[RunTestsInSeparateProcesses]
final class KeyEnvProviderSourceTest extends KernelTestBase {

  /**
   * The environment variable used by the test.
   */
  private const ENV_VARIABLE = 'KEY_TEST_ENV_SOURCE';

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
    putenv(self::ENV_VARIABLE);
    unset($_ENV[self::ENV_VARIABLE]);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    putenv(self::ENV_VARIABLE);
    unset($_ENV[self::ENV_VARIABLE]);
    parent::tearDown();
  }

  /**
   * Tests a value only present in $_ENV, as set by symfony/dotenv.
   */
  public function testEnvSuperglobalFallback(): void {
    $_ENV[self::ENV_VARIABLE] = 'dotenv-value';
    $this->assertFalse(getenv(self::ENV_VARIABLE));

    $key = $this->createEnvKey();
    $this->assertSame('dotenv-value', $key->getKeyValue());
    $this->assertSame([], $this->validateProviderForm($key));
  }

  /**
   * Tests that getenv() takes precedence over $_ENV.
   */
  public function testGetenvTakesPrecedence(): void {
    putenv(self::ENV_VARIABLE . '=getenv-value');
    $_ENV[self::ENV_VARIABLE] = 'dotenv-value';

    $this->assertSame('getenv-value', $this->createEnvKey()->getKeyValue());
  }

  /**
   * Tests that a missing variable stays NULL and does not blank an override.
   */
  public function testMissingVariable(): void {
    $key = $this->createEnvKey();
    $this->assertNull($key->getKeyValue());
    $this->assertArrayHasKey('env_variable', $this->validateProviderForm($key));

    $this->config('system.site')->set('name', 'Original name')->save();
    KeyConfigOverride::create([
      'id' => 'site_name',
      'label' => 'Site name',
      'config_type' => 'system.simple',
      'config_name' => 'system.site',
      'config_item' => 'name',
      'key_id' => $key->id(),
    ])->save();
    $this->container->get('kernel')->rebuildContainer();

    $this->assertSame('Original name', \Drupal::config('system.site')->get('name'));
  }

  /**
   * Creates and saves a key that uses the Environment provider.
   *
   * @return \Drupal\key\KeyInterface
   *   The saved key.
   */
  private function createEnvKey(): KeyInterface {
    $key = Key::create([
      'id' => 'env_source',
      'label' => 'Environment source',
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
    return $key;
  }

  /**
   * Runs the provider's configuration form validation.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key whose provider is validated.
   *
   * @return array
   *   The form errors.
   */
  private function validateProviderForm(KeyInterface $key): array {
    $provider = $key->getKeyProvider();
    $this->assertInstanceOf(KeyPluginFormInterface::class, $provider);
    $form = [];
    $form_state = (new FormState())->setValues(['env_variable' => self::ENV_VARIABLE]);
    $provider->validateConfigurationForm($form, $form_state);
    return $form_state->getErrors();
  }

}
