<?php

declare(strict_types=1);

namespace Drupal\ctools_views\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\ctools_views\Plugin\Display\Block;
use Drupal\views\Plugin\views\display\Block as CoreBlock;

/**
 * Hook implementations for ctools_views.
 */
final class CtoolsViewsHooks {

  /**
   * Implements hook_views_plugins_display_alter().
   */
  #[Hook('views_plugins_display_alter')]
  public function viewsPluginsDisplayAlter(array &$displays): void {
    if (!empty($displays['block']['class']) && $displays['block']['class'] == CoreBlock::class) {
      $displays['block']['class'] = Block::class;
    }
  }

  /**
   * Implements hook_config_schema_info_alter().
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(array &$definitions): void {
    // Add to the views block plugin schema.
    $definitions['views_block']['mapping']['pager'] = [
      'type' => 'string',
      'label' => 'Pager type',
      'requiredKey' => FALSE,
    ];
    $definitions['views_block']['mapping']['fields'] = [
      'type' => 'sequence',
      'label' => 'Fields settings',
      'requiredKey' => FALSE,
      'sequence' => [
        [
          'type' => 'mapping',
          'label' => 'Field settings',
          'mapping' => [
            'hide' => [
              'type' => 'boolean',
              'label' => 'Hide field',
            ],
            'weight' => [
              'type' => 'integer',
              'label' => 'Field weight',
            ],
          ],
        ],
      ],
    ];
    $definitions['views_block']['mapping']['filter'] = [
      'type' => 'sequence',
      'label' => 'Filters settings',
      'requiredKey' => FALSE,
      'sequence' => [
        [
          'type' => 'mapping',
          'label' => 'Filter settings',
          'mapping' => [
            'type' => [
              'type' => 'string',
              'label' => 'Plugin id',
            ],
            'disable' => [
              'type' => 'boolean',
              'label' => 'Disable filter',
            ],
          ],
        ],
      ],
    ];

    $definitions['views_block']['mapping']['exposed'] = [
      'type' => 'sequence',
      'label' => 'Exposed filter values',
      'requiredKey' => FALSE,
      'sequence' => [
        'type' => 'views.filter.[plugin_id]',
      ],
    ];

    $definitions['views_block']['mapping']['sort'] = [
      'type' => 'sequence',
      'label' => 'Sort settings',
      'requiredKey' => FALSE,
      'sequence' => [
        [
          'type' => 'string',
          'label' => 'Sort order value',
        ],
      ],
    ];
    $definitions['views_block']['mapping']['pager_offset'] = [
      'type' => 'integer',
      'label' => 'Pager offset',
      'requiredKey' => FALSE,
    ];

    // Add to the views block display plugin schema.
    $definitions['views.display.block']['mapping']['allow']['mapping']['offset'] = [
      'type' => 'string',
      'label' => 'Pager offset',
    ];
    $definitions['views.display.block']['mapping']['allow']['mapping']['pager'] = [
      'type' => 'string',
      'label' => 'Pager type',
    ];
    $definitions['views.display.block']['mapping']['allow']['mapping']['hide_fields'] = [
      'type' => 'string',
      'label' => 'Hide fields',
    ];
    $definitions['views.display.block']['mapping']['allow']['mapping']['sort_fields'] = [
      'type' => 'string',
      'label' => 'Sort fields',
    ];
    $definitions['views.display.block']['mapping']['allow']['mapping']['configure_filters'] = [
      'type' => 'string',
      'label' => 'Configure filters',
    ];
    $definitions['views.display.block']['mapping']['allow']['mapping']['disable_filters'] = [
      'type' => 'string',
      'label' => 'Disable filters',
    ];
    $definitions['views.display.block']['mapping']['allow']['mapping']['configure_sorts'] = [
      'type' => 'string',
      'label' => 'Configure sorts',
    ];
  }

}
