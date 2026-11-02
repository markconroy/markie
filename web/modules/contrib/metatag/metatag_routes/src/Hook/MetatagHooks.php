<?php

namespace Drupal\metatag_routes\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\metatag_routes\Helper\MetatagRoutesHelperInterface;

/**
 * Hook implementations for metatag_routes.
 */
class MetatagHooks {

  /**
   * The metatag routes helper.
   *
   * @var \Drupal\metatag_routes\Helper\MetatagRoutesHelperInterface
   */
  protected readonly MetatagRoutesHelperInterface $metatagRoutesHelper;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected readonly EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new MetatagHooks object.
   *
   * @param \Drupal\metatag_routes\Helper\MetatagRoutesHelperInterface $metatag_routes_helper
   *   The metatag routes helper.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    MetatagRoutesHelperInterface $metatag_routes_helper,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->metatagRoutesHelper = $metatag_routes_helper;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Implements hook_metatags_alter().
   */
  #[Hook('metatags_alter')]
  public function metatagsAlter(array &$metatags, array $context) {
    // Ignore some system routes that are not appropriate for meta tags.
    if (metatag_is_current_route_supported()) {
      // Look to see if a configuration was assigned for this route.
      $current_route = $this->metatagRoutesHelper->getCurrentMetatagRouteId();
      if (!empty($current_route)) {
        $defaults = $this->entityTypeManager->getStorage('metatag_defaults')->load($current_route);
        if (!empty($defaults)) {
          $tags = $defaults->get('tags');
          // Replace the new values and keep on the global values.
          $metatags = array_merge($metatags, $tags);
        }
      }
    }
  }

}
