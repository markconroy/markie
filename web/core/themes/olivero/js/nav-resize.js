/**
* DO NOT EDIT THIS FILE.
* See the following change record for more information,
* https://www.drupal.org/node/2815083
* @preserve
**/

(function (Drupal, once) {
  function transitionToDesktopNavigation(navWrapper, navItem) {
    document.body.classList.remove('is-always-mobile-nav');

    if (navWrapper.clientHeight > navItem.clientHeight) {
      document.body.classList.add('is-always-mobile-nav');
    }
  }

  function checkIfDesktopNavigationWraps(entries) {
    var navItem = document.querySelector('.primary-nav__menu-item');

    if (Drupal.olivero.isDesktopNav() && entries[0].contentRect.height > navItem.clientHeight) {
      var navMediaQuery = window.matchMedia("(max-width: ".concat(window.innerWidth + 5, "px)"));
      document.body.classList.add('is-always-mobile-nav');
      navMediaQuery.addEventListener('change', function () {
        transitionToDesktopNavigation(entries[0].target, navItem);
      }, {
        once: true
      });
    }
  }

  function init(primaryNav) {
    if ('ResizeObserver' in window) {
      var resizeObserver = new ResizeObserver(checkIfDesktopNavigationWraps);
      resizeObserver.observe(primaryNav);
    }
  }

  Drupal.behaviors.automaticMobileNav = {
    attach: function attach(context) {
      once('olivero-automatic-mobile-nav', '[data-drupal-selector="primary-nav-menu--level-1"]', context).forEach(init);
    }
  };
})(Drupal, once);