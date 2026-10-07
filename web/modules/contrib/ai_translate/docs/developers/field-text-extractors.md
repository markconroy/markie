# Field text extractors

A field text extractor is a plugin that tells AI Translate how to read
translatable text out of a field type and how to write the translation back.
When AI Translate processes an entity, it matches each field to an extractor by
field type and calls it.

## The plugin type

- **Attribute:** `Drupal\ai_translate\Attribute\FieldTextExtractor`
- **Interface:** `Drupal\ai_translate\FieldTextExtractorInterface`
- **Base class:** `Drupal\ai_translate\Plugin\FieldTextExtractor\FieldExtractorBase`
- **Manager:** `plugin.manager.text_extractor`
- **Directory:** `src/Plugin/FieldTextExtractor/`

### Attribute properties

| Property | Description |
|---|---|
| `id` | Plugin ID. It must equal the group or be prefixed with it: for group `foo`, use `foo` or `foo:bar`. |
| `label` | Human-readable name (`TranslatableMarkup`). |
| `field_types` | Array of field types this extractor handles, for example `['text', 'text_long']`. |
| `deriver` | Optional deriver class. |

## Interface

```php
public function getColumns(): array;
public function shouldExtract(ContentEntityInterface $entity, FieldConfigInterface $fieldDefinition): bool;
public function skipFieldAccessCheck(): bool;
public function extract(ContentEntityInterface $entity, string $fieldName): array;
public function setValue(ContentEntityInterface $entity, string $fieldName, array $textMeta): void;
```

- `getColumns()` returns the field columns to translate. Most text fields use
  `['value']`.
- `shouldExtract()` decides whether a given field instance should be translated.
  The base class returns `TRUE` for any translatable field.
- `extract()` returns an array of text metadata, one entry per field delta. Each
  entry carries a `_columns` key naming the parts to translate. The default
  `_columns` value is `['value']`.
- `skipFieldAccessCheck()` opts the field out of the view access check. The
  base class returns `FALSE`; see below before changing that.
- `setValue()` merges the translated text back into the entity's field.

### Skipping the field access check

`TextExtractor::shouldExtract()` checks that the acting account may view the
field before consulting the plugin. `skipFieldAccessCheck()` turns that check
off for every field a plugin handles; the base class returns `FALSE`:

```php
public function skipFieldAccessCheck(): bool {
  return TRUE;
}
```

Only return `TRUE` when the field carries no text of its own and the plugin
only recurses into entities whose fields are checked separately — anywhere else
it leaks content the account is not allowed to see.

`LbFieldExtractor` is the one case in this module: core denies view access to
the `layout_section` field for every account
(`LayoutSectionItemList::defaultAccess()`,
[#2942975](https://www.drupal.org/node/2942975)), so without the opt-out the
layout field is dropped and inline block content is silently left untranslated.
`ReferenceFieldExtractor` recurses too but keeps the check: view access on an
entity reference field is a real permission decision a site can make.

### Text metadata shape

`extract()` returns entries like this. The `_columns` key lists which values get
sent to the provider.

```php
[
  'delta' => 0,
  'field_name' => 'field_faq',
  'question' => 'What is the capital of the United Kingdom?',
  'answer' => 'London',
  '_columns' => ['question', 'answer'],
]
```

## Example

Extend `FieldExtractorBase`, which already implements `extract()` and
`shouldExtract()` for simple value fields. You supply the columns and how to
write the translation back.

```php
<?php

namespace Drupal\my_module\Plugin\FieldTextExtractor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_translate\Attribute\FieldTextExtractor;
use Drupal\ai_translate\Plugin\FieldTextExtractor\FieldExtractorBase;

/**
 * Extracts text from my_custom field types.
 */
#[FieldTextExtractor(
  id: "my_custom",
  label: new TranslatableMarkup('My custom field'),
  field_types: [
    'my_custom',
  ],
)]
class MyCustomExtractor extends FieldExtractorBase {

  /**
   * {@inheritdoc}
   */
  public function getColumns(): array {
    return ['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function setValue(ContentEntityInterface $entity, string $fieldName, array $textMeta): void {
    $newValue = $entity->get($fieldName)->getValue();
    foreach ($textMeta as $delta => $singleValue) {
      unset($singleValue['field_name'], $singleValue['field_type']);
      $newValue[$delta] = isset($newValue[$delta])
        ? array_merge($newValue[$delta], $singleValue)
        : $singleValue;
    }
    $entity->set($fieldName, $newValue);
  }

}
```

Place the class in `src/Plugin/FieldTextExtractor/`, clear caches, and AI
Translate picks it up for the listed field types. For a field with multiple
translatable columns, return them all from `getColumns()` and override
`extract()` if the default value-per-delta shape does not fit. The bundled
`ReferenceFieldExtractor` and `LinkTextExtractor` plugins are useful references.
