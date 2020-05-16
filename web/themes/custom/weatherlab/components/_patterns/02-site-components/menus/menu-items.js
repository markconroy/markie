/**
 * Provides custom menu expand/collaps behaviour for menu-items.
 *
 * To-do:
 *
 *   - consider how to animate this in javascript (for better overall quality
 *     of animation). Probably this would need to be a configurable option.
 */
(function menuItemsScript(Drupal) {
  Drupal.behaviors.menuItems = {
    attach(context) {
      const submenusSelector = '[data-submenus]';
      const menuRoots = context.querySelectorAll(submenusSelector);
      const menuProcessedClass = 'js-navigation';
      const toggleSelector = '.sub-menu-item-toggle';

      menuRoots.forEach(m => {
        init(m);
      });

      /**
       * Handles menu initialization.
       */
      function init(menuRoot) {
        // If this menu has already been processed, we can stop here.
        if (menuRoot.classList.contains(menuProcessedClass)) {
          return;
        }

        // Gather some info.
        const toggles = menuRoot.querySelectorAll(toggleSelector);

        // Close all submenus, add event listeners to buttons, and make
        // them visible.
        toggles.forEach(el => {
          // In this context, it's more efficient to do this directly than to
          // call our closeMenu() function.
          el.setAttribute('aria-expanded', false);
          el.setAttribute('aria-label', menuRoot.dataset.menuOpenLabel);
          el.removeAttribute('hidden');
          el.addEventListener('click', handleButtonClick);
        });

        // Add an event listener for `Esc` key menu-closing.
        menuRoot.addEventListener('keydown', handleEscKeydown);

        // Tag the menu as processed.
        menuRoot.classList.add(menuProcessedClass);
      }

      /**
       * Handles submenu item toggle button clicks.
       */
      function handleButtonClick(ev) {
        // We'll handle the whole behaviour in script.
        ev.preventDefault();

        const expanded = ev.target.getAttribute('aria-expanded') === 'true';
        const menuRoot = ev.target.closest(submenusSelector);

        // When the menu corresponding to a button is already expanded.
        if (expanded) {
          closeMenu(ev.target, menuRoot);
        }
        // When the corresponding menu is not already expanded.
        else {
          openMenu(ev.target, menuRoot);
        }
      }

      /**
       * Opens the menu corresponding to a button element.
       *
       * To OPEN a menu, we need to:
       *
       * - Open the menu associated with the button,
       * - Close all other menus EXCEPT the parent menus,
       * - Set arial-label attributes appropriately.
       */
      function openMenu(toggle, menuRoot) {
        const allToggles = menuRoot.querySelectorAll(toggleSelector);
        const parentToggles = parentControls(toggle, menuRoot);

        // Close all menus that aren't parents of the clicked button.
        allToggles.forEach(el => {
          if (parentToggles.indexOf(el) === -1) {
            closeMenu(el, menuRoot);
          }
        });

        // Open the menu corresponding to the click.
        toggle.setAttribute('aria-expanded', true);
        toggle.setAttribute('aria-label', menuRoot.dataset.menuCloseLabel);
      }

      /**
       * Toggles closed already-open submenus.
       */
      function closeMenu(toggle, menuRoot) {
        // Close the menu associated with this control only, unless
        // `button` is set to "all" in which case, we indiscriminately
        // close everything.
        const toggleParent = (toggle === 'all') ? menuRoot.closest(submenusSelector) : toggle.parentNode;
        const closeToggles = toggleParent.querySelectorAll(toggleSelector);

        closeToggles.forEach(el => {
          el.setAttribute('aria-expanded', false);
          el.setAttribute('aria-label', menuRoot.dataset.menuOpenLabel);
        });
      }

      /**
       * Returns an array of buttons belonging to parents of the current button.
       */
      function parentControls(element, menuRoot) {
        // Start with some initial variables.
        let parentElement = '';
        let parentElements = [element];
        let parentControls = [];

        // Keep getting parent elements until we reach the root of this menu.
        while (parentElement !== menuRoot) {
          // Get the parent node and add it to our array.
          parentElement = parentElements.slice(-1).pop().parentNode;
          parentElements.push(parentElement);

          // If this particular parent element is one of our list-items,
          // search it for button elements.
          if (parentElement.classList.contains('menu-item')) {
            // Loop over its children and add any buttons found to the return
            // array.
            const childElements = parentElement.children;

            for (let i = 0; i < childElements.length; i++) {
              if (childElements[i].classList.contains('sub-menu-item-toggle') && childElements[i] !== element) {
                parentControls.push(childElements[i]);

                // There can only ever be one button as a direct child
                // of a list item, so if we've found one, exit the loop.
                break;
              }
            }
          }
        }

        return parentControls;
      }

      /**
       * Allows open menus to be closed with the escape key.
       *
       * Note that this requires the menu element or some child element to have
       * focus to work. For example a menu toggled open with the mouse can't
       * be closed by the escape key until it gains focus by a click or a tab.
       */
      function handleEscKeydown(ev) {
        ev = ev || window.event;

        const isEscape = (('key' in ev) && (ev.key.substring(0, 3) === 'Esc')) || (ev.keyCode == 27);

        if (isEscape) {
          closeMenu('all', ev.target);
        }
      }
    }
  };
})(Drupal);
