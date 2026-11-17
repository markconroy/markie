<?php

declare(strict_types=1);

namespace Drupal\key\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a key type attribute object.
 *
 * Plugins of this type are discovered in the Plugin\KeyType namespace.
 *
 * @see \Drupal\key\Annotation\KeyType
 * @see \Drupal\key\Plugin\KeyTypeInterface
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class KeyType extends Plugin {

  /**
   * Constructs a KeyType attribute.
   *
   * @param string $id
   *   The plugin ID of the key type.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The human-readable name of the key type.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) The description of the key type.
   * @param string $group
   *   (optional) The group to which this key type belongs, such as
   *   "authentication" or "encryption". Defaults to "none".
   * @param array $key_value
   *   (optional) The settings to use when a key value can be submitted, such
   *   as the key input plugin to use. Defaults to the text_field plugin.
   * @param array $multivalue
   *   (optional) Whether the key type supports multiple values and, if so,
   *   its fields, keyed by field name. A field is defined either as its label:
   *   @code
   *   'username' => new TranslatableMarkup('User name'),
   *   @endcode
   *   or as an array with a label and whether the field is required:
   *   @code
   *   'password' => [
   *     'label' => new TranslatableMarkup('Password'),
   *     'required' => FALSE,
   *   ],
   *   @endcode
   *   Fields are required unless they say otherwise.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly string $group = 'none',
    public readonly array $key_value = [
      'plugin' => 'text_field',
    ],
    public readonly array $multivalue = [
      'enabled' => FALSE,
      'fields' => [],
    ],
    public readonly ?string $deriver = NULL,
  ) {}

}
