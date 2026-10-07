

## Extractive Question Answering

Extractive Question Answering calls take a question and a context passage, then extract the answer directly from the context along with the position where it was found and a confidence score. Popular providers include Hugging Face models like `deepset/roberta-base-squad2` and `distilbert/distilbert-base-cased-distilled-squad`.

### Example normalized Extractive Question Answering call

The following is an example of sending the question "What is the capital of France?" with the context "The capital of France is Paris, which is also the largest city in the country." into Hugging Face using the deepset/roberta-base-squad2 model and getting the extracted answer back as an array of ExtractiveQuestionAnsweringItem.

```php
use Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringInput;

$input = new ExtractiveQuestionAnsweringInput(
  'What is the capital of France?',
  'The capital of France is Paris, which is also the largest city in the country.'
);
/** @var \Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringOutput $result */
$result = \Drupal::service('ai.provider')->createInstance('huggingface')->extractiveQuestionAnswering($input, 'deepset/roberta-base-squad2', ['my-custom-call']);

$items = $result->getNormalized();
foreach ($items as $item) {
  // Get the extracted answer text.
  $answer = $item->getAnswer(); // e.g. "Paris"
  // Get the confidence score (0.0 to 1.0).
  $score = $item->getScore(); // e.g. 0.98
  // Get the start/end character positions in the context.
  $start = $item->getStart(); // e.g. 25
  $end = $item->getEnd(); // e.g. 30

  if ($score > 0.7) {
    // High-confidence answer found.
    \Drupal::logger('my_module')->info('Answer: @answer (confidence: @score%)', [
      '@answer' => $answer,
      '@score' => $item->getScorePercentage(),
    ]);
  }
}
```

### Extractive Question Answering Interfaces & Models

The following files defines the methods available when doing an extractive question answering call as well as the input and output.

* [ExtractiveQuestionAnsweringInterface.php](https://git.drupalcode.org/project/ai/-/blob/HEAD/src/OperationType/ExtractiveQuestionAnswering/ExtractiveQuestionAnsweringInterface.php)
* [ExtractiveQuestionAnsweringInput.php](https://git.drupalcode.org/project/ai/-/blob/HEAD/src/OperationType/ExtractiveQuestionAnswering/ExtractiveQuestionAnsweringInput.php)
* [ExtractiveQuestionAnsweringOutput.php](https://git.drupalcode.org/project/ai/-/blob/HEAD/src/OperationType/ExtractiveQuestionAnswering/ExtractiveQuestionAnsweringOutput.php)

### Extractive Question Answering Explorer
If you install the AI API Explorer, you can go `configuration > AI > AI API Explorer > Extractive Question Answering Explorer` under `/admin/config/ai/explorers/ai-extractive-question-answering` to test out different calls and see the code that you need for it.
