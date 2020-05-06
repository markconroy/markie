<?php

namespace Drupal\codesnippetgeshi\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "codesnippet" plugin.
 *
 * @CKEditorPlugin(
 *   id = "codesnippet",
 *   label = @Translation("Code snippet ckeditor button")
 * )
 */
class CodeSnippetCKEditorButton extends CKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return 'libraries/codesnippet/plugin.js';
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
    return [];
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
    return [];
  }

}
