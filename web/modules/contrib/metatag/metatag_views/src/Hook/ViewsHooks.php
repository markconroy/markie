<?php

namespace Drupal\metatag_views\Hook;

use Drupal\metatag_views\Plugin\views\display_extender\MetatagDisplayExtender;
use Drupal\views\Plugin\views\cache\CachePluginBase;
use Drupal\views\ViewExecutable;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for metatag_views.
 */
class ViewsHooks {

  /**
   * Implements hook_views_post_render().
   */
  #[Hook('views_post_render')]
  public function viewsPostRender(ViewExecutable $view, &$output, CachePluginBase $cache) {
    $extenders = $view->getDisplay()->getExtenders();
    if (isset($extenders['metatag_display_extender'])) {
      $first_row_tokens = MetatagDisplayExtender::getFirstRowTokensFromStylePlugin($view);
      /** @var \Drupal\metatag_views\Plugin\views\display_extender\MetatagDisplayExtender */
      $extender = $extenders['metatag_display_extender'];
      $extender->setFirstRowTokens($first_row_tokens);
    }
  }

}
