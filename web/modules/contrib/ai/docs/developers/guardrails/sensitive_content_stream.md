# Sensitive Content Stream Filter

The **Sensitive Content Stream Filter** (`sensitive_content_stream`) is a streaming guardrail that suppresses content enclosed between configurable plain-text markers during streaming, replacing it with a safe message before it reaches the consumer.

Unlike regular post-generation guardrails that evaluate the full response after it has been received, this guardrail hooks into the stream iterator in real time via `StreamableGuardrailInterface`. Content before the start marker is passed through immediately; content between the markers is buffered and suppressed; content after the stop marker resumes flowing normally.

## When to Use

Use this guardrail to:

- Prevent sensitive sections of an AI response from being displayed to end users mid-stream (e.g. model reasoning chains, internal notes, or classified content).
- Strip out structured sections that an AI model is instructed to wrap in known delimiters.
- Implement a "safe zone" pattern where the model may freely include unsafe internal monologue between markers without it leaking to the UI.

## Configurable Fields

| Field | Key | Type | Default | Description |
|-------|-----|------|---------|-------------|
| **Start marker** | `start_marker` | Textfield | `[SENSITIVE]` | Plain-text string that signals the beginning of suppressed content. Must differ from the stop marker. |
| **Stop marker** | `stop_marker` | Textfield | `[/SENSITIVE]` | Plain-text string that signals the end of suppressed content. |
| **Replacement message** | `replacement_message` | Textfield | `[Content removed.]` | Text shown to the consumer in place of the suppressed section. |

Both markers are automatically escaped with `preg_quote()` before use, so plain-text strings like `[SENSITIVE]` are matched literally without needing to escape regex metacharacters.

## How it Evaluates the Stream

1. Chunks arriving before the start marker flow through to the consumer unchanged.
2. Once the start marker is detected in the accumulated chunk buffer, the guardrail becomes **active** and starts holding all subsequent chunks.
3. When the stop marker is detected, `processStreamedBuffer()` is called with everything buffered since activation. The replacement message is emitted in place of the suppressed content.
4. Streaming continues normally after the stop marker.
5. If the buffer grows beyond `maxGuardrailBufferSize` (default 8,192 bytes) before the stop marker appears, the buffer is force-evaluated immediately to prevent unbounded memory growth — the replacement message is emitted and buffering resets.
6. If the stream ends while the guardrail is still active (unclosed marker), the entire remaining buffer is passed to `processStreamedBuffer()` and the replacement message is emitted.

## Example Configuration

```yaml
id: my_sensitive_filter
label: Sensitive content filter
guardrail: sensitive_content_stream
guardrail_settings:
  start_marker: '[INTERNAL]'
  stop_marker: '[/INTERNAL]'
  replacement_message: '[Response contains internal notes that are not shown.]'
```

## Registering on a Guardrail Set

Streaming guardrails must be placed on the **post-generate** list of a guardrail set. The `GuardrailsEventSubscriber` automatically detects `StreamableGuardrailInterface` and routes the plugin to the stream iterator rather than the normal `processOutput()` path.

```php
$guardrail_helper = \Drupal::service('ai.guardrail_helper');
$input = new ChatInput([new ChatMessage('user', $user_message)]);
$input->setStreamedOutput(TRUE);
$input = $guardrail_helper->applyGuardrailSetToChatInput('my_guardrail_set', $input);

$result = $provider->chat($input, $model_id, ['my_module']);
$iterator = $result->getNormalized(); // StreamedChatMessageIteratorInterface

foreach ($iterator as $chunk) {
  // Chunks between [INTERNAL]…[/INTERNAL] are suppressed.
  // Only safe content reaches this loop.
  print $chunk->getText();
}
```

## Tuning the Buffer Size

If your model produces very long sensitive sections, increase the buffer limit on the iterator before consuming the stream:

```php
$iterator = $result->getNormalized();
$iterator->setMaxGuardrailBufferSize(32768); // 32 KB
foreach ($iterator as $chunk) { … }
```

## See Also

- [Guardrails Overview](index.md) — architecture, result types, and how to apply guardrail sets.
- [Writing a Custom Guardrail Plugin](custom_guardrail.md#streamableguardrailinterface) — how to implement your own streaming guardrail.
