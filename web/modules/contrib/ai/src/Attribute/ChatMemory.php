<?php

namespace Drupal\ai\Attribute;

use Drupal\Component\Plugin\Attribute\AttributeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a ChatMemory plugin attribute.
 *
 * Plugin Namespace: Plugin\ChatMemory.
 *
 * @see \Drupal\ai\Plugin\ChatMemory\ChatMemoryInterface
 * @see \Drupal\ai\PluginManager\ChatMemoryPluginManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ChatMemory extends AttributeBase {

  /**
   * Constructs a ChatMemory attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   A brief description of the plugin.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly TranslatableMarkup $description,
  ) {}

}
