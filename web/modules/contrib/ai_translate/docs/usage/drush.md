# Drush commands

AI Translate ships two Drush commands for translating from the command line.

## Translate an entity

Create AI translations of one or more entities.

```bash
drush ai:translate-entity <entityType> <entityIds> <langFrom> <langTo>
```

| Argument | Description |
|---|---|
| `entityType` | Entity type ID, for example `node`. |
| `entityIds` | Comma-separated entity IDs, for example `16,18,20,21`. |
| `langFrom` | Source language code, for example `en`. |
| `langTo` | Target language code, for example `fr`. |

Example: translate three nodes from English to French.

```bash
drush ai:translate-entity node 16,18,20 en fr
```

If a target translation already exists for an entity, that entity is skipped.
The command uses the same extraction, provider, and prompt configuration as the
Translate tab.

## Translate a string

Translate a single piece of text and print the result.

```bash
drush ai:translate-text <text> <langFrom> <langTo>
```

| Argument | Description |
|---|---|
| `text` | The text to translate. |
| `langFrom` | Source language code, for example `en`. |
| `langTo` | Target language code, for example `fr`. |

Example:

```bash
drush ai:translate-text "The white tiger is a rare color variant." en es
```
