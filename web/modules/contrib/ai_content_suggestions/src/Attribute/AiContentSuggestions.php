<?php

declare(strict_types=1);

namespace Drupal\ai_content_suggestions\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an AI Content Suggestions plugin attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AiContentSuggestions extends Plugin {

  /**
   * Constructs an AiContentSuggestions attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   The description of the plugin.
   * @param string $operation_type
   *   The AI operation type for the plugin.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly TranslatableMarkup $description,
    public readonly string $operation_type,
    public readonly ?string $deriver = NULL,
  ) {}

}
