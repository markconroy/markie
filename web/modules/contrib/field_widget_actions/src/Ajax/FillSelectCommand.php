<?php

namespace Drupal\field_widget_actions\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * AJAX command to fill a select field widget with data.
 */
class FillSelectCommand implements CommandInterface {

  /**
   * Constructs a command to fill in a select field.
   *
   * @param string $selector
   *   The CSS selector of the target element (select or 'Tagify' select).
   * @param array $values
   *   The array of values to select.
   */
  public function __construct(protected string $selector, protected array $values) {}

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'fieldWidgetActionsFillSelect',
      'selector' => $this->selector,
      'values' => array_values($this->values),
    ];
  }

}
