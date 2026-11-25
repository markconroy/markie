<?php

declare(strict_types=1);

namespace Drupal\ctools\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Relationship plugin attribute.
 *
 * Mirrors \Drupal\ctools\Annotation\Relationship so that both produce the same
 * plugin definition.
 *
 * @see \Drupal\ctools\Plugin\RelationshipManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Relationship extends Plugin {

  /**
   * Constructs a Relationship attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   (optional) The label of the plugin.
   * @param string|null $data_type
   *   (optional) The returned data type of this relationship.
   * @param string|null $property_name
   *   (optional) The name of the property from which this relationship is
   *   derived.
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $context
   *   (optional) The contexts required or optional for this plugin.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?string $data_type = NULL,
    public readonly ?string $property_name = NULL,
    public readonly array $context = [],
    public readonly ?string $deriver = NULL,
  ) {}

}
