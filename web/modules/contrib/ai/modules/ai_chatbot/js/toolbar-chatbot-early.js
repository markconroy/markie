/**
 * @file
 * Marks the restored chatbot open state before the first paint.
 *
 * Attached to the page head via hook_page_attachments() so the
 * transition-suppression class is in place before rendering starts. This
 * keeps a chatbot that was left open from replaying its fold-out animation
 * on page load, including when the chatbot block arrives in a late BigPipe
 * chunk. The class is removed after the window load event, once the
 * restored state has painted, so user-initiated toggles animate normally.
 */
(function () {
  'use strict';

  try {
    if (window.localStorage.getItem('Drupal.ai.chatbotOpened') !== 'true') {
      return;
    }
  } catch (e) {
    // localStorage is unavailable; the open state is simply not restored.
    return;
  }

  document.documentElement.classList.add('ai-chatbot-restoring');

  window.addEventListener(
    'load',
    function () {
      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          document.documentElement.classList.remove('ai-chatbot-restoring');
        });
      });
    },
    { once: true },
  );
})();
