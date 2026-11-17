<?php

declare(strict_types=1);

namespace Drupal\key\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a key provider attribute object.
 *
 * Plugins of this type are discovered in the Plugin\KeyProvider namespace.
 *
 * Unlike the annotation, this attribute has no deprecated 'storage_method'
 * property: use 'tags' instead.
 *
 * @see \Drupal\key\Annotation\KeyProvider
 * @see \Drupal\key\Plugin\KeyProviderInterface
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class KeyProvider extends Plugin {

  /**
   * Constructs a KeyProvider attribute.
   *
   * @param string $id
   *   The plugin ID of the key provider.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The human-readable name of the key provider.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) The description of the key provider.
   * @param string[] $tags
   *   (optional) The key provider tags, used for classification and filtering.
   * @param array $key_value
   *   (optional) Whether this provider accepts and requires a key value.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly array $tags = [],
    public readonly array $key_value = [
      'accepted' => FALSE,
      'required' => FALSE,
    ],
    public readonly ?string $deriver = NULL,
  ) {}

}
