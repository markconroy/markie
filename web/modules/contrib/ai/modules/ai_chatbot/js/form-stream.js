(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.searchApiAiStream = {
    attach: (context) => {
      // Set the assistant id.
      $('.chat-form-assistant-id').val(drupalSettings.ai_chatbot.assistant_id);
      let streamElements = $('[data-ai-ajax]', context);
      // @todo: Move away from once() since its not in core?
      once('data-streamed', streamElements).forEach((item) => {
        const element = $(item);
        const form = element.closest('form');

        // Set up a key down handler to submit the form on Enter press in textarea.
        form.find('.chat-form-query').on('keydown', (event) => {
          if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.find('.chat-form-send').click();
          }
        });

        // Set up a click handler to submit the form and stream the response back.
        element.click((event) => {
          event.preventDefault();

          const clickedElement = $(event.currentTarget);
          const clickedBlock = clickedElement.attr('data-ai-ajax');
          // Get the message from the textarea.
          let message = form.find('.chat-form-query').val();
          renderUserChatMessage(message)
          .then(() => {
            renderBotChatMessage(form);
          });
        });
      });
    }
  };

  function renderUserChatMessage(message) {
    let converter = new showdown.Converter();
    return new Promise((resolve, reject) => {
      $.ajax({
        url: drupalSettings.path.baseUrl + 'ajax/chatbot/message-skeleton'
      })
      .done((data) => {
        let skeleton = data.skeleton;
        $('.chat-history').append(skeleton);
        $('.chat-history .chat-message:last h5').html(drupalSettings.ai_chatbot.default_username);
        $('.chat-history .chat-message:last img').attr('src', drupalSettings.ai_chatbot.default_avatar);
        let responseField = $('.chat-history .chat-message:last');
        responseField.find('.chat-message-message').html(converter.makeHtml(message));
        $('.chat-history').scrollTop($('.chat-history')[0].scrollHeight);
        return resolve();
      })
      .fail(() => {
        return reject();
      });
    });
  }

  function renderBotChatMessage(form) {
    let converter = new showdown.Converter({
      disableForced4SpacesIndentedSublists: true,
      tables: true,
      smoothLivePreview: true,
      parseImgDimensions: true
    });
    $.ajax({
      url: drupalSettings.path.baseUrl + 'ajax/chatbot/message-skeleton'
    })
    .done((data) => {
      let skeleton = data.skeleton;
      $('.chat-history').append(skeleton);
      $('.chat-history .chat-message:last h5').html(drupalSettings.ai_chatbot.bot_name);
      $('.chat-history .chat-message:last img').attr('src', drupalSettings.ai_chatbot.bot_image);
      let responseField = $('.chat-history .chat-message:last .chat-message-message');
      $('.chat-history').scrollTop($('.chat-history')[0].scrollHeight);
      let postData = form.serializeArray();
      // Check while creating if its HTML or not.
      let isHtml = false;
      $('.chat-form-query').val('');
      $.ajax({
        url: form.attr('action'),
        method: 'POST',
        data: postData,
        xhrFields: {
          onprogress: function (event) {
            // Actual HTML test.
            if (!isHtml && /<\/?[a-z][\s\S]*>/i.test(event.currentTarget.response)) {
              isHtml = true;
            }
            responseField.html(isHtml ? event.currentTarget.response : converter.makeHtml(event.currentTarget.response));
            $('.chat-history').scrollTop($('.chat-history')[0].scrollHeight);
          },
          onended: function (event) {
            // Also add on the last event.
            if (!isHtml && /<\/?[a-z][\s\S]*>/i.test(event.currentTarget.response)) {
              isHtml = true;
            }
            responseField.html(isHtml ? event.currentTarget.response : converter.makeHtml(event.currentTarget.response));
            $('.chat-history').scrollTop($('.chat-history')[0].scrollHeight);
          }
        }
      });
    });
  }

  // Logic for minimizing the chatbot.
  $(document).ready(() => {

    let chatStatus = 'true';
    if (drupalSettings.ai_chatbot.toggle_state == 'remember') {
      chatStatus = localStorage.getItem("livechat.closed");
    }
    else if (drupalSettings.ai_chatbot.toggle_state == 'open') {
      chatStatus = 'false';
    }
    if (chatStatus == 'false') {
      $('#live-chat .chat').show();
    }
    $('#live-chat header').click(function() {
      $('.chat').toggle(function () {
        localStorage.setItem("livechat.closed", localStorage.getItem("livechat.closed") == 'true' ? 'false' : 'true');
        $(this).animate({
          display: 'block',
        }, 100);
      })
    });
    expandTextarea('edit-query');
  });

  function expandTextarea(id) {
    document.getElementById(id).addEventListener('keyup', function () {
      this.style.overflow = 'hidden';
      this.style.height = 0;
      this.style.height = this.scrollHeight + 'px';
    }, false);
  }

})(jQuery, Drupal, drupalSettings);


