/**
 * @file
 * Unlock protected forms.
 *
 * This works by resetting the form action to the path that It should be as well
 * as injecting the secret form key, only if the current user is verified to be
 * human which is done by waiting for a mousemove, swipe, or tab/enter key to be
 * pressed.
 */

(function ($, Drupal, drupalSettings) {
  "use strict";

  Drupal.antibot = {};

  Drupal.behaviors.antibot = {
    attach: function (context) {
      // Assume the user is not human, despite JS being enabled.
      drupalSettings.antibot.human = false;

      // Wait for a mouse to move, indicating they are human.
      $('body').mousemove(function () {
        // Unlock the forms.
        Drupal.antibot.unlockForms();
      });

      // Wait for a touch move event, indicating that they are human.
      $('body').bind('touchmove', function () {
        // Unlock the forms.
        Drupal.antibot.unlockForms();
      });

      // A tab or enter key pressed can also indicate they are human.
      $('body').keydown(function (e) {
        if ((e.keyCode == 9) || (e.keyCode == 13)) {
          // Unlock the forms.
          Drupal.antibot.unlockForms();
        }
      });
    }
  };

  /**
   * Unlock all locked forms.
   */
  Drupal.antibot.unlockForms = function () {
    // Act only if we haven't yet verified this user as being human.
    if (!drupalSettings.antibot.human) {
      // Check if there are forms to unlock.
      if (drupalSettings.antibot.forms != undefined) {
        // Iterate all antibot forms that we need to unlock.
        $.each(drupalSettings.antibot.forms, function (id, config) {
          // Switch the action.
          $('form#' + config.id).attr('action', $('form#' + config.id).data('action'));

          // Set the key.
          $('form#' + config.id).find('input[name="antibot_key"]').val(config.key);
        });
      }
      // Mark this user as being human.
      drupalSettings.antibot.human = true;
    }
  };
})(jQuery, Drupal, drupalSettings);
