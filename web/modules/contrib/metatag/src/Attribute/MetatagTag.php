<?php

namespace Drupal\metatag\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\MetatagType;

/**
 * Defines a MetatagGroup attribute object.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class MetatagTag extends Plugin {

  /**
   * Type of the meta tag.
   *
   * @var string
   */
  public readonly string $type;

  /**
   * Constructor.
   *
   * @param string $id
   *   The meta tag plugin's internal ID, in machine name format.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The display label/name of the meta tag plugin.
   * @param string $name
   *   Proper name of the actual meta tag itself.
   * @param string $group
   *   The group this meta tag fits in, corresponds to a MetatagGroup plugin.
   * @param \Drupal\metatag\MetatagType|string $type
   *   Type of the meta tag.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   A longer explanation of what the field is for.
   * @param int $weight
   *   Weight of the tag.
   * @param bool $secure
   *   True if URL must use HTTPS.
   * @param bool $multiple
   *   True if more than one is allowed.
   * @param bool $long
   *   True if the tag should use a text area.
   * @param bool $absolute_url
   *   True if the URL value(s) must be absolute.
   * @param bool $trimmable
   *   True if the value can be trimmed.
   * @param string|null $deriver
   *   The deriver class, if any.
   * @param string|null $property_type
   *   The property type. Only used by schema_metatag.
   * @param array|null $tree_parent
   *   The array of parents in the tree. Only used by schema_metatag.
   * @param int|null $tree_depth
   *   The tree depth. Only used by schema_metatag.
   */
  public function __construct(
    string $id,
    readonly public TranslatableMarkup $label,
    readonly public string $name,
    readonly public string $group,
    MetatagType|string $type,
    readonly public ?TranslatableMarkup $description = NULL,
    readonly public int $weight = 0,
    readonly public bool $secure = FALSE,
    readonly public bool $multiple = FALSE,
    readonly public bool $long = FALSE,
    readonly public bool $absolute_url = FALSE,
    readonly public bool $trimmable = FALSE,
    ?string $deriver = NULL,
    readonly public ?string $property_type = NULL,
    readonly public ?array $tree_parent = NULL,
    readonly public ?int $tree_depth = NULL,
  ) {
    parent::__construct($id, $deriver);
    if (is_string($type)) {
      $old_type = $type;
      $type = MetatagType::tryFrom($type);
      if (is_null($type)) {
        throw new \InvalidArgumentException("Invalid type '$old_type'.");
      }
    }
    $this->type = $type->value;
  }

}
