# Installation

## Requirements

- [AI (Artificial Intelligence)](https://www.drupal.org/project/ai) with a
  provider set for the translation operation. If you do not run a dedicated
  translation provider, the bundled *Chat proxy to LLM* provider lets any
  configured chat model act as the translator, so an existing chat provider is
  enough to get started. See [Configuration](configuration.md#ai-provider).
- Drupal core **Content Translation**, with the entity types and languages you
  want to translate already enabled.

## Install with Composer

```bash
composer require drupal/ai_translate
```

## Enable the module

```bash
drush en ai_translate content_translation
```

!!! note "Moving off the AI submodule"
    AI Translate shipped inside the AI module until AI 1.3.0, which deprecated
    the submodule in favor of this project. The deprecated submodule is still
    present in the AI 1.x releases and is removed in AI 2.0.0.

    If you are upgrading a site that used the submodule, install and enable the
    standalone module **while still on AI 1.x**, before upgrading AI itself:

    ```bash
    composer require 'drupal/ai_translate:^1.3'
    drush en ai_translate
    ```

    The standalone 1.3.0 release is functionally identical to the submodule, so
    existing configuration carries over. Once that is in place, upgrade AI to
    2.0.0 and move to `drupal/ai_translate:^2.0`. See the change record
    [Moving AI Translate Module out of AI project](https://www.drupal.org/node/3570275).

## Optional: expose translation as a Tool API plugin

To let AI agents translate content through the [Tool](https://www.drupal.org/project/tool)
module, install Tool and enable the `ai_translate_tool` submodule:

```bash
composer require drupal/tool
drush en ai_translate_tool
```

This is not needed for the AI function call plugin, which ships with
AI Translate itself. See
[Agentic integrations](developers/agentic-integrations.md).

## Set up translation

1. Enable a second language at `Administration > Configuration > Regional and
   language > Languages` (`/admin/config/regional/language`).
2. Enable translation for the entity types and bundles you want to translate at
   `Administration > Configuration > Regional and language > Content language
   and translation` (`/admin/config/regional/content-language`).
3. Grant the **Create AI translation** permission to the roles that should
   translate content. See [Configuration](configuration.md#permissions).
4. Configure prompts, models, and reference depth at `Administration >
   Configuration > AI > AI Translate Settings`
   (`/admin/config/ai/ai-translate`).
