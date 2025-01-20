<?php

/**
 * @file
 * Hooks related to AI Chatbot.
 */

use Drupal\Core\Url;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the Deepchat rendering array.
 *
 * Read more about it in https://deepchat.dev/docs/styles.
 *
 * @param array $deepchat_settings
 *   The array as its being user for attributes on the Deepchat tag.
 */
function hook_deepchat_settings(array &$deepchat_settings) {
  $deepchat_settings['style'] .= 'background-color: #000;';
}

/**
 * Alter the Deepchat buttons.
 *
 * These are buttons that are set after each assistant message, and can be
 * things like copy, like, dislike, etc.
 *
 * @param array $buttons
 *   The buttons array, passed by reference.
 */
function hook_deepchat_buttons_alter(array &$buttons) {
  $buttons[] = [
    'svg' => '/path/to/icon.svg',
    'alt' => 'Alt text',
    'title' => 'Title text',
    'weight' => 0,
    'url' => Url::fromRoute('node', ['node' => 1]),
  ];
}

/**
 * Prepend any outgoing messages from the AI Assistant.
 *
 * @param string $message
 *   The message to be sent.
 * @param string $type
 *   The type of message (text/html).
 * @param string $assistant_id
 *   The assistant id.
 * @param string $thread_id
 *   The thread id.
 *
 * @return string
 *   The message to be prepended.
 */
function hook_deepchat_prepend_message($message, $type, $assistant_id, $thread_id) {
  return "Did you like this message?";
}

/**
 * @} End of "addtogroup hooks".
 */
