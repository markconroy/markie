# AI Validations
## What is the AI Validations module
The AI Validations module integrates with the contributed Field
Validation module to allow you to validate some fields using AI.

## Requirements
The AI Validations module requires:

- The [AI (Artificial Intelligence) Core module](https://www.drupal.org/project/ai),
  installed and configured with a valid AI Provider.
- The [Field Validation module](https://www.drupal.org/project/field_validation).
- Core Image module, for the image-based constraints.

## Installation
Install as you would normally install a contributed Drupal module. For
further information, see
[Installing Modules](https://www.drupal.org/docs/extending-drupal/installing-modules).

## Configuration
1. Enable the module and its dependencies.
2. Configure an AI Provider under
   `Configuration > AI > AI Settings` and confirm the operations required by
   the constraints you intend to use (chat, moderation, image classification,
   object detection, speech-to-text) are supported by your chosen provider.
3. Add a Field Validation rule set to the entity/field you want validated,
   at `Configuration > Content authoring > Field validation`, and select one
   of the AI Validations constraints described below.

## Using the module
The module provides seven Field Validation plugins that interact with the Field
Validation module:

1. **AI text prompt constraint**: This allows you to send a custom prompt to
   your chosen AI Provider along with a text field value. The prompt ***MUST***
   instruct the AI to evaluate the field content and return XTRUE if the
   validation has passed or XFALSE if it has failed.
2. **AI image classification constraint**: This allows you to pass images
   uploaded by users to your chosen AI Provider for classification: validation
   will fail if the image IS classified as being in the category you select when
   configuring the validation. You may also specify the level of confidence the
   AI should have that it has correctly classified the image.
3. **AI image constraint**: This allows you to send a custom prompt to
   your chosen AI Provider along with an image uploaded by a user. The prompt
   ***MUST*** instruct the AI to evaluate the field content and return XTRUE if
   the validation has passed or XFALSE if it has failed.
   Optionally, you may set a "Warning message" to enable a non-blocking
   warning tier. When it is set, the prompt ***MUST*** instead instruct the
   AI to answer with exactly one of XSAFE, XWARN or XBLOCK: XSAFE accepts
   the content silently, XBLOCK rejects it with the configured (blocking)
   message, and XWARN accepts the content while showing the warning message
   to the user. Warnings are also logged to the "ai_validations" logger
   channel, and validation running outside forms (e.g. via JSON:API or
   programmatically) is never blocked by a warning.
4. **AI object detection constraint**: This validates images using the AI
   module's object detection operation. You can require or forbid the presence
   of specific object names in an image, use AND or OR logic for multiple
   keywords, and configure when validation passes or fails.
5. **AI moderation constraint**: This validates text fields using the AI
   module's native moderation operation. Content flagged by the provider will
   fail validation. You may optionally restrict triggering to a
   comma-separated list of specific moderation categories (e.g.
   `hate,violence,sexual`); if left blank, any flagged category will cause
   the validation to fail.
6. **AI text classification constraint**: This allows you to pass a text field
   value to your chosen AI Provider for classification: validation will fail if
   the text IS classified as the category you select when configuring the
   validation. You may also specify the level of confidence the AI should have
   that it has correctly classified the text.
7. **AI audio constraint**: This allows you to validate audio file fields. A
   speech-to-text provider transcribes the uploaded audio file, then a chat
   provider evaluates the transcript against your custom prompt. The prompt
   ***MUST*** instruct the AI to return XTRUE if the validation has passed or
   XFALSE if it has failed.

For the three prompt constraints, your prompt is sent as the chat system
prompt, and the field value (or transcript) is sent as the user message. The
module adds one fixed line after the field value that reminds the AI to begin
its reply with XTRUE or XFALSE (or XSAFE, XWARN or XBLOCK in warning mode). Only
the start of the reply is checked, so a reply that opens with anything else
fails validation, or is blocked in warning mode.

For information on how to use and configure these plugins, please see the
[Field Validation module](https://www.drupal.org/project/field_validation)
module's documentation.

## Optional: Context Control Center
To push site context (style guides, policies, brand rules) into the AI text,
image, and audio prompt constraints, enable
[AI Context](https://www.drupal.org/project/ai_context) **1.0.0-beta5 or
later** alongside this module:

1. Enable AI Context and this module.
2. Enable the **Validation** consumer type under **Context Control
   Center → Consumer Types**.
3. Open **Context consumers** and edit a validation rule, such as
   `validation:{rule_uuid}`. Each AI text, image, and audio prompt rule is
   listed by its title.
4. Turn on **Push context automatically**.
5. Subscribe to scopes, and optionally set Always / Never include.

Validation chats include the host entity type, bundle, and id (id is
empty before the entity is saved). AI Context does not read the current
route. Context items scoped to Entity Types, Specific Entities, or
Taxonomy can match without Always include or Global:

- **Entity Types** match on every save, including the first save of a
  new entity.
- **Specific Entities** and **Taxonomy** need the entity id, so they
  match only when an existing entity is saved again. Taxonomy uses the
  terms stored on the saved entity, not unsaved changes on the form.

Always include and Global still work. On older AI versions that cannot
store request metadata on the chat input, those entity scopes will not
match; use Always include or Global there.

AI Context adds pushed context after your prompt. The reply-format reminder
in the user message still comes last, so long context does not push the
XTRUE/XFALSE instruction out of view. Within a request, a cached verdict is
reused only for the same rule and the same entity.

Automatic push stays off until you turn it on for a rule. Classification,
moderation, and object detection rules do not use a chat prompt, so they are
not listed.
