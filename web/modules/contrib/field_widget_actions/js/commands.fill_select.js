(function (Drupal) {
  'use strict';

  /**
   * AJAX command to fill a <select> widget with data.
   *
   * @param {Drupal.Ajax} [ajax]
   *   The ajax object.
   * @param {object} response
   *   The response object.
   * @param {string} response.selector
   *   The target selector.
   * @param {Array} response.values
   *   The array of values to select.
   * @param {number} [status]
   *   The HTTP status code.
   */
  Drupal.AjaxCommands.prototype.fieldWidgetActionsFillSelect = function (ajax, response, status) {
    const target = document.querySelector(response.selector);
    let values = response.values;

    if (!target) {
      console.warn('Field Widget Actions: Target element not found for selector ' + response.selector);
      return;
    }

    if (target.tagName !== 'SELECT') {
      console.warn('Field Widget Actions: Target element is not a select tag.');
      return;
    }

    // Ensure values is an array.
    if (!Array.isArray(values)) {
      values = [values];
    }

    // Convert all values to strings to ensure strict comparison works with option values.
    const stringValues = values.map(String);

    if (target.multiple) {
      // For multi-select, iterate over all options and set selected state
      // based on whether the value exists in the input array.
      for (let i = 0; i < target.options.length; i++) {
        target.options[i].selected = stringValues.includes(target.options[i].value);
      }
    }
    else {
      // For single select, grab the first item from the array.
      if (stringValues.length > 0) {
        target.value = stringValues[0];
      }
    }

    // Trigger change event so other JS (autosave, states, Tagify, etc) can react.
    target.dispatchEvent(new Event('input', { bubbles: true }));
    target.dispatchEvent(new Event('change', { bubbles: true }));

    // If this is a "Tagify" select, after updating the underlying select,
    // update the "Tagify" instance.
    const tagifyInput = target.previousElementSibling;
    const tagifyInstance = tagifyInput ? tagifyInput.__tagify : null;
    if (tagifyInstance) {
      tagifyInstance.removeAllTags();
      const displayProp = tagifyInstance.settings.tagTextProp || 'value';
      const tagsToAdd = stringValues.map(val => {
        const option = target.querySelector(`option[value="${val}"]`);
        if (option) {
          let tagObj = {};
          tagObj[displayProp] = option.text;
          tagObj.id = val;
          return tagObj;
        }
        return val;
      });
      tagifyInstance.addTags(tagsToAdd);
    }

  };

})(Drupal);
