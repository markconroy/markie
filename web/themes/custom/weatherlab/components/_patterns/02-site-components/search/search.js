(function searchScript($, Drupal) {
  Drupal.behaviors.search = {
    attach(context) {
      // We probably should create a new block for this, so we can disable the
      // search view.
      var $searchBlockForm = $('.search-block-form');
      $searchBlockForm.submit(function(event) {
        event.preventDefault();
        window.location.replace('/search?keywords=' + $searchBlockForm.find('.form-search').val().replace(' ', '+'));
      });
    },
  };
}(jQuery, Drupal));