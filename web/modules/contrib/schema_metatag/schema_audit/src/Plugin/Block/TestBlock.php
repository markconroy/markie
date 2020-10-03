<?php

namespace Drupal\schema_audit\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'TestBlock' block.
 *
 * @Block(
 *  id = "test_block",
 *  admin_label = @Translation("Test structured data on Google."),
 * )
 */
class TestBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [
      '#markup' => $this->t('This report is deprecated.'),
    ];

    return $build;
  }

}
