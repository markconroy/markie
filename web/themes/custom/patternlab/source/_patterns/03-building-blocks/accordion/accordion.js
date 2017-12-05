(function accordionScript($, Drupal) {
  Drupal.behaviors.accordion = {
    attach(context) {

      var $accordionTitle = $('.accordion-item__trigger');
      var $accordionResponse = $('.accordion-item__response');

      function anrt_accordion() {
        $accordionTitle.unbind().click(function() {
          var $this = $(this);
          $this.siblings($accordionResponse).slideToggle();
          $this.find('.fa').toggleClass('show hide');
        });
      }
      anrt_accordion();

    },
  };
}(jQuery, Drupal));
