<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Stores an api key.
 *
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'setupAiProvider',
  admin_label: new TranslatableMarkup('Setup an AI provider'),
  entity_types: ['*'],
)]
final class SetupAiProvider implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    private readonly ConfigActionPluginInterface $simpleConfigUpdate,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AiProviderPluginManager $aiProviderPluginManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('plugin.manager.config_action')->createInstance('simpleConfigUpdate'),
      $container->get(EntityTypeManagerInterface::class),
      $container->get('ai.provider'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    // Assert array.
    assert(is_array($value));
    // Provider has to be set.
    assert(isset($value['provider']));
    // Load the provider.
    $provider = [];
    try {
      $provider = $this->aiProviderPluginManager->createInstance($value['provider']);
    }
    catch (\Exception $e) {
      throw new \Exception('The provider ' . $value['provider'] . ' is not supported.');
    }
    $setupData = [];
    if ($provider->getSetupData()) {
      $setupData = $provider->getSetupData();
    }
    if (isset($value['provider']) && !empty($value['key_value']) && !empty($setupData['key_config_name'])) {

      // Create a key.
      $key = $this->createKeyFromApiKey($value['key_name'], $value['key_label'], $value['key_value']);

      $this->simpleConfigUpdate->apply($configName, [
        $setupData['key_config_name'] => $key->id(),
      ]);
      if (isset($setupData['default_models'])) {
        foreach ($setupData['default_models'] as $operation => $model) {
          $this->aiProviderPluginManager->defaultIfNone($operation, $value['provider'], $model);
        }
      }
    }
  }

  /**
   * Create a key from API Key.
   *
   * @param string $key_name
   *   The key name.
   * @param string $key_label
   *   The key label.
   * @param string $api_key
   *   The API key.
   *
   * @return \Drupal\key\Entity\Key
   *   The key entity.
   */
  protected function createKeyFromApiKey(string $key_name, string $key_label, string $api_key) {
    // Double check if the key already exists.
    $key = $this->entityTypeManager->getStorage('key')->load($key_name);
    if ($key) {
      return $key;
    }

    $values = [
      'label' => $key_label,
      'id' => $key_name,
      'key_type' => 'authentication',
      'key_type_settings' => [],
      'key_provider' => 'config',
      'key_provider_settings' => [
        'key_value' => $api_key,
      ],
      'key_input' => 'text_field',
      'description' => 'Automatically created by the AI Core module.',
    ];
    $key = $this->entityTypeManager->getStorage('key')->create($values);
    $key->save();
    return $key;
  }

}
