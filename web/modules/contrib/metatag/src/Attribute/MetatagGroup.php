<?php

namespace Drupal\metatag\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a MetatagGroup attribute object.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class MetatagGroup extends Plugin {

  /**
   * Constructor.
   *
   * @param string $id
   *   The group's internal ID, in machine name format.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The name of the group.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   Description of the group.
   * @param int $weight
   *   Weight of the group.
   */
  public function __construct(
    string $id,
    readonly public TranslatableMarkup $label,
    readonly public ?TranslatableMarkup $description = NULL,
    readonly public int $weight = 0,
  ) {
    parent::__construct($id);
  }

}
