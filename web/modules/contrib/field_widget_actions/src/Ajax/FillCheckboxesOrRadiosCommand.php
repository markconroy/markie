<?php

namespace Drupal\field_widget_actions\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * AJAX command to fill checkbox or radio button field widgets with data.
 */
class FillCheckboxesOrRadiosCommand implements CommandInterface {

  /**
   * Constructs a command to fill checkboxes or radio buttons.
   *
   * @param string $selector
   *   The base name attribute of the widget, e.g. "field_topics[widget]".
   * @param array $values
   *   The entity IDs to check/select.
   */
  public function __construct(protected string $selector, protected array $values) {}

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'fieldWidgetActionsFillCheckboxesOrRadios',
      'selector' => $this->selector,
      'values' => array_values(array_filter($this->values)),
    ];
  }

}
