<?php

/**
 * @file
 * Hooks provided by the Field Widget Actions module.
 */

/**
 * Alter the list of field widget types that do not render child form elements.
 *
 * Field Widget Actions attaches action buttons as child elements of the field
 * widget. Some widgets — such as JavaScript-enhanced selects (Tagify, Select2)
 * — do not render children in the normal Drupal sense, so the buttons would
 * never appear. The module automatically wraps these "childless" widgets in a
 * container element so that the buttons can be placed alongside the widget.
 *
 * Use this hook to declare that your widget plugin ID is also childless, so
 * the container-wrapping behavior is applied and action buttons appear.
 *
 * @param string[] &$childless_widgets
 *   An indexed array of widget plugin IDs whose rendered output does not
 *   include child form elements. Append your widget plugin ID to this array to
 *   enable automatic container wrapping.
 *
 * @see \Drupal\field_widget_actions\Hook\FieldWidgetAction::getChildlessWidgets()
 *
 * @ingroup field_widget_actions
 */
function hook_field_widget_actions_childless_widgets_alter(array &$childless_widgets): void {
  // Mark a custom JavaScript-enhanced widget as childless so FWA buttons
  // appear next to it.
  $childless_widgets[] = 'my_module_fancy_select_widget';
}
