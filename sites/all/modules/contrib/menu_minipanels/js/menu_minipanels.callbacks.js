/**
 * @file
 * Default JS callbacks for the Menu_MiniPanels module.
 */

/**
 * If either of these callbacks need to be customized then the following must
 * be done:
 *  1. Disable the "Load default JS callbacks" option on the main settings
 *     page.
 *  2. Copy this file to a new location and set it to load as necessary, e.g.
 *     by adding it to the site's theme directory and listing it on the theme's
 *     .info file.
 *  3. Customize as necessary, see API.txt for further details of the callbacks
 *     that are available for use.
 */
(function($) {

  Drupal.behaviors.menuMiniPanelsCallbacks = {
    attach: function(context, settings) {

      // Mark target element as selected.
      MenuMiniPanels.setCallback('beforeShow', function(qTip, event, content) {
        // Forceably remove the class off all DOM elements, avoid problems
        // of it not being properly removed in certain scenarios.
        $('.qtip-hover').removeClass('qtip-hover');

        // Add the hover class to the current item.
        var $target = $(qTip.elements.target.get(0));
        if ($target !== undefined) {
          $target.addClass('qtip-hover');
        }
      });

      // Unmark target element as selected.
      MenuMiniPanels.setCallback('beforeHide', function(qTip, event, content) {
        // Remove the class off all DOM elements.
        $('.qtip-hover').removeClass('qtip-hover');
      });

      // Integrate with the core Contextual module.
      MenuMiniPanels.setCallback('onRender', function(qTip, event, content) {
        $('div.menu-minipanels div.contextual-links-wrapper', context).each(function () {
          var $wrapper = $(this);
          // Remove the popup link added from the first time the Contextual
          // module processed the links.
          $wrapper.children('a.contextual-links-trigger').detach();
          // Continue as normal.
          var $region = $wrapper.closest('.contextual-links-region');
          var $links = $wrapper.find('ul.contextual-links');
          var $trigger = $('<a class="contextual-links-trigger" href="#" />').text(Drupal.t('Configure')).click(
            function () {
              $links.stop(true, true).slideToggle(100);
              $wrapper.toggleClass('contextual-links-active');
              return false;
            }
          );
          // Attach hover behavior to trigger and ul.contextual-links.
          $trigger.add($links).hover(
            function () { $region.addClass('contextual-links-region-active'); },
            function () { $region.removeClass('contextual-links-region-active'); }
          );
          // Hide the contextual links when user clicks a link or rolls out of
          // the .contextual-links-region.
          $region.bind('mouseleave click', Drupal.contextualLinks.mouseleave);
          // Prepend the trigger.
          $wrapper.prepend($trigger);
        });
      });

    }
  };
})(jQuery);
