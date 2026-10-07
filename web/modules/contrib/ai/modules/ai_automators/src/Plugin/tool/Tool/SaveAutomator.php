<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\AiFieldRules;
use Drupal\ai_automators\PluginBaseClasses\RuleBase;
use Drupal\ai_automators\PluginManager\AiAutomatorFieldProcessManager;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates or updates an ai_automator config entity on a field.
 */
#[Tool(
  id: 'ai_automator:save_automator',
  label: new TranslatableMarkup('Save AI Automator'),
  description: new TranslatableMarkup('Creates or updates an ai_automator config entity on entity_type/bundle/field_name. The entity ID is always computed as "<entity_type>.<bundle>.<field_name>.default", never supplied directly. When creating, rule is required (plus base_field+prompt for base mode, or token for token mode). When updating, only supplied fields change; plugin_config keys not implied by a supplied field are left untouched. plugin_config_extra is a JSON-encoded object string of additional automator_*-prefixed plugin_config keys (e.g. "{\"automator_ai_provider\":\"openai\",\"automator_ai_model\":\"gpt-4o\"}").'),
  operation: ToolOperation::Write,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity type'),
      description: new TranslatableMarkup('The host entity type ID, e.g. "node".'),
      required: TRUE,
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('The host bundle, e.g. "article".'),
      required: TRUE,
    ),
    'field_name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field name'),
      description: new TranslatableMarkup('The field machine name, e.g. "field_summary".'),
      required: TRUE,
    ),
    'label' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Label'),
      description: new TranslatableMarkup('Human-readable name. Defaults to "<field label> Default" when creating. Omit (empty string) when updating to leave unchanged.'),
      required: FALSE,
      default_value: '',
    ),
    'rule' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Automator type ID'),
      description: new TranslatableMarkup('The automator type plugin ID (e.g. "summarize_to_text_long"). Required when creating; optional when updating. Must be compatible with the field type. Run ai_automator:list_automator_types to discover valid IDs.'),
      required: FALSE,
      default_value: '',
    ),
    'input_mode' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Input mode'),
      description: new TranslatableMarkup('Either "base" (Twig prompt) or "token" (Token-module string). Defaults to "base" when creating. Omit (empty string) when updating to leave unchanged.'),
      required: FALSE,
      default_value: '',
    ),
    'weight' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Weight'),
      description: new TranslatableMarkup('Processing weight. Defaults to 100 when creating. Omit when updating to leave unchanged.'),
      required: FALSE,
    ),
    'worker_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Worker type'),
      description: new TranslatableMarkup('One of "direct", "queue", "batch", "action", "field_widget_actions". Defaults to "direct" when creating. Omit (empty string) when updating to leave unchanged.'),
      required: FALSE,
      default_value: '',
    ),
    'edit_mode' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Edit mode'),
      description: new TranslatableMarkup('If TRUE, overwrite existing field values on re-run. Defaults to FALSE when creating. Omit when updating to leave unchanged.'),
      required: FALSE,
    ),
    'base_field' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Base field'),
      description: new TranslatableMarkup('The field the rule reads its context from. Required (with prompt) for rules that need a prompt in base input mode. Omit (empty string) when updating to leave unchanged.'),
      required: FALSE,
      default_value: '',
    ),
    'prompt' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Prompt'),
      description: new TranslatableMarkup('Twig prompt for input_mode=base, e.g. "Summarize: {{ context }}". Omit (empty string) when updating to leave unchanged.'),
      required: FALSE,
      default_value: '',
    ),
    'token' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Token'),
      description: new TranslatableMarkup('Token-module prompt string for input_mode=token. Omit (empty string) when updating to leave unchanged.'),
      required: FALSE,
      default_value: '',
    ),
    'guardrail_set_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Guardrail set ID'),
      description: new TranslatableMarkup('An ai_guardrail_set entity ID to evaluate this automator against. Pass "none" to explicitly clear it. Omit (empty string) to leave unchanged.'),
      required: FALSE,
      default_value: '',
    ),
    'status' => new InputDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Status'),
      description: new TranslatableMarkup('Whether the automator is enabled. Defaults to TRUE when creating. Omit when updating to leave unchanged.'),
      required: FALSE,
    ),
    'plugin_config_extra' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Plugin config extra (JSON)'),
      description: new TranslatableMarkup('JSON-encoded object string of additional automator_*-prefixed plugin_config keys not covered above (e.g. "{\"automator_ai_provider\":\"openai\",\"automator_ai_model\":\"gpt-4o\"}"). Every key must start with "automator_". Omit (empty string) to leave other plugin_config keys untouched.'),
      required: FALSE,
      default_value: '',
    ),
  ],
  output_definitions: [
    'id' => new ContextDefinition(data_type: 'string', label: new TranslatableMarkup('Automator ID'), required: FALSE),
    'label' => new ContextDefinition(data_type: 'string', label: new TranslatableMarkup('Label'), required: FALSE),
    'rule' => new ContextDefinition(data_type: 'string', label: new TranslatableMarkup('Automator type ID'), required: FALSE),
    'input_mode' => new ContextDefinition(data_type: 'string', label: new TranslatableMarkup('Input mode'), required: FALSE),
    'weight' => new ContextDefinition(data_type: 'integer', label: new TranslatableMarkup('Weight'), required: FALSE),
    'worker_type' => new ContextDefinition(data_type: 'string', label: new TranslatableMarkup('Worker type'), required: FALSE),
    'entity_type' => new ContextDefinition(data_type: 'string', label: new TranslatableMarkup('Entity type'), required: FALSE),
    'bundle' => new ContextDefinition(data_type: 'string', label: new TranslatableMarkup('Bundle'), required: FALSE),
    'field_name' => new ContextDefinition(data_type: 'string', label: new TranslatableMarkup('Field name'), required: FALSE),
    'edit_mode' => new ContextDefinition(data_type: 'boolean', label: new TranslatableMarkup('Edit mode'), required: FALSE),
    'base_field' => new ContextDefinition(data_type: 'string', label: new TranslatableMarkup('Base field'), required: FALSE),
    'prompt' => new ContextDefinition(data_type: 'string', label: new TranslatableMarkup('Prompt'), required: FALSE),
    'token' => new ContextDefinition(data_type: 'string', label: new TranslatableMarkup('Token'), required: FALSE),
    'guardrail_set_id' => new ContextDefinition(data_type: 'string', label: new TranslatableMarkup('Guardrail set ID'), required: FALSE),
    'plugin_config' => new ContextDefinition(data_type: 'any', label: new TranslatableMarkup('Plugin config'), required: FALSE),
    'status' => new ContextDefinition(data_type: 'boolean', label: new TranslatableMarkup('Status'), required: FALSE),
    'warnings' => new ContextDefinition(data_type: 'any', label: new TranslatableMarkup('Warnings'), required: FALSE),
  ],
)]
class SaveAutomator extends ToolBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The automator field rules helper.
   *
   * @var \Drupal\ai_automators\AiFieldRules
   */
  protected AiFieldRules $fieldRules;

  /**
   * The automator process (worker_type) plugin manager.
   *
   * @var \Drupal\ai_automators\PluginManager\AiAutomatorFieldProcessManager
   */
  protected AiAutomatorFieldProcessManager $processManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->fieldRules = $container->get('ai_automator.field_rules');
    $instance->processManager = $container->get('plugin.manager.ai_processor');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $entityType = (string) $values['entity_type'];
    $bundle = (string) $values['bundle'];
    $fieldName = (string) $values['field_name'];
    $label = (string) $values['label'];
    $rule = (string) $values['rule'];
    $inputMode = (string) $values['input_mode'];
    $weight = $values['weight'];
    $workerType = (string) $values['worker_type'];
    $editMode = $values['edit_mode'];
    $baseField = (string) $values['base_field'];
    $prompt = (string) $values['prompt'];
    $token = (string) $values['token'];
    $guardrailSetId = (string) $values['guardrail_set_id'];
    $status = $values['status'];
    $pluginConfigExtraRaw = (string) $values['plugin_config_extra'];

    if (!$this->entityTypeManager->hasDefinition($entityType)) {
      return ExecutableResult::failure(
        new TranslatableMarkup('Unknown entity type "@type".', ['@type' => $entityType]),
      );
    }
    $entityTypeDefinition = $this->entityTypeManager->getDefinition($entityType);
    if (!$entityTypeDefinition->entityClassImplements(ContentEntityInterface::class)) {
      return ExecutableResult::failure(
        new TranslatableMarkup('"@type" is not a content entity type; automators can only target content entity fields.', ['@type' => $entityType]),
      );
    }

    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle);
    if (!isset($fieldDefinitions[$fieldName])) {
      return ExecutableResult::failure(
        new TranslatableMarkup('Field "@field" does not exist on @entity_type/@bundle.', [
          '@field' => $fieldName,
          '@entity_type' => $entityType,
          '@bundle' => $bundle,
        ]),
      );
    }
    $fieldDefinition = $fieldDefinitions[$fieldName];

    $pluginConfigExtra = [];
    if ($pluginConfigExtraRaw !== '') {
      $decoded = json_decode($pluginConfigExtraRaw, TRUE);
      if (!is_array($decoded)) {
        return ExecutableResult::failure(
          new TranslatableMarkup('plugin_config_extra must be a valid JSON object string. Received: @raw', ['@raw' => $pluginConfigExtraRaw]),
        );
      }
      foreach (array_keys($decoded) as $key) {
        if (!str_starts_with((string) $key, 'automator_')) {
          return ExecutableResult::failure(
            new TranslatableMarkup('plugin_config_extra keys must start with "automator_". Invalid key: @key', ['@key' => $key]),
          );
        }
      }
      $pluginConfigExtra = $decoded;
    }

    $id = sprintf('%s.%s.%s.default', $entityType, $bundle, $fieldName);
    $storage = $this->entityTypeManager->getStorage('ai_automator');
    $entity = $storage->load($id);
    $isNew = $entity === NULL;

    $bundleKey = $entityTypeDefinition->getKey('bundle');
    $sampleEntity = $this->entityTypeManager->getStorage($entityType)->create($bundleKey ? [$bundleKey => $bundle] : []);
    $candidates = $this->fieldRules->findRuleCandidates($sampleEntity, $fieldDefinition);

    $effectiveRule = $rule !== '' ? $rule : ($isNew ? '' : (string) $entity->get('rule'));

    if ($rule !== '' && !isset($candidates[$rule])) {
      $available = implode(', ', array_keys($candidates));
      return ExecutableResult::failure(
        new TranslatableMarkup('Automator type "@rule" is not compatible with field "@field" (type @field_type). Compatible types: @list', [
          '@rule' => $rule,
          '@field' => $fieldName,
          '@field_type' => $fieldDefinition->getType(),
          '@list' => $available ?: '(none)',
        ]),
      );
    }

    if ($isNew && $effectiveRule === '') {
      $available = implode(', ', array_keys($candidates));
      return ExecutableResult::failure(
        new TranslatableMarkup('rule is required when creating a new automator. Compatible types for field "@field": @list', [
          '@field' => $fieldName,
          '@list' => $available ?: '(none)',
        ]),
      );
    }

    $ruleInstance = $candidates[$effectiveRule] ?? $this->fieldRules->findRule($effectiveRule);
    if ($ruleInstance === NULL) {
      return ExecutableResult::failure(
        new TranslatableMarkup('Unknown automator type "@rule".', ['@rule' => $effectiveRule]),
      );
    }

    $effectiveLabel = $label !== '' ? $label : ($isNew ? ($fieldDefinition->getLabel() . ' Default') : (string) $entity->label());
    $effectiveInputMode = $inputMode !== '' ? $inputMode : ($isNew ? 'base' : (string) $entity->get('input_mode'));
    if (!in_array($effectiveInputMode, ['base', 'token'], TRUE)) {
      return ExecutableResult::failure(
        new TranslatableMarkup('input_mode must be either "base" or "token". Received: @mode', ['@mode' => $effectiveInputMode]),
      );
    }
    $effectiveWeight = $weight ?? ($isNew ? 100 : (int) $entity->get('weight'));
    $effectiveWorkerType = $workerType !== '' ? $workerType : ($isNew ? 'direct' : (string) $entity->get('worker_type'));
    $processDefinitions = $this->processManager->getDefinitions();
    if (!isset($processDefinitions[$effectiveWorkerType])) {
      return ExecutableResult::failure(
        new TranslatableMarkup('Unknown worker_type "@type". Available: @list', [
          '@type' => $effectiveWorkerType,
          '@list' => implode(', ', array_keys($processDefinitions)),
        ]),
      );
    }
    $effectiveEditMode = $editMode ?? ($isNew ? FALSE : (bool) $entity->get('edit_mode'));
    $effectiveBaseField = $baseField !== '' ? $baseField : ($isNew ? '' : (string) $entity->get('base_field'));
    $effectivePrompt = $prompt !== '' ? $prompt : ($isNew ? '' : (string) $entity->get('prompt'));
    $effectiveToken = $token !== '' ? $token : ($isNew ? '' : (string) $entity->get('token'));
    $effectiveStatus = $status ?? ($isNew ? TRUE : $entity->status());

    if ($ruleInstance->needsPrompt() && $effectiveInputMode === 'base') {
      if ($effectiveBaseField === '') {
        return ExecutableResult::failure(
          new TranslatableMarkup('base_field is required for rule "@rule" in base input mode.', ['@rule' => $effectiveRule]),
        );
      }
      if ($effectivePrompt === '') {
        return ExecutableResult::failure(
          new TranslatableMarkup('prompt is required for rule "@rule" in base input mode.', ['@rule' => $effectiveRule]),
        );
      }
    }
    if ($effectiveInputMode === 'token' && $effectiveToken === '') {
      return ExecutableResult::failure(
        new TranslatableMarkup('token is required when input_mode is "token".'),
      );
    }

    $effectiveGuardrailSetId = NULL;
    if ($guardrailSetId === 'none') {
      $effectiveGuardrailSetId = NULL;
    }
    elseif ($guardrailSetId !== '') {
      if ($this->entityTypeManager->getStorage('ai_guardrail_set')->load($guardrailSetId) === NULL) {
        return ExecutableResult::failure(
          new TranslatableMarkup('Guardrail set "@id" does not exist. Run ai_guardrail:list_guardrail_sets to see configured sets.', ['@id' => $guardrailSetId]),
        );
      }
      $effectiveGuardrailSetId = $guardrailSetId;
    }
    elseif (!$isNew) {
      $effectiveGuardrailSetId = $entity->get('guardrail_set_id');
    }

    if ($isNew) {
      $entity = $storage->create([
        'id' => $id,
        'entity_type' => $entityType,
        'bundle' => $bundle,
        'field_name' => $fieldName,
      ]);
    }

    $entity->set('label', $effectiveLabel);
    $entity->set('rule', $effectiveRule);
    $entity->set('input_mode', $effectiveInputMode);
    $entity->set('weight', $effectiveWeight);
    $entity->set('worker_type', $effectiveWorkerType);
    $entity->set('edit_mode', $effectiveEditMode);
    $entity->set('base_field', $effectiveBaseField);
    $entity->set('prompt', $effectivePrompt);
    $entity->set('token', $effectiveToken);
    $entity->set('guardrail_set_id', $effectiveGuardrailSetId);
    if ($effectiveStatus) {
      $entity->enable();
    }
    else {
      $entity->disable();
    }

    // Rebuild plugin_config from the *existing* bag on update so unrelated
    // automator_*-prefixed keys (provider, model, rule-specific settings)
    // survive when the caller only changes e.g. the prompt.
    $pluginConfig = $isNew ? [] : ((array) $entity->get('plugin_config'));
    $pluginConfig['automator_enabled'] = $effectiveStatus ? 1 : 0;
    $pluginConfig['automator_rule'] = $effectiveRule;
    $pluginConfig['automator_mode'] = $effectiveInputMode;
    $pluginConfig['automator_base_field'] = $effectiveBaseField;
    $pluginConfig['automator_prompt'] = $effectivePrompt;
    $pluginConfig['automator_token'] = $effectiveToken;
    $pluginConfig['automator_edit_mode'] = $effectiveEditMode ? 1 : 0;
    $pluginConfig['automator_label'] = $effectiveLabel;
    $pluginConfig['automator_weight'] = (string) $effectiveWeight;
    $pluginConfig['automator_worker_type'] = $effectiveWorkerType;
    $pluginConfig['automator_guardrail_set_id'] = $effectiveGuardrailSetId ?? '';
    // Explicit extras always win over the mirrored values above.
    $pluginConfig = array_replace($pluginConfig, $pluginConfigExtra);
    $entity->set('plugin_config', $pluginConfig);

    $result = $entity->save();
    $action = $result === SAVED_NEW ? 'Created' : 'Updated';

    $warnings = [];
    if ($ruleInstance instanceof RuleBase && empty($pluginConfig['automator_ai_provider'])) {
      $warnings[] = (string) new TranslatableMarkup('No automator_ai_provider is set in plugin_config; this automator will fail at runtime until one is configured (pass it via plugin_config_extra, e.g. {"automator_ai_provider":"openai","automator_ai_model":"gpt-4o"}).');
    }

    $data = [
      'id' => $entity->id(),
      'label' => $entity->label(),
      'rule' => $entity->get('rule'),
      'input_mode' => $entity->get('input_mode'),
      'weight' => $entity->get('weight'),
      'worker_type' => $entity->get('worker_type'),
      'entity_type' => $entity->get('entity_type'),
      'bundle' => $entity->get('bundle'),
      'field_name' => $entity->get('field_name'),
      'edit_mode' => $entity->get('edit_mode'),
      'base_field' => $entity->get('base_field') ?? '',
      'prompt' => $entity->get('prompt') ?? '',
      'token' => $entity->get('token') ?? '',
      'guardrail_set_id' => $entity->get('guardrail_set_id') ?? '',
      'plugin_config' => $entity->get('plugin_config') ?? [],
      'status' => $entity->status(),
      'warnings' => $warnings,
    ];

    return ExecutableResult::success(
      new TranslatableMarkup('@action automator "@id": @json', [
        '@action' => $action,
        '@id' => $entity->id(),
        '@json' => json_encode($data, JSON_PRETTY_PRINT),
      ]),
      $data,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $result = AccessResult::allowedIfHasPermission($account, 'administer ai_automator');
    return $return_as_object ? $result : $result->isAllowed();
  }

}
