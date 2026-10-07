<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Schema\ArrayElement;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\Type\FloatInterface;
use Drupal\Core\TypedData\Type\IntegerInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\AiVdbProviderPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sets up a vdb server, using default embeddings model.
 *
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'setupVdbServerWithDefaults',
  admin_label: new TranslatableMarkup('Setup an VDB Server'),
  entity_types: ['search_api.server.*'],
)]
final class SetupVdbServer implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Backend configuration that must match an existing VDB server.
   */
  private const REMOTE_CONFIGURATION_KEYS = [
    'database',
    'database_settings',
    'embeddings_engine',
    'embeddings_engine_configuration',
    'embedding_strategy',
    'embedding_strategy_configuration',
  ];

  public function __construct(
    private readonly ConfigActionPluginInterface $simpleConfigUpdate,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AiProviderPluginManager $aiProviderPluginManager,
    private readonly AiVdbProviderPluginManager $aiVdbProviderPluginManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly TypedConfigManagerInterface $typedConfigManager,
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
      $container->get('ai.vdb_provider'),
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('config.typed'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    // Basic validation that it's a vdb server.
    assert(isset($value['id']));
    assert(isset($value['name']));
    assert(isset($value['backend_config']['embedding_strategy']));
    assert(isset($value['backend_config']['embedding_strategy_configuration']));

    // Handle default database setting if not provided.
    if (!isset($value['backend_config']['database'])) {
      $default_vdb_provider = $this->aiVdbProviderPluginManager->defaultIfNone();
      if (empty($default_vdb_provider)) {
        throw new \Exception('No default VDB provider is set and database backend is not specified.');
      }
      $value['backend_config']['database'] = $default_vdb_provider;
    }

    // Handle default database_name setting if not provided.
    if (!isset($value['backend_config']['database_settings'])) {
      $value['backend_config']['database_settings'] = [];
    }
    if (!isset($value['backend_config']['database_settings']['database_name'])) {
      try {
        $vdb_provider = $this->aiVdbProviderPluginManager->createInstance($value['backend_config']['database']);
        $value['backend_config']['database_settings']['database_name'] = $vdb_provider->getDefaultDatabase();
      }
      catch (\Exception $e) {
        throw new \Exception('Could not get default database name from VDB provider: ' . $e->getMessage());
      }
    }
    // Make sure a default embeddings model is set and can be loaded.
    $defaults = $this->aiProviderPluginManager->getDefaultProviderForOperationType('embeddings');
    if (empty($defaults)) {
      throw new \Exception('No default embeddings model is set.');
    }
    try {
      $provider = $this->aiProviderPluginManager->createInstance($defaults['provider_id']);
    }
    catch (\Exception $e) {
      throw new \Exception('The default embeddings model is not supported.');
    }

    // Get the vector size. This asks the provider for an actual vector, so it
    // fails when the provider server is unreachable as well as when the model
    // is unsupported.
    $vector_size = NULL;
    try {
      $vector_size = $provider->embeddingsVectorSize($defaults['model_id']);
    }
    catch (\Exception $e) {
      throw new \Exception('Could not get a vector from the provider ' . $defaults['provider_id'] . ' using the model ' . $defaults['model_id'] . '. The provider server might be down or the model might not be supported. The error was: ' . $e->getMessage(), $e->getCode(), $e);
    }
    // Set the embeddings engine & configuration.
    $value['backend_config']['embeddings_engine'] = $defaults['provider_id'] . '__' . $defaults['model_id'];
    $value['backend_config']['embeddings_engine_configuration']['dimensions'] = $vector_size;
    $value['backend_config']['embeddings_engine_configuration']['set_dimensions'] = FALSE;

    $storage = $this->entityTypeManager->getStorage('search_api_server');
    /** @var \Drupal\search_api\ServerInterface|null $server */
    $server = $storage->load($value['id']);
    if ($server) {
      // Config::save() casts values to the types declared in config schema
      // before storing them, so cast the recipe values the same way. Without
      // this an unquoted "chunk_size: 300" in a recipe would be reported as
      // conflicting with the stored string "300". The backend is always
      // included so the dynamic backend_config schema type can be resolved.
      $expected = $this->castToSchema('search_api.server.' . $value['id'], [
        'backend' => $value['backend'] ?? $server->getBackendId(),
        'backend_config' => array_intersect_key(
          $value['backend_config'],
          array_flip(self::REMOTE_CONFIGURATION_KEYS),
        ),
      ]);
      if (!array_key_exists('backend', $value)) {
        unset($expected['backend']);
      }
      $actual = [
        'backend' => $server->getBackendId(),
        'backend_config' => $server->getBackendConfig(),
      ];
      $differences = $this->findDifferences($expected, $actual);
      if ($differences) {
        throw new ConfigActionException(sprintf(
          'The VDB server "%s" already exists with conflicting remote configuration at %s. Update the recipe to match the existing server, or remove the server and its remote collection before reapplying the recipe.',
          $value['id'],
          implode(', ', $differences),
        ));
      }
      $this->loggerFactory->get('ai')->notice('The VDB server "@id" already exists with matching remote configuration. The setupVdbServerWithDefaults config action left it unchanged.', [
        '@id' => $value['id'],
      ]);
      return;
    }

    // Save the configuration.
    try {
      $storage->create($value)
        ->save();
    }
    catch (\Exception $e) {
      throw new \Exception('Could not save the configuration.');
    }
  }

  /**
   * Casts values to the types declared in the config schema.
   *
   * This mirrors what happens when the configuration is saved, so that the
   * recipe values can be strictly compared with the stored values.
   *
   * @param string $config_name
   *   The configuration name used to look up the schema.
   * @param array $data
   *   The configuration data to cast.
   *
   * @return array
   *   The configuration data with scalar values cast to their schema types.
   *
   * @see \Drupal\Core\Config\StorableConfigBase::castValue()
   */
  private function castToSchema(string $config_name, array $data): array {
    $typed = $this->typedConfigManager->createFromNameAndData($config_name, $data);
    return $this->castElement($typed);
  }

  /**
   * Recursively casts a typed data element to its schema type.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $element
   *   The typed data element.
   *
   * @return mixed
   *   The cast value.
   */
  private function castElement(TypedDataInterface $element): mixed {
    $value = $element->getValue();
    if ($element instanceof PrimitiveInterface) {
      $empty_number = $value === '' && ($element instanceof IntegerInterface || $element instanceof FloatInterface);
      return ($value === NULL || $empty_number) ? NULL : $element->getCastedValue();
    }
    if ($element instanceof ArrayElement && is_array($value)) {
      $cast = [];
      foreach ($element as $key => $child) {
        $cast[$key] = $this->castElement($child);
      }
      return $cast;
    }
    // Undefined or ignored schema elements keep their raw value, which is
    // what happens when the configuration is saved as well.
    return $value;
  }

  /**
   * Finds expected configuration values that differ from stored values.
   *
   * Only keys present in the expected configuration are compared. This avoids
   * treating defaults added by Search API as conflicts.
   *
   * @param array $expected
   *   The configuration expected by the recipe.
   * @param array $actual
   *   The stored configuration.
   * @param string $prefix
   *   The parent configuration path.
   *
   * @return string[]
   *   The dotted paths of conflicting values.
   */
  private function findDifferences(array $expected, array $actual, string $prefix = ''): array {
    $differences = [];
    foreach ($expected as $key => $expected_value) {
      $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
      if (!array_key_exists($key, $actual)) {
        $differences[] = $path;
        continue;
      }
      if (is_array($expected_value)) {
        if (!is_array($actual[$key])) {
          $differences[] = $path;
          continue;
        }
        $differences = array_merge(
          $differences,
          $this->findDifferences($expected_value, $actual[$key], $path),
        );
      }
      elseif ($expected_value !== $actual[$key]) {
        $differences[] = $path;
      }
    }
    return $differences;
  }

}
