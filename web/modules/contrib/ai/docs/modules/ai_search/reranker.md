# AI Reranker Processor

## What is it?

The AI Reranker is a [Search API](https://www.drupal.org/project/search_api) processor plugin provided by the **`ai` core module** (not the AI Search submodule). It re-orders search result items by semantic relevance using an AI reranking model, improving result quality beyond what keyword or vector similarity scoring alone can provide.

Because it is a generic Search API processor, it works with any Search API backend — Database, Solr, or a vector database.

The processor runs at the `postprocess_query` stage: after Search API has fetched and scored results, the reranker scores each result against the original query and returns them in a new, semantically ranked order.

!!! warning "Your content leaves the site"
    On every search that carries a query string, the reranker sends the search keywords **and** the text of the configured source fields for each result on the current page to the configured AI provider. If that provider is a hosted, third-party service this data leaves your site and may be billed per request. Choose your provider and source fields accordingly.

## Prerequisites

- The [Search API](https://www.drupal.org/project/search_api) module installed and enabled.
- A Search API server and index configured with at least one datasource and indexed fields.
- At least one AI provider that supports the **rerank** operation type installed and configured (e.g. [Cohere](https://www.drupal.org/project/ai_provider_cohere)).

The processor is only offered on an index when at least one rerank-capable provider is installed.

## Installation

No extra modules or packages are required. The processor is automatically discovered by Search API when both `search_api` and `ai` are installed.

## Configuration

1. Navigate to your Search API index and open the **Processors** tab (`/admin/config/search/search-api/index/{index_id}/processors`).
2. Check **AI Reranker** to enable it.
3. Scroll to the **AI Reranker** processor settings section and configure:

| Setting | Description |
|---|---|
| **AI provider and model** | The rerank-capable provider and model to use. Only providers that support the rerank operation are listed. Required. |
| **Top N results** | How many results to ask the reranker to prioritize. This is the number of top results the model ranks — it does **not** truncate the result set and does **not** change the total count shown by the pager. Set to `0` to rank all results on the page. |
| **Source fields** | Fields whose values are concatenated to form the document text sent to the reranker. At least one field is **required**. Choose the fields that best represent the content (for example Title and Body). |

4. Save the processor configuration.

## How it works

For each search request that carries a query string:

1. Field values from the configured **Source fields** are extracted for each result item on the current page and concatenated into a document string. Fulltext (`text`) field values are unwrapped to their plain text.
2. The document strings and the original query are sent to the configured AI provider's rerank endpoint.
3. Results are re-ordered by the scores returned by the provider, and each reordered item's Search API score is updated to match.
4. Any items the reranker did not return are kept, in their original order, after the reranked ones.
5. If the rerank call fails for any reason, the original result order is preserved and a warning is logged to the `ai` channel.

### Page scope and pagination

The `postprocess_query` stage runs **once per result page**, so reranking only ever sees and reorders the results on the current page — not the whole result set. To avoid breaking the pager, the processor never changes the total result count. **Top N** therefore controls how many results the model prioritizes within the page; it does not remove results or alter paging.

## Choosing source fields

The reranker's quality depends on the text it receives. Index and select the fields that best represent what users are searching for — typically **Title** and **Body** for node content. Avoid purely numeric or taxonomy reference fields, as those rarely contain useful text for semantic comparison.

## Troubleshooting

**Warning: "AI Reranker: N of M result items had no text in the configured source fields..."**
The processor could not find any text in the configured source fields for that many items on the page. Those items are sent to the reranker with an empty document (their internal IDs are never sent), which produces meaningless scores for them. Check that the selected fields are indexed and populated.

**Results are not reordered**
Ensure a query string is present. The processor skips reranking for browse requests (no search terms). Also verify the provider and model are correctly configured and that the provider API key is valid.

**The processor does not appear on my index**
At least one installed AI provider must support the rerank operation. Install and configure a rerank-capable provider (for example Cohere).

## For developers

The processor is implemented in `Drupal\ai\Plugin\search_api\processor\AiReranker`. It uses `AiProviderPluginManager::getSimpleProviderModelOptions('rerank')` to build the provider/model dropdown, which is safe to use inside Search API's `SubformState` context.

To call the rerank operation directly in your own code, see `Drupal\ai\OperationType\Rerank\ReRankInput` and `ReRankOutput`, the `ReRankInterface` docblock (which documents the normalized output shape), and the [Making AI Calls](../../developers/base_calls.md) developer guide.
