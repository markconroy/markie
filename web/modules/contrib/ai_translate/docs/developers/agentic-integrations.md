# Agentic integrations

AI Translate ships two plugins that let an AI agent translate content by calling
the [entity translation orchestrator](entity-translation-orchestrator.md). Both
were added in 1.4.0 and take the same arguments, return the same information,
and enforce the same access rules — they differ only in which API they plug
into.

| | AI function call | Tool API |
|---|---|---|
| Plugin ID | `ai_translate:translate_entity` | `ai_translate_tool:translate_entity` |
| Provided by | `ai_translate` | `ai_translate_tool` submodule |
| Requires | AI module | [Tool](https://www.drupal.org/project/tool) module |
| Plugin manager | `plugin.manager.ai.function_calls` | `plugin.manager.tool` |

!!! note "Which one should I use?"
    Function Call plugins are the AI module's 1.x mechanism; the Tool API is
    expected to replace them in AI 2.0.x. Use the Tool API plugin for new work
    if your site already runs the Tool module, and the function call plugin if
    you are integrating with AI 1.x agents and assistants.

## AI function call plugin

`ai_translate:translate_entity` is exposed to models as the function
`ai_translate_translate_entity`. It requires no setup beyond enabling AI
Translate: the AI module discovers it automatically, so it can be enabled for
any agent or assistant that selects its own functions.

It implements `StructuredExecutableFunctionCallInterface`, so it returns both a
readable summary and a structured array.

### Parameters

| Parameter | Required | Description |
|---|---|---|
| `entity_type` | Yes | The content entity type to translate, for example `node`. |
| `entity_id` | Yes | The ID of the entity to translate. |
| `target_language` | Yes | The language code to translate into. |
| `source_language` | No | The language code to translate from. Defaults to the entity's own language. |

### Calling it from code

Agents call the plugin for you. To run it yourself, load it from the function
call manager — inject `plugin.manager.ai.function_calls` in real code rather
than using `\Drupal::service()`.

```php
$functionCallManager = \Drupal::service('plugin.manager.ai.function_calls');
$function = $functionCallManager->createInstance('ai_translate:translate_entity');

$function->setContextValue('entity_type', 'node');
$function->setContextValue('entity_id', $node->id());
$function->setContextValue('target_language', 'fr');
// Optional; omit to use the entity language.
$function->setContextValue('source_language', 'en');
$function->execute();

$output = $function->getStructuredOutput();
if ($output['status'] === 'success') {
  // $output['translated_entity_label'] holds the new translation's label.
}
```

`getReadableOutput()` returns the same information as text, which is what a
model receives:

```text
Entity translation status: success
Message: Content translated successfully.
Translated entity label: Bonjour le monde
```

### Structured output

| Key | Description |
|---|---|
| `status` | `success`, `skipped`, `failed`, or `access_denied`. |
| `message` | The result message. |
| `entity_type` | The requested entity type. |
| `entity_id` | The requested entity ID. |
| `source_language` | The source language actually used, with the default resolved. |
| `target_language` | The requested target language. |
| `translated_entity_label` | Label of the new translation, or an empty string. |
| `failures` | Names of fields that could not be translated. |

`skipped` means the entity already had a translation in the target language.
`failed` covers an unknown entity type, a missing entity, an entity type that is
not translatable, and translation errors. Because a partial translation is still
saved, `status` can be `success` while `failures` is non-empty.

## Tool API plugin

The Tool API plugin lives in the optional `ai_translate_tool` submodule, which
depends on the [Tool](https://www.drupal.org/project/tool) module. The Tool
module is only a Composer suggestion of AI Translate, so install it explicitly:

```bash
composer require drupal/tool
drush en ai_translate_tool
```

The tool is declared as a `Write` operation, so hosts that separate read-only
from state-changing tools will treat it accordingly.

### Inputs

| Input | Required | Description |
|---|---|---|
| `entity_type_id` | Yes | The content entity type machine name, for example `node`. |
| `entity_id` | Yes | The ID of the entity to translate. |
| `target_language` | Yes | The language code to translate into. Must be an enabled site language. |
| `source_language` | No | The language code to translate from. Defaults to the entity's own language. |

Note that this input is named `entity_type_id`, not `entity_type` as in the
function call plugin.

Both language inputs are validated against the site's enabled languages, so an
unknown language code is rejected as invalid input before the entity is loaded.
That differs from the function call plugin, which passes the code through to the
orchestrator.

### Calling it from code

```php
$toolManager = \Drupal::service('plugin.manager.tool');
$tool = $toolManager->createInstance('ai_translate_tool:translate_entity');

$tool->setInputValue('entity_type_id', 'node');
$tool->setInputValue('entity_id', (string) $node->id());
$tool->setInputValue('target_language', 'fr');

if ($tool->access(\Drupal::currentUser())) {
  $tool->execute();
  if ($tool->getResultStatus()) {
    $status = $tool->getOutputValue('status');
    $failures = $tool->getOutputValue('failures');
  }
}
```

`getResultStatus()` is `TRUE` both when a translation was created and when one
already existed; read the `status` output to tell those apart.

### Outputs

| Output | Description |
|---|---|
| `status` | `success`, `skipped`, or `failed`. |
| `message` | The result message. |
| `entity_type_id` | The requested entity type machine name. |
| `entity_id` | The requested entity ID. |
| `source_language` | The source language actually used, with the default resolved. |
| `target_language` | The requested target language. |
| `translated_entity_label` | Label of the new translation, or an empty string. |
| `failures` | Names of fields that could not be translated. |

## Access

Both plugins require the caller to have:

- the **Create AI translation** permission (`create ai content translation`),
  see [Permissions](../configuration.md#permissions), and
- `update` access on the entity being translated.

The function call plugin checks the current user and reports a denial as the
`access_denied` status. The Tool API plugin checks the account passed to
`access()` and reports the denial through that method, so hosts should call it
before `execute()`.

Because both plugins write content, whoever the agent runs as decides what it
can translate. Grant the permission deliberately, and see the security
considerations in the AI module's
[agent documentation](https://project.pages.drupalcode.org/ai/) for how agents
resolve the acting user.
