---
name: setup-automators
description: Configures AI Automators (ai_automator config entities) that auto-populate fields on a running Drupal site, without touching the admin UI. Also lists replacement tokens and available automator type plugins. Driven through dedicated Tool-module plugins via drush tool:run. Does NOT create new automator type plugin classes — use the create-automator-type skill for that.
---

<!-- ============================================================
     HARD STOP — READ THIS BEFORE DOING ANYTHING ELSE
     ============================================================ -->

> **CRITICAL GATE — DO NOT RUN ANY DRUSH COMMANDS, CHANGE ANY CONFIG,
> OR ENABLE ANY MODULE UNTIL YOU HAVE COMPLETED STEP 0 AND RECEIVED
> THE USER'S EXPLICIT ANSWERS.**
>
> This skill modifies live field-automation behavior. Running the wrong
> tool can silently start (or stop) an LLM from writing into a content
> field on every future save. Identify the intent first. Act second.

# Setup Automators

This skill reads and writes AI Automator configuration on a running Drupal
site. It covers three areas:

- **Automator entities** (`ai_automator`) — the per-field configuration that
  makes a field auto-populate on entity save (e.g. summarizing body text
  into a summary field, generating alt text for an image). Each entity is
  keyed by the host field it targets, with a computed ID of the form
  `<entity_type>.<bundle>.<field_name>.default`.
- **Automator type plugins** — the catalog of automator "rules" (e.g.
  `summarize_to_text_long`, `llm_image_alt_text`) that an `ai_automator`
  entity's `rule` property points at. This skill only lists and inspects
  existing types; it does not author new ones.
- **Replacement tokens** — the placeholders available for a prompt in a
  given field/entity context: Twig-style placeholders (e.g. `{{ context }}`)
  for `input_mode=base`, or core Token module placeholders (e.g.
  `[node:title]`) for `input_mode=token`.

Settings are changed through dedicated Tool-module plugins invoked via
`drush tool:run`, so every action is typed, validated, and access-checked.

> **This skill operates existing automators.** To author a new automator
> type plugin class, use the `create-automator-type` skill instead.

## Step 0: Clarify What the User Wants — MANDATORY BLOCKING STEP

**DO NOT RUN ANY COMMANDS UNTIL THIS STEP IS COMPLETE.**

Ask the user:

> **What would you like to do with AI Automators?**
>
> **Discovery**
> - **List automator type plugins** — see all available automator "rules"
>   with their descriptions
> - **Inspect one automator type** — full configuration detail for one rule
>   (needs prompt? advanced mode? allowed field types? placeholder text?)
> - **List replacement tokens** — see what can be referenced in a prompt for
>   a specific field (Twig placeholders, or core Token-module placeholders)
>
> **Automator configuration (`ai_automator`)**
> - **List configured automators** — optionally filtered by entity type,
>   bundle, and/or field
> - **Read an automator** — show full configuration for one field
> - **Create an automator** — attach automation to a field (needs: entity
>   type, bundle, field name, automator type, and a prompt or token string)
> - **Update an automator** — change prompt, provider, weight, worker type,
>   guardrail set, etc. (only supplied fields change)
> - **Delete an automator** — permanently remove one (requires confirmation)

**Wait for the user's answer.** For create/update operations, also ask for
the specific values needed. Use Step 3 (discover) to surface valid values —
rule IDs, tokens, existing configuration — if the user is unsure.

## Step 1: Record the Tool Module's Current State

Before enabling anything, check whether the Tool module is already enabled
so you can restore the site's module state afterward. Use a read-only
listing command — do not run arbitrary PHP to answer this question.

```bash
drush pm:list | grep "Tool (tool)"
```

On ddev sites prefix with `ddev`:

```bash
ddev drush pm:list | grep "Tool (tool)"
```

- If a line is returned and shows `Enabled` → record `TOOL_WAS_ENABLED=true`.
- If a line is returned and shows `Disabled` → record `TOOL_WAS_ENABLED=false`.
- If no line is returned at all, the `tool` module package is not present in
  the codebase yet — record `TOOL_INSTALLED=false` and `TOOL_WAS_ENABLED=false`,
  and see Step 2 for the composer install fallback.

Keep this value in mind for Step 5.

## Step 2: Enable the Tool Module if Needed

If `TOOL_INSTALLED=false` (the previous step returned no output at all), the
`drupal/tool` package itself is missing from this codebase — `drush
pm:enable` will fail with "module not found" until it is required via
Composer:

```bash
composer require drupal/tool:^1.0@beta
```

On ddev sites:

```bash
ddev composer require drupal/tool:^1.0@beta
```

Once the package is present (or if it already was), enable the module if
`TOOL_WAS_ENABLED=false`:

```bash
drush pm:enable tool -y && drush cr
```

On ddev sites:

```bash
ddev drush pm:enable tool -y && ddev drush cr
```

If `TOOL_WAS_ENABLED=true`, skip the enable step entirely — the module is
already active. Note that a package installed by this step (`TOOL_INSTALLED=false`
case) is **not** removed in Step 5 — only the module's enabled/disabled state is
restored. Removing a Composer dependency is out of scope for this skill; mention
it to the user if they want the package removed afterward too.

## Step 3: Discover Available Types, Tokens, and Automators (when needed)

Run only the discovery commands relevant to the user's intent.

### 3a. List available automator type plugins

When the user wants to create an automator but is unsure of the rule ID:

```bash
drush tool:run ai_automator:list_automator_types --json
```

The output lists all registered automator type IDs, labels, target field
types, and descriptions.

### 3b. Inspect one automator type in full detail

```bash
drush tool:run ai_automator:list_automator_types \
  --input='{"type":"summarize_to_text_long"}' --json
```

Returns `needs_prompt`, `advanced_mode`, `allowed_inputs`, and
`placeholder_text` for the given rule — everything needed to configure it.

### 3c. List replacement tokens for a field

```bash
drush tool:run ai_automator:list_tokens \
  --input='{"entity_type":"node","bundle":"article","field_name":"field_summary","rule":"summarize_to_text_long","mode":"base"}' \
  --json
```

Use `"mode":"token"` instead of `"mode":"base"` to see core Token-module
placeholders for `input_mode=token`. `rule` can be omitted if an automator
is already configured on this field — it falls back to that automator's rule.

### 3d. List configured automators

```bash
drush tool:run ai_automator:list_automators \
  --input='{"entity_type":"node","bundle":"article"}' --json
```

All filters (`entity_type`, `bundle`, `field_name`) are optional; omit
`--input` entirely to list every configured automator on the site.

## Step 4: Execute the Requested Operations

Run only the tools that match the user's intent from Step 0.

### 4a. Read one automator's full details

```bash
drush tool:run ai_automator:get_automator \
  --input='{"entity_type":"node","bundle":"article","field_name":"field_summary"}' \
  --json
```

Identify the automator either by `automator_id` (the computed ID, e.g.
`"node.article.field_summary.default"`) or by the `entity_type`/`bundle`/
`field_name` trio, as shown above. Returns the full record including
`prompt`/`token` and `plugin_config`.

### 4b. Create or update an automator

```bash
drush tool:run ai_automator:save_automator \
  --input='{"entity_type":"node","bundle":"article","field_name":"field_summary","label":"Summary Default","rule":"summarize_to_text_long","input_mode":"base","base_field":"body","prompt":"Summarize the following text in two sentences:\n\n{{ context }}","plugin_config_extra":"{\"automator_ai_provider\":\"openai\",\"automator_ai_model\":\"gpt-4o\"}"}' \
  --json
```

- The entity ID is **always computed** as
  `<entity_type>.<bundle>.<field_name>.default` — it is never accepted as a
  separate input.
- If no automator exists yet for this field, one is created. `rule` is
  required for creation, plus `base_field` and `prompt` (for
  `input_mode=base` rules that need a prompt) or `token` (for
  `input_mode=token`).
- If an automator already exists for this field, only the supplied fields
  change — omitted fields (including rule-specific `plugin_config` keys not
  present in `plugin_config_extra`) are left untouched.
- `plugin_config_extra` is a **JSON-encoded object string** nested inside the
  outer `--input` JSON, holding rule-specific settings not covered by the
  named inputs (e.g. `automator_ai_provider`, `automator_ai_model`,
  `automator_clean_up`). Every key inside it must start with `automator_`.
- `rule`/field-type compatibility is validated server-side; an incompatible
  combination fails with the list of compatible rule IDs for that field.

**Chaining automators — set explicit weights.** Automators on the same
entity run in ascending `weight` order on entity presave
(`AiAutomatorEntityModifier::saveEntity()`). Every new automator defaults to
`weight = 100`; if two automators share the same weight, execution order
falls back to alphabetical order of the computed config entity ID — not
dependency order. So if one automator's `base_field` or token input is
populated *by another automator* on the same entity (e.g. a `summary` field
generated from `title`, then a `body` field generated from that `summary`),
leave both at the default weight and the chain may not fully resolve until a
second save. Instead, give the upstream ("producer") automator a strictly
lower weight than the downstream ("consumer") automator — space them by 10s
rather than relying on the default:

```bash
drush tool:run ai_automator:save_automator \
  --input='{"entity_type":"node","bundle":"article","field_name":"field_summary","label":"Summary from Title","rule":"summarize_to_text_long","input_mode":"token","token":"[node:title]","weight":10}' \
  --json

drush tool:run ai_automator:save_automator \
  --input='{"entity_type":"node","bundle":"article","field_name":"field_body","label":"Body from Summary","rule":"summarize_to_text_long","input_mode":"base","base_field":"field_summary","prompt":"Expand the following summary into a full article body:\n\n{{ context }}","weight":20}' \
  --json
```

If adding to a site that already has automators configured, check
`list_automators` (Step 3d) first so the new weights don't collide with the
existing relative ordering.

**Example — change only the prompt on an existing automator, leaving the
provider, model, and every other setting intact:**

```bash
drush tool:run ai_automator:save_automator \
  --input='{"entity_type":"node","bundle":"article","field_name":"field_summary","prompt":"Summarize in one punchy sentence:\n\n{{ context }}"}' \
  --json
```

### 4c. Delete an automator (destructive — confirm first)

**Always ask the user to confirm before running this command.** Deletion
cannot be undone. The underlying Drupal field and its data are **not**
touched — only the automation config is removed.

```bash
drush tool:run ai_automator:delete_automator \
  --input='{"entity_type":"node","bundle":"article","field_name":"field_summary"}' \
  --json
```

## Step 5: Restore the Tool Module State

If `TOOL_WAS_ENABLED=false` (you enabled Tool in Step 2), uninstall it now:

```bash
drush pm:uninstall tool -y && drush cr
```

On ddev sites:

```bash
ddev drush pm:uninstall tool -y && ddev drush cr
```

If `TOOL_WAS_ENABLED=true`, leave the module enabled — you did not change
its state.

## Step 6: Report What Changed

Summarise the actions taken:

- Which automator types or tokens were read.
- Which automators were created, updated, or deleted, and their key
  settings (rule, base field, worker type, provider if known).
- Whether the Tool module was enabled and subsequently restored.
- Any errors encountered and what was skipped as a result.

## Critical Rules

1. **Always complete Step 0 before running any command.** Do not infer the
   user's intent from context alone — ask.
2. **Always check Tool module state in Step 1** before enabling it. Never
   assume the site's current module state.
3. **Always restore the Tool module in Step 5** if Step 2 enabled it. The
   site's module state must be identical before and after a skill run.
4. **`delete_automator` is destructive.** Always ask the user to explicitly
   confirm before running it. Never call it speculatively or as a side
   effect of another operation.
5. **The automator ID is always computed, never supplied.** It is always
   `<entity_type>.<bundle>.<field_name>.default` — identify automators by
   their entity type/bundle/field name trio (or the computed ID if already
   known), not by guessing a slug.
6. **`plugin_config` is a flat bag whose keys all use the `automator_`
   prefix**, and it duplicates several of the entity's own top-level
   properties (rule, mode, prompt, etc.) in addition to rule-specific
   settings (`automator_ai_provider`, `automator_ai_model`,
   `automator_clean_up`, ...). `save_automator` only ever touches the keys
   implied by the fields you actually pass, plus whatever you put in
   `plugin_config_extra` — it never wipes unrelated existing keys on update.
7. **`plugin_config_extra` is double-encoded**, exactly like
   `guardrail_settings` in the `setup-guardrails` skill: a JSON object
   string nested inside the outer `--input` JSON, e.g.
   `"{\"automator_ai_provider\":\"openai\"}"`. Every key inside it must
   start with `automator_`.
8. **Rule/field-type compatibility is enforced server-side** using the
   site's own `ai_automator.field_rules` service — if you pick a `rule`
   that isn't compatible with the field's type, `save_automator` fails and
   tells you exactly which rule IDs are compatible. Use
   `ai_automator:list_automator_types` beforehand if unsure.
9. **`base_field`+`prompt` vs `token` requirement depends on `input_mode`.**
   In `base` mode, both are required for rules that need a prompt
   (`needs_prompt` is `true`); in `token` mode, `token` is required instead.
10. **When automators are chained** (one automator's input reads a field that
    another automator on the same entity populates), set explicit, distinct
    `weight` values so the producer runs before the consumer. Leaving both at
    the default weight (100) makes execution order fall back to alphabetical
    config-ID order, not dependency order, and the chain may not fully
    resolve until a second save.
11. **Use `--json` on all `drush tool:run` calls** to get structured output
    that is easier to parse and present to the user.
12. **Run `drush cr` after enabling or uninstalling the Tool module** so
    the plugin manager cache is rebuilt before and after the skill runs.
13. **Do not write automators for fields the user did not ask to change.**
    Run only the tools relevant to the stated intent.
14. **The `--uid` option defaults to user 1** on `drush tool:run`. If access
    is denied, verify the drush user has the `administer ai_automator`
    permission.
15. **This skill does NOT author new automator type plugin classes.** For
    that, use the `create-automator-type` skill.

## Reference Examples

- `src/Plugin/tool/Tool/ListAutomatorTypes.php` — queries
  `plugin.manager.ai_automator` for all registered automator type plugins;
  full detail when filtered by `type`
- `src/Plugin/tool/Tool/ListTokens.php` — Twig placeholders from a rule's
  `tokens()` (base mode) or core Token module placeholders (token mode)
- `src/Plugin/tool/Tool/ListAutomators.php` — lists all `ai_automator`
  entities, optionally filtered
- `src/Plugin/tool/Tool/GetAutomator.php` — reads one entity's full details
- `src/Plugin/tool/Tool/SaveAutomator.php` — upserts an `ai_automator`
  entity; validates rule/field-type compatibility via `ai_automator.field_rules`;
  rebuilds `plugin_config` from the existing bag on update so unrelated
  `automator_*` keys survive
- `src/Plugin/tool/Tool/DeleteAutomator.php` — deletes one `ai_automator`
  entity (`destructive: TRUE`)
- `src/AiAutomatorEntityModifier.php` (`saveEntity()`) — sorts an entity's
  automators by `weight` (ascending) and runs each in that order on entity
  presave; the authoritative source for the chaining/weight behavior above
- `src/Traits/AutomatorToolIdentifierTrait.php` — computes the canonical
  `<entity_type>.<bundle>.<field_name>.default` ID, shared by every tool
  above that identifies an automator
- `src/Entity/AiAutomator.php` — entity definition; fields: `id`, `label`,
  `rule`, `input_mode`, `weight`, `worker_type`, `entity_type`, `bundle`,
  `field_name`, `edit_mode`, `base_field`, `prompt`, `token`,
  `guardrail_set_id`, `plugin_config`
- `src/FormAlter/AiAutomatorFieldConfig.php` (`addConfigValues()`,
  `validateConfigValues()`) — the authoritative reference for how the admin
  form itself builds and validates `plugin_config`
- `src/AiFieldRules.php` — `findRuleCandidates()`/`findRule()`, reused by
  `save_automator` and `list_tokens` for rule/field validation and lookup
- `src/PluginManager/AiAutomatorTypeManager.php`,
  `src/PluginManager/AiAutomatorFieldProcessManager.php` — automator type
  and worker type (`direct`/`queue`/`batch`/`action`/`field_widget_actions`)
  plugin managers
- `src/PluginBaseClasses/RuleBase.php` — default `tokens()`, `needsPrompt()`,
  `advancedMode()`, `helpText()` shared by most LLM-driven automator types
- `config/schema/ai_automators.schema.yml` — authoritative schema for the
  `ai_automator` config entity

## Summary of Operations

| User intent | Tool invoked | Entity / config written |
|---|---|---|
| List automator type plugins | `ai_automator:list_automator_types` | — (read-only) |
| Inspect one automator type | `ai_automator:list_automator_types` (`type` filter) | — (read-only) |
| List tokens for a field | `ai_automator:list_tokens` | — (read-only) |
| List configured automators | `ai_automator:list_automators` | — (read-only) |
| Read one automator | `ai_automator:get_automator` | — (read-only) |
| Create/update automator | `ai_automator:save_automator` | `ai_automator` entity |
| Delete automator | `ai_automator:delete_automator` | `ai_automator` entity |
