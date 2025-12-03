/**
 * @file
 * Enables syntax highlighting via HighlightJs on the HTML code tag.
 */

/* global ClipboardJS, hljs */

(function ($, Drupal, drupalSettings, once) {
  function showCopiedMessage(copySuccessText, buttonElement) {
    const copyMessage = $(buttonElement).siblings('.copy-message');
    copyMessage.text(copySuccessText);
    setTimeout(() => {
      copyMessage.text('');
    }, 2000);
  }

  Drupal.behaviors.highlight_js = {
    attach(context, settings) {
      // Initialize Clipboard.js
      const clipboard = new ClipboardJS('.copy-btn');
      clipboard.on('success', (e) => {
        // This triggers the deselection of the content text.
        e.clearSelection();
        if (settings.button_data.copy_success_text !== '') {
          showCopiedMessage(settings.button_data.copy_success_text, e.trigger);
        }
      });

      // Provide the body font as CSS variable to be used for the copy button.
      // Will otherwise be inherited from pre element (monospace).
      const bodyFont = getComputedStyle(document.body).fontFamily || 'inherit';
      document.documentElement.style.setProperty(
        '--hljs-inherit-body-font',
        bodyFont,
      );

      once('highlight-js', 'code', context).forEach((block, i) => {
        let inline = false;
        // Do not guess language on inline code.
        if (!(block.parentElement.tagName.toLowerCase() === 'pre')) {
          block.classList.add('language-plaintext');
          inline = true;
        }

        hljs.highlightElement(block);

        const copyEnable = settings.button_data.copy_enable;
        const copyBgTransparent = settings.button_data.copy_bg_transparent;
        let copyBgColor = settings.button_data.copy_bg_color;
        const copyTxtColor = settings.button_data.copy_txt_color;
        const copyBtnText = settings.button_data.copy_btn_text;
        const successBgTransparent =
          settings.button_data.success_bg_transparent;
        let successBgColor = settings.button_data.success_bg_color;
        const successTxtColor = settings.button_data.success_txt_color;

        // Wrap the code block with a container and listen for clicks.
        const element = $(block);
        const wrapper = $('<div/>', { class: 'code-container' });

        if (inline) {
          wrapper.addClass('code-container--inline');
        } else {
          wrapper.removeClass('code-container--inline');
        }

        wrapper.on('click', (e) => {
          const $btn = $(e.currentTarget).find('.copy-btn');
          // If the click did NOT originate on a .copy-btn and there *is* a
          // copy-btn inside, trigger its click.
          if (!$(e.target).closest('.copy-btn').length && $btn.length) {
            $btn.trigger('click');
          }
        });

        element.wrap(wrapper);

        // Add copy button.
        if (copyEnable && element.attr('copy-disabled') === undefined) {
          if (copyBgTransparent) {
            copyBgColor = 'transparent';
          }
          const buttonStyle = `style="--copy-bg-color: ${copyBgColor}; color: ${copyTxtColor};"`;

          if (successBgTransparent) {
            successBgColor = 'transparent';
          }
          const successMsgStyle = `style="--success-bg-color: ${successBgColor}; color: ${successTxtColor};"`;

          const copyBtn = $(
            `<div ${buttonStyle} class="copy-btn" data-clipboard-target="#code-${i}">${copyBtnText}</div><div ${successMsgStyle} class="copy-message" id="copy-message"></div>`,
          );
          element.before(copyBtn);
        }

        // Add an ID to the code block for Clipboard.js to target.
        element.attr('id', `code-${i}`);
      });
    },
  };
})(jQuery, Drupal, drupalSettings, once);
