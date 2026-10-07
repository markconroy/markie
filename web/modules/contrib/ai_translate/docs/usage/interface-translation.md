# Interface translation

AI Translate can translate interface strings (the UI text Drupal collects for
localization) through the same AI provider used for content.

## Requirements

- The core **Interface Translation** (`locale`) module enabled.
- The **Create AI Interface translations** permission for the roles that should
  use it.

## Using it

The module exposes an interface translation callback at
`/admin/ai-translate/interface-translate-callback`. It generates a translation
for an interface string in the requested language using the configured provider,
so translators get a suggested translation instead of starting from a blank
field.

Interface translation shares the AI provider and prompt configuration described
in [Configuration](../configuration.md). No separate setup is required beyond the
permission and a working provider.
