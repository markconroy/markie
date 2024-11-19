

## Translate Text

Translate text calls are calls for translating text from one language to another. One known provider using this is DeepL.

### Example normalized text translation call

The following is an example of sending text to DeepL and getting the translated text back.

For the example to work, you need to have the contributed module DeepL Provider (`provider_deepl`) installed.

```php
$translator = \Drupal::service('ai.provider')->createInstance('deepl');
// Note that for DeepL, plain 'en' is not supported, you need to use
// 'en-gb' or 'en-us'.
$input = new TranslateTextInput($text, 'fi', 'en-gb');
/** @var \Drupal\ai\OperationType\TranslateText\TranslateTextOutput $translation */
$translation = $translator->translateText($input, 'default', []);
// Will output 'Hello world'.
return $translation->getNormalized();
```

### Text Translation Interfaces & Models

The following files defines the methods available when doing a text translation call as well as the input and output.

* [TranslateTextInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/TranslateText/TranslateTextInterface.php?ref_type=heads)
* [TranslateTextInput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/TranslateText/TranslateTextInput.php?ref_type=heads)
* [TranslateTextOutput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/TranslateText/TranslateTextOutput.php?ref_type=heads)

### Text Translation Explorer
If you install the AI API Explorer, you can go `configuration > AI > AI API Explorer > Text Translation Explorer` under `/admin/config/ai/explorers/ai-translate-text` to test out different calls and see the code that you need for it.
