<?php

namespace Drupal\upgrade_status_test_twig\TwigExtension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Deprecated filter.
 */
class DeprecatedFilter extends AbstractExtension {

  /**
   * Get filters.
   */
  public function getFilters() {
    return [new TwigFilter('deprecatedfilter', 'strlen', ['deprecated' => TRUE])];
  }

}
