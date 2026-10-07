## How to create your own Field Widget Action plugin

Create a plugin class in `Plugin\FieldWidgetAction` namespace. It is recommended to extend `FieldWidgetActionBase` class
in order to focus on functionality of your plugin itself and not do any form integration. Of course you can implement
all methods from the `FieldWidgetActionInterface` on your own. For actions with suggestions there are two helper methods
in the base class:
- `returnSuggestions` that will help to display the suggestions in a modal dialog with ability to use selected value in
  the form field directly in case the form element selector is provided;
- `getSuggestionsTarget` - that gets the selector for the most common use cases of a suggestions action. In special
  cases the plugin can overwrite the method to use its own logic.
- `getTargetElementDelta` - that gets the delta for the field widget form element.
- `getTargetElementFieldName` - that gets the corresponding field name of the form element.
- `fieldValuesDiffer`, `reportProducedValue` and `reportNoValueProduced` - that report the outcome of the
  action to the author. See below.

The methods are protected ones, therefore, they are not part of the interface (as not every action
needs them).

### Reporting the result to the author

An action that returns nothing replaces the field widget with identical markup, so the click is
indistinguishable from no click at all. The module therefore reports the outcome of the action to the
author, using messages that a site builder can configure per action on the form display.

For an action whose AJAX callback returns an `AjaxResponse` with a `Fill*` command, the command payload
decides the outcome and the plugin needs no code for this. For an action that fills the field during the
submit phase and returns a render array, nothing in the return value describes the outcome, so the plugin
reports it with three helper methods in the base class:

- `fieldValuesDiffer` - that compares the field value before and after the action ran, and handles the
  type differences that would otherwise read as a change;
- `reportProducedValue` - that reports that the action produced a value for the field;
- `reportNoValueProduced` - that reports that the action ran but produced nothing.

The question is whether the action itself produced something, not whether the field holds a value, as a
field the author filled in earlier is populated before the action is ever run. Therefore the plugin reads
the field value off the entity before doing its work:
```php
$values_before = $entity->get($field_name)->getValue();
```
reads it again the same way afterwards, and passes both to `fieldValuesDiffer` to decide which of the two
report methods to call. Both values should be read from the entity rather than the form so that the two
sides have the same shape. An action that bails out early calls neither method, in which case the module
shows no message at all. See `RebuildFillTestAction` in the `field_widget_actions_test` module for a
complete example.

### Actions with interactive refinement

If your action generates content that users may want to adjust before it is inserted into the field, extend
`FieldWidgetRefinableFormActionBase` and implement two methods:

- `generateContent` - returns the initial content when the modal form is opened;
- `refineContent` - revises the current content based on the refinement instructions entered by the user.

The base class presents the generated content in the standard field widget action modal form. When the
*Enable interactive refinement* option is checked in the action configuration, the modal also contains a refinement
prompt and a **Refine** button that rebuilds the modal with the refined content. The user can iterate as often as
needed, edit the content directly in the dialog, and insert the result with the **Insert** button. When refinement is
disabled and the plugin provides its own AJAX callback via `getAjaxCallback`, the action button keeps the direct
field-fill behavior and no modal is shown. See `RefinableTextsTestAction` in the `field_widget_actions_test` module
for a complete example.

### IMPORTANT NOTE

Depending on field type and field widget the form element that will be updated can be changed. By default it is `value`
as a lot of field types have one property that is also main property. But in case you create a plugin for entity
reference or image alt text, you need to specify the form element in your plugin class by overriding the constant
`FORM_ELEMENT_PROPERTY`. For example for entity reference field type the constant should be set to this:
```php
const FORM_ELEMENT_PROPERTY = 'target_id';
```
For alt text in image widget:
```php
const FORM_ELEMENT_PROPERTY = 'alt';
```
And so on.
