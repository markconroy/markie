# ai_translate

## Requirements

- The "ai" module needs to be enabled
- The "ai" module needs at least one LLM configured (openai or mistral or ...). So find a provider module for the LLM you want to use and enable and configure that provider module.

### Which models to use?
You can find more information on reddit or google
https://www.reddit.com/r/LocalLLaMA/comments/1dc2xur/best_llm_for_translating_texts/
https://www.reddit.com/r/ChatGPT/comments/11t389v/wow_gpt4_beats_all_the_other_translators/

## Installation & configuration

1. Enable the module
2. navigate to /admin/config/ai/ai_translate
3. Configure a good translation prompt and a good translation model.

### Which prompt to use?
We have provided a suggestion prompt in the settings screen but you can customize this.
https://www.google.com/search?q=a+good+translation+prompt

## Usage
Navigate to the node.
Click on the translate menu.
Click on the translate link to start the translating of the node.
SUCCESS!

## Async translations & paragraphs
If you are using async translations you should also use the module https://www.drupal.org/project/ai_translate_paragraph_asymetric. This fixes some things. It is better to keep that apart (dependencies on paragraphs and async things). 

## Layout builder
We are working on having layout builder support in https://www.drupal.org/project/ai/issues/3467075. 


## Similar modules

- https://www.drupal.org/project/ai_tmgmt Ai Translation management tools (use tmgmt with the ai module as a translation provider)
- https://www.drupal.org/project/auto_translation The Auto translate module is very similar to this one. 
- https://www.drupal.org/project/ai_translate_textfield Translate textfield also uses AI but the interface is substantially different. You have to translate on a per field basis. The approach is different.
- 