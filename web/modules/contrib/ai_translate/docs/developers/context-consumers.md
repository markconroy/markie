# Context Control Center

[AI Context](https://www.drupal.org/project/ai_context) (1.0.0-beta5 or later)
can push selected site context into an AI system prompt. That UI is the Context
Control Center (CCC). This module ships a CCC **consumer type** plugin so
chat-proxy translations can receive that context. The plugin lives in the main
module. It is discovered only when AI Context is enabled. There is no extra
submodule and no hard dependency.

## What is a consumer here

A **context consumer** is one caller that can receive pushed context. Canonical
context consumer IDs use the short type prefix, the same pattern as `agent:`,
`automator:`, and `ckeditor:`:

- `translate:default` is the chat-proxy translator

Provider request tags use the module machine name (`ai_translate`), not that
short type prefix. `ChatTranslationProvider` already sends that tag on the inner
chat call.

AI Translate registers one consumer instance, `translate:default`. UI batch
translation, Drush entity translation, and interface chat-proxy translation
share its subscriptions. Language pairs share that row too. Per-language-pair
consumers are a follow-up.

## What this consumer is not

- **Native `translate_text` providers.** Those never emit a chat request, so CCC
  never sees them.
- **Agent-driven translation.** The function call and Tool API plugins from
  1.4.0 use `agent:{id}` consumers. The CCC consumer router never routes
  requests tagged `ai_agents` or `ai_assistant_api` to non-agent types, so
  this type never sees them.
- **CKEditor Translate.** That tool is `ckeditor:ai_ckeditor_translate` in the
  AI CKEditor module.

Nested chat-proxy calls from an agent tool still emit only `ai_translate`. They
**may** receive this type's push, because the inner chat uses its own system
prompt (`You are a helpful translator.`), which the agent path does not fill.

## Matching and entity context

CCC matches a chat-proxy call when the provider request tags include
`ai_translate`. The route is not used. If that tag is missing, the type
declines.

Scope subscriptions that filter by entity type, taxonomy terms, or a specific
entity need `entity_context` on the chat input. The orchestrator and Translate
tab pass the entity being translated (`entity_type`, `bundle`, `id`). That
metadata is stored with AI request metadata (`get/setRequestMetadataValue`).
Older AI versions without those methods skip entity-scoped matching.
Interface translation and `ai:translate-text` have no entity, so those scopes
simply do not match.

Chat-proxy also sets `language_context` with `target` (required) and
`source` (when known). Those langcodes come from the translation job, not
the route. CCC Language scope still uses page/content language today, so
this metadata is unused until a CCC follow-up honors it. French-only vs
any-language items still stay on this single `translate:default` consumer;
do not add per-language consumer rows for that.

Automatic push is off until an admin turns on **Push context automatically** on
`translate:default` under **Context consumers**. An unsaved row follows the
site-wide selection mode at runtime. With AI Context 1.0.0-rc1 or later the
editor also opens on **Site default** and stores `inherit`; earlier 1.0.x
betas preselect **Minimal** on the first save. Either way an editor can lock a
ladder mode on that row.

Context items are selected as the acting user. Drush, cron, and queue runs
usually execute as the anonymous user. Unless that role has been granted
access to view context items, those runs match the consumer but push no items
(`status=no_items`). Run Drush with `--user` or an equivalent account when
pushed context is needed.

The translation cache does not vary by user. With **Cache translation
results** on, a translation cached by an anonymous run, which got no context,
is served to a later run by an editor for the same text and entity, and the
other way around. The same applies to items limited by the Roles scope. Run
Drush with `--user`, rebuild the cache after an anonymous run, or turn caching
off when pushed context must depend on the user.

One chat call is made per field today, with the same tags, so selected context
is billed and usage-tracked once per field. Use the consumer's `max_tokens`
limit if that matters.

## Cached translations

With **Cache translation results** on, which is the default for new installs,
a cached translation is served without a chat call. Nothing is pushed and no
CCC usage is recorded for that field. Invalidating that cache, as described
below, only means the next translation of the text reaches the provider again;
translations already saved on content are never touched.

While push is on for `translate:default` and translation uses the chat proxy
with request-metadata support, entries are keyed on the entity
being translated, so two entities that share a string are translated
separately and never receive a translation made with the other entity's
context. Saving a context item or changing the site-wide selection mode
invalidates the cached translations, so the next run pushes again. Interface
translation and `ai:translate-text` have no entity and share one entry per
string. This costs the reuse of a translation across entities; that is the
price of pushed context that can differ per entity. A site whose items are
site-wide guidance can opt back into one entry per string with **Share cached
translations across content while context is pushed** on the Translate type
under **Consumer Types** (`ai_context.consumer_type_settings.translate`,
`share_entity_cache`). The option only applies while translation results are
cached and while push is on; the form says so when push is currently off.
Only enable it when no item the Translate consumer can receive depends on the
content being translated, for example through the Entity Types, Taxonomy or
Specific Entities scopes. Otherwise a translation made with one entity's
context can be reused for, and saved to, another entity.
Cached translations are still invalidated when a context item, the AI Context
settings, a consumer row, or the type settings change, including this option.

While push is off, the default, the cache behaves as it does without AI
Context and one string is shared across the whole site. Switching push on or
off, or disabling the Translate consumer type, invalidates every cached
translation so an entry is never served under the other setting. Rebuild the
cache, or turn the setting off, when every run must reach the provider. See
[Configuration](../configuration.md#cache-translation-results).

The push cache namespace is separate even for calls without an entity and
when sharing is enabled. This excludes older entries without CCC cache tags,
including entries created before AI Context was installed. With push off,
the cache ID remains unchanged. Native translation providers and older AI
versions without request metadata also retain the original cache ID.

Within the push namespace, entity-less calls keep the same ID when sharing
is toggled or consumer subscriptions change. The consumers and type-settings
cache tags are required to invalidate those entries.

## API compatibility

`TextTranslatorInterface::translateContent()` retains its `$context` parameter
name, including for named-argument callers. Only `entity_context` is read for
this integration; other context keys remain available to callers.

`EntityTranslationOrchestratorInterface::translateTextMetadataItem()` adds an
optional fourth parameter, `array $entityContext = []`. Existing callers remain
valid, but custom interface implementations must add this parameter to their
method signature when upgrading. The Translate-tab batch callback retains its
historical four-argument signature.

## Enable it

See [Installation](../installation.md#optional-context-control-center).
