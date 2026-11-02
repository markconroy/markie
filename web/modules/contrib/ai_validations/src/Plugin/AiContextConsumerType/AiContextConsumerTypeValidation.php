<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\AiContextConsumerType;

use Drupal\ai_context\Attribute\AiContextConsumerType;
use Drupal\ai_context\Model\AiContextConsumerId;
use Drupal\ai_context\Model\AiContextConsumerInstance;
use Drupal\ai_context\Model\AiContextConsumerInstanceCollection;
use Drupal\ai_context\Model\AiContextProviderRequestContext;
use Drupal\ai_context\Plugin\AiContextConsumerType\AiContextConsumerTypeBase;
use Drupal\ai_validations\AiContextAwareFieldValidationRuleInterface;
use Drupal\ai_validations\AiValidationsRequestTags;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AI validation rule context consumer type.
 *
 * Lists each field validation rule whose constraint runs a chat prompt
 * (AI text, image, and audio prompt constraints) as a Context Control
 * Center consumer. Automatic context push stays off until an admin turns
 * it on for that rule. A request is matched only by the
 * ai_validations:{rule_uuid} provider request tag. Context consumer IDs
 * use the short type prefix validation: (same pattern as agent: and
 * ckeditor:). Provider request tags use the module name ai_validations.
 * Consumer names link to the rule set that holds the rule. Selection
 * mode follows the site default (inherit). The type has no availability
 * gate of its own: discovery is the gate.
 */
#[AiContextConsumerType(
  id: self::PLUGIN_ID,
  label: new TranslatableMarkup('Validation'),
  description: new TranslatableMarkup(
    'AI field validation rules that receive pushed context in their system prompt.',
  ),
)]
final class AiContextConsumerTypeValidation extends AiContextConsumerTypeBase {

  /**
   * The consumer type plugin ID.
   */
  public const PLUGIN_ID = 'validation';

  /**
   * The field validation rule set entity type ID.
   */
  private const RULE_SET_ENTITY_TYPE = 'field_validation_rule_set';

  /**
   * Cache ID for context-aware rule UUID to rule-set ID.
   */
  private const RULE_SET_ID_MAP_CID = 'ai_validations.context_aware_rule_set_ids';

  /**
   * The field validation rule plugin manager.
   */
  private PluginManagerInterface $ruleManager;

  /**
   * The entity type manager.
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * Cache backend for the rule UUID map.
   */
  private CacheBackendInterface $cache;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
    $instance->ruleManager = $container
      ->get('plugin.manager.field_validation.field_validation_rule');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->cache = $container->get('cache.default');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabelRoute(string $instanceId): ?array {
    $rule_set_id = $this->findRuleSetId($instanceId);
    if ($rule_set_id === NULL) {
      return NULL;
    }

    return [
      'route_name' => 'entity.field_validation_rule_set.edit_form',
      'route_parameters' => [
        'field_validation_rule_set' => $rule_set_id,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutingRequestTags(): array {
    return [AiValidationsRequestTags::ROUTING];
  }

  /**
   * {@inheritdoc}
   */
  public function getInstances(): AiContextConsumerInstanceCollection {
    $cache = $this->createInstanceCacheability();
    // Which rule plugins exist depends on enabled modules.
    $cache->addCacheTags(['config:core.extension']);
    // Listing rows, descriptions, and label links depend on the rules
    // stored on each rule set and on the rule set labels.
    if ($this->entityTypeManager->hasDefinition(self::RULE_SET_ENTITY_TYPE)) {
      $cache->addCacheTags(
        $this->entityTypeManager
          ->getDefinition(self::RULE_SET_ENTITY_TYPE)
          ->getListCacheTags(),
      );
    }

    $instances = [];
    $rule_set_ids = [];
    foreach ($this->loadRuleSets() as $rule_set) {
      $rule_set_label = trim((string) $rule_set->label());
      if ($rule_set_label === '') {
        $rule_set_label = (string) $rule_set->id();
      }
      foreach ($this->getContextAwareRules($rule_set) as $uuid => $rule) {
        if (!isset($rule_set_ids[$uuid])) {
          $rule_set_ids[$uuid] = (string) $rule_set->id();
        }
        $plugin_label = $this->getRulePluginLabel($rule['id']);
        $title = trim((string) ($rule['title'] ?? ''));
        $instances[] = new AiContextConsumerInstance(
          AiContextConsumerId::fromParts(self::PLUGIN_ID, $uuid),
          $title !== '' ? $title : $plugin_label,
          $this->buildInstanceDescription(
            $plugin_label,
            (string) ($rule['field_name'] ?? ''),
            $rule_set_label,
          ),
        );
      }
    }
    $this->setCachedRuleSetIdMap($rule_set_ids);

    return new AiContextConsumerInstanceCollection($instances, $cache);
  }

  /**
   * {@inheritdoc}
   *
   * Requests tagged ai_agents or ai_assistant_api never reach this
   * method: AiContextConsumerRouter::getRoutingTypeIds() returns no
   * types for them.
   */
  public function resolveConsumerId(
    AiContextProviderRequestContext $providerRequestContext,
  ): ?AiContextConsumerId {
    $tags = $providerRequestContext->getRequestTags();
    $instance_id = $this->instanceIdFromRequestTags($tags);
    if ($instance_id === NULL) {
      return NULL;
    }

    if ($this->findRuleSetId($instance_id) === NULL) {
      return NULL;
    }

    return AiContextConsumerId::fromParts(self::PLUGIN_ID, $instance_id);
  }

  /**
   * Returns the rule UUID from provider request tags, if unique.
   *
   * Only ai_validations:{rule_uuid} is used. The coarse ai_validations
   * tag is ignored.
   *
   * @param string[] $requestTags
   *   Provider request tags.
   *
   * @return string|null
   *   The instance ID, or NULL when missing, empty, or ambiguous.
   */
  private function instanceIdFromRequestTags(array $requestTags): ?string {
    $ids = [];
    $prefix = AiValidationsRequestTags::INSTANCE_PREFIX;
    foreach ($requestTags as $tag) {
      if (!str_starts_with($tag, $prefix)) {
        continue;
      }
      $id = substr($tag, strlen($prefix));
      if ($id === '') {
        continue;
      }
      $ids[$id] = $id;
    }

    if (count($ids) !== 1) {
      return NULL;
    }

    return array_values($ids)[0];
  }

  /**
   * Returns the ID of the rule set that holds a context-aware rule.
   *
   * Uses a cached UUID map so resolve and label links do not
   * load every rule set on each call.
   *
   * @param string $ruleUuid
   *   The field validation rule UUID.
   *
   * @return string|null
   *   The rule set ID, or NULL when no rule set holds a context-aware
   *   rule with that UUID.
   */
  private function findRuleSetId(string $ruleUuid): ?string {
    $id = $this->getCachedRuleSetIdMap()[$ruleUuid] ?? NULL;
    return is_string($id) && $id !== '' ? $id : NULL;
  }

  /**
   * Returns context-aware rule UUIDs mapped to rule-set IDs.
   *
   * @return array<string, string>
   *   Rule-set IDs keyed by rule UUID. The first rule set that
   *   holds a UUID wins.
   */
  private function getCachedRuleSetIdMap(): array {
    $cached = $this->cache->get(self::RULE_SET_ID_MAP_CID);
    if (is_object($cached) && is_array($cached->data)) {
      return $this->sanitizeRuleSetIdMap($cached->data);
    }

    $map = $this->buildRuleSetIdMap();
    $this->setCachedRuleSetIdMap($map);
    return $map;
  }

  /**
   * Builds context-aware rule UUIDs mapped to rule-set IDs.
   *
   * @return array<string, string>
   *   Rule-set IDs keyed by rule UUID.
   */
  private function buildRuleSetIdMap(): array {
    $map = [];
    foreach ($this->loadRuleSets() as $rule_set) {
      foreach (array_keys($this->getContextAwareRules($rule_set)) as $uuid) {
        if (!isset($map[$uuid])) {
          $map[$uuid] = (string) $rule_set->id();
        }
      }
    }
    return $map;
  }

  /**
   * Stores the rule UUID map until rule sets or modules change.
   *
   * @param array<string, string> $map
   *   Rule-set IDs keyed by rule UUID.
   */
  private function setCachedRuleSetIdMap(array $map): void {
    $this->cache->set(
      self::RULE_SET_ID_MAP_CID,
      $map,
      CacheBackendInterface::CACHE_PERMANENT,
      $this->ruleSetMapCacheTags(),
    );
  }

  /**
   * Cache tags that invalidate the rule UUID map.
   *
   * @return string[]
   *   Tags for rule-set list changes and enabled modules.
   */
  private function ruleSetMapCacheTags(): array {
    $tags = ['config:core.extension'];
    if ($this->entityTypeManager->hasDefinition(self::RULE_SET_ENTITY_TYPE)) {
      $tags = array_merge(
        $tags,
        $this->entityTypeManager
          ->getDefinition(self::RULE_SET_ENTITY_TYPE)
          ->getListCacheTags(),
      );
    }
    return $tags;
  }

  /**
   * Drops non-string UUID and rule-set ID pairs from cached data.
   *
   * @param array<mixed, mixed> $data
   *   Raw cache data.
   *
   * @return array<string, string>
   *   Rule-set IDs keyed by rule UUID.
   */
  private function sanitizeRuleSetIdMap(array $data): array {
    $map = [];
    foreach ($data as $uuid => $id) {
      if (!is_string($uuid) || $uuid === '' || !is_string($id) || $id === '') {
        continue;
      }
      $map[$uuid] = $id;
    }
    return $map;
  }

  /**
   * Loads all field validation rule sets.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface[]
   *   Rule sets keyed by ID.
   */
  private function loadRuleSets(): array {
    if (!$this->entityTypeManager->hasDefinition(self::RULE_SET_ENTITY_TYPE)) {
      return [];
    }

    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface[] */
    return $this->entityTypeManager
      ->getStorage(self::RULE_SET_ENTITY_TYPE)
      ->loadMultiple();
  }

  /**
   * Returns the stored rules on a rule set that can receive context.
   *
   * Reads the stored rule configuration instead of instantiating the
   * rule plugins, so a rule whose plugin is gone is skipped instead of
   * breaking the listing.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $ruleSet
   *   The field validation rule set.
   *
   * @return array<string, array<string, mixed>>
   *   Stored rule configuration keyed by rule UUID.
   */
  private function getContextAwareRules(ConfigEntityInterface $ruleSet): array {
    $rules = $ruleSet->get('field_validation_rules');
    if (!is_array($rules)) {
      return [];
    }

    $aware = [];
    foreach ($rules as $key => $rule) {
      if (!is_array($rule) || !isset($rule['id']) || !is_string($rule['id'])) {
        continue;
      }
      $uuid = $rule['uuid'] ?? $key;
      if (!is_string($uuid) || $uuid === '') {
        continue;
      }
      if (!$this->isContextAwarePlugin($rule['id'])) {
        continue;
      }
      $aware[$uuid] = $rule;
    }

    return $aware;
  }

  /**
   * Whether a rule plugin runs a chat prompt that can receive context.
   *
   * @param string $pluginId
   *   The field validation rule plugin ID.
   *
   * @return bool
   *   TRUE when the plugin class implements the context-aware interface.
   */
  private function isContextAwarePlugin(string $pluginId): bool {
    $definition = $this->ruleManager->getDefinition($pluginId, FALSE);
    $class = is_array($definition) ? ($definition['class'] ?? NULL) : NULL;
    return is_string($class)
      && is_subclass_of($class, AiContextAwareFieldValidationRuleInterface::class);
  }

  /**
   * Returns the label of a field validation rule plugin.
   *
   * @param string $pluginId
   *   The field validation rule plugin ID.
   *
   * @return string
   *   The plugin label, or the plugin ID when it has none.
   */
  private function getRulePluginLabel(string $pluginId): string {
    $definition = $this->ruleManager->getDefinition($pluginId, FALSE);
    $label = is_array($definition) ? trim((string) ($definition['label'] ?? '')) : '';
    return $label !== '' ? $label : $pluginId;
  }

  /**
   * Builds a consumer description that names the rule type and field.
   *
   * @param string $pluginLabel
   *   The rule plugin label.
   * @param string $fieldName
   *   The field the rule validates.
   * @param string $ruleSetLabel
   *   The rule set label.
   *
   * @return string
   *   The description.
   */
  private function buildInstanceDescription(
    string $pluginLabel,
    string $fieldName,
    string $ruleSetLabel,
  ): string {
    // Replace the placeholders without HTML escaping. The consumers
    // listing escapes the full description; t() placeholders would
    // double escape labels.
    if ($fieldName === '') {
      return strtr((string) $this->t('@type in the "@set" rule set.'), [
        '@type' => $pluginLabel,
        '@set' => $ruleSetLabel,
      ]);
    }
    return strtr((string) $this->t('@type on the @field field in the "@set" rule set.'), [
      '@type' => $pluginLabel,
      '@field' => $fieldName,
      '@set' => $ruleSetLabel,
    ]);
  }

}
