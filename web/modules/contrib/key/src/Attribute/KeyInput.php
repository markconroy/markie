<?php

declare(strict_types=1);

namespace Drupal\key\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a key input attribute object.
 *
 * Plugins of this type are discovered in the Plugin\KeyInput namespace.
 *
 * @see \Drupal\key\Annotation\KeyInput
 * @see \Drupal\key\Plugin\KeyInputInterface
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class KeyInput extends Plugin {

  /**
   * Constructs a KeyInput attribute.
   *
   * @param string $id
   *   The plugin ID of the key input.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The human-readable name of the key input.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) The description of the key input.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
