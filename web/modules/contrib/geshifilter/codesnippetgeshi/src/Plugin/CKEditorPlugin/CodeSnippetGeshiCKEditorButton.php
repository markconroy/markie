<?php

namespace Drupal\codesnippetgeshi\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\editor\Entity\Editor;
use Drupal\geshifilter\GeshiFilter;

/**
 * Defines the "codesnippetgeshi" plugin.
 *
 * @CKEditorPlugin(
 *   id = "codesnippetgeshi",
 *   label = @Translation("Add a button to use codesnippetgeshi plugin.")
 * )
 */
class CodeSnippetGeshiCKEditorButton extends CKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [
      'CodeSnippet' => [
        'label' => t('Add a button to use codesnippetgeshi plugin.'),
        'image' => drupal_get_path('module', 'codesnippetgeshi') . '/icons/codesnippet.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return 'libraries/codesnippetgeshi/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function isInternal() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDependencies(Editor $editor) {
    return ['xml', 'ajax', 'codesnippet'];
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    $languages = GeshiFilter::getEnabledLanguages();
    // Before sending along to CKEditor, alpha sort and capitalize the language.
    $languages = array_map(function ($language) {
      return ucwords($language);
    }, $languages);

    asort($languages);

    return [
      'codeSnippet_languages' => $languages,
    ];
  }

}
