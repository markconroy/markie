import { Command } from 'ckeditor5/src/core';

export default class AiDrupalDialog extends Command {

  constructor(editor) {
    super(editor);
  }

  execute(group_name, plugin_id, plugin_label) {
    const config = this.editor.config;
    const options = config.get('ai_ckeditor_ai');
    const {dialogURL, openDialog, dialogSettings = {}} = options;

    if (!dialogURL || typeof openDialog !== 'function') {
      return;
    }

    const selected = this.editor.editing.model.getSelectedContent(this.editor.model.document.selection);
    const selectedText = this.editor.data.stringify(selected);

    dialogSettings.title = dialogSettings.title + ' - ' + plugin_label;

    const url = new URL(dialogURL, document.baseURI);
    if (selectedText.length > 0) {
      url.searchParams.append('selected_text', selectedText);
    }
    // Since we can't attach an editor instance to the dialog, we need to
    // pass the key for the configuration in the query.
    url.searchParams.append('editor_id', this.editor.sourceElement.dataset.editorActiveTextFormat);
    url.searchParams.append('plugin_id', plugin_id);

    openDialog(
      url.toString(),
      ({attributes}) => {
        const model = this.editor.model;
        model.change(writer => {
          const selection = model.document.selection;
          const insertPosition = selection.getFirstPosition();

          // If the insert position is a selection, remove the selection.
          if (selection.hasOwnRange) {
            const range = selection.getFirstRange();
            writer.remove(range);
          }

          if (typeof attributes.returnsHtml != 'undefined' && attributes.returnsHtml) {

            // Covert the value to html and insert it.
            const viewFragment = this.editor.data.processor.toView(attributes.value);
            const modelFragment = this.editor.data.toModel(viewFragment);
            this.editor.model.insertContent(modelFragment);
            //writer.insert(modelFragment, insertPosition);
          }
          else {

            // Insert the value as plain text.
            // const textNode = writer.createText(attributes.value);
            // writer.insert(insertPosition, textNode);
            this.editor.model.insertContent(
              writer.createText(attributes.value)
            );
          }
        });
      },
      dialogSettings,
    );
  }

  /**
   * If the dialog is active, disable the AI plugin.
   */
  refresh() {
    const el = document.getElementsByClassName('ckeditor5-ai-ckeditor-dialog-form');
    this.isEnabled = (el.length === 0);
    this.isOn = this.isEnabled;
    this.isReadOnly = this.isEnabled;
  }

}
