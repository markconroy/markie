<?php

declare(strict_types=1);

namespace Drupal\ctools_block\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for ctools_block.
 */
final class CtoolsBlockHooks {

  /**
   * Implements hook_plugin_filter_TYPE_alter() for block plugins.
   *
   * Removes ctools blocks from Layout Builder. In general, users should be
   * using the core block types instead.
   */
  #[Hook('plugin_filter_block_alter')]
  public function pluginFilterBlockAlter(array &$definitions, array $extra, $consumer): void {
    if ($consumer == 'layout_builder') {
      foreach ($definitions as $label => $definition) {
        if ($definition['provider'] == 'ctools_block') {
          unset($definitions[$label]);
        }
      }
    }
  }

}
