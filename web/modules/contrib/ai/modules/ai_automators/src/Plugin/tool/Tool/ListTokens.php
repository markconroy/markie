<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Token;
use Drupal\ai_automators\AiFieldRules;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists replacement tokens available for an automator's field context.
 */
#[Tool(
  id: 'ai_automator:list_tokens',
  label: new TranslatableMarkup('List AI Automator Tokens'),
  description: new TranslatableMarkup('Lists the replacement tokens available for a field/entity context. In "base" mode these are the Twig placeholders (e.g. {{ context }}) an automator type plugin exposes for its prompt; in "token" mode these are core Token module placeholders (e.g. [node:title]) for use with input_mode=token.'),
  operation: ToolOperation::Explain,
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
    'rule' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Automator type ID'),
      description: new TranslatableMarkup('Optional. The automator type plugin ID whose tokens to read, for mode=base. If omitted, the rule from the existing ai_automator config on this field is used. Run ai_automator:list_automator_types to discover valid IDs.'),
      required: FALSE,
      default_value: '',
    ),
    'mode' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Mode'),
      description: new TranslatableMarkup('Either "base" (Twig placeholders from the rule, default) or "token" (core Token module placeholders for the entity type).'),
      required: FALSE,
      default_value: 'base',
    ),
  ],
  output_definitions: [
    'tokens' => new ContextDefinition(data_type: 'any', label: new TranslatableMarkup('Tokens'), required: FALSE),
    'mode' => new ContextDefinition(data_type: 'string', label: new TranslatableMarkup('Mode'), required: FALSE),
    'rule' => new ContextDefinition(data_type: 'string', label: new TranslatableMarkup('Automator type ID'), required: FALSE),
    'token_type' => new ContextDefinition(data_type: 'string', label: new TranslatableMarkup('Token type'), required: FALSE),
  ],
)]
class ListTokens extends ToolBase {

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
   * The core token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->fieldRules = $container->get('ai_automator.field_rules');
    $instance->token = $container->get('token');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $entityType = (string) $values['entity_type'];
    $bundle = (string) $values['bundle'];
    $fieldName = (string) $values['field_name'];
    $rule = (string) $values['rule'];
    $mode = (string) $values['mode'];
    if ($mode === '') {
      $mode = 'base';
    }
    if (!in_array($mode, ['base', 'token'], TRUE)) {
      return ExecutableResult::failure(
        new TranslatableMarkup('mode must be either "base" or "token". Received: @mode', ['@mode' => $mode]),
      );
    }

    if (!$this->entityTypeManager->hasDefinition($entityType)) {
      return ExecutableResult::failure(
        new TranslatableMarkup('Unknown entity type "@type".', ['@type' => $entityType]),
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

    if ($mode === 'token') {
      $tokenType = $this->getEntityTokenType($entityType);
      $info = $this->token->getInfo();
      $tokenDefinitions = $info['tokens'][$tokenType] ?? [];
      $tokenMap = [];
      foreach ($tokenDefinitions as $name => $definition) {
        $tokenMap[$name] = (string) ($definition['description'] ?? ($definition['name'] ?? $name));
      }

      return ExecutableResult::success(
        new TranslatableMarkup('@count token(s) available for [@type:*]: @json', [
          '@count' => count($tokenMap),
          '@type' => $tokenType,
          '@json' => json_encode($tokenMap, JSON_PRETTY_PRINT),
        ]),
        ['tokens' => $tokenMap, 'mode' => $mode, 'rule' => '', 'token_type' => $tokenType],
      );
    }

    // Mode = base: resolve the rule to inspect.
    if ($rule === '') {
      $id = sprintf('%s.%s.%s.default', $entityType, $bundle, $fieldName);
      $existing = $this->entityTypeManager->getStorage('ai_automator')->load($id);
      if ($existing) {
        $rule = (string) $existing->get('rule');
      }
    }
    if ($rule === '') {
      return ExecutableResult::failure(
        new TranslatableMarkup('rule is required in base mode when no ai_automator is already configured for this field. Run ai_automator:list_automator_types to see available rule IDs.'),
      );
    }

    $ruleInstance = $this->fieldRules->findRule($rule);
    if ($ruleInstance === NULL) {
      return ExecutableResult::failure(
        new TranslatableMarkup('Unknown automator type "@rule". Run ai_automator:list_automator_types to see available rule IDs.', ['@rule' => $rule]),
      );
    }

    $entityTypeDefinition = $this->entityTypeManager->getDefinition($entityType);
    $bundleKey = $entityTypeDefinition->getKey('bundle');
    $sampleEntity = $this->entityTypeManager->getStorage($entityType)->create($bundleKey ? [$bundleKey => $bundle] : []);

    try {
      $tokens = $ruleInstance->tokens($sampleEntity);
    }
    catch (\Throwable $e) {
      return ExecutableResult::failure(
        new TranslatableMarkup('Could not compute tokens for rule "@rule": this rule inspects real entity data, which is unavailable from a transient entity. Try again once a real entity of this bundle exists. (@message)', [
          '@rule' => $rule,
          '@message' => $e->getMessage(),
        ]),
      );
    }

    $tokenMap = [];
    foreach ($tokens as $name => $description) {
      $tokenMap[$name] = (string) $description;
    }

    return ExecutableResult::success(
      new TranslatableMarkup('@count token(s) available for rule "@rule": @json', [
        '@count' => count($tokenMap),
        '@rule' => $rule,
        '@json' => json_encode($tokenMap, JSON_PRETTY_PRINT),
      ]),
      ['tokens' => $tokenMap, 'mode' => $mode, 'rule' => $rule, 'token_type' => ''],
    );
  }

  /**
   * Corrects the entity type ID to the token type it corresponds to.
   *
   * Mirrors AiAutomatorFieldConfig::getEntityTokenType() and
   * AiPromptHelper::getEntityTokenType(), replicated here since those
   * methods live on services this tool has no other reason to depend on.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   *
   * @return string
   *   The corrected token type.
   */
  protected function getEntityTokenType(string $entityTypeId): string {
    return $entityTypeId === 'taxonomy_term' ? 'term' : $entityTypeId;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $result = AccessResult::allowedIfHasPermission($account, 'administer ai_automator');
    return $return_as_object ? $result : $result->isAllowed();
  }

}
