# ai_ckeditor

## Requirements

- The "ai" module needs to be enabled
- The "ai" module needs at least one LLM configured (openai or mistral or ...). So find a provider module for the LLM you want to use and enable and configure that provider module.

### Which models to use?
You can find more information on reddit or google
https://www.reddit.com/r/LocalLLaMA/comments/1dc2xur/best_llm_for_translating_texts/
https://www.reddit.com/r/ChatGPT/comments/11t389v/wow_gpt4_beats_all_the_other_translators/

## Installation & configuration

1. Enable the module
2. Navigate to the page where you configure text formats and editors (/admin/config/content/formats) and choose one (example Basic HTML - Click on Configure).
3. Drag the AI Stars ✨ widget into the Active toolbar.
4. Under "CKEditor 5 plugin settings" there is now "AI tools"
5. Configure each tool to your liking (Enable/Disable , choose the right model for you).

## Usage
Navigate to the content type with the ckeditor toolbar enabled.
Try out the widget.

### How to create custom tones.

High level these are the steps:
1. Create a taxonomy "Tone of voice"
2. In the taxonomy create some terms for the different tones (eg. Friendly / ELI5 / professional / ...)
3. In the configuration of the ckeditor widget under "Choose vocabulary" choose your Taxonomy.
4. If needed you can use "Use term description for tone description" if the tone of voice is elaborated in the description.

You can now use the tone of voices in your ckeditor!

### How to create custom languages for Ckeditor translation.

High level these are the steps:
1. Create a taxonomy "Languages"
2. In the taxonomy create some terms for the different languages
3. In the configuration of the ckeditor widget under "Choose vocabulary" choose your Taxonomy.

You can now translate to these languages in your ckeditor!
