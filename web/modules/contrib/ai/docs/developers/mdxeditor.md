# MDXEditor

## Why the editor is included in AI core?

Check the reasons for this [here](https://www.drupal.org/project/ai/issues/3570097).

## How can I use the editor?

All form elements of type `textarea` can use the editor. You need to add the attribute
`data-mdxeditor` to your element and the editor will be added automatically. If you
need to pass some configuration to the editor, the attribute should be a unique identifier
that will also be used in `drupalSettings`. For example for typeahead plugin config:

```php
    $typeahead_config = [
      // Here comes some configuration for typeahead plugin.
    ];
    $editor_id = '<some_unique_id>';
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#attributes' => [
        'data-mdxeditor' => $editor_id,
      ],
      '#attached' => [
        'drupalSettings' => [
          'mdxeditor' => [
            $editor_id => [
              'plugins' => [
                'typeaheadPlugin' => $typeahead_config,
              ],
            ],
          ],
        ],
      ],
    ];
```

## Enabling the editor on a Text (plain, long) field

The MDX Editor can be enabled directly from the field widget settings without any custom
code. This is supported for fields of type **Text (plain, long)** that use the
**Textarea** field widget (`string_textarea`).

To enable it, go to the **Manage form display** page of the content type (or any other
entity type), expand the widget settings for the relevant field, and check the
**Use MDX editor** checkbox. The editor will replace the plain textarea on the entity form.

You can also enable it programmatically by setting the `use_mdx_editor` third-party
setting on the widget component in the entity form display:

```php
use Drupal\Core\Entity\Entity\EntityFormDisplay;

$form_display = EntityFormDisplay::load('node.article.default');
$form_display->setComponent('field_body', [
  'type' => 'string_textarea',
  'region' => 'content',
  'settings' => [
    'rows' => 9,
    'placeholder' => '',
  ],
  'third_party_settings' => [
    'ai' => [
      'use_mdx_editor' => TRUE,
    ],
  ],
]);
$form_display->save();
```

## Why build editor is not included?

The built assets are only included for tagged versions of the module. If you use the branch
for development you need to build the assets yourself. For this you will need to have `npm`
running locally. The minimum required version of `node` is `20.14.0`.

To build the editor assets follow the instructions:

1. Go to `ui/mdxeditor` folder.
2. Run `npm install`
3. Run `npm run build`
4. Do not commit the changes from `ui/mdxeditor/dist` folder.
