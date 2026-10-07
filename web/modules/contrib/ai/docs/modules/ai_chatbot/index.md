# AI Chatbot

## What is the AI Chatbot module?
The AI Chatbot module provides a user interface for chatbot interactions,
consisting of a block with a text-input that processes user messages through
ChatProcessor plugins and renders AI responses as user-readable text.

## ChatProcessor Plugin System
The module uses the AI module's ChatProcessor plugin system to process chat
input and generate responses. This decouples the UI from the processing logic,
allowing for:

- Custom chatbot behaviors (RAG, tool use, etc.)
- Integration with different AI services
- Specialized processing workflows
- Backward compatibility with AI Assistant API

For information on creating custom ChatProcessor plugins, see
[Writing a ChatProcessor Plugin](../../developers/writing_a_chat_processor_plugin.md).

## Built-in Plugins
The module includes two reference implementations:

- **AI Assistant API Processor**: Provides backward compatibility with the
  existing [AI Assistant API](../ai_assistant_api/index.md)
- **RAG Processor**: Simple RAG (Retrieval-Augmented Generation) implementation

## Dependencies
1. The AI module must be installed and configured.
2. At least one Provider module must be enabled and configured.

## How to configure the AI Chatbot module
1. Enable the AI Chatbot module.
2. Visit /admin/structure/block
3. Choose a region of your theme template and click the "place" button.
4. Select the AI Chatbot block and press the place button.
5. Configure the block:
    1. Give it an admin name and user-facing label
    2. Make sure to not show the label.
    3. Select which ChatProcessor plugin to use.
    4. Provide an initial statement to use shown to the user.

### Token-based avatars
The **Default Avatar** field in the chatbot block settings accepts Drupal tokens,
allowing you to dynamically resolve the avatar image for each user. For example:

- `[current-user:user_picture]` — uses the user's picture field.
- `[current-user:profile_type:field_name]` — uses an image field from a Profile
  entity (requires the [Profile](https://www.drupal.org/project/profile) module).
  Replace `profile_type` with the machine name of the profile type and
  `field_name` with the image field name, e.g.
  `[current-user:admin_profile:field_profile_image:entity:url]`.

When a token resolves to an `<img>` HTML tag, the `src` URL is automatically
extracted. If the token produces no result, the block falls back to the user's
`user_picture` field. If that is also empty, no avatar is displayed.

## How to use the AI Chatbot
When an AI Chatbot block is placed on a page, it will display its label to the
user. If the user clicks it, it will open a form to allow the user to pass
messages via the configured ChatProcessor plugin and see the responses.

The message history can be retained inside the block until the page is reloaded
or the user navigates away, depending on settings.

## Customize the Chatbot
The Chatbot is based on [Deepchat](https://deepchat.dev/) by OvidijusParsiunas
and can be customized both in look and feel. The designing of the chatbot is
done via attributes, see the
[documentation on deepchat.dev](https://deepchat.dev/examples/design), but we
have abstracted it away into
[yaml files](https://git.drupalcode.org/project/ai/-/blob/1.0.x/modules/ai_chatbot/deepchat_styles/bard.yml?ref_type=heads).

You can in your active theme add a folder called deepchat_styles and load your
own custom themes to use.

There is also a hook called
[hook_deepchat_settings](https://git.drupalcode.org/project/ai/-/blob/1.0.x/modules/ai_chatbot/ai_chatbot.api.php?ref_type=heads)
where you can change the attributes on the fly.
