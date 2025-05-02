<?php

namespace Drupal\entity_usage_test\Plugin\Block;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Block\BlockBase;

/**
 * Provides a block with dependencies.
 *
 * @Block(
 *   id = "entity_usage_test_dependencies",
 *   admin_label = @Translation("Block with dependencies"),
 *   category = @Translation("Entity Usage Test"),
 * )
 */
class DependenciesBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'dependencies' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    if ($this->configuration['dependencies']) {
      $dependencies = NestedArray::mergeDeep($dependencies, $this->configuration['dependencies']);
    }
    return $dependencies;
  }

}
