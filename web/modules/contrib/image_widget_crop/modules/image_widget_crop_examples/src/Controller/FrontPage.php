<?php

namespace Drupal\image_widget_crop_examples\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Simple front page controller for image_widget_crop_example module.
 */
class FrontPage extends ControllerBase {

  /**
   * Node types that were created for Image Widget Crop Example.
   *
   * @var array
   */
  protected $iwcExampleNodeTypes = [
    'crop_file_entity_example',
    'crop_file_imce_example',
    'crop_media_example',
    'crop_responsive_example',
    'crop_entity_browser_example',
    'crop_simple_example',
  ];

  /**
   * Displays useful information for image_widget_crop on the front page.
   */
  public function content() {
    $items = [];
    foreach ($this->iwcExampleNodeTypes as $node_type) {
      $node_type = \Drupal::entityTypeManager()->getStorage('node_type')->load($node_type);
      $items['#items'][] = $this->t('<a href="@url">@label',
        [
          '@url' => Url::fromRoute('node.add', ['node_type' => $node_type->id()])->toString(),
          '@label' => $node_type->label(),
        ]
      );
    }

    $this->addExample($items);

    return [
      'intro' => [
        '#markup' => '<p>' . $this->t('Welcome to Image Widget Crop example.') . '</p>',
      ],
      'description' => [
        '#markup' => '<p>' . $this->t('Image Widget Crop provides an interface for using the features of the Crop API.') . '</p>'
        . '<p>' . $this->t('You can test the functionality with custom content types created for the demonstration of features from Image Widget Crop:') . '</p>',
      ],
      'content_types' => [
        '#type' => 'item',
        'list' => [
          '#theme' => 'item_list',
          '#items' => [
            array_values($items),
          ],
        ],
      ],
    ];
  }

  /**
   * Add examples cases into list of examples.
   *
   * @param array $items
   *   Array of all entity examples available to IWC.
   *
   *   return array
   *   Array of each examples with all elements added.
   */
  public function addExample(array &$items) {
    // Add Form API examples.
    $items['#items'][] = $this->t('<a href="@url">@label',
      [
        '@url' => Url::fromRoute('image_widget_crop_examples.form')->toString(),
        '@label' => $this->t('ImageWidgetCrop Form API examples'),
      ]
    );

    return $items;
  }

}
