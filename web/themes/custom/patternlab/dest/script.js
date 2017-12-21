'use strict';

(function mainThemeScript($, Drupal) {
  Drupal.behaviors.plStarter = {
    attach: function attach(context) {
      $('html', context).addClass('js');
    }
  };
})(jQuery, Drupal);
'use strict';

(function secondaryThemeScript($, Drupal) {
  Drupal.behaviors.demo2 = {
    attach: function attach(context) {
      $('html', context).addClass('js2');
    }
  };
})(jQuery, Drupal);
"use strict";

(function verticalrhythmScript($, Drupal) {
  Drupal.behaviors.verticalrhythm = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function messagesScript($, Drupal) {
  Drupal.behaviors.messages = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function searchblockScript($, Drupal) {
  Drupal.behaviors.searchblock = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
'use strict';

(function accordionScript($, Drupal) {
  Drupal.behaviors.accordion = {
    attach: function attach(context) {

      var $accordionTitle = $('.accordion-item__trigger');
      var $accordionResponse = $('.accordion-item__response');

      function anrt_accordion() {
        $accordionTitle.unbind().click(function () {
          var $this = $(this);
          $this.siblings($accordionResponse).slideToggle();
          $this.find('.fa').toggleClass('show hide');
        });
      }
      anrt_accordion();
    }
  };
})(jQuery, Drupal);
"use strict";

(function calltoactionScript($, Drupal) {
  Drupal.behaviors.calltoaction = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function fileuploadScript($, Drupal) {
  Drupal.behaviors.fileupload = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function imagewithtextScript($, Drupal) {
  Drupal.behaviors.imagewithtext = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function largeimagectaScript($, Drupal) {
  Drupal.behaviors.largeimagecta = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function quoteScript($, Drupal) {
  Drupal.behaviors.quote = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function relatedcontentScript($, Drupal) {
  Drupal.behaviors.relatedcontent = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function sectionbreakScript($, Drupal) {
  Drupal.behaviors.sectionbreak = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function singleimageScript($, Drupal) {
  Drupal.behaviors.singleimage = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function textScript($, Drupal) {
  Drupal.behaviors.text = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function tiledlayoutScript($, Drupal) {
  Drupal.behaviors.tiledlayout = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function videoScript($, Drupal) {
  Drupal.behaviors.video = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function articleScript($, Drupal) {
  Drupal.behaviors.article = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function basicpageScript($, Drupal) {
  Drupal.behaviors.basicpage = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function aboveheaderScript($, Drupal) {
  Drupal.behaviors.aboveheader = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function belowheaderScript($, Drupal) {
  Drupal.behaviors.belowheader = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function footerScript($, Drupal) {
  Drupal.behaviors.footer = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function headerScript($, Drupal) {
  Drupal.behaviors.header = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function pagetitleScript($, Drupal) {
  Drupal.behaviors.pagetitle = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function tabsScript($, Drupal) {
  Drupal.behaviors.tabs = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function imageScript($, Drupal) {
  Drupal.behaviors.image = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function paragraphScript($, Drupal) {
  Drupal.behaviors.paragraph = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
'use strict';

(function messagesScript($, Drupal) {
  Drupal.behaviors.menu_block_main_navigation = {
    attach: function attach(context) {
      var $menuToggle = $('.menu-toggle');
      var $mainMenu = $('.main-navigation .main-navigation__menu');

      function mainMenu() {

        if ($(window).width() > 1024) {
          $mainMenu.show();
        } else {
          $mainMenu.hide();
        }

        $menuToggle.unbind().click(function () {
          $mainMenu.slideToggle();
        });
      }

      mainMenu();

      $(window).resize(function () {
        mainMenu();
      });
    }
  };
})(jQuery, Drupal);
"use strict";

(function fullScript($, Drupal) {
  Drupal.behaviors.full = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function teaserScript($, Drupal) {
  Drupal.behaviors.teaser = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
"use strict";

(function defaultScript($, Drupal) {
  Drupal.behaviors.default = {
    attach: function attach(context) {}
  };
})(jQuery, Drupal);
//# sourceMappingURL=script.js.map
