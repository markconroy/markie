<?php

namespace Drupal\Tests\key\Unit\Plugin\KeyProvider;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\key\Entity\Key;
use Drupal\key\Plugin\KeyProvider\StateKeyProvider;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\key\Plugin\KeyProvider\StateKeyProvider
 * @group key
 */
#[Group('key')]
class StateKeyProviderTest extends UnitTestCase {

  /**
   * The state key provider.
   *
   * @var \Drupal\key\Plugin\KeyProvider\StateKeyProvider
   */
  protected $provider;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface|\PHPUnit\Framework\MockObject\Stub
   */
  protected $state;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface|\PHPUnit\Framework\MockObject\Stub
   */
  protected $logger;

  /**
   * The configuration object.
   *
   * @var array
   */
  protected $configuration;

  /**
   * The state key provider plugin id.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * The state key provider plugin definition.
   *
   * @var array
   */
  protected $pluginDefinition;

  /**
   * The key entity object.
   *
   * @var \Drupal\key\Entity\Key|\PHPUnit\Framework\MockObject\Stub
   */
  protected $key;

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit\Framework\MockObject\Stub
   */
  protected $stringTranslation;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a container with necessary services.
    $container = new ContainerBuilder();

    // Mock the string translation service.
    $this->stringTranslation = $this->createStub(TranslationInterface::class);
    $this->stringTranslation->method('translate')
      ->willReturnCallback(function ($string) {
        return $string;
      });
    $container->set('string_translation', $this->stringTranslation);

    // Mock the logger factory.
    $loggerFactory = $this->getLoggerFactoryMock();
    $container->set('logger.factory', $loggerFactory);

    // Set the container.
    \Drupal::setContainer($container);

    // Stub the state service. Tests that expect calls on it replace it with
    // a mock through ::createProvider().
    $this->state = $this->createStub(StateInterface::class);
    $this->logger = $loggerFactory->get('key');
    $this->configuration = ['state_key' => 'test_key_state'];
    $this->pluginId = 'state';
    $this->pluginDefinition = [
      'id' => 'state',
      'label' => 'State',
    ];

    $this->provider = $this->createProvider($this->state);

    // Create a properly configured key stub.
    $this->key = $this->createStub(Key::class);
    $this->key->method('id')->willReturn('test_key');
  }

  /**
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfiguration() {
    $expected = ['state_key' => ''];
    $this->assertEquals($expected, $this->provider->defaultConfiguration());
  }

  /**
   * @covers ::getKeyValue
   */
  public function testGetKeyValue() {
    $state = $this->createMock(StateInterface::class);
    $state->expects($this->once())
      ->method('get')
      ->with('test_key_state')
      ->willReturn('secret_value');
    $this->provider = $this->createProvider($state);

    $result = $this->provider->getKeyValue($this->key);
    $this->assertEquals('secret_value', $result);
  }

  /**
   * @covers ::buildConfigurationForm
   */
  public function testBuildConfigurationForm() {
    $form = [];
    $form_state = new FormState();

    $result = $this->provider->buildConfigurationForm($form, $form_state);

    // Check the structure without comparing
    // TranslatableMarkup objects directly.
    $this->assertEquals('textfield', $result['state_key']['#type']);
    $this->assertEquals(TRUE, $result['state_key']['#required']);
    $this->assertEquals('test_key_state', $result['state_key']['#default_value']);

    // Verify TranslatableMarkup objects exist.
    $this->assertInstanceOf('Drupal\Core\StringTranslation\TranslatableMarkup', $result['state_key']['#title']);
    $this->assertInstanceOf('Drupal\Core\StringTranslation\TranslatableMarkup', $result['state_key']['#description']);

    // Get the raw strings from the translation arguments.
    $titleMarkup = $result['state_key']['#title'];
    $descriptionMarkup = $result['state_key']['#description'];

    // Check the untranslated strings.
    $this->assertEquals('State key', $titleMarkup->getUntranslatedString());
    $this->assertSame('Name of the state variable.', $descriptionMarkup->getUntranslatedString());
  }

  /**
   * @covers ::submitConfigurationForm
   */
  public function testSubmitConfigurationForm() {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues(['state_key' => 'test_key_state']);

    $this->provider->submitConfigurationForm($form, $form_state);

    $config = $this->provider->getConfiguration();
    $this->assertEquals('test_key_state', $config['state_key']);
  }

  /**
   * @covers ::validateConfigurationForm
   */
  public function testValidateConfigurationForm() {
    $form = [];
    $form_state = new FormState();
    $form_state->setValues(['state_key' => 'test_key_state']);

    // Set up the state mock to return a value.
    $state = $this->createMock(StateInterface::class);
    $state->expects($this->once())
      ->method('get')
      ->with('test_key_state')
      ->willReturn('some_value');
    $this->provider = $this->createProvider($state);

    // This should not cause any errors.
    $this->provider->validateConfigurationForm($form, $form_state);
    $this->assertFalse($form_state->hasAnyErrors());
  }

  /**
   * Creates the state key provider under test.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service to give the provider.
   *
   * @return \Drupal\key\Plugin\KeyProvider\StateKeyProvider
   *   The state key provider.
   */
  protected function createProvider(StateInterface $state): StateKeyProvider {
    $provider = new StateKeyProvider(
      $this->configuration,
      $this->pluginId,
      $this->pluginDefinition,
      $state,
      $this->logger
    );
    $provider->setStringTranslation($this->stringTranslation);
    return $provider;
  }

  /**
   * Helper to mock the logger.factory service.
   */
  protected function getLoggerFactoryMock() {
    $logger = $this->createStub('Drupal\Core\Logger\LoggerChannelInterface');

    $factory = $this->createMock('Drupal\Core\Logger\LoggerChannelFactoryInterface');
    $factory
      ->method('get')
      ->with('key')
      ->willReturn($logger);
    return $factory;
  }

}
