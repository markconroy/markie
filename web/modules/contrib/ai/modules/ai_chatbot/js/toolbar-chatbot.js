(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.aiChatbot = {
    attach: function (context, settings) {
      // Set the chatbot open state explicitly and persist it. This is
      // idempotent, so re-running it cannot flip the chatbot: when a
      // CKEditor or Media Library dialog re-attaches behaviors and the
      // toolbar button is processed again, restoring an already-open
      // chatbot is a no-op instead of a blind toggle that closed it.
      const setChatbotOpen = (open) => {
        document.body.classList.toggle('ai-chatbot-opened', open);

        if (open && window.Drupal.ginCoreNavigation) {
          window.Drupal.ginCoreNavigation.collapseToolbar();
        }

        window.localStorage.setItem('Drupal.ai.chatbotOpened', open ? 'true' : 'false');
      };

      const toggleChatbot = () => {
        setChatbotOpen(!document.body.classList.contains('ai-chatbot-opened'));
      };

      once('ai-chatbot', '.button--ai-chatbot', context).forEach(($toolbarIcon) => {
        $toolbarIcon.classList.remove('hidden');

        if ($toolbarIcon.classList.contains('button--primary')) {
          $toolbarIcon.classList.add('button');
        }

        setChatbotOpen(
          window.localStorage.getItem('Drupal.ai.chatbotOpened') === 'true',
        );

        $toolbarIcon.addEventListener('click', (e) => {
          toggleChatbot();
        });
      });

      once('ai-chatbot-toolbar', '.ai-deepchat.toolbar', context).forEach(($chatContainer) => {
        const $dropdownMenu = $chatContainer.querySelector('.chat-dropdown');
        const $menuButton = $chatContainer.querySelector('.chat-dropdown-button');
        const $clearHistoryButton = $chatContainer.querySelector('.clear-history');
        const $closeButton = $chatContainer.querySelector('.toolbar-button.close');
        const $expandButton = $chatContainer.querySelector('.toolbar-button.expand');
        const $blockWrapper = $chatContainer.closest('.block-ai-deepchat-block');

        const toggleMenu = (event) => {
          // Don't run parent event
          event.stopPropagation();
          // Toggle it
          $dropdownMenu.classList.toggle('active');
        }

        $menuButton.addEventListener('click', toggleMenu)

        // Fullscreen functionality
        const exitFullscreen = (callback) => {
          if ($blockWrapper && document.body.classList.contains('ai-chatbot-fullscreen')) {
            $blockWrapper.addEventListener('animationend', (e) => {
              if (e.animationName === 'ai-chatbot-fade-out') {
                document.body.classList.remove('ai-chatbot-fullscreen');
                $blockWrapper.style.opacity = '0';
                $blockWrapper.classList.remove('ai-chatbot-fade-out');
                requestAnimationFrame(() => {
                  requestAnimationFrame(() => {
                    $blockWrapper.style.opacity = '';
                    $blockWrapper.classList.remove('ai-chatbot-exiting-fullscreen');
                    resetExpandState();
                    callback?.();
                  });
                });
              }
            }, { once: true });

            $blockWrapper.classList.add('ai-chatbot-fade-out', 'ai-chatbot-exiting-fullscreen');
          } else {
            callback?.();
          }
        };

        const enterFullscreen = () => {
          if ($blockWrapper) {
            $blockWrapper.addEventListener('animationend', (e) => {
              if (e.animationName === 'ai-chatbot-fade-in') {
                $blockWrapper.classList.remove('ai-chatbot-fade-in');
              }
            }, { once: true });

            document.body.classList.add('ai-chatbot-fullscreen');
            $blockWrapper.classList.add('ai-chatbot-fade-in');
            $expandButton?.classList.toggle('active');
            $expandButton?.setAttribute('aria-expanded', 'true');
          }
        };

        const resetExpandState = () => {
          $expandButton?.classList.remove('active');
          $expandButton?.setAttribute('aria-expanded', 'false');
        };

        // Menu items
        $clearHistoryButton.addEventListener('click', (e) => {
          Drupal.clearDeepchatMessages(e);
          toggleMenu(e);
        });

        $closeButton.addEventListener('click', (e) => {
          if (document.body.classList.contains('ai-chatbot-fullscreen')) {
            exitFullscreen(() => toggleChatbot());
            return;
          }

          if (document.body.classList.contains('ai-chatbot-expanded')) {
            document.body.classList.remove('ai-chatbot-expanded');
            resetExpandState();
          }

          toggleChatbot();
        });

        if ($expandButton) {
          const expansionMethod = $expandButton.getAttribute('data-expansion-method') || 'expand';

          $expandButton.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();

            if (expansionMethod === 'fullscreen') {
              const isFullscreen = document.body.classList.contains('ai-chatbot-fullscreen');

              if (isFullscreen) {
                exitFullscreen();
              } else {
                enterFullscreen();
              }
            } else {
              document.body.classList.toggle('ai-chatbot-expanded');
              $expandButton?.classList.toggle('active');
              $expandButton?.setAttribute('aria-expanded', $expandButton?.classList.contains('active') ? 'true' : 'false');
            }
          });
        }

        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape') {
            if (document.body.classList.contains('ai-chatbot-fullscreen')) {
              e.preventDefault();
              exitFullscreen();
            }
          }
        });
      })
    }
  };

})(Drupal, once);
