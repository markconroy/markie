(function (Drupal) {
  /**
   * AJAX command to fill checkbox or radio button field widgets with data.
   *
   * Handles both checkboxes (cardinality > 1) and radio buttons (cardinality 1).
   * Tries the [widget][VALUE] name pattern first, then falls back to [VALUE]
   * directly on the field name.
   *
   * @param {Drupal.Ajax} [ajax]
   *   The ajax object.
   * @param {object} response
   *   The response object.
   * @param {string} response.selector
   *   The base name attribute of the widget, e.g. "field_topics[widget]".
   * @param {Array} response.values
   *   The entity IDs to check/select.
   * @param {number} [status]
   *   The HTTP status code.
   */
  Drupal.AjaxCommands.prototype.fieldWidgetActionsFillCheckboxesOrRadios =
    function (ajax, response, status) {
      const base = response.selector;
      const values = response.values.map(String);

      // --- Checkboxes ---
      // Try name^="base[" (e.g. field_topics[widget][453]).
      let checkboxes = Array.from(
        document.querySelectorAll(`input[type="checkbox"][name^="${base}["]`),
      );
      // Fallback: some Drupal versions omit [widget] and use field_topics[453].
      if (checkboxes.length === 0) {
        checkboxes = Array.from(
          document.querySelectorAll(
            `input[type="checkbox"][name^="${base.replace("[widget]", "")}["]`,
          ),
        );
      }

      if (checkboxes.length > 0) {
        checkboxes.forEach(function (checkbox) {
          // Extract the value from the trailing [...] in the name attribute.
          const match = checkbox.name.match(/\[([^\]]+)\]$/);
          if (!match) return;
          const shouldCheck = values.includes(match[1]);
          if (checkbox.checked !== shouldCheck) {
            checkbox.checked = shouldCheck;
            checkbox.dispatchEvent(new Event("change", { bubbles: true }));
          }
        });
        return;
      }

      // --- Radios ---
      // Try name="base" (e.g. field_topics[widget]) then name="field_topics".
      let radios = Array.from(
        document.querySelectorAll(`input[type="radio"][name="${base}"]`),
      );
      if (radios.length === 0) {
        radios = Array.from(
          document.querySelectorAll(
            `input[type="radio"][name="${base.replace("[widget]", "")}"]`,
          ),
        );
      }

      if (radios.length > 0 && values.length > 0) {
        radios.forEach(function (radio) {
          if (radio.value === values[0]) {
            radio.checked = true;
            radio.dispatchEvent(new Event("change", { bubbles: true }));
          }
        });
        return;
      }

      console.warn(
        `Field Widget Actions: No checkboxes or radios found for selector base: ${base}`,
      );
    };
})(Drupal);
