(function messagesScript($, Drupal) {
  Drupal.behaviors.menu_block_main_navigation = {
    attach(context) {
      var $menuToggle = $(".menu-toggle");
      var $mainMenu = $(".main-navigation .main-navigation__menu");

      function mainMenu() {
        if ($(window).width() > 1023) {
          $mainMenu.show();
        } else {
          $mainMenu.hide();
        }

        $menuToggle.unbind().click(function() {
          $mainMenu.slideToggle();
        });
      }

      mainMenu();

      $(window).resize(function() {
        mainMenu();
      });
    }
  };
})(jQuery, Drupal);
