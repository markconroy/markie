/**
 * @file JS file for the header component.
 */

(function headerScript(Drupal, drupalSettings) {
  Drupal.behaviors.header = {
    attach(context) {
      context = context || document;

      const menuToggleButton = document.querySelector('.header__menu-toggle .menu-toggle__button');
      
      // If we've already processed the content and added the event listeners,
      // we don't need to continue.
      if (menuToggleButton.classList.contains('js-menu-toggle-button')) {
        return;
      }

      const menuToggleLink = document.querySelector('.header__menu-toggle .menu-toggle__link');
      const headerOffCanvas = document.querySelector('.header__off-canvas');
      const header = document.querySelector('header.header');
      let headerHeight = header.offsetHeight;
      const menu = header.querySelector('.header__off-canvas .menu--main');
      const menuID = menu.getAttribute('id');

      var focusable = headerOffCanvas.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
      var lastFocusable = focusable[focusable.length - 1];

      // Show toggle button and hide anchor.
      menuToggleButton.classList.add('js-menu-toggle-button');
      menuToggleButton.removeAttribute('hidden');
      menuToggleButton.setAttribute('aria-controls', menuID);
      menuToggleLink.setAttribute('hidden', true);

      // Set offset canvas below header
      function getHeaderHeight() {
        headerHeight = header.offsetHeight;
        headerOffCanvas.style.top = headerHeight + header.offsetTop + 'px';
      }
      getHeaderHeight();

      // When the "Menu" or "Close" buttons are clicked, this function is called.
      // It just toggles some classes to bring the .header__off-canvas into/out of view.
      function toggleOffCanvas(ev) {
        const expanded = (ev.target.getAttribute('aria-expanded') === 'true');

        // Hide the menu overlay.
        if (expanded) {
          // Handle button changes.
          ev.target.setAttribute('aria-expanded', false);
          ev.target.setAttribute('aria-label', ev.target.dataset.menuHiddenLabel);
          ev.target.textContent = ev.target.dataset.menuExpandText;
          ev.target.classList.remove('open');
          ev.target.focus();

          // Handle overlay changes.
          headerOffCanvas.classList.remove("header__off-canvas--is-on-canvas");
          headerOffCanvas.classList.add("header__off-canvas--is-off-canvas");
        }
        // Show the menu overlay.
        else {
          // Handle button changes.
          ev.target.setAttribute('aria-expanded', true);
          ev.target.setAttribute('aria-label', ev.target.dataset.menuExpandedLabel);
          ev.target.textContent = ev.target.dataset.menuHideText;
          ev.target.classList.add('open');

          // Handle overlay changes.
          headerOffCanvas.style.top = headerHeight + header.offsetTop + 'px';
          headerOffCanvas.classList.remove("header__off-canvas--is-off-canvas");
          headerOffCanvas.classList.add("header__off-canvas--is-on-canvas");
        }
      }

      // This function is called when the window is resized. If we are on a large screen
      // it just resets the .header__off-canvas, so we can see the desktop version of the menu.
      function hideShowOffCanvasWindowWidth() {
        if (window.matchMedia('(min-width: 1024px)').matches) {
          headerOffCanvas.classList.remove("header__off-canvas--is-on-canvas");
          headerOffCanvas.classList.add("header__off-canvas--is-off-canvas");
          menuToggleButton.style.display = 'block';
          menuToggleButton.setAttribute('aria-expanded', 'false');
          menuToggleButton.classList.remove("open");
        }
      }

      function keepFocusInMenu() {
        if ((window.matchMedia('(max-width: 1023px)').matches)) {
          lastFocusable.addEventListener('keydown', function() {
            menuToggleButton.focus();
          });
        }
      }
      keepFocusInMenu();

      window.addEventListener("resize", hideShowOffCanvasWindowWidth);
      window.addEventListener("resize", getHeaderHeight);
      menuToggleButton.addEventListener("click", toggleOffCanvas);

      // Add class to body for styling hook
      const body = menuToggleButton.closest("body");
      body.classList.add('js-header');

    }
  };
}(Drupal, drupalSettings));
