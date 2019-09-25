<?php

/**
 * @file
 * Contains Drupal\schema_audit\Plugin\Block\TestBlock.
 */

namespace Drupal\schema_audit\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\Core\Link;

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
    $build = [];

    $heading = '<h3>Test this page</h3>';
    $description = "<p>View page source to see the JSON-LD on this page. Test the results of this page by checking it on Google's structured content tester.</p>";

    // Get current path.
    $options = ['absolute' => 'true'];
    $drupal_url = Url::fromRoute('<current>', [], $options)->toString();

    $google_url = Url::fromUri('https://search.google.com/structured-data/testing-tool');
    $link = Link::fromTextAndUrl(t('Test on Google'), $google_url);

    $build = [
      'description' => [
        '#type' => 'markup',
        '#markup' => $description,
      ],
      'description_link' =>  [
        $link->toRenderable(),
      ],
    ];

    return $build;
  }

}
