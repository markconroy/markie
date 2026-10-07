# AI Logging
## What is the AI Logging module
The AI logging module allows developers to capture prompts sent to and outputs
received from LLMs by other modules and store them as entities to allow for
review and debugging. Whilst this module is safe to use on a production
environment, it stores a large amount of data into the database and so is
**recommended only to aid local development**.

## How to configure the AI Logging module
For more information, please see the [AI Logging module documentation](https://project.pages.drupalcode.org/ai/latest/modules/ai_logging/).

## Drush commands
E.g. `drush ai:logs`; see the Drush Commands file for full options.
Use `drush ai:logs:thread <thread_id>` to show every call of one conversation
thread, oldest first.

## Reply text
When "Automatically log responses" is on, each chat call also stores the text
the model replied with, as normalized by the AI module, in the "Reply" field.
Unlike the raw provider output it reads the same for every provider. A
structured (JSON schema) call stores its JSON answer there; it is
pretty-printed and shown as "Result". A turn that only calls tools has no
reply text.

## Conversation threads
The AI Log Threads page (`/admin/config/ai/logging/threads`) groups logs into
conversation threads, with a page per thread listing its calls in order.

A call belongs to a thread only if one of its request tags starts with one of
the "Thread tag prefixes" on the settings page. The rest of the tag is the
thread ID, so calls tagged with several prefixes for the same ID are shown as
one thread. The defaults cover:

- `ai_agents_thread_<id>`: added by AI Agents when progress tracking is on,
  which covers agents run through an AI Assistant (e.g. the chatbot). An agent
  run directly without progress tracking (from ECA, the API explorer or custom
  code) gets no thread tag.
- `ai_assistant_thread_<id>`: added by the AI Assistant API to its own calls.

Every other AI call (CKEditor AI, field widget actions, embeddings, etc.)
only appears in the main AI Logs list, unless a module or site adds its own
thread tag, whose prefix can then be added to the setting. Leave the setting
empty to turn the thread pages off.

Each thread is labeled with the first user message of its earliest chat call
with a tag matching the "Primary turn tag pattern" (default
`ai_agents_prompt_%`), which skips helper calls such as guardrails that join a
thread before its first real turn. To label threads differently, decorate or
replace the `ai_logging.thread_label_resolver` service, which implements
`Drupal\ai_logging\Thread\ThreadLabelResolverInterface`.
