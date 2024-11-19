<?php

declare(strict_types=1);

namespace Drupal\ai_ckeditor\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The ai_ckeditor plugin attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AiCKEditor extends Plugin {

  /**
   * Constructs a new AiCKEditor plugin instance.
   *
   * @param string $id
   *   The plugin id.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the plugin.
   * @param string $description
   *   The plugin description.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly TranslatableMarkup $description,
  ) {
  }

}
