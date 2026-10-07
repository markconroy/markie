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
 * Permanently deletes an ai_automator config entity.
 */
#[Tool(
  id: 'ai_automator:delete_automator',
  label: new TranslatableMarkup('Delete AI Automator'),
  description: new TranslatableMarkup('Permanently deletes an ai_automator config entity. This action cannot be undone. The underlying Drupal field and its data are not touched; only the automation config is removed. Identify it either by automator_id, or by the entity_type/bundle/field_name trio.'),
  operation: ToolOperation::Write,
  destructive: TRUE,
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
    'deleted_id' => new ContextDefinition(data_type: 'string', label: new TranslatableMarkup('Deleted automator ID'), required: FALSE),
  ],
)]
class DeleteAutomator extends ToolBase {

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

    $storage = $this->entityTypeManager->getStorage('ai_automator');
    $entity = $storage->load($id);
    if ($entity === NULL) {
      return ExecutableResult::failure(
        new TranslatableMarkup('Automator "@id" not found. Run ai_automator:list_automators to see configured automators.', ['@id' => $id]),
      );
    }

    $label = $entity->label();
    $fieldName = $entity->get('field_name');
    $entity->delete();

    return ExecutableResult::success(
      new TranslatableMarkup('Automator "@id" ("@label") on field "@field" has been deleted. The field and its data are untouched; only the automation config was removed.', [
        '@id' => $id,
        '@label' => $label,
        '@field' => $fieldName,
      ]),
      ['deleted_id' => $id],
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
