<?php

namespace Drupal\metatag\Plugin\diff\Field;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\diff\Attribute\FieldDiffBuilder;
use Drupal\diff\FieldDiffBuilderBase;

/**
 * Plugin to diff metatag fields.
 *
 * Note that this requires drupal/diff:^2.0.
 */
#[FieldDiffBuilder(
  id: 'metatag_field_diff_builder',
  label: new TranslatableMarkup('Metatag Field Diff'),
  field_types: ['metatag'],
)]
class MetatagFieldBuilder extends FieldDiffBuilderBase {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items): mixed {
    $result = [];

    // Every item from $field_items is of type FieldItemInterface.
    foreach ($field_items as $field_key => $field_item) {
      if (!$field_item->isEmpty()) {
        $values = $field_item->getValue();
        if (isset($values['value'])) {
          // Metatag data store as serialize string.
          $metatag_data = metatag_data_decode($values['value']);

          foreach ($metatag_data as $value) {
            $result[$field_key][] = (string) $value;
          }
        }
      }
    }

    return $result;
  }

}
