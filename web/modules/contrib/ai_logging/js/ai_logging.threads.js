/**
 * @file
 * Expands or collapses a thread page's calls, and opens a linked call.
 */

((Drupal, once) => {
  /**
   * Opens the collapsed call a URL fragment points to.
   *
   * @param {string} hash
   *   The URL fragment, with its leading "#".
   */
  function openTarget(hash) {
    if (!hash || hash.length < 2) {
      return;
    }
    let id;
    try {
      id = decodeURIComponent(hash.slice(1));
    } catch {
      return;
    }
    const target = document.getElementById(id);
    if (target && target.tagName === 'DETAILS') {
      target.open = true;
    }
  }

  /**
   * Expands or collapses every call in the thread.
   *
   * @param {boolean} open
   *   TRUE to expand them, FALSE to collapse them.
   */
  function setAllOpen(open) {
    document
      .querySelectorAll('.ai-logging-thread > details')
      .forEach((details) => {
        details.open = open;
      });
  }

  /**
   * Makes the thread page's contents and buttons open calls.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Shows the expand and collapse buttons, and opens a call when the
   *   contents or the page's URL link to it.
   */
  Drupal.behaviors.aiLoggingThread = {
    attach(context) {
      once(
        'ai-logging-thread-actions',
        '[data-ai-logging-actions]',
        context,
      ).forEach((actions) => {
        actions.hidden = false;
      });
      once(
        'ai-logging-thread-toggle',
        '[data-ai-logging-details]',
        context,
      ).forEach((button) => {
        button.addEventListener('click', () => {
          setAllOpen(button.dataset.aiLoggingDetails === 'open');
        });
      });
      once(
        'ai-logging-thread-contents',
        '.ai-logging-contents__link',
        context,
      ).forEach((link) => {
        link.addEventListener('click', () => openTarget(link.hash));
      });
      // A link to a call from elsewhere, e.g. a copied URL.
      once('ai-logging-thread-hash', '.ai-logging-thread', context).forEach(
        () => openTarget(window.location.hash),
      );
    },
  };
})(Drupal, once);
