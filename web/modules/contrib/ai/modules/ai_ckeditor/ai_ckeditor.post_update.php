<?php

/**
 * @file
 * Contains post updates for ai_ckeditor module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\editor\EditorInterface;

/**
 * Enable modify_prompt plugin for all editors that use AI CKEditor button.
 */
function ai_ckeditor_post_update_10001(&$sandbox) {
  $config_entity_updater = \Drupal::classResolver(ConfigEntityUpdater::class);
  $callback = function (EditorInterface $editor) {
    $needs_save = FALSE;
    $settings = $editor->getSettings();
    // Check if this editor uses CKEditor 5 and has AI CKEditor enabled.
    if (isset($settings['plugins']['ai_ckeditor_ai']['plugins'])) {
      $needs_save = TRUE;
      // Add the modify prompt plugin with enabled = FALSE.
      $settings['plugins']['ai_ckeditor_ai']['plugins']['ai_ckeditor_modify_prompt'] = [
        'enabled' => FALSE,
        'provider' => NULL,
      ];

      // Save the updated settings.
      $editor->setSettings($settings);
    }
    return $needs_save;
  };

  $config_entity_updater->update($sandbox, 'editor', $callback);
}

/**
 * Update logic to transform prompts to an AI prompt config entity.
 */
function ai_ckeditor_post_update_convert_ai_prompt(): void {
  // Get the AI prompt manager.
  /** @var \Drupal\ai\Service\AiPromptManager $prompt_manager */
  $promptManager = \Drupal::service('ai.prompt_manager');

  // Get the config to update.
  $configFactory = \Drupal::configFactory();
  $config = $configFactory->getEditable('ai_ckeditor.settings');

  // Make sure the appropriate prompt types exist.
  $promptManager->upsertPromptType([
    'id' => 'ai_ckeditor_complete',
    'label' => t('AI CKEditor completion prompt'),
    'variables' => [],
    'tokens' => [],
    'dependencies' => [
      'enforced' => ['module' => ['ai_ckeditor']],
    ],
  ]);
  $promptManager->upsertPromptType([
    'id' => 'ai_ckeditor_modify',
    'label' => t('AI CKEditor modify prompt'),
    'variables' => [
      [
        'name' => 'modifyPrompt',
        'help_text' => 'User-provided prompt, instructing how to modify the selected text',
        'required' => TRUE,
      ],
      [
        'name' => 'inputText',
        'help_text' => 'The input text to modify',
        'required' => TRUE,
      ],
    ],
    'tokens' => [],
    'dependencies' => [
      'enforced' => ['module' => ['ai_ckeditor']],
    ],
  ]);
  $promptManager->upsertPromptType([
    'id' => 'ai_ckeditor_reformat',
    'label' => t('AI CKEditor reformat prompt'),
    'variables' => [
      [
        'name' => 'inputText',
        'help_text' => 'The input text to reformat',
        'required' => TRUE,
      ],
    ],
    'tokens' => [],
    'dependencies' => [
      'enforced' => ['module' => ['ai_ckeditor']],
    ],
  ]);
  $promptManager->upsertPromptType([
    'id' => 'ai_ckeditor_spellfix',
    'label' => t('AI CKEditor spellfix prompt'),
    'variables' => [
      [
        'name' => 'inputText',
        'help_text' => 'The input text to fix spelling for',
        'required' => TRUE,
      ],
    ],
    'tokens' => [],
    'dependencies' => [
      'enforced' => ['module' => ['ai_ckeditor']],
    ],
  ]);
  $promptManager->upsertPromptType([
    'id' => 'ai_ckeditor_summarize',
    'label' => t('AI CKEditor summarize prompt'),
    'variables' => [
      [
        'name' => 'inputText',
        'help_text' => 'The input text to summarize',
        'required' => TRUE,
      ],
    ],
    'tokens' => [],
    'dependencies' => [
      'enforced' => ['module' => ['ai_ckeditor']],
    ],
  ]);
  $promptManager->upsertPromptType([
    'id' => 'ai_ckeditor_tone',
    'label' => t('AI CKEditor tone prompt'),
    'variables' => [
      [
        'name' => 'tone',
        'help_text' => 'Name of desired tone',
        'required' => TRUE,
      ],
      [
        'name' => 'toneDescription',
        'help_text' => 'Description of desired tone',
        'required' => FALSE,
      ],
      [
        'name' => 'inputText',
        'help_text' => 'The input text to change the tone for',
        'required' => TRUE,
      ],
    ],
    'tokens' => [],
    'dependencies' => [
      'enforced' => ['module' => ['ai_ckeditor']],
    ],
  ]);
  $promptManager->upsertPromptType([
    'id' => 'ai_ckeditor_translate',
    'label' => t('AI CKEditor translate prompt'),
    'variables' => [
      [
        'name' => 'lang',
        'help_text' => 'The language name to translate the selected text to',
        'required' => TRUE,
      ],
      [
        'name' => 'inputText',
        'help_text' => 'The input text to translate',
        'required' => TRUE,
      ],
    ],
    'tokens' => [],
    'dependencies' => [
      'enforced' => ['module' => ['ai_ckeditor']],
    ],
  ]);

  // Update "complete" prompt.
  $currentCompletePrompt = $config->get('prompts.complete');
  if (!empty($currentCompletePrompt)) {
    // Create the default prompt config entity, based on the current textual
    // prompt, configured on this site.
    $completePrompt = $promptManager->upsertPrompt([
      'id' => 'ai_ckeditor_complete__default',
      'label' => t('Default prompt for CKEditor text completion'),
      'prompt' => $currentCompletePrompt,
      'type' => 'ai_ckeditor_complete',
    ]);
    // Set the prompt as default.
    $config->set('prompts.complete', $completePrompt->id());
  }

  // Update "modify" prompt.
  $currentModifyPrompt = $config->get('prompts.modify_prompt');
  // Update the variables to the new variable naming.
  $currentModifyPrompt = strtr($currentModifyPrompt, [
    '{{ modify_prompt }}' => '{modifyPrompt}',
  ]);
  // Append inputText variable.
  $currentModifyPrompt .= "\n{inputText}";
  // Create the default prompt config entity, based on the current textual
  // prompt, configured on this site.
  $modifyPrompt = $promptManager->upsertPrompt([
    'id' => 'ai_ckeditor_modify__default',
    'label' => t('Default prompt for modifying CKEditor text'),
    'prompt' => $currentModifyPrompt,
    'type' => 'ai_ckeditor_modify',
  ]);
  // Set the prompt as default.
  $config->set('prompts.modify_prompt', $modifyPrompt->id());

  // Update "reformat" prompt.
  $currentReformatPrompt = $config->get('prompts.reformat');
  // Append inputText variable.
  $currentReformatPrompt .= "\n{inputText}";
  // Create the default prompt config entity, based on the current textual
  // prompt, configured on this site.
  $reformatPrompt = $promptManager->upsertPrompt([
    'id' => 'ai_ckeditor_reformat__default',
    'label' => t('Default prompt for reformatting CKEditor text'),
    'prompt' => $currentReformatPrompt,
    'type' => 'ai_ckeditor_reformat',
  ]);
  // Set the prompt as default.
  $config->set('prompts.reformat', $reformatPrompt->id());

  // Update "spellfix" prompt.
  $currentSpellfixPrompt = $config->get('prompts.spellfix');
  // Append inputText variable.
  $currentSpellfixPrompt .= "\n{inputText}";
  // Create the default prompt config entity, based on the current textual
  // prompt, configured on this site.
  $spellfixPrompt = $promptManager->upsertPrompt([
    'id' => 'ai_ckeditor_spellfix__default',
    'label' => t('Default prompt for fixing the spelling of CKEditor text'),
    'prompt' => $currentSpellfixPrompt,
    'type' => 'ai_ckeditor_spellfix',
  ]);
  // Set the prompt as default.
  $config->set('prompts.spellfix', $spellfixPrompt->id());

  // Update "summarize" prompt.
  $currentSummarizePrompt = $config->get('prompts.summarise');
  // Append inputText variable.
  $currentSummarizePrompt .= "\n{inputText}";
  // Create the default prompt config entity, based on the current textual
  // prompt, configured on this site.
  $summarizePrompt = $promptManager->upsertPrompt([
    'id' => 'ai_ckeditor_summarize__default',
    'label' => t('Default prompt for summarizing CKEditor text'),
    'prompt' => $currentSummarizePrompt,
    'type' => 'ai_ckeditor_summarize',
  ]);
  // Set the prompt as default.
  $config->set('prompts.summarize', $summarizePrompt->id());
  // Remove obsoleted prompts.summarise setting.
  $config->clear('prompts.summarise');

  // Update "tone" prompt.
  $currentTonePrompt = $config->get('prompts.tone');
  // Update the variables to the new variable naming.
  $currentTonePrompt = strtr($currentTonePrompt, [
    '{{ tone }}' => '{tone}',
  ]);
  // Append conditional use_description text into the prompt.
  $currentTonePrompt .= "\n{% if use_description %}";
  $currentTonePrompt .= "\n  That tone can described as: {toneDescription}";
  $currentTonePrompt .= "\n{% endif %}";
  // Append inputText variable.
  $currentTonePrompt .= "\nThe text that we want to change is the following:\n{inputText}";
  // Create the default prompt config entity, based on the current textual
  // prompt, configured on this site.
  $tonePrompt = $promptManager->upsertPrompt([
    'id' => 'ai_ckeditor_tone__default',
    'label' => t('Default prompt for changing the tone of CKEditor text'),
    'prompt' => $currentTonePrompt,
    'type' => 'ai_ckeditor_tone',
  ]);
  // Set the prompt as default.
  $config->set('prompts.tone', $tonePrompt->id());

  // Update "translate" prompt.
  $currentTranslatePrompt = $config->get('prompts.translate');
  // Update the variables to the new variable naming.
  $currentTranslatePrompt = strtr($currentTranslatePrompt, [
    '{{ lang }}' => '{lang}',
  ]);
  // Append conditional use_description text into the prompt.
  $currentTranslatePrompt .= "\n{% if use_description %}";
  $currentTranslatePrompt .= "\n  Translation context: {context}";
  $currentTranslatePrompt .= "\n{% endif %}";
  // Append inputText variable.
  $currentTranslatePrompt .= "\nThe text that we want to translate is the following:\n{inputText}";
  // Create the default prompt config entity, based on the current textual
  // prompt, configured on this site.
  $translatePrompt = $promptManager->upsertPrompt([
    'id' => 'ai_ckeditor_translate__default',
    'label' => t('Default prompt for translating CKEditor text'),
    'prompt' => $currentTranslatePrompt,
    'type' => 'ai_ckeditor_translate',
  ]);
  // Set the prompt as default.
  $config->set('prompts.translate', $translatePrompt->id());

  // Save the config.
  $config->save();
}
