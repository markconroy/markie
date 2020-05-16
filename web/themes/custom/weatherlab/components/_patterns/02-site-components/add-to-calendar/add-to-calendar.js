(function addtocalendarScript($, Drupal) {
  Drupal.behaviors.addtocalendar = {
    attach(context) {
      var addToCalendarTrigger = $(".add-to-calendar__trigger");

      addToCalendarTrigger.unbind("click").click(function() {
        $(this)
          .parent(".add-to-calendar")
          .find(".add-to-calendar__items")
          .slideToggle();
      });
    }
  };
})(jQuery, Drupal);
