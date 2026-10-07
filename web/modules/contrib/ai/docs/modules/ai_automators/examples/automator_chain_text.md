# Text field + `Automator Chain Text Suggestion` action

This example shows how to trigger a multi-step [AI Automator Chain](../index.md#ai-automator-chains)
from a content edit form using the `Automator Chain Text Suggestion` Field
Widget Action, so a single button click can run several chained AI steps and
write the final result into a plain or long text field without saving or
reloading the page.

Unlike the other Field Widget Action examples, the automator here is not
configured on the field itself: it is configured on a separate, reusable
**Automator Chain**, and the Field Widget Action is what maps entity fields
into and out of that chain.

## Prerequisites

- The following modules should be enabled:
  - `ai`
  - `ai_automators`
  - `field_widget_actions`
  - `token`
  - `field_ui`
- At least one AI Provider must be configured and working under `Configuration → AI → Providers`.
- Access to the Automator Chain administration pages
  (`/admin/structure/ai/automator_chain_types`) requires one of the following,
  since this UI is otherwise hidden to avoid confusing site builders who are
  not using chains:
  - The AI CKEditor Integration (`ai_ckeditor`) or AI Agents (`ai_agents`) module enabled, or
  - `$settings['ai_automator_advanced_mode_enabled'] = TRUE;` added to your
    site's `settings.php`. This can be safely removed once your chains have
    been created.
- The **Use automator chain widget actions** permission must be granted to
  any role that should be able to click the button. Running a chain consumes
  AI provider resources, so this is kept separate from ordinary field-edit
  permissions.

## Step 1: Create an Automator Chain Type

This chain takes a raw product specification, summarizes its key selling
points, then writes marketing copy from that summary — two AI steps chained
together, each of which would otherwise need its own field on the content
type.

1. Go to `/admin/structure/ai/automator_chain_types` and add a new Automator
   Chain Type, for example:
   - **Label**: `Product Page Generator`
   - **Machine name**: `product_page_generator`
2. Under **Manage fields** for the new chain type, add the chain's input
   field:
   - **Label**: `Input Spec`, **Machine name**: `field_input_spec`
   - **Field type**: `Text (plain, long)`
   - **Mark the field as Required**. This is how the chain identifies its
     input field; it does **not** need an AI Automator configured on it.
3. Add an intermediate field to hold the extracted key points:
   - **Label**: `Summary`, **Machine name**: `field_summary`
   - **Field type**: `Text (plain, long)`
   - Enable an AI Automator on it (for example `LLM: Simple long text`), with
     **Automator Base Field** set to `Input Spec` and a prompt such as
     `Extract the key selling points from: {{ context }}`.
   - Set **Automator Worker** to `Direct`, since chains must resolve
     synchronously while the button's AJAX request is in flight.
4. Add the field that will provide the chain's output:
   - **Label**: `Page Copy`, **Machine name**: `field_page_copy`
   - **Field type**: `Text (plain, long)`
   - Enable an AI Automator on it, with **Automator Base Field** set to
     `Summary` and a prompt such as
     `Write marketing copy for a product page based on: {{ context }}`.
   - Set **Automator Worker** to `Direct`.
5. On the chain type's **Automator Run Order** tab, make sure `Summary` has a
   lower weight than `Page Copy` so the summary is generated first (see
   [AI Automator weights](../index.md#ai-automator-weights)).

## Step 2: Add the source and target fields to your content type

1. Go to `/admin/structure/types` and edit your content type (for example,
   *Product*).
2. Under **Manage fields**, add the field that editors will type the raw
   specification into:
   - **Label**: `Product Spec`, **Field type**: `Text (plain, long)`
   - This field does not need an AI Automator: it is only used as the source
     value mapped into the chain.
3. Add the field that will receive the generated marketing copy:
   - **Label**: `Product Copy`, **Field type**: `Text (plain, long)`
4. Under **Manage form display**, make sure `Product Copy` uses a widget the
   action supports, such as `Text area (multiple rows)`
   (`string_textarea`/`text_textarea`).

## Step 3: Attach the `Automator Chain Text Suggestion` action

1. Still on **Manage form display**, click the gear icon (⚙️) next to
   `Product Copy`.
2. In the **Field Widget Actions** section:
   - In **Add New Action**, choose `Automator Chain Text Suggestion`.
   - Click **Add action**.
   - In the new action's configuration:
     - Check **Enable Automator Chains**.
     - Under **Automator chain**, select `Product Page Generator`.
       - Only chains that have at least one automated field compatible with
         a text field's type are listed here.
     - A **Product Page Generator settings** section expands:
       - **Source field for Input Spec**: select `Product Spec (field_product_spec)`.
         Only fields compatible with the chain input's type are offered.
       - **Chain output field**: select `Page Copy`.
     - Set **Button label** to something like `Generate Product Copy`.
   - Click **Update**.
3. Click **Save** at the bottom of the form display page.

## Step 4: Grant the permission

1. Go to `/admin/people/permissions`.
2. Under **AI Automators**, check **Use automator chain widget actions** for
   any role that should be able to run the chain from the edit form.

## Step 5: Using the "Generate Product Copy" button

1. Create or edit content of the configured type.
2. Fill in **Product Spec** with a raw product description.
3. Scroll to **Product Copy** and click **Generate Product Copy**:
   - The request is sent via AJAX; the page does not reload.
   - A disposable Automator Chain entity of type `product_page_generator` is
     created with `field_input_spec` set to the value of `Product Spec`.
   - Its automators run in weight order: `Summary` is generated from the
     input, then `Page Copy` is generated from `Summary`.
   - The value of `Page Copy` is read back and written into `Product Copy`
     on the form, and the temporary chain entity is deleted.
4. Review and edit the generated copy before saving the entity as usual.

## How it works

- The configuration saved on the form display looks like this:

  ```yaml
  settings:
    automator_chain_type: product_page_generator
    chain_settings:
      product_page_generator:
        input_mapping:
          field_input_spec: field_product_spec
        output_field: field_page_copy
  ```

- Only the selected chain's `chain_settings` are persisted; settings for
  other chains shown (and hidden) in the configuration form are discarded on
  save.
- Values are massaged between the host entity's fields and the chain's
  fields so that compatible-but-different field types can be mapped to each
  other (for example a `text_with_summary` field feeding a plain `text_long`
  chain input): item properties the target field does not recognize are
  stripped, and the field's cardinality is respected.
- If the configured chain or output field is later deleted or de-automated,
  the button logs a warning and shows an on-screen error instead of causing
  a fatal error.
- Automator Chain entities are always temporary: they are deleted right
  after the chain runs. They briefly appear at `/admin/content/automator-chain`
  while running, which is intended for debugging only.

## Adding support for more field types

The `Automator Chain Text Suggestion` action (`automator_chain_text`) only
targets text-like fields (`string`, `string_long`, `text`, `text_long`,
`text_with_summary`). To trigger chains from other field types (for example
boolean or entity reference fields), create a new Field Widget Action plugin
that extends
`\Drupal\ai_automators\Plugin\FieldWidgetAction\AutomatorChainBaseAction`,
declaring the target `widget_types`/`field_types` in its `#[FieldWidgetAction]`
attribute (mirroring the matching single-automator plugin), and add a two-line
`field_widget_action.plugin.YOUR_PLUGIN_ID` entry of type
`field_widget_action_automator_chain_base` to your module's config schema.
Override `transformFormInput()`/`setFormInput()` only if the widget's user
input shape differs from the field's storage shape. See
[writing an AI Automators plugin](../../../developers/writing_an_ai_automators_plugin.md)
for background on Field Widget Action plugins in general.

## Related documentation

- [AI Automator Chains](../index.md#ai-automator-chains)
- [AI Automators module](../index.md)
