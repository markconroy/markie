# ai_eca

## Requirements

- The "ai" module needs to be enabled
- The "ai" module needs at least one LLM configured (openai or mistral or ...). So find a provider module for the LLM you want to use and enable and configure that provider module.

### Which models to use?
You can find more information on reddit or google
https://www.reddit.com/r/LocalLLaMA/comments/1dc2xur/best_llm_for_translating_texts/
https://www.reddit.com/r/ChatGPT/comments/11t389v/wow_gpt4_beats_all_the_other_translators/

## Installation & configuration

Enable the module
There's no configuration needed on a module level.
The configuration happens in the workflows.

## Usage

If you don't know how ECA works yet, this is not for you.
Then it's better to check the ECA docs first. Trust me.
https://www.drupal.org/project/eca

### Adding AI actions.

When you're creating a workflow and you want to add AI actions.
Type "AI" in the filter and you will see
- Chat
- Speech to text
- Text to Speech

These are the ECA actions we have for now. If you're interested in more (text to image, image to text, image to image, translate, ...) Reach out to us.

Each of the actions wil have a number of parameters that it needs to be able to run in your workflow.
For example: the Chat action will allow you to choose
- a Model
- Token input
- Token result
- Specific configuration for the model (described in the form)
- Prompt (for the LLM)

With this you should have enough to get it running.
If not, reach out to us!
