# AI Validations
## What is the AI Validations module
The AI Validations module integrates with the contributed Field
Validation module to allow you to validate some fields using AI.

## Dependencies
The AI Validations module requires the AI Core module to be installed and
configured, and a valid AI Provider module to be enabled and configured.

It also requires the [Field Validation module](https://www.drupal.org/project/field_validation).

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

For information on how to use and configure these plugins, please see the
[Field Validation module](https://www.drupal.org/project/field_validation)
module's documentation.
