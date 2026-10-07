<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists configured ai_automator entities, optionally filtered.
 */
#[Tool(
  id: 'ai_automator:list_automators',
  label: new TranslatableMarkup('List AI Automators'),
  description: new TranslatableMarkup('Lists configured ai_automator entities as compact summaries, optionally filtered by entity_type, bundle, and/or field_name. Use ai_automator:get_automator to read full details (including prompt/token/plugin_config) for a specific one.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity type'),
      description: new TranslatableMarkup('Optional filter: only automators on this host entity type.'),
      required: FALSE,
      default_value: '',
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('Optional filter: only automators on this bundle.'),
      required: FALSE,
      default_value: '',
    ),
    'field_name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field name'),
      description: new TranslatableMarkup('Optional filter: only automators on this field name.'),
      required: FALSE,
      default_value: '',
    ),
  ],
  output_definitions: [
    'automators' => new ContextDefinition(data_type: 'any', label: new TranslatableMarkup('Automators'), required: FALSE),
  ],
)]
class ListAutomators extends ToolBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $filters = array_filter([
      'entity_type' => (string) $values['entity_type'],
      'bundle' => (string) $values['bundle'],
      'field_name' => (string) $values['field_name'],
    ], static fn ($value) => $value !== '');

    $storage = $this->entityTypeManager->getStorage('ai_automator');
    $entities = $filters ? $storage->loadByProperties($filters) : $storage->loadMultiple();

    $automators = [];
    foreach ($entities as $entity) {
      $automators[] = [
        'id' => $entity->id(),
        'label' => $entity->label(),
        'rule' => $entity->get('rule'),
        'entity_type' => $entity->get('entity_type'),
        'bundle' => $entity->get('bundle'),
        'field_name' => $entity->get('field_name'),
        'worker_type' => $entity->get('worker_type'),
        'input_mode' => $entity->get('input_mode'),
        'weight' => $entity->get('weight'),
        'edit_mode' => $entity->get('edit_mode'),
        'status' => $entity->status(),
      ];
    }

    return ExecutableResult::success(
      new TranslatableMarkup('@count automator(s) found: @json. Run ai_automator:get_automator for full details.', [
        '@count' => count($automators),
        '@json' => json_encode($automators, JSON_PRETTY_PRINT),
      ]),
      ['automators' => $automators],
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
