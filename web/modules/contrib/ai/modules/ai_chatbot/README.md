# AI Chatbot module

## What is the AI Chatbot module?
The AI Chatbot module provides a user interface for chatbot interactions, consisting of a block with a text-input that processes user messages through ChatProcessor plugins and renders AI responses as user-readable text.

## ChatProcessor Plugin System
The module uses the AI module's ChatProcessor plugin system to process chat input and generate responses. This decouples the UI from the processing logic, allowing for:

- Custom chatbot behaviors (RAG, tool use, etc.)
- Integration with different AI services
- Specialized processing workflows
- Backward compatibility with AI Assistant API

For information on creating custom ChatProcessor plugins, see [Writing a ChatProcessor Plugin](https://project.pages.drupalcode.org/ai/latest/developers/writing_a_chat_processor_plugin/).

## Built-in Plugins
The module includes two reference implementations:

- **AI Assistant API Processor**: Provides backward compatibility with the existing AI Assistant API
- **RAG Processor**: Simple RAG (Retrieval-Augmented Generation) implementation

## Deepchat
In the backend it uses Deepchat by OvidijusParsiunas.

## Using the module
For more information, please see:
- [AI Assistant API module documentation](https://project.pages.drupalcode.org/ai/latest/modules/ai_assistant_api/)
- [Writing a ChatProcessor Plugin](https://project.pages.drupalcode.org/ai/latest/developers/writing_a_chat_processor_plugin/)
