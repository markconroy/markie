<?php

namespace Drupal\entity_usage\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local task definitions for all entity types.
 */
class EntityUsageLocalTask extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Creates an EntityUsageLocalTask object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config) {
    $this->entityTypeManager = $entity_type_manager;
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];
    $configured_types = $this->config->get('entity_usage.settings')->get('local_task_enabled_entity_types') ?: [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      // We prefer the canonical template, but we also allow edit-form templates
      // on entities that don't have canonical (like views, etc).
      if ($entity_type->hasLinkTemplate('canonical')) {
        $template_key = 'canonical';
      }
      elseif ($entity_type->hasLinkTemplate('edit-form')) {
        $template_key = 'edit_form';
      }
      if (!empty($template_key)) {
        if (!in_array($entity_type_id, $configured_types, TRUE)) {
          continue;
        }
        $this->derivatives["$entity_type_id.entity_usage"] = [
          'route_name' => "entity.$entity_type_id.entity_usage",
          'title' => $this->t('Usage'),
          'base_route' => "entity.$entity_type_id.$template_key",
          'weight' => 99,
        ];
      }
    }

    foreach ($this->derivatives as &$entry) {
      $entry += $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
