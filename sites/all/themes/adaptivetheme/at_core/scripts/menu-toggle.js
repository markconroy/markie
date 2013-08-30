(function ($) {
  Drupal.behaviors.ATmenuToggle = {
    attach: function (context, settings) {

      var activeTheme = Drupal.settings["ajaxPageState"]["theme"];
      var themeSettings = Drupal.settings['adaptivetheme'];
      var mtsTP = themeSettings[activeTheme]['menu_toggle_settings']['tablet_portrait'];
      var mtsTL = themeSettings[activeTheme]['menu_toggle_settings']['tablet_landscape'];

      var breakpoints = {
         bp1: themeSettings[activeTheme]['media_query_settings']['smalltouch_portrait'],
         bp2: themeSettings[activeTheme]['media_query_settings']['smalltouch_landscape'],
      };

      if (mtsTP == 'true') { breakpoints.push(bp3 + ':' + themeSettings[activeTheme]['media_query_settings']['tablet_portrait']); }
      if (mtsTL == 'true') { breakpoints.push(bp4 + ':' + themeSettings[activeTheme]['media_query_settings']['tablet_portrait']); }

      $(".at-menu-toggle h2").removeClass('element-invisible').addClass('at-menu-toggle-button').wrapInner('<a href="#menu-toggle" class="at-menu-toggle-button-link" />');
      $(".at-menu-toggle ul[class*=menu]:nth-of-type(1)").wrap('<div class="menu-toggle" />');

      !function(breakName, query){

        // Run the callback on current viewport
        cb({
            media: query,
            matches: matchMedia(query).matches
        });

        // Subscribe to breakpoint changes
        matchMedia(query).addListener(cb);

      }(name, breakpoints[name]);

      // Callback
      function cb(data){

        // Toggle menus open or closed
        $(".at-menu-toggle-button-link").click(function() {
          $(this).parent().siblings('.menu-toggle').slideToggle(100, 'swing');
          return false;
        });

      }

      //console.log(themeSettings);
    }
  };
})(jQuery);
