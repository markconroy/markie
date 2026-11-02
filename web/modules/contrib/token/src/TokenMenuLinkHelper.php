<?php

namespace Drupal\token;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;

/**
 * Loads menu link titles for menu link tokens.
 *
 * The service lives for the whole request, so the parent titles it loads are
 * kept until the request ends, or until resetParents() is called.
 *
 * @internal
 */
class TokenMenuLinkHelper {

  /**
   * The menu link parent titles, keyed by plugin ID and language code.
   *
   * @var array
   */
  protected array $parents = [];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
    protected MenuLinkManagerInterface $menuLinkManager,
  ) {
  }

  /**
   * Loads the titles of all the parents of a menu link.
   *
   * @param string $plugin_id
   *   The menu link plugin ID.
   * @param string $langcode
   *   The language code.
   *
   * @return string[]
   *   The parent titles, keyed by plugin ID, from the root down.
   */
  public function loadParents(string $plugin_id, string $langcode): array {
    if (!isset($this->parents[$plugin_id][$langcode])) {
      $this->parents[$plugin_id][$langcode] = [];
      $parent_ids = $this->menuLinkManager->getParentIds($plugin_id);
      // Remove the current plugin ID from the parents.
      unset($parent_ids[$plugin_id]);
      foreach ($parent_ids as $parent_id) {
        $parent = $this->menuLinkManager->createInstance($parent_id);
        $this->parents[$plugin_id][$langcode] = [$parent_id => $this->getTranslatedTitle($parent, $langcode)] + $this->parents[$plugin_id][$langcode];
      }
    }

    return $this->parents[$plugin_id][$langcode];
  }

  /**
   * Gets the title of a menu link in the given language.
   *
   * If the underlying entity is a content menu item, it is loaded to get the
   * translated menu item title.
   *
   * @param \Drupal\Core\Menu\MenuLinkInterface $menu_link
   *   The menu link.
   * @param string|null $langcode
   *   The language code, or NULL for the current content language.
   *
   * @return string
   *   The menu link title.
   */
  public function getTranslatedTitle(MenuLinkInterface $menu_link, ?string $langcode = NULL) {
    $metadata = $menu_link->getMetaData();
    if (isset($metadata['entity_id']) && $menu_link->getProvider() == 'menu_link_content') {
      /** @var \Drupal\menu_link_content\MenuLinkContentInterface|null $entity */
      $entity = $this->entityTypeManager->getStorage('menu_link_content')->load($metadata['entity_id']);
      if ($entity !== NULL) {
        $entity = $this->entityRepository->getTranslationFromContext($entity, $langcode);
        return $entity->getTitle();
      }
    }
    return $menu_link->getTitle();
  }

  /**
   * Forgets the menu link parent titles, so they are loaded again.
   */
  public function resetParents(): void {
    $this->parents = [];
  }

}
