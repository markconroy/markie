<?php

namespace Drupal\ai_automators\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines dynamic local tasks.
 */
class ChainLocalTasks extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates a Automator Chain derivite object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {

    $entity_definitions = $this->entityTypeManager->getDefinitions();
    foreach ($entity_definitions as $entity_type_id => $entity_type) {
      if (!$entity_type->entityClassImplements('Drupal\Core\Config\Entity\ConfigEntityInterface')) {
        continue;
      }
      if (!$entity_type->getBundleOf()) {
        continue;
      }
      $this->derivatives["entity.$entity_type_id.automator_chain"] = $base_plugin_definition;
      $this->derivatives["entity.$entity_type_id.automator_chain"]['title'] = $this->t("Automator chain");
      $this->derivatives["entity.$entity_type_id.automator_chain"]['route_name'] = 'ai_automator.config_chain.' . $entity_type->getBundleOf();
      $this->derivatives["entity.$entity_type_id.automator_chain"]['base_route'] = "entity.$entity_type_id.edit_form";
      $this->derivatives["entity.$entity_type_id.automator_chain"]['weight'] = 100;
    }
    return $this->derivatives;
  }

}
