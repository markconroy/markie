/**
 * @file
 * Auto-triggers field widget action buttons marked as automatic.
 */

(function (Drupal, once) {

  'use strict';

  Drupal.behaviors.fieldWidgetActionsAutomatic = {
    attach: function (context) {
      var buttons = once(
        'fwa-automatic',
        '[data-fwa-automatic="true"]',
        context
      );
      buttons.forEach(function (button) {
        button.dispatchEvent(new MouseEvent('mousedown'));
      });
    }
  };

})(Drupal, once);
