## Setup Field Widget Action with a recipe

The module provides config action `setComponentThirdPartySetting` that helps you to integrate your action into form
display using Drupal recipes. There is a core issue to add this config action, but it is not added yet to any version of
Drupal, that is why this action was added to the module directly, but with some limitations: the action only works for
entity form displays (as field widget action can be added only to the form), and the only provider for 3rd party
settings is 'field_widget_actions' module. For the installations that will be using Drupal version with core config
action `setComponentThirdPartySetting`, the core plugin will prevail.

The example of usage:
```
config:
  actions:
    core.entity_form_display.node.page.default:
      setComponentThirdPartySetting:
        component: page_preview_text
        provider: field_widget_actions
        settings:
          bc6795f3-3956-4df2-bd64-980e5002579c:
            plugin_id: prompt_content_suggestion
            enabled: false
            weight: 0
            button_label: 'Summaries'
            settings:
              model: ''
              prompt: 'Summaries given text'
```
The above example adds to the form element "Preview text" for "Page" content type the field widget action from "AI
content suggestions" module.

It is also possible to configure 2 components when providing the array of settings:
```
config:
  actions:
    core.entity_form_display.node.page.default:
      setComponentThirdPartySetting:
        -
          component: page_preview_text
          provider: field_widget_actions
          settings:
            bc6795f3-3956-4df2-bd64-980e5002579c:
              plugin_id: prompt_content_suggestion
              enabled: true
              weight: 0
              button_label: 'Summaries'
              settings:
                model: ''
                prompt: 'Summaries given text'
        -
          component: title
          provider: field_widget_actions
          settings:
            ec6795f3-3956-4df2-bd64-980e5002129d:
              plugin_id: prompt_content_suggestion
              enabled: true
              weight: 0
              button_label: 'Generate Title'
              settings:
                model: ''
                prompt: 'Suggest 5 titles for the given content'
```
