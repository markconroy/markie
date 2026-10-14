/**
 * @file
 * Initialises the Deep Chat widget and loads its bundle on demand.
 *
 * The loader lives in this file rather than in one of its own because
 * Drupal.aiDeepchat.whenNeeded() below is its only caller: shipping the two
 * as separate library files cost an extra request and made the init half
 * depend on the loader half having been evaluated first.
 *
 * The Deep Chat bundle can be a heavy asset to load, and it is only needed
 * once the chat is opened, so it is kept out of the ai_chatbot/deepchat
 * library and imported from here according to the block's loading strategy.
 *
 * The strategy is resolved in the browser on purpose: with a "remember" toggle
 * state the open/closed state lives in localStorage, so deciding server side
 * would need a per user cache context and would make the block uncacheable.
 */
((Drupal, drupalSettings) => {
  'use strict';

  Drupal.aiDeepchat = Drupal.aiDeepchat || {};

  /**
   * Imports the bundle once, whatever the number of chats on the page.
   *
   * @return {Promise}
   *   Resolves once the <deep-chat> custom element is defined. Callers must
   *   wait for this before touching the element: properties set on a custom
   *   element before it upgrades are shadowed by the class definition.
   */
  Drupal.aiDeepchat.load = () => {
    if (!Drupal.aiDeepchat.loading) {
      const strategy = drupalSettings.ai_deepchat.load_strategy || 'eager';
      const url = drupalSettings.ai_deepchat.bundle_url;

      if (strategy === 'eager') {
        // The eager strategy attaches the bundle as a library, so there is
        // nothing left to import and only the upgrade has to be awaited.
        Drupal.aiDeepchat.loading = customElements.whenDefined('deep-chat');
      }
      else if (!url) {
        // DeepChatFormBlock::getBundleUrl() returns an empty string when the
        // library cannot be resolved. Never resolving is deliberate: there is
        // nothing to import, and deepchat-init.js must not configure an
        // element that is never going to upgrade.
        console.error('Deep Chat: the bundle URL is missing, the chat cannot be loaded.');
        Drupal.aiDeepchat.loading = new Promise(() => {});
      }
      else {
        Drupal.aiDeepchat.loading = import(url)
          .then(() => customElements.whenDefined('deep-chat'));
      }
    }

    return Drupal.aiDeepchat.loading;
  };

  /**
   * Warms the HTTP cache without executing the bundle.
   *
   * Called once the visitor shows intent to open the chat, so the click does
   * not have to wait for the download.
   */
  const prefetch = () => {
    const href = drupalSettings.ai_deepchat.bundle_url;

    // Nothing to warm when the block could not resolve the bundle: preloading
    // an empty href would make the browser re-request the current page.
    // Drupal.aiDeepchat.load() reports the failure when the chat is opened.
    if (!href) {
      return;
    }

    if (document.querySelector(`link[rel="modulepreload"][href="${href}"]`)) {
      return;
    }

    const link = document.createElement('link');
    link.rel = 'modulepreload';
    link.href = href;
    document.head.append(link);
  };

  /**
   * Schedules a callback for when the browser is idle.
   */
  const whenIdle = (callback) => {
    if (typeof requestIdleCallback === 'function') {
      requestIdleCallback(callback);
    }
    else {
      setTimeout(callback, 1000);
    }
  };

  /**
   * Resolves when the chat holding the given element needs the bundle.
   *
   * The open state is read from the class the placement's toggle script
   * already sets rather than from the toggle control itself, so this works for
   * every placement without duplicating the toggle_state rules those scripts
   * apply. The watched element depends on the placement: the toolbar toggles a
   * class on <body>, every other placement toggles chat-open on the container.
   *
   * @param {HTMLElement} deepChat
   *   The <deep-chat> element.
   *
   * @return {Promise}
   *   Resolves once the bundle has been loaded.
   */
  Drupal.aiDeepchat.whenNeeded = (deepChat) => {
    const strategy = drupalSettings.ai_deepchat.load_strategy || 'eager';

    if (strategy === 'eager') {
      return Drupal.aiDeepchat.load();
    }

    if (strategy === 'idle') {
      return new Promise((resolve) => {
        whenIdle(() => resolve(Drupal.aiDeepchat.load()));
      });
    }

    const container = deepChat.closest('.chat-container');

    // Without a container there is no collapse machinery to wait for, so the
    // chat is always visible and the bundle is needed straight away.
    if (!container) {
      return Drupal.aiDeepchat.load();
    }

    // The open signal differs per placement. The toolbar chatbot opens by
    // toggling a class on <body> and its launcher lives in the admin toolbar,
    // outside the container; every other placement toggles chat-open on the
    // container itself and is launched from its own header. Resolving this
    // here keeps whenNeeded from having to know which toggle script owns each
    // placement, while still watching the element that script actually
    // mutates.
    const isToolbar = container.classList.contains('toolbar');
    const openTarget = isToolbar ? document.body : container;
    const openClass = isToolbar ? 'ai-chatbot-opened' : 'chat-open';
    const launcherSelector = isToolbar
      ? '.button--ai-chatbot'
      : '.ai-deepchat--header';
    const launcher = isToolbar
      ? document.querySelector(launcherSelector)
      : container.querySelector(launcherSelector);

    if (openTarget.classList.contains(openClass)) {
      return Drupal.aiDeepchat.load();
    }

    // Nothing is fetched until the visitor shows intent. Hovering or focusing
    // the launcher is signal enough, and the fetch is normally finished by the
    // time the click lands, so the first open still feels instant without
    // costing a download to everyone who never opens the chat.
    if (launcher) {
      ['pointerenter', 'focusin'].forEach((event) => {
        launcher.addEventListener(event, prefetch, { once: true });
      });
    }
    else {
      // The launcher can be rendered after this runs, for instance when the
      // toolbar arrives through BigPipe or a lazy builder, so fall back to
      // delegated listeners. pointerover is used rather than pointerenter
      // because pointerenter does not bubble and cannot be delegated.
      const delegate = (event) => {
        if (!event.target.closest || !event.target.closest(launcherSelector)) {
          return;
        }

        document.removeEventListener('pointerover', delegate);
        document.removeEventListener('focusin', delegate);
        prefetch();
      };

      document.addEventListener('pointerover', delegate);
      document.addEventListener('focusin', delegate);
    }

    return new Promise((resolve) => {
      const observer = new MutationObserver(() => {
        if (openTarget.classList.contains(openClass)) {
          observer.disconnect();
          resolve(Drupal.aiDeepchat.load());
        }
      });

      observer.observe(openTarget, {
        attributes: true,
        attributeFilter: ['class'],
      });
    });
  };
})(Drupal, drupalSettings);

(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.deepChatToggle = {
    chats: [],
    initialized: false,
    csrfToken: '',
    shouldContinue: false,
    stepMessages: [],
    agentUsageIsOpen: false,
    processing: false,
    attach: function (context, settings) {
      once('ai-deepchat', 'deep-chat', context).forEach(($deepChat) => {
      // The bundle is loaded on demand, and the <deep-chat> element only
      // accepts the properties set below once it has upgraded.
      Drupal.aiDeepchat.whenNeeded($deepChat).then(() => {

      if (Drupal.behaviors.deepChatToggle.initialized) {
        return;
      }

      // Select all chat containers within the current context
      const chatContainers = context.querySelectorAll('.chat-container');

      // Mark as initialized to prevent re-processing
      Drupal.behaviors.deepChatToggle.initialized = true;

      let pendingRenders = chatContainers.length;
      chatContainers.forEach((container) => {
        // Check if the behavior has already been applied to this container
        if (container.dataset.deepChatToggleInitialized) {
          return; // Skip if already initialized
        }

        // Mark as initialized to prevent re-processing
        container.dataset.deepChatToggleInitialized = 'true';

        // Retrieve the unique chat ID
        const chatId = container.getAttribute('data-chat-id');
        if (!chatId) {
          console.warn('Chat container is missing a data-chat-id attribute.');
          return;
        }

        // Select the actual deepchat element
        const deepchatElement = container.querySelector('.deepchat-element');

        Drupal.behaviors.deepChatToggle.chats.push(deepchatElement);

        // Function to set thread_id.
        const setThreadId = (thread_id) => {
          const connect = JSON.parse(deepchatElement.getAttribute('connect'));
          connect.additionalBodyProps.thread_id = thread_id;
          deepchatElement.setAttribute('connect', JSON.stringify(connect));
          // Reset thread_id in Drupal setting in case of rerendering.
          drupalSettings.ai_deepchat.thread_id = thread_id;
        }

        // Assign thread_id to chat.
        setThreadId(drupalSettings.ai_deepchat.thread_id);

        // Function to clear messages
        Drupal.clearDeepchatMessages = (event) => {
          // Don't run parent event
          event.stopPropagation();
          // Ask the server to rotate the conversation thread. The thread id
          // itself lives server-side in the session; we only identify which
          // chatbot instance to reset.
          const connect = JSON.parse(deepchatElement.getAttribute('connect'));
          fetch(drupalSettings.path.baseUrl + 'api/deepchat/reset', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              thread_id: drupalSettings.ai_deepchat.thread_id,
              chat_processor_plugin: connect.additionalBodyProps.chat_processor_plugin,
              plugin_configuration: connect.additionalBodyProps.plugin_configuration,
            }),
          }).then(response => {
            if (!response.ok) {
              throw new Error('Failed to clear the chat history.');
            }
            return response.json();
          }).then(data => {
            setThreadId(data.thread_id);
            // Clear the messages from the drupal setting so they are not
            // rerendered.
            drupalSettings.ai_deepchat.messages = [];
            deepchatElement.clearMessages(false);
            // Unset connection to force rerendering.
            delete deepchatElement._activeService;
            deepchatElement.onRender();
          });
        }

        // Function to handle errors appropriately.
        const handleError = (event) => {
          // Add a new assistant message.
          deepchatElement.addMessage({
            role: 'assistant',
            html: `<p>` + Drupal.t('You can retry your last message with this button:') + `</p>
            <div class="deep-chat-temporary-message">
        <button class="deep-chat-button deep-chat-suggestion-button" style="margin-top: 5px">` + Drupal.t("Retry last instruction") + `</button>
      </div>`,
          });
        }

        // Add retry on error
        deepchatElement.addEventListener('error', handleError);

        deepchatElement.loadHistory = (index) => {
          // Add the history to the chat.
          if (index === 0 && drupalSettings.ai_deepchat.messages.length > 0) {
            return drupalSettings.ai_deepchat.messages;
          }
          return [];
        }

        // We need to know if we should automatically continuer to agent.
        deepchatElement.responseInterceptor = (response) => {
          Drupal.behaviors.deepChatToggle.shouldContinue = response.should_continue || false;
          if (response.should_continue) {
            response.html = buildStepHtml(response.html);
          }
          return response;
        };

        // When the message is rendered.
        deepchatElement.onMessage = (message) => {
          if (Drupal.behaviors.deepChatToggle.shouldContinue === true && message.message.role === 'ai') {
            Drupal.behaviors.deepChatToggle.shouldContinue = false;
            // Make sure that the submit button is still disabled.
            deepchatElement.disableSubmitButton();
            // Get the following messages.
            getAllMessages(deepchatElement);
          }
        };

        // We create a session when the first message is sent.
        deepchatElement.requestInterceptor = async (request) => {
          // If a session does not exist, we need to set one and get a new csrf.
          if (drupalSettings.ai_deepchat.session_exists === false && !Drupal.behaviors.deepChatToggle.csrfToken) {
            // Get the session and csrf token.
            if (!Drupal.behaviors.deepChatToggle.csrfToken) {
              Drupal.behaviors.deepChatToggle.csrfToken = await Drupal.behaviors.deepChatToggle.getSession();
            }
            // Remove the current ?token= from the connect url.
            let newUrl = deepchatElement.connect.url.replace(/\?token=[^&]+/, '');
            // Add the new csrf token.
            deepchatElement.connect.url = newUrl + '?token=' + Drupal.behaviors.deepChatToggle.csrfToken;
          }

          if (!drupalSettings.ai_deepchat.verbose_mode && drupalSettings.ai_deepchat.loading_message) {
            deepchatElement.addMessage({
              role: 'ai',
              html: '<div class="deep-chat-temporary-message"><span>' + drupalSettings.ai_deepchat.loading_message + '</span></div>',
            });
          }
        }

        deepchatElement.addEventListener('render', async () => {
          pendingRenders--;

          // Some extra theming.
          deepchatElement.htmlClassUtilities['chat-button-link'] = {
            "styles": {
              "default": {
                "text-decoration": "none"
              }
            }
          }
          deepchatElement.htmlClassUtilities['chat-button'] = {
            "styles": {
              "default": {
                "width": "20px",
                "height": "20px",
                "display": "inline",
                "float": "none",
                "cursor": "pointer"
              }
            }
          }
          // Loading when using multiple tools/agents.
          deepchatElement.htmlClassUtilities['loading-span'] = {
            "styles": {
              "default": {
                "color": "#555",
                "animation": "pulse-color 2s infinite",
              }
            }
          }
          deepchatElement.htmlClassUtilities['step-messages'] = {
            events: {
              toggle: (event) => {
                Drupal.behaviors.deepChatToggle.agentUsageIsOpen = event.target.open;
              }
            }
          },
          deepchatElement.htmlClassUtilities['loading-wrapper'] = {
            "styles": {
              "default": {
                "padding-bottom": "10px",
              }
            }
          },
          deepchatElement.htmlClassUtilities['loading-text'] = {
            "styles": {
              "default": {
                "color": "#888",
                "font-style": "italic",
              }
            }
          }
          // Hide the structured results dump.
          deepchatElement.htmlClassUtilities['structured-results-dump'] = {
            'styles': {
              'default': {
                'display': 'none',
              }
            }
          }
          // Structured results should open in modal.
          deepchatElement.htmlClassUtilities['structured-results'] = {
            'events': {
              'click': (event) => {
                event.preventDefault();
                let details = event.target.closest('.message-bubble').querySelector('.structured-results-dump').cloneNode(true);
                const dialog = Drupal.dialog(details, {
                  'title': Drupal.t('Structured results'),
                  'width': 'auto',
                  'buttons': {
                    'close': {
                      'text': Drupal.t('Close'),
                      'click': () => {
                        dialog.close();
                      }
                    }
                  }
                });
                dialog.showModal();
              }
            }
          }
          // Copy function.
          deepchatElement.htmlClassUtilities['copy'] = {
            "events": {
              "click": (event) => {
                event.preventDefault();
                let text = event.target.closest('.message-bubble').innerText;
                navigator.clipboard.writeText(text);
              }
            }
          }

          // When all chatbots are rendered.
          if (pendingRenders === 0) {
            // Add event listener for the initialized event.
            const event = new CustomEvent('DrupalDeepchatInitialized', {
              detail: {
                chats: this.chats,
              }
            });
            // Disatch the event.
            document.dispatchEvent(event);
          }
        })
      });
      });
    })
    },
    getSession: async function () {
      return new Promise((resolve, reject) => {
        // Set a session and get a csrf token.
        fetch(drupalSettings.path.baseUrl + 'api/deepchat/session', {
          method: 'POST',
        }).then(response => {
          if (!response.ok) {
            resolve({ error: 'Failed to set session.' });
          }
          return response.text();
        }).then(token => {
          Drupal.behaviors.deepChatToggle.csrfToken = token;
          resolve(token);
        });
      })
    },
  }

  function buildStepHtml(html) {
    Drupal.behaviors.deepChatToggle.stepMessages.push(html);
    let open = Drupal.behaviors.deepChatToggle.agentUsageIsOpen ? 'open' : '';
    let wrapped = `<div class="loading-wrapper"><span class="loading-span">${drupalSettings.ai_deepchat.agent_delegation_message || Drupal.t('Contacting agents...')}</span>`;
    wrapped += `<details class="step-messages loading-text" ${open}><summary class="step-messages-summary">`;
    wrapped += `${Drupal.t('Details')}</summary>${html}</details></div>`;
    return wrapped;
  }

  function getAllMessages(deepchatElement) {
    // Start processing.
    Drupal.behaviors.deepChatToggle.processing = true;
    const connect = JSON.parse(deepchatElement.getAttribute('connect'));
    const n = (deepchatElement.getMessages().length - 1);
    fetch(deepchatElement.connect.url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        chat_processor_plugin: connect.additionalBodyProps.chat_processor_plugin,
        plugin_configuration: connect.additionalBodyProps.plugin_configuration,
        thread_id: drupalSettings.ai_deepchat.thread_id,
        show_copy_icon: drupalSettings.ai_deepchat.show_copy_icon,
        messages: [
          {
            role: 'user',
            text: 'dummy_loading', // Dummy message to trigger.
          }
        ]
      }),
    }).then(response => {
      if (!response.ok) {
        throw new Error('Failed to get messages.');
      }
      return response.json();
    }).then(data => {
      if (deepchatElement.responseInterceptor) {
        data = deepchatElement.responseInterceptor(data);
      }
      if ("should_continue" in data && data.should_continue) {
        deepchatElement.updateMessage({
          role: 'ai',
          html: data.html,
        }, n);
        getAllMessages(deepchatElement);
      }
      else {
        // End the processing.
        Drupal.behaviors.deepChatToggle.processing = false;
        // Reset the agent usage open state.
        Drupal.behaviors.deepChatToggle.agentUsageIsOpen = false;
        // Create a details on the top of the chat with the step messages.
        let details = '';
        if (Drupal.behaviors.deepChatToggle.stepMessages.length > 0) {
          details = `<details class="step-messages loading-text"><summary class="step-messages-summary">`
          details += Drupal.t('Details') + `</summary><ol><li>`;
          details += Drupal.behaviors.deepChatToggle.stepMessages.join('</li><li>');
          details += `</li></ol></details>`;
        }
        if ("error" in data) {
          // Add an error message to the chat.
          deepchatElement.updateMessage({
            role: 'assistant',
            html: details + `<p>` + data.error + `</p>`,
          }, n);
        }
        else {
          // We just replace the message.
          deepchatElement.updateMessage({
            role: 'ai',
            html: details + data.html,
          }, n);
        }
        // Enabled submit again.
        deepchatElement.disableSubmitButton(false);
        // Reset the step messages.
        Drupal.behaviors.deepChatToggle.stepMessages = [];
        // Reset the should continue.
        Drupal.behaviors.deepChatToggle.shouldContinue = false;

        // Trigger event when agent call is completed.
        const agentCallEvent = new CustomEvent("agent-call-completed", {
          detail: {
            message: {
              role: 'ai',
              html: data.html
            }
          }
        });
        deepchatElement.dispatchEvent(agentCallEvent);
      }
      // Empty
    }).catch(error => {
      // Add an error message to the chat.
      deepchatElement.updateMessage({
        role: 'assistant',
        html: `<p>` + Drupal.t('An error occurred while fetching messages. Please try again.') + `</p>`,
      }, n);
      deepchatElement.disableSubmitButton(false);
    });

    // Make sure that we do not close while getting answers.
    window.addEventListener("beforeunload", function (e) {
      if (Drupal.behaviors.deepChatToggle.processing) {
        e.preventDefault();
        e.returnValue = Drupal.t("The chat is still processing. Are you sure you want to leave, it might still be modifying things?");
      }
    });
  }
})(Drupal, drupalSettings);
