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
      if (Drupal.behaviors.deepChatToggle.initialized) {
        return;
      }
      // Select all chat containers within the current context
      const chatContainers = context.querySelectorAll('.chat-container');
      const dropDownMenu = context.querySelector('.chat-dropdown');
      // If its not found, return.
      if (!dropDownMenu) {
        return;
      }
      // Mark as initialized to prevent re-processing
      Drupal.behaviors.deepChatToggle.initialized = true;
      const chevron = context.querySelector('.chevron-icon');
      const menuButton = dropDownMenu.querySelector('.chat-dropdown-button');
      const clearHistory = dropDownMenu.querySelector('.clear-history');

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

        // Select the header element within the container
        const header = container.querySelector('.ai-deepchat--header');
        if (!header) {
          console.warn('Header with class .ai-deepchat--header not found in container:', container);
          return;
        }

        // Select the chat element within the container
        const chatElement = container.querySelector('.chat-element');
        if (!chatElement) {
          console.warn('Chat element with class .chat-element not found in container:', container);
          return;
        }

        // Optional: Select the toggle icon if present
        const toggleIcon = header.querySelector('.toggle-icon');

        // Select the actual deepchat element
        const deepchatElement = container.querySelector('.deepchat-element');
        Drupal.behaviors.deepChatToggle.chats.push(deepchatElement);

        // Function to set thread_id.
        const setThreadId = (thread_id) => {
          let connect = JSON.parse(deepchatElement.getAttribute('connect'));
          connect.additionalBodyProps.thread_id = thread_id;
          deepchatElement.setAttribute('connect', JSON.stringify(connect));
          // Reset thread_id in Drupal setting in case of rerendering.
          drupalSettings.ai_deepchat.thread_id = thread_id;
        }

        // Assign thread_id to chat.
        setThreadId(drupalSettings.ai_deepchat.thread_id);

        // Function to update toggle icon (optional)
        const updateToggleIcon = (isOpen) => {
          if (toggleIcon) {
            toggleIcon.classList.toggle('is-opened', !isOpen);
            toggleIcon.classList.toggle('is-closed', isOpen);
          }
        };

        // Function to open the chat
        const openChat = () => {
          container.classList.add('chat-open');
          container.classList.remove('chat-collapsed');
          container.classList.remove('chat-collapsed-minimal');
          header.classList.add('active');
          header.setAttribute('aria-expanded', 'true');
          if (toggleIcon) updateToggleIcon(true);
          localStorage.setItem(`deepChatState_${chatId}`, 'open');
        };

        // Function to close the chat
        const closeChat = () => {
          container.classList.add('chat-collapsed');
          if (drupalSettings.ai_deepchat.collapse_minimal) {
            container.classList.add('chat-collapsed-minimal');
          }
          container.classList.remove('chat-open');
          header.classList.remove('active');
          header.setAttribute('aria-expanded', 'false');
          if (toggleIcon) updateToggleIcon(false);
          localStorage.removeItem(`deepChatState_${chatId}`);
        };

        // Function to clear messages
        const clearMessages = (event) => {
          // Don't run parent event
          event.stopPropagation();
          // Close the menu
          toggleMenu(event);
          // Make a request to clear the history.
          let url = drupalSettings.path.baseUrl + 'ajax/chatbot/reset-session/' + drupalSettings.ai_deepchat.assistant_id + '/' + drupalSettings.ai_deepchat.thread_id;
          fetch(url, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
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

        // Toggle function
        const toggleChat = () => {
          if (container.classList.contains('chat-open')) {
            closeChat();
          } else {
            openChat();
          }
        };

        // Toggle menu
        const toggleMenu = (event) => {
          // Don't run parent event
          event.stopPropagation();
          // Toggle it
          dropDownMenu.classList.toggle('active');
          chevron.classList.toggle('rotate');
        }

        // Attach click event listener to the header
        header.addEventListener('click', toggleChat);

        // Attach keypress event listener for accessibility (e.g., Enter or Space keys)
        header.addEventListener('keypress', (event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            toggleChat();
          }
        });

        // Initialize state from localStorage
        const savedState = localStorage.getItem(`deepChatState_${chatId}`);
        if (savedState === 'open') {
          openChat();
        } else {
          closeChat();
        }

        // Add retry on error
        deepchatElement.addEventListener('error', handleError);

        // We need to know if we should automatically continuer to agent.
        deepchatElement.responseInterceptor = (response) => {
          Drupal.behaviors.deepChatToggle.shouldContinue = response.should_continue || false;
          if (response.should_continue) {
            Drupal.behaviors.deepChatToggle.stepMessages.push(response.html);
            const html = response.html;
            response.html = '<div class="loading-wrapper"><span class="loading-span">' + Drupal.t('Contacting agents..') + '</span>';
            response.html += `<details class="step-messages loading-text"><summary class="step-messages-summary">`;
            response.html += Drupal.t('Details') + `</summary>` + html + `</details></div>`;
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
                Drupal.dialog(details, {
                  'title': Drupal.t('Structured results'),
                  'width': 'auto',
                  'buttons': {
                    'close': {
                      'text': Drupal.t('Close'),
                      'click': () => {
                        Drupal.dialog.close();
                      }
                    }
                  }
                }).showModal();
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
          // Add the history to the chat.
          if (drupalSettings.ai_deepchat.messages.length > 0) {
            for (let message of drupalSettings.ai_deepchat.messages) {
              deepchatElement.addMessage(message);
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

        // Toggle the menu
        menuButton.addEventListener('click', toggleMenu);

        // Menu items
        clearHistory.addEventListener('click', clearMessages);
      });
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

  function getAllMessages(deepchatElement) {
    // Start processing.
    Drupal.behaviors.deepChatToggle.processing = true;
    const n = (deepchatElement.getMessages().length - 1);
    fetch(deepchatElement.connect.url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        thread_id: drupalSettings.ai_deepchat.thread_id,
        assistant_id: drupalSettings.ai_deepchat.assistant_id,
        show_copy_icon: drupalSettings.ai_deepchat.show_copy_icon,
        structured_results: drupalSettings.ai_deepchat.structured_results,
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
      if ("should_continue" in data && data.should_continue) {
        Drupal.behaviors.deepChatToggle.stepMessages.push(data.html);
        let open = Drupal.behaviors.deepChatToggle.agentUsageIsOpen ? 'open' : '';
        let html = `<div class="loading-wrapper"><span class="loading-span">` + Drupal.t('Calling agents..') + '</span>';
        html += `<details class="step-messages loading-text" ${open}><summary class="step-messages-summary">`;
        html += Drupal.t('Details') + `</summary>` + data.html + `</details></div>`;

        // Store the messages in the stepMessages array.

        // We just replace the message.
        deepchatElement.updateMessage({
          role: 'ai',
          html: html,
        }, n);
        // Rerun the request to get the next messages.
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
