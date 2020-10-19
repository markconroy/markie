/**
 * @file
 * Enables syntax highlighting via HighlightJS on the HTML code tag.
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.codesnippet = {
    attach: function (context, settings) {
      hljs.initHighlightingOnLoad();
      $("pre code").css('overflow-x', 'auto');
    }
  };

})(jQuery, Drupal);
