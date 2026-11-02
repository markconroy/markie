<?php

namespace Drupal\metatag\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\metatag\MetatagManager;
use Drupal\taxonomy\TermInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;

/**
 * Output hook implementations for Metatag.
 */
class OutputHooks {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected readonly ModuleHandlerInterface $moduleHandler;

  /**
   * The metatag manager.
   *
   * @var \Drupal\metatag\MetatagManager
   */
  protected readonly MetatagManager $metatagManager;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected readonly RouteMatchInterface $routeMatch;

  /**
   * Constructs a new OutputHooks object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\metatag\MetatagManager $metatag_manager
   *   The metatag manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(
    ModuleHandlerInterface $module_handler,
    MetatagManager $metatag_manager,
    RouteMatchInterface $route_match,
  ) {
    $this->moduleHandler = $module_handler;
    $this->metatagManager = $metatag_manager;
    $this->routeMatch = $route_match;
  }

  /**
   * Implements hook_page_attachments().
   *
   * Load all meta tags for this page.
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments) {
    if (!metatag_is_current_route_supported()) {
      return NULL;
    }
    $metatag_attachments =& drupal_static('metatag_attachments');
    if (is_null($metatag_attachments)) {
      // Load the meta tags from the route.
      $metatag_attachments = metatag_get_tags_from_route();
    }
    if (!$metatag_attachments) {
      return NULL;
    }
    // Trigger hook_metatags_attachments_alter().
    // Allow modules to rendered metatags prior to attaching.
    $this->moduleHandler->alter('metatags_attachments', $metatag_attachments);
    // If any Metatag items were found, append them.
    if (!empty($metatag_attachments['#attached']['html_head'])) {
      if (empty($attachments['#attached'])) {
        $attachments['#attached'] = [];
      }
      if (empty($attachments['#attached']['html_head'])) {
        $attachments['#attached']['html_head'] = [];
      }
      // Work out what separator should be used in the string processing.
      $separator = $this->metatagManager->getSeparator();
      foreach ($metatag_attachments['#attached']['html_head'] as $item) {
        // Do not attach a title meta tag as this unnecessarily duplicates the
        // title tag.
        // @see metatag_preprocess_html()
        if ($item[1] == 'title') {
          continue;
        }
        // Replace multiple value delimiter with commas if the delimiter hasn't
        // already been replaced by some other module.
        if (isset($item['#attributes']['content']) && is_string($item['#attributes']['content'])) {
          $item['#attributes']['content'] = str_replace($separator, ', ', $item['#attributes']['content']);
        }
        $attachments['#attached']['html_head'][] = $item;
      }
    }
  }

  /**
   * Implements hook_page_attachments_alter().
   */
  #[Hook('page_attachments_alter', order: Order::Last)]
  public function pageAttachmentsAlter(array &$attachments) {
    $route_match = $this->routeMatch;
    // Can be removed once https://www.drupal.org/node/2282029 is fixed.
    if ($route_match->getRouteName() == 'entity.taxonomy_term.canonical' && ($term = $route_match->getParameter('taxonomy_term')) && $term instanceof TermInterface) {
      _metatag_remove_duplicate_entity_tags($attachments, $term);
    }
  }

  /**
   * Implements hook_entity_view_alter().
   */
  #[Hook('entity_view_alter')]
  public function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display) {
    // Don't proceed any further if the entity being viewed isn't the route
    // entity.
    if (!_metatag_is_entity_route_entity($entity)) {
      return;
    }
    if (!$entity->getEntityType()->hasLinkTemplate('canonical')) {
      return;
    }
    // If this is a 403 or 404 page then don't output these meta tags.
    // @todo Make the default meta tags load properly so this is unnecessary.
    if ($display->getOriginalId() == 'node.403.default' || $display->getOriginalId() == 'node.404.default') {
      $build['#attached']['html_head_link'] = [];
      return;
    }
    _metatag_remove_duplicate_entity_tags($build, $entity);
  }

}
