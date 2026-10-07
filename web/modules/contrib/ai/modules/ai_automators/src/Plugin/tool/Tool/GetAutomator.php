<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Traits\AutomatorToolIdentifierTrait;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns full details of one ai_automator config entity.
 */
#[Tool(
  id: 'ai_automator:get_automator',
  label: new TranslatableMarkup('Get AI Automator'),
  description: new TranslatableMarkup('Returns full details of one ai_automator config entity, including its prompt/token, worker type, and plugin_config. Identify it either by automator_id, or by the entity_type/bundle/field_name trio.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'automator_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Automator ID'),
      description: new TranslatableMarkup('The computed ai_automator entity ID, e.g. "node.article.field_summary.default". Omit if supplying entity_type/bundle/field_name instead.'),
      required: FALSE,
      default_value: '',
    ),
    'entity_type' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity type'),
      description: new TranslatableMarkup('The host entity type ID. Used with bundle and field_name as an alternative to automator_id.'),
      required: FALSE,
      default_value: '',
    ),
    'bundle' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Bundle'),
      description: new TranslatableMarkup('The host bundle. Used with entity_type and field_name as an alternative to automator_id.'),
      required: FALSE,
      default_value: '',
    ),
    'field_name' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field name'),
      description: new TranslatableMarkup('The field machine name. Used with entity_type and bundle as an alternative to automator_id.'),
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
  ],
)]
class GetAutomator extends ToolBase {

  use AutomatorToolIdentifierTrait;

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
    $id = $this->resolveAutomatorId($values);
    if ($id instanceof ExecutableResult) {
      return $id;
    }

    $entity = $this->entityTypeManager->getStorage('ai_automator')->load($id);
    if ($entity === NULL) {
      return ExecutableResult::failure(
        new TranslatableMarkup('Automator "@id" not found. Run ai_automator:list_automators to see configured automators.', ['@id' => $id]),
      );
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
    ];

    return ExecutableResult::success(
      new TranslatableMarkup('Automator "@id": @json', [
        '@id' => $id,
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
