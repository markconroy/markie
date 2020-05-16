"use strict";/**
 * Provides custom menu expand/collaps behaviour for menu-items.
 *
 * To-do:
 *
 *   - consider how to animate this in javascript (for better overall quality
 *     of animation). Probably this would need to be a configurable option.
 */(function(a){a.behaviors.menuItems={attach:function attach(a){/**
       * Handles menu initialization.
       */function b(a){// If this menu has already been processed, we can stop here.
if(!a.classList.contains("js-navigation")){// Gather some info.
var b=a.querySelectorAll(".sub-menu-item-toggle");// Close all submenus, add event listeners to buttons, and make
// them visible.
b.forEach(function(b){b.setAttribute("aria-expanded",!1),b.setAttribute("aria-label",a.dataset.menuOpenLabel),b.removeAttribute("hidden"),b.addEventListener("click",c)}),a.addEventListener("keydown",g),a.classList.add("js-navigation")}}/**
       * Handles submenu item toggle button clicks.
       */function c(a){a.preventDefault();var b="true"===a.target.getAttribute("aria-expanded"),c=a.target.closest("[data-submenus]");b?e(a.target,c):d(a.target,c)}/**
       * Opens the menu corresponding to a button element.
       *
       * To OPEN a menu, we need to:
       *
       * - Open the menu associated with the button,
       * - Close all other menus EXCEPT the parent menus,
       * - Set arial-label attributes appropriately.
       */function d(a,b){var c=b.querySelectorAll(".sub-menu-item-toggle"),d=f(a,b);// Close all menus that aren't parents of the clicked button.
// Open the menu corresponding to the click.
c.forEach(function(a){-1===d.indexOf(a)&&e(a,b)}),a.setAttribute("aria-expanded",!0),a.setAttribute("aria-label",b.dataset.menuCloseLabel)}/**
       * Toggles closed already-open submenus.
       */function e(a,b){// Close the menu associated with this control only, unless
// `button` is set to "all" in which case, we indiscriminately
// close everything.
var c="all"===a?b.closest("[data-submenus]"):a.parentNode,d=c.querySelectorAll(".sub-menu-item-toggle");d.forEach(function(a){a.setAttribute("aria-expanded",!1),a.setAttribute("aria-label",b.dataset.menuOpenLabel)})}/**
       * Returns an array of buttons belonging to parents of the current button.
       */function f(a,b){// Start with some initial variables.
// Keep getting parent elements until we reach the root of this menu.
for(var c="",d=[a],e=[];c!==b;)// If this particular parent element is one of our list-items,
// search it for button elements.
if(c=d.slice(-1).pop().parentNode,d.push(c),c.classList.contains("menu-item"))// Loop over its children and add any buttons found to the return
// array.
for(var f=c.children,g=0;g<f.length;g++)if(f[g].classList.contains("sub-menu-item-toggle")&&f[g]!==a){e.push(f[g]);// There can only ever be one button as a direct child
// of a list item, so if we've found one, exit the loop.
break}return e}/**
       * Allows open menus to be closed with the escape key.
       *
       * Note that this requires the menu element or some child element to have
       * focus to work. For example a menu toggled open with the mouse can't
       * be closed by the escape key until it gains focus by a click or a tab.
       */function g(a){a=a||window.event;var b="key"in a&&"Esc"===a.key.substring(0,3)||27==a.keyCode;b&&e("all",a.target)}var h=a.querySelectorAll("[data-submenus]");h.forEach(function(a){b(a)})}}})(Drupal);
//# sourceMappingURL=menu-items.js.map
