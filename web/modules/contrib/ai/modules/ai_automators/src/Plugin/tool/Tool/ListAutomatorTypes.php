<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\PluginManager\AiAutomatorTypeManager;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists available AiAutomatorType plugins, or inspects one in detail.
 */
#[Tool(
  id: 'ai_automator:list_automator_types',
  label: new TranslatableMarkup('List AI Automator Types'),
  description: new TranslatableMarkup('Lists all available AiAutomatorType plugins registered with the automator type plugin manager, including their IDs, labels, target field types, and descriptions. Pass "type" to inspect a single plugin in full configuration detail instead of listing the whole catalog. This does not create new automator type plugin classes; use the create-automator-type skill for that.'),
  operation: ToolOperation::Explain,
  input_definitions: [
    'type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Automator type ID'),
      description: new TranslatableMarkup('Optional. The automator type plugin ID to inspect in full detail (e.g. "summarize_to_text_long"). Omit (empty string) to list the full catalog.'),
      required: FALSE,
      default_value: '',
    ),
  ],
  output_definitions: [
    'types' => new ContextDefinition(data_type: 'any', label: new TranslatableMarkup('Automator types'), required: FALSE),
  ],
)]
class ListAutomatorTypes extends ToolBase {

  /**
   * The automator type plugin manager.
   *
   * @var \Drupal\ai_automators\PluginManager\AiAutomatorTypeManager
   */
  protected AiAutomatorTypeManager $automatorTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->automatorTypeManager = $container->get('plugin.manager.ai_automator');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $type = (string) $values['type'];
    $definitions = $this->automatorTypeManager->getDefinitions();

    if ($type !== '') {
      if (!isset($definitions[$type])) {
        $available = implode(', ', array_keys($definitions));
        return ExecutableResult::failure(
          new TranslatableMarkup('Unknown automator type "@type". Available types: @list', [
            '@type' => $type,
            '@list' => $available ?: '(none)',
          ]),
        );
      }

      $instance = $this->automatorTypeManager->createInstance($type);
      $data = [
        'id' => $type,
        'label' => (string) ($definitions[$type]['label'] ?? $type),
        'field_rule' => (string) ($definitions[$type]['field_rule'] ?? ''),
        'target' => (string) ($definitions[$type]['target'] ?? ''),
        'description' => (string) $instance->helpText(),
        'needs_prompt' => (bool) $instance->needsPrompt(),
        'advanced_mode' => (bool) $instance->advancedMode(),
        'allowed_inputs' => $instance->allowedInputs(),
        'placeholder_text' => (string) $instance->placeholderText(),
        'tokens_hint' => (string) new TranslatableMarkup('Run ai_automator:list_tokens with entity_type, bundle, field_name, and rule="@type" to see the actual replacement tokens for a real field context.', ['@type' => $type]),
      ];

      return ExecutableResult::success(
        new TranslatableMarkup('Automator type "@type": @json', [
          '@type' => $type,
          '@json' => json_encode($data, JSON_PRETTY_PRINT),
        ]),
        ['types' => $data],
      );
    }

    $types = [];
    foreach ($definitions as $id => $definition) {
      $description = '';
      try {
        $description = (string) $this->automatorTypeManager->createInstance($id)->helpText();
      }
      catch (\Throwable) {
        // Leave description empty if the plugin can't be instantiated.
      }
      $types[] = [
        'id' => $id,
        'label' => (string) ($definition['label'] ?? $id),
        'field_rule' => (string) ($definition['field_rule'] ?? ''),
        'target' => (string) ($definition['target'] ?? ''),
        'description' => $description,
      ];
    }

    return ExecutableResult::success(
      new TranslatableMarkup('@count automator type(s) available: @json', [
        '@count' => count($types),
        '@json' => json_encode($types, JSON_PRETTY_PRINT),
      ]),
      ['types' => $types],
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
