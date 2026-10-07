# Restrict to Topic Guardrail

The **Restrict to Topic** guardrail (`restrict_to_topic`) is a non-deterministic guardrail that uses an AI provider (LLM) to classify whether the user's message matches configured lists of allowed or disallowed topics. It implements both `NonDeterministicGuardrailInterface` and `NonStreamableGuardrailInterface`.

## When to Use

Use this guardrail to:
- Confine a chatbot to specific subject areas (e.g., support for a particular product only).
- Proactively block off-topic queries or attempts to distract the model (prompt injection, roleplay, etc.).
- Blocklist specific sensitive or forbidden subjects.

## Configurable Fields

| Field | Key | Type | Default | Description |
|-------|-----|------|---------|-------------|
| **Valid Topics** | `valid_topics` | Textarea | *None* | List of allowed topics (one per line). If any are configured, the message must relate to at least one of these topics to pass. |
| **Invalid Topics** | `invalid_topics` | Textarea | *None* | List of disallowed topics (one per line). If any match, the message is blocked. |
| **Topic matching mode** | `matching_mode` | Select | `exact` | Controls how the LLM-returned topics are matched against the configured lists. `exact` (default) requires verbatim matches; `semantic` hardens the prompt and fuzzy-matches residual drift via string similarity. |
| **Similarity threshold** | `similarity_threshold` | Number (0.0–1.0) | `0.75` | *(Semantic mode only)* Minimum similarity score for a returned topic to count as a match. Topics below this threshold are recorded as unmatched. Only visible in the UI when Semantic mode is selected. |
| **Message to send if invalid topics are present** | `invalid_topics_present_message` | Textarea | `The text contains invalid topics` | The violation message displayed when the input matches one of the disallowed topics. |
| **Message to send if no valid topics are found** | `valid_topics_missing_message` | Textarea | `The text does not contain any of the valid topics` | The violation message displayed when the input does not match any of the allowed topics (only evaluated if Valid Topics is not empty). |
| **AI Provider** | `llm_provider` | Select | *None* | The AI provider (e.g., OpenAI, Anthropic) used to classify the text's topics. |
| **AI Model** | `llm_model` | Select | *None* | The specific model used for the topic classification query. |

> [!NOTE]
> Under the hood, this guardrail issues a classification prompt to the selected LLM. If no provider is configured, it falls back to the site's default chat provider configured in the AI module settings.

## How Topic Matching Works

In **exact mode**, the LLM is asked to identify which topics from the configured list are present in the text. The guardrail compares the returned strings verbatim against the valid and invalid lists using `in_array()`. Topics the LLM names that do not appear in either list are recorded as unmatched.

In **semantic mode**, the prompt instructs the model to return only strings from the configured list verbatim. Any topic that still drifts (e.g. the model returns `"banana fruit"` instead of `"banana"`) is caught by a secondary string-similarity pass using PHP's `similar_text()`. A drifted topic must score at or above the **Similarity threshold** to be bucketed; topics below the threshold are recorded as unmatched rather than silently assigned to the wrong bucket.

Use semantic mode when your configured topics can be expressed in multiple ways (plurals, synonyms, or translated equivalents) and you need unmatched topics surfaced in the result metadata for debugging.

## Example Configuration

Below is an example configuration restricting a chatbot to customer support topics while disallowing financial advice:

```yaml
id: restrict_to_topic
valid_topics: |
  shipping questions
  product returns
  billing issues
  general inquiries
invalid_topics: |
  financial advice
  investment strategies
  stock market tips
matching_mode: semantic
similarity_threshold: 0.8
invalid_topics_present_message: "We cannot discuss financial advice or investments here."
valid_topics_missing_message: "Please ask a question related to shipping, returns, billing, or general store support."
llm_provider: openai
llm_model: gpt-4o-mini
```
