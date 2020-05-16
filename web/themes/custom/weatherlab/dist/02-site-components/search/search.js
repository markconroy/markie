"use strict";(function(a,b){b.behaviors.search={attach:function attach(){// We probably should create a new block for this, so we can disable the
// search view.
var b=a(".search-block-form");b.submit(function(a){a.preventDefault(),window.location.replace("/search?keywords="+b.find(".form-search").val().replace(" ","+"))})}}})(jQuery,Drupal);
//# sourceMappingURL=search.js.map
