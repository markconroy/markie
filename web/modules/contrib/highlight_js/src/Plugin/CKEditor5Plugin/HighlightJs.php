<?php

declare(strict_types=1);

namespace Drupal\highlight_js\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Url;
use Drupal\editor\EditorInterface;

/**
 * Plugin class to add dialog url for highlight js.
 */
class HighlightJs extends CKEditor5PluginDefault {

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $highlight_js_dialog_url = Url::fromRoute('highlight_js.dialog')
      ->toString(TRUE)
      ->getGeneratedUrl();
    $static_plugin_config['highlightJs']['dialogURL'] = $highlight_js_dialog_url;
    $highlight_js_preview_url = Url::fromRoute('highlight_js.preview', [
      'editor' => $editor->id(),
    ])
      ->toString(TRUE)
      ->getGeneratedUrl();
    $static_plugin_config['highlightJs']['previewURL'] = $highlight_js_preview_url;
    return $static_plugin_config;
  }

}
