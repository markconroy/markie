# Entity translation orchestrator

`ai_translate.translation_orchestrator` is the entry point for translating a
content entity from code. It owns the whole workflow — resolving the source
translation, extracting translatable text, translating it, and saving the new
translation — so callers do not have to reassemble those steps themselves.

Before AI Translate 1.4.0 that workflow was duplicated in the Translate tab
controller and in the Drush commands. Both now call this service, so anything
you build on it behaves exactly like the built-in translation paths. See the
change record
[Entity translation moved into a shared orchestrator service](https://www.drupal.org/node/3619537).

## Getting the service

The service is aliased to its interface, so you can inject either the service ID
or the interface.

```yaml
services:
  my_module.my_service:
    class: Drupal\my_module\MyService
    arguments:
      - '@ai_translate.translation_orchestrator'
```

```php
use Drupal\ai_translate\EntityTranslationOrchestratorInterface;

public function __construct(
  protected EntityTranslationOrchestratorInterface $translationOrchestrator,
) {}
```

In a controller, plugin, or other class with a `create()` method:

```php
$instance->translationOrchestrator = $container->get('ai_translate.translation_orchestrator');
```

## Translating an entity

`translateEntity()` runs the full workflow and returns a result object.

```php
use Drupal\node\Entity\Node;

$node = Node::load(42);
$result = $this->translationOrchestrator->translateEntity($node, 'en', 'nl');

if ($result->isSuccess()) {
  // The translation is already saved.
  $translation = $result->getTranslatedEntity();
  $this->messenger()->addStatus($result->getMessage());
}
```

The entity is not required to be in the source language you pass: the
orchestrator switches to the `$langFrom` translation first if the entity has
one.

## The result object

`translateEntity()` and `saveTranslatedEntity()` both return an
`\Drupal\ai_translate\EntityTranslationResult`.

| Method | Returns | Description |
|---|---|---|
| `isSuccess()` | `bool` | `TRUE` if a translation was created and saved. |
| `translationExists()` | `bool` | `TRUE` if the entity already had a translation in the target language, in which case nothing was saved. |
| `getTranslatedEntity()` | `ContentEntityInterface` or `NULL` | The saved translation, or `NULL` if none was created. |
| `getMessage()` | `string` or `TranslatableMarkup` | The user-facing result message. Cast to `string` for machine-readable output. |
| `getFailures()` | `string[]` | Names of fields that could not be translated. |

The possible outcomes are:

| Outcome | `isSuccess()` | `translationExists()` | Message |
|---|---|---|---|
| Translation created | `TRUE` | `FALSE` | *Content translated successfully.* |
| Target translation already present | `FALSE` | `TRUE` | *Translation already exists.* |
| Unknown target language | `FALSE` | `FALSE` | *Invalid target language.* |
| Save failed | `FALSE` | `FALSE` | *There was some issue with content translation.* |

Check `translationExists()` before treating a non-successful result as an
error — an existing translation is a deliberate skip, not a failure.

## Partial translations

A field that the AI provider cannot translate does not abort the run. The field
is skipped, its name is collected, and the remaining fields are still saved, so
`isSuccess()` can be `TRUE` while `getFailures()` is non-empty.

```php
$result = $this->translationOrchestrator->translateEntity($node, 'en', 'nl');

if ($result->isSuccess() && $result->getFailures()) {
  $this->messenger()->addWarning($this->t('These fields were not translated: @fields', [
    '@fields' => implode(', ', $result->getFailures()),
  ]));
}
```

## Building a custom workflow

`translateEntity()` translates every field in one pass, which is fine for a
Drush command but not for a long-running request. The interface therefore also
exposes each step, so you can drive them from a batch, a queue worker, or your
own pipeline. This is how the Translate tab runs one field per batch operation.

| Method | Purpose |
|---|---|
| `resolveSourceEntity($entity, $langFrom)` | Returns the entity's `$langFrom` translation, or the entity itself if it has none. |
| `extractTextMetadata($entity)` | Returns a flat list of translatable text items, one per field delta. |
| `translateTextMetadataItem($item, $langFrom, $langTo)` | Translates one item and returns it with a `translated` key added, or `NULL` if it failed. |
| `saveTranslatedEntity($entity, $langTo, $processedTranslations, $failures)` | Writes the translated items back, saves the translation, and returns the result. |

Note that `translateTextMetadataItem()` takes `LanguageInterface` objects, while
the other methods take language codes.

```php
$entity = $this->translationOrchestrator->resolveSourceEntity($entity, $langFrom);
$langFromObject = $this->languageManager->getLanguage($langFrom);
$langToObject = $this->languageManager->getLanguage($langTo);

$processed = [];
$failures = [];
foreach ($this->translationOrchestrator->extractTextMetadata($entity) as $item) {
  // In a batch or queue, do this one item per operation.
  $translated = $this->translationOrchestrator->translateTextMetadataItem($item, $langFromObject, $langToObject);
  if ($translated === NULL) {
    $failures[] = $item['field_name'];
    continue;
  }
  $processed[] = $translated;
}

$result = $this->translationOrchestrator->saveTranslatedEntity($entity, $langTo, $processed, $failures);
```

When you drive the steps yourself you are responsible for the checks that
`translateEntity()` performs for you: skipping entities that already have the
target translation, and validating the target language.

!!! note "Reference traversal is not part of the orchestrator"
    The orchestrator translates one entity. Following entity reference fields
    to translate the entities they point to is the job of the field text
    extractors. See [Field text extractors](field-text-extractors.md).

## Other behavior

- **Publication status.** If the `translation_status` setting is
  `create_draft`, new translations are unpublished and, where content moderation
  is in use, set to the `draft` state. Otherwise the status of the source entity
  is kept. See [Translation status](../configuration.md#translation-status).
- **Language fallback.** The target language must exist, otherwise the call
  fails. An unknown *source* language code is not fatal: it falls back to the
  language of the resolved source entity, because the source language is only
  used to tell the AI provider what it is translating from.
- **Caching.** Translation caching happens inside the text translator, so
  repeated strings cost one AI request per unique string no matter which
  orchestrator method you call.
- **Logging.** A failed save is logged to the `ai_translate` logger channel and
  converted into a failure result rather than an exception.

## Exposing translation to agents

The orchestrator is also wrapped in an AI function call plugin and a Tool API
plugin, so an AI agent can translate content without any custom code. See
[Agentic integrations](agentic-integrations.md).
