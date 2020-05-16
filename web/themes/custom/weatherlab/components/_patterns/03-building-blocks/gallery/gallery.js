(function galleryScript($, Drupal) {
  Drupal.behaviors.gallery = {
    attach(context) {
      var $galleries = $(".gallery");
      $galleries.each(function() {
        var customId = $(this).attr("id");
        $("#" + customId).unitegallery({
          gallery_mousewheel_role: "none"
        });
      });
    }
  };
})(jQuery, Drupal);
