# Field Widget Actions with Automators

## How to configure a Field Widget Action (FWA) with automators

Configuring a "Generate tags" button in the Article content type is a 2-step process. **Note**: The automator is configured directly in the field settings form, not as a separate entity.

### Step #1: Configure the AI Automator in field settings

1. Go to `/admin/structure/types/manage/article/fields`
2. In the Operations column, click the **Edit** button of `field_tags`
3. Scroll down to find the **Enable AI Automator** section
4. Check **Enable AI Automator** checkbox
5. Under **Choose AI Automator Type**, select `LLM: Taxonomy`
6. The **AI Automator Settings** section will expand. Configure:
   - **Automator Input Mode**: `Base Mode`
   - **Automator Base Field**: `Body` (or another field that provides context)
   - **Automator Prompt**: `Please provide a comma separated list of 3 keywords that best encapsulate {{ context }}.`
   - **Advanced Settings** (expand to see):
      - **Automator Worker**: Select `Field Widget` (required for Field Widget Actions)
      - **AI Provider**: Select your provider
7. Click **Save settings** to save the field configuration
   - This automatically creates an AI Automator entity linked to this field

### Step #2: Attach the Field Widget Action in form display

1. Go to `/admin/structure/types/manage/article/form-display`
2. Click the gear icon (⚙️) next to `field_tags`
3. In the **Field Widget Actions** section:
   - In **Add New Action** dropdown, choose `Automator Taxonomy`
   - Click **Add action**
   - In the action configuration:
      - Check **Enable Automators**
      - Under **Automator to use for suggestions**, select the automator created in Step 1
      - Set **Button label** to `Generate tags`
   - Click **Update**
4. Click **Save** at the bottom of the form display page

Now when you edit an article, you should see the "Generate tags" action button under the Tags field.
