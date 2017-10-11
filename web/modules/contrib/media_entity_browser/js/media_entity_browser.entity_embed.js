/**
 * @file media_entity_browser.entity_embed.js
 */
(function ($, Drupal) {

  "use strict";

  /**
   * Registers behaviours related to Entity Embed integrations.
   */
  Drupal.behaviors.MediaEntityBrowserEntityEmbed = {
    attach: function (context) {
      // Add an event handler that triggers a click inside the iFrame when our
      // duped element is clicked.
      $('.entity-browser-modal-submit').once('entity-browser-modal').click(function (e) {
        $('.entity-embed-dialog iframe').contents().find('.is-entity-browser-submit').click();
        e.preventDefault();
        e.stopPropagation();
      });

      // On iFrame load, hide the real nested "Select Files" button.
      $('body').once('entity-browser-modal').on('entityBrowserIFrameAppend', function () {
        $(this).find('.entity-embed-dialog iframe').load(function () {
          $(this).contents().find('.is-entity-browser-submit').hide();
        });
      });
    }
  };

}(jQuery, Drupal));
