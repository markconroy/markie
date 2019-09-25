<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Provides a plugin for the 'schema_type_base' meta tag.
 */
class SchemaTypeBase extends SchemaNameBase {

  /**
   * Return a list of object labels.
   *
   * This is generally the only method that needs to be extended for type tags.
   * Either return a simple array of types, or prefix the type names with dashes
   * and spaces to indicate their hierarchy. The prefixed dashes and spaces will
   * be removed when creating the raw list of available types.
   *
   * @see SchemaOrganizationType::labels()
   */
  public static function labels() {
    return ['Organization'];
  }

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = [
      '#type' => 'select',
      '#title' => $this->label(),
      '#description' => $this->description(),
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => $this->typeOptions(),
      '#default_value' => $this->value(),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function testValue() {
    $types = static::types();
    return array_shift($types);
  }

  /**
   * Return a list of object types.
   */
  public static function types() {
    $labels = static::labels();
    return array_map('static::removePrefix', $labels);
  }

  /**
   * Turn the list of types into an option list.
   */
  public function typeOptions() {
    $types = static::types();
    $labels = static::labels();
    return array_combine($types, $labels);
  }

  /**
   * Clean up a list of labels by removing leading spaces and dashes.
   */
  public static function removePrefix($item) {
    return str_replace(['-', ' '], '', $item);
  }

}
