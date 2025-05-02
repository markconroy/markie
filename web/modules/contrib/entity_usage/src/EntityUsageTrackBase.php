<?php

namespace Drupal\entity_usage;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DeprecatedServicePropertyTrait;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base implementation for track plugins.
 */
abstract class EntityUsageTrackBase extends PluginBase implements EntityUsageTrackInterface, ContainerFactoryPluginInterface {
  use DeprecatedServicePropertyTrait;

  /**
   * Deprecated service properties.
   *
   * @var string[]
   */
  protected $deprecatedProperties = [
    'pathValidator' => 'path.validator',
  ];

  /**
   * The usage tracking service.
   *
   * @var \Drupal\entity_usage\EntityUsageInterface
   */
  protected $usageService;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The Entity Update config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The EntityRepository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * A list of entity types that are tracked.
   *
   * @var string[]|null
   */
  private readonly ?array $enabledTargetEntityTypes;

  /**
   * Logger for entity usage.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $entityUsageLogger;

  /**
   * The URL to Entity service.
   */
  protected UrlToEntityInterface $urlToEntity;

  /**
   * The list of entity type IDs to always track base fields for.
   *
   * @var string[]
   */
  private array $alwaysTrackBaseFields;

  /**
   * Plugin constructor.
   *
   * @param mixed[] $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\entity_usage\EntityUsageInterface $usage_service
   *   The usage tracking service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The EntityTypeManager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The EntityFieldManager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The EntityRepositoryInterface service.
   * @param \Psr\Log\LoggerInterface|null $entityUsageLogger
   *   The entity usage logger.
   * @param \Drupal\entity_usage\UrlToEntityInterface|null $urlToEntity
   *   The URL to entity service.
   * @param string[]|null $always_track_base_fields
   *   A list of entity types ID to always track base fields for.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityUsageInterface $usage_service,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    ConfigFactoryInterface $config_factory,
    EntityRepositoryInterface $entity_repository,
    ?LoggerInterface $entityUsageLogger = NULL,
    ?UrlToEntityInterface $urlToEntity = NULL,
    ?array $always_track_base_fields = NULL,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configuration += $this->defaultConfiguration();
    $this->usageService = $usage_service;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->config = $config_factory->get('entity_usage.settings');
    $this->entityRepository = $entity_repository;
    $this->enabledTargetEntityTypes = $this->config->get('track_enabled_target_entity_types');
    if ($entityUsageLogger === NULL) {
      // @phpstan-ignore-next-line
      $entityUsageLogger = \Drupal::service('logger.channel.entity_usage');
    }
    $this->entityUsageLogger = $entityUsageLogger;
    if ($urlToEntity === NULL) {
      // @phpstan-ignore-next-line
      $urlToEntity = \Drupal::service(UrlToEntityInterface::class);
    }
    $this->urlToEntity = $urlToEntity;
    if ($always_track_base_fields === NULL) {
      // @phpstan-ignore-next-line
      $always_track_base_fields = \Drupal::getContainer()->getParameter('entity_usage')['always_track_base_fields'] ?? [];
    }
    $this->alwaysTrackBaseFields = $always_track_base_fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_usage.usage'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('config.factory'),
      $container->get('entity.repository'),
      $container->get('logger.channel.entity_usage'),
      $container->get(UrlToEntityInterface::class),
      $container->getParameter('entity_usage')['always_track_base_fields'] ?? []
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string|TranslatableMarkup {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string|TranslatableMarkup {
    return $this->pluginDefinition['description'] ?: '';
  }

  /**
   * {@inheritdoc}
   */
  public function getApplicableFieldTypes(): array {
    return $this->pluginDefinition['field_types'] ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function trackOnEntityCreation(EntityInterface $source_entity): void {
    if (!($source_entity instanceof FieldableEntityInterface)) {
      return;
    }
    $trackable_field_types = $this->getApplicableFieldTypes();
    $fields = array_keys($this->getReferencingFields($source_entity, $trackable_field_types));
    foreach ($fields as $field_name) {
      if ($source_entity->hasField($field_name) && !$source_entity->{$field_name}->isEmpty()) {
        try {
          if ($this instanceof EntityUsageTrackMultipleLoadInterface) {
            $target_entities = $this->getTargetEntitiesFromField($source_entity->{$field_name});
          }
          else {
            $target_entities = [];
            /** @var \Drupal\Core\Field\FieldItemInterface $field_item */
            foreach ($source_entity->{$field_name} as $field_item) {
              // The entity is being created with value on this field, so we
              // just need to add a tracking record.
              $target_entities = array_merge($target_entities, $this->getTargetEntities($field_item));
            }
          }
        }
        catch (\Exception $e) {
          $this->logTrackingException($e, $source_entity, $field_name);
          continue;
        }
        // If a field references the same target entity, we record only one
        // usage.
        $target_entities = array_unique($target_entities);
        foreach ($target_entities as $target_entity) {
          [$target_type, $target_id] = explode("|", $target_entity);
          $source_vid = ($source_entity instanceof RevisionableInterface && $source_entity->getRevisionId()) ? $source_entity->getRevisionId() : 0;
          $this->usageService->registerUsage($target_id, $target_type, $source_entity->id(), $source_entity->getEntityTypeId(), $source_entity->language()->getId(), $source_vid, $this->pluginId, $field_name);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function trackOnEntityUpdate(EntityInterface $source_entity): void {
    // We depend on $source_entity->original to do anything useful here.
    if (empty($source_entity->original) || !($source_entity instanceof FieldableEntityInterface)) {
      return;
    }

    // New revisions should be tracked the same way as new entities.
    if ($source_entity instanceof RevisionableInterface && $source_entity->getRevisionId() != $source_entity->original->getRevisionId()) {
      $this->trackOnEntityCreation($source_entity);
      return;
    }

    $trackable_field_types = $this->getApplicableFieldTypes();
    $fields = array_keys($this->getReferencingFields($source_entity, $trackable_field_types));
    foreach ($fields as $field_name) {
      $this->updateTrackingDataForField($source_entity, $field_name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencingFields(EntityInterface $source_entity, array $field_types): array {
    $referencing_fields_on_bundle = [];
    if (!($source_entity instanceof FieldableEntityInterface)) {
      return $referencing_fields_on_bundle;
    }

    $source_entity_type_id = $source_entity->getEntityTypeId();
    $all_fields_on_bundle = $this->entityFieldManager->getFieldDefinitions($source_entity_type_id, $source_entity->bundle());
    foreach ($all_fields_on_bundle as $field_name => $field) {
      if (in_array($field->getType(), $field_types)) {
        $referencing_fields_on_bundle[$field_name] = $field;
      }
    }

    if (!$this->config->get('track_enabled_base_fields') && !in_array($source_entity_type_id, $this->alwaysTrackBaseFields, TRUE)) {
      foreach ($referencing_fields_on_bundle as $key => $referencing_field_on_bundle) {
        if ($referencing_field_on_bundle->getFieldStorageDefinition()->isBaseField()) {
          unset($referencing_fields_on_bundle[$key]);
        }
      }
    }

    return $referencing_fields_on_bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function updateTrackingDataForField(FieldableEntityInterface $source_entity, string $field_name): void {
    // We are updating an existing revision, compare target entities to see if
    // we need to add or remove tracking records.
    $current_targets = [];
    try {
      if ($source_entity->hasField($field_name) && !$source_entity->{$field_name}->isEmpty()) {
        if ($this instanceof EntityUsageTrackMultipleLoadInterface) {
          $current_targets = $this->getTargetEntitiesFromField($source_entity->{$field_name});
        }
        else {
          /** @var \Drupal\Core\Field\FieldItemInterface $field_item */
          foreach ($source_entity->{$field_name} as $field_item) {
            $current_targets = array_merge($current_targets, $this->getTargetEntities($field_item));
          }
        }
      }
    }
    catch (\Exception $e) {
      $this->logTrackingException($e, $source_entity, $field_name);
    }

    $source_entity_langcode = $source_entity->language()->getId();
    $source_vid = ($source_entity instanceof RevisionableInterface && $source_entity->getRevisionId()) ? $source_entity->getRevisionId() : 0;
    $original_targets = $this->usageService->listTargetEntitiesByFieldAndMethod($source_entity->id(), $source_entity->getEntityTypeId(), $source_entity_langcode, $source_vid, $this->pluginId, $field_name);

    // If a field references the same target entity, we record only one usage.
    $original_targets = array_unique($original_targets);
    $current_targets = array_unique($current_targets);

    $added_ids = array_diff($current_targets, $original_targets);
    $removed_ids = array_diff($original_targets, $current_targets);

    foreach ($added_ids as $added_entity) {
      [$target_type, $target_id] = explode('|', $added_entity);
      $this->usageService->registerUsage($target_id, $target_type, $source_entity->id(), $source_entity->getEntityTypeId(), $source_entity_langcode, $source_vid, $this->pluginId, $field_name);
    }
    foreach ($removed_ids as $removed_entity) {
      [$target_type, $target_id] = explode('|', $removed_entity);
      $this->usageService->registerUsage($target_id, $target_type, $source_entity->id(), $source_entity->getEntityTypeId(), $source_entity_langcode, $source_vid, $this->pluginId, $field_name, 0);
    }
  }

  /**
   * Process the url to a Url object.
   *
   * @param string $url
   *   A relative or absolute URL string.
   *
   * @return \Drupal\Core\Url|false
   *   The Url object
   *
   * @deprecated in entity_usage:8.x-2.0-beta18 and is removed from entity_usage:8.x-2.0-rc1. Use the
   *   Drupal\entity_usage\UrlToEntityInterface service instead.
   *
   * @see https://www.drupal.org/project/entity_usage/issues/3341932
   */
  protected function processUrl($url) {
    @trigger_error('\Drupal\entity_usage\EntityUsageTrackBase::processUrl() is deprecated in entity_usage:8.x-2.0-beta18 and is removed from entity_usage:8.x-2.0-rc1. Use the Drupal\entity_usage\UrlToEntityInterface service instead. See https://www.drupal.org/project/entity_usage/issues/3341932', E_USER_DEPRECATED);
    // Strip off the scheme and host, so we only get the path.
    $site_domains = $this->config->get('site_domains') ?: [];
    foreach ($site_domains as $site_domain) {
      $site_domain = rtrim($site_domain, "/");
      $host_pattern = str_replace('.', '\.', $site_domain) . "/";
      $host_pattern = "/" . str_replace("/", '\/', $host_pattern) . "/";
      if (preg_match($host_pattern, $url)) {
        // Strip off everything that is not the internal path.
        $url = parse_url($url, PHP_URL_PATH);

        if (preg_match('/^[^\/]+(\/.+)/', $site_domain, $matches)) {
          $sub_directory = $matches[1];
          if (str_starts_with($url, $sub_directory)) {
            $url = substr($url, strlen($sub_directory));
          }
        }

        break;
      }
    }

    return $this->pathValidator()->getUrlIfValidWithoutAccessCheck($url);
  }

  /**
   * Try to retrieve an entity from an URL string.
   *
   * @param string $url
   *   A relative or absolute URL string.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity object that corresponds to the received URL, or NULL if no
   *   entity could be retrieved.
   *
   * @deprecated in entity_usage:8.x-2.0-beta18 and is removed from entity_usage:8.x-2.0-rc1. Use the
   *   Drupal\entity_usage\UrlToEntityInterface service instead.
   *
   * @see https://www.drupal.org/project/entity_usage/issues/3341932
   */
  protected function findEntityByUrlString($url) {
    @trigger_error('\Drupal\entity_usage\EntityUsageTrackBase::findEntityByUrlString() is deprecated in entity_usage:8.x-2.0-beta18 and is removed from entity_usage:8.x-2.0-rc1. Use the Drupal\entity_usage\UrlToEntityInterface service instead. See https://www.drupal.org/project/entity_usage/issues/3341932', E_USER_DEPRECATED);
    $entity_info = $this->findEntityIdByUrlString($url);
    if (is_array($entity_info)) {
      ['type' => $entity_type_id, 'id' => $entity_id] = $entity_info;
      return $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
    }

    return NULL;
  }

  /**
   * Try to retrieve entity information from a URL string.
   *
   * @param string $url
   *   A URL string.
   *
   * @return string[]|null
   *   An array with two values, the entity type and entity ID, or NULL if no
   *   entity could be retrieved.
   *
   * @deprecated in entity_usage:8.x-2.0-beta18 and is removed from entity_usage:8.x-2.0-rc1. Use the
   *   Drupal\entity_usage\UrlToEntityInterface service instead.
   *
   * @see https://www.drupal.org/project/entity_usage/issues/3341932
   */
  protected function findEntityIdByUrlString(string $url): ?array {
    @trigger_error('\Drupal\entity_usage\EntityUsageTrackBase::findEntityIdByUrlString() is deprecated in entity_usage:8.x-2.0-beta18 and is removed from entity_usage:8.x-2.0-rc1. Use the Drupal\entity_usage\UrlToEntityInterface service instead. See https://www.drupal.org/project/entity_usage/issues/3341932', E_USER_DEPRECATED);
    return $this->urlToEntity->findEntityIdByUrl($url);
  }

  /**
   * Try to retrieve an entity from an URL object.
   *
   * @param \Drupal\Core\Url $url
   *   A URL object.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity object that corresponds to the URL object, or NULL if no
   *   entity could be retrieved.
   *
   * @deprecated in entity_usage:8.x-2.0-beta18 and is removed from entity_usage:8.x-2.0-rc1. Use the
   *   Drupal\entity_usage\UrlToEntityInterface service instead.
   *
   * @see https://www.drupal.org/project/entity_usage/issues/3341932
   */
  protected function findEntityByRoutedUrl(Url $url): ?EntityInterface {
    @trigger_error('\Drupal\entity_usage\EntityUsageTrackBase::findEntityByRoutedUrl() is deprecated in entity_usage:8.x-2.0-beta18 and is removed from entity_usage:8.x-2.0-rc1. Use the Drupal\entity_usage\UrlToEntityInterface service instead. See https://www.drupal.org/project/entity_usage/issues/3341932', E_USER_DEPRECATED);
    $entity_info = $this->findEntityIdByRoutedUrl($url);
    if (is_array($entity_info)) {
      ['type' => $entity_type_id, 'id' => $entity_id] = $entity_info;
      return $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
    }

    return NULL;
  }

  /**
   * Try to retrieve entity information from a URL object.
   *
   * @param \Drupal\Core\Url $url
   *   A URL object.
   *
   * @return string[]|null
   *   An array with two values, the entity type and entity ID, or NULL if no
   *   entity could be retrieved.
   *
   * @deprecated in entity_usage:8.x-2.0-beta18 and is removed from entity_usage:8.x-2.0-rc1. Use the
   *   Drupal\entity_usage\UrlToEntityInterface service instead.
   *
   * @see https://www.drupal.org/project/entity_usage/issues/3341932
   */
  protected function findEntityIdByRoutedUrl(Url $url): ?array {
    @trigger_error('\Drupal\entity_usage\EntityUsageTrackBase::findEntityIdByRoutedUrl() is deprecated in entity_usage:8.x-2.0-beta18 and is removed from entity_usage:8.x-2.0-rc1. Use the Drupal\entity_usage\UrlToEntityInterface service instead. See https://www.drupal.org/project/entity_usage/issues/3341932', E_USER_DEPRECATED);
    return $this->urlToEntity->findEntityIdByRoutedUrl($url);
  }

  /**
   * Returns the path validator service.
   *
   * @return \Drupal\Core\Path\PathValidatorInterface
   *   The path validator.
   *
   * @deprecated in entity_usage:8.x-2.0-beta18 and is removed from entity_usage:8.x-2.0-rc1. There is no
   *   replacement.
   *
   * @see https://www.drupal.org/project/entity_usage/issues/3341932
   */
  protected function pathValidator() {
    @trigger_error('\Drupal\entity_usage\EntityUsageTrackBase::pathValidator() is deprecated in entity_usage:8.x-2.0-beta18 and is removed from entity_usage:8.x-2.0-rc1. There is no replacement. See https://www.drupal.org/project/entity_usage/issues/3341932', E_USER_DEPRECATED);
    // @phpstan-ignore-next-line
    return $this->pathValidator;
  }

  /**
   * Return the public file directory path.
   *
   * @return string
   *   The public file directory path.
   *
   * @deprecated in entity_usage:8.x-2.0-beta18 and is removed from entity_usage:8.x-2.0-rc1. There is no
   *   replacement.
   *
   * @see https://www.drupal.org/project/entity_usage/issues/3341932
   */
  protected function publicFileDirectory() {
    @trigger_error('\Drupal\entity_usage\EntityUsageTrackBase::publicFileDirectory() is deprecated in entity_usage:8.x-2.0-beta18 and is removed from entity_usage:8.x-2.0-rc1. There is no replacement. See https://www.drupal.org/project/entity_usage/issues/3341932', E_USER_DEPRECATED);
    // @phpstan-ignore-next-line
    return \Drupal::service('stream_wrapper.public')->getDirectoryPath();
  }

  /**
   * Determines if an entity type is tracked.
   *
   * @param string $entity_type_id
   *   The entity type ID to check.
   *
   * @return bool
   *   Determines if an entity type is tracked.
   */
  protected function isEntityTypeTracked(string $entity_type_id): bool {
    // Every entity type is tracked if not set.
    return $this->enabledTargetEntityTypes === NULL || in_array($entity_type_id, $this->enabledTargetEntityTypes, TRUE);
  }

  /**
   * Prepare target entity values to be in the correct format.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param array $ids
   *   An array of entity IDs, can be revision or UUIDs as well.
   * @param string $idField
   *   The ID field; 'uuid', 'revision' or 'id'.
   *
   * @return string[]
   *   An array of the corresponding entity IDs from the IDs passed in,
   *   each prefixed with the string "$entityTypeId|". Non-loadable entities
   *   will be filtered out.
   */
  protected function checkAndPrepareEntityIds(string $entityTypeId, array $ids, string $idField): array {
    if (empty($ids) || !$this->isEntityTypeTracked($entityTypeId)) {
      return [];
    }
    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition($storage->getEntityType()->getKey($idField), $ids, 'IN')
      ->execute();

    return array_map(fn ($id) => $entityTypeId . '|' . $id, $ids);
  }

  /**
   * Logs a tracking exception.
   *
   * @param \Exception $e
   *   The exception to log.
   * @param \Drupal\Core\Entity\EntityInterface $source_entity
   *   The source entity that caused the exception.
   * @param string $field_name
   *   The field name that caused the exception.
   */
  private function logTrackingException(\Exception $e, EntityInterface $source_entity, string $field_name): void {
    Error::logException(
      $this->entityUsageLogger,
      $e,
      'Calculating entity usage for field %field on @entity_type:@entity_id using the %plugin plugin threw %type: @message in %function (line %line of %file).',
      [
        '%plugin' => $this->getPluginId(),
        '@entity_type' => $source_entity->getEntityTypeId(),
        '@entity_id' => $source_entity->id(),
        '%field' => $field_name,
      ]
    );
  }

}
