<?php

declare(strict_types=1);

namespace Drupal\ai_content_suggestions\Plugin\AiContentSuggestions;

use Drupal\ai_content_suggestions\AiContentSuggestionsPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the ai_content_suggestions.
 *
 * @AiContentSuggestions(
 *   id = "title_suggest",
 *   label = @Translation("Suggest title"),
 *   description = @Translation("Allow an LLM to suggest an SEO-friendly title for the content."),
 *   operation_type = "chat"
 * )
 */
final class Title extends AiContentSuggestionsPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'prompt' => 'Suggest an SEO friendly title for this page based off of the following content in 10 words or less, in the same language as the input:',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, array $fields): void {
    $form[$this->getPluginId()] = $this->getAlterFormTemplate($fields);
    $form[$this->getPluginId()][$this->getPluginId() . '_submit']['#value'] = $this->t('Suggest title');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $config = $this->getConfiguration();
    $form['prompt'] = [
      '#title' => $this->t('Suggest Title prompt', []),
      '#type' => 'textarea',
      '#required' => TRUE,
      '#default_value' => $config['prompt'],
      '#states' => [
        'visible' => [
          ':input[name="plugins[' . $this->getPluginId() . '][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function updateFormWithResponse(array &$form, FormStateInterface $form_state): void {
    $config = $this->getConfiguration();

    if ($value = $this->getTargetFieldValue($form_state)) {
      $message = $this->sendChat($config['prompt'] . $value . '"');
    }
    else {
      $message = $this->t('The selected field has no text. Please supply content to the field.');
    }

    $form[$this->getPluginId()]['response']['response']['#context']['response']['response'] = [
      '#markup' => $message,
      '#weight' => 100,
    ];
  }

}
