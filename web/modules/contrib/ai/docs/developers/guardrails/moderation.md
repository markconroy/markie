# Moderation Guardrail

The **Moderation** guardrail (`moderation`) is a non-deterministic guardrail that sends user chat messages to a configurable moderation provider/model and stops the request if the moderation endpoint flags the content. It implements both `NonDeterministicGuardrailInterface` and `NonStreamableGuardrailInterface`. It only moderates input; `processOutput()` always passes.

## When to Use

Use this guardrail to:
- Block harmful or inappropriate user input before it reaches the model.
- Use a provider's dedicated moderation endpoint (e.g., OpenAI's `/moderations`, which is free to call) instead of spending LLM tokens on a classification prompt.
- Apply moderation selectively per guardrail set, rather than globally for every request as `ModeratePreRequestEventSubscriber` does.

## Configurable Fields

| Field | Key | Type | Default | Description |
|-------|-----|------|---------|-------------|
| **Message to send if the content is flagged** | `flagged_message` | Textarea | `The content was flagged by the moderation endpoint and the request was stopped.` | The violation message displayed to the user when the moderation endpoint flags the input. |
| **Moderation provider and model** | `moderation_model` | Select | *None* | The moderation provider and model used to evaluate the input, stored as a combined `provider__model` value. Leave as "- None -" to use the site's default moderation provider. |
| **Scan all user messages** | `scan_all_user_messages` | Checkbox | `FALSE` | When enabled, every user message in the chat history is moderated and the request is stopped as soon as any one of them is flagged. When disabled, only the latest user message is moderated. |

> [!NOTE]
> This guardrail **fails closed**. If no provider/model is selected and no default moderation provider is configured in the AI module settings, or if the selected provider does not support the moderation operation, the guardrail returns a stop result with a descriptive message instead of letting the content through unchecked.

## How Moderation Works

The guardrail only applies to chat input; any other input type passes straight through. It selects the user messages to check — by default just the latest user message, or every user turn when **Scan all user messages** is enabled — and sends each one to the configured provider's moderation endpoint. If the endpoint flags a message, the guardrail returns a stop result carrying the configured **flagged message**, with the verbose moderation details attached to the result metadata under `moderation_information`. If no message is flagged, the request proceeds.

Unlike the [Restrict to Topic](restrict_to_topic.md) guardrail, which issues a classification prompt to a chat model, this guardrail calls the provider's dedicated `moderation` operation, so only providers that support that operation type appear in the provider/model select.

## Example Configuration

Below is an example configuration that moderates every user message in the conversation using OpenAI's moderation endpoint:

```yaml
id: moderation
flagged_message: "Your message was flagged by our content moderation system and cannot be processed."
moderation_model: openai__omni-moderation-latest
scan_all_user_messages: true
```
