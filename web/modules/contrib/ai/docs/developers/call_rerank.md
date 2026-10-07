

## Rerank

Rerank calls take a query and a list of documents (or other inputs) and return those documents re-ordered from most to least relevant to the query, each with an optional relevance score. This is commonly used as a second-pass refinement on top of keyword or vector search results to improve relevance.

This is the generic, backend-agnostic developer call. If you want to rerank Search API results without writing any code, use the AI Reranker processor instead — see [AI Reranker Processor](../modules/ai_search/reranker.md).

### Example normalized Rerank call

The following is an example of reranking three documents against the query "What type of comedy is Steve Martin famous for?".

```php
use Drupal\ai\OperationType\Rerank\ReRankInput;

$documents = [
  'Steve Martin is known for his absurdist, stand-up and slapstick comedy.',
  'Steve Martin plays the banjo and has released bluegrass albums.',
  'Steve Martin wrote the novella Shopgirl.',
];

// The third argument is top_n: how many results to return. 0 returns all.
$input = new ReRankInput('What type of comedy is Steve Martin famous for?', $documents, 0);

/** @var \Drupal\ai\OperationType\Rerank\ReRankOutput $rerank_object */
$rerank_object = \Drupal::service('ai.provider')->createInstance('cohere')->rerank($input, 'rerank-v3.5', ['my-custom-call']);

print_r($rerank_object->getNormalized());
```

### The normalized output shape

`ReRankOutput::getNormalized()` returns a list of result items ordered from most to least relevant. Each item — whether an associative array or an object — is expected to expose:

* `index` (int, **required**): the zero-based position of the document in the `ReRankInput` inputs array that this result refers to. Consumers rely on this to map results back to their original items; items without a valid index cannot be reordered.
* `relevance_score` (or `score`) (float, optional): the relevance score the model assigned to the document.

Provider implementations should preserve these keys when normalizing the raw API response so that any consumer (including the AI Reranker Search API processor) can map results back reliably.

### Rerank Interfaces & Models

The following files define the methods available when doing a rerank call as well as the input and output.

* [ReRankInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/Rerank/ReRankInterface.php?ref_type=heads)
* [ReRankInput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/Rerank/ReRankInput.php?ref_type=heads)
* [ReRankOutput.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/Rerank/ReRankOutput.php?ref_type=heads)
