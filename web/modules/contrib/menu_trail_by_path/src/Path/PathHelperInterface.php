<?php

namespace Drupal\menu_trail_by_path\Path;

interface PathHelperInterface {
  /**
   * @return \Drupal\Core\Url[]
   */
  public function getUrls();
}
