<?php

namespace Drupal\highlight_js\Plugin\HighlightJs;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\highlight_js\HighlightJsInterface;
use Drupal\highlight_js\HighlightJsPluginBase;

/**
 * Plugin iframes.
 *
 * @HighlightJs(
 *   id = "language_select",
 *   label = @Translation("Select Language"),
 *   description = @Translation("Renders a Language Selection."),
 * )
 */
class LanguageSelect extends HighlightJsPluginBase implements HighlightJsInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'text' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {

    return [
      '#theme' => 'highlight_js_language_select',
      '#text' => $this->configuration['text'],
      '#attached' => [
        'library' => [
          'highlight_js/highlight_js.tomorrow-night',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Source Code'),
      '#default_value' => $this->configuration['text'],
      '#required' => TRUE,
    ];
    return $form;
  }

}
