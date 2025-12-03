<?php

namespace Drupal\highlight_js;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface for submission result render plugins.
 */
interface HighlightJsInterface extends PluginFormInterface, ConfigurableInterface {

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The translated title.
   */
  public function label();

  /**
   * Render the submitted result of a webform element.
   *
   * @return array
   *   The render array.
   */
  public function build(): array;

  /**
   * Get the attachments used by the plugin.
   */
  public function getAttachments(): array;

}
