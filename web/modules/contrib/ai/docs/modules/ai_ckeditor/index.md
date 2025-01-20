# AI CKEditor integration
## What is the AI CKEditor integration module?
The AI CKEditor integration module provides a number of plugins that integrate
with core's CK Editor 5 module. These plugins allow data to be passed to an LLM
to allow it to make suggests about content, from translations to tone
suggestions to text completion. 

## Dependencies
The AI Content Editing Tools requires the AI Core module to be installed and
configured, and a valid AI Provider module to be enabled and configured.

## Installation & configuration
1. Enable the module
2. Navigate to the page where you configure text formats and editors (/admin/config/content/formats) and choose one (example Basic HTML - Click on Configure).
3. Drag the AI Stars ✨ widget into the Active toolbar.
4. Under "CKEditor 5 plugin settings" there is now "AI tools"
5. Configure each tool to your liking (Enable/Disable , choose the right model for you).

### Plugin configuration
Some plugins require additional configuration to use.

#### Tone of Voice plugin
1. Create a taxonomy "Tone of voice"
2. In the taxonomy create some terms for the different tones (eg. Friendly / ELI5 / professional / ...)
3. In the configuration of the ckeditor widget under "Choose vocabulary" choose your Taxonomy.
4. If needed you can use "Use term description for tone description" if the tone of voice is elaborated in the description.

#### Translation plugin
1. Create a taxonomy "Languages"
2. In the taxonomy create some terms for the different languages
3. In the configuration of the ckeditor widget under "Choose vocabulary" choose your Taxonomy.

## Usage
When adding/editing content that uses CK Editor in its Text Form, you will see
an "AI Tools" button on the CK editor toolbar. Pressing that will allow you to
choose one of your configured plugins.
