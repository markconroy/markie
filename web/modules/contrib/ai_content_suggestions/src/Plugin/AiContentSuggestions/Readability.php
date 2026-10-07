<?php

declare(strict_types=1);

namespace Drupal\ai_content_suggestions\Plugin\AiContentSuggestions;

use Drupal\ai_content_suggestions\AiContentSuggestionsPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the ai_content_suggestions.
 *
 * @AiContentSuggestions(
 *   id = "readability",
 *   label = @Translation("Evaluate Readability"),
 *   description = @Translation("Allow an LLM to score the readability of the content."),
 *   operation_type = "chat"
 * )
 */
final class Readability extends AiContentSuggestionsPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'prompt' => 'Provide a Flesch score of the following text as well as one sentence description of how that score should be interpreted.
      Afterward, provide a brief list of suggested improvements to enhance readability, focusing on sentence length, word complexity, and overall structure.
      Return it like so (in html), don\'t answer with other things than the score, the brief explanation and suggestions. no pleasantries or greetings.
      Always answer in the following output format:
````
<p class="score">
<b>Score</b>: SCORE<br>
<b>Explanation</b>: ONE SENTENCE TO EXPLAIN<br>
<b>Suggestions</b>: <ul><li>SUGGESTION 1</li></ul>
</p>
````

    Apart form the formatted output, return nothing else.

      The input text:',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, array $fields): void {
    $form[$this->getPluginId()] = $this->getAlterFormTemplate($fields);
    $form[$this->getPluginId()][$this->getPluginId() . '_submit']['#value'] = $this->t('Score readability');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $config = $this->getConfiguration();
    $form['prompt'] = [
      '#title' => $this->t('Evaluate readability prompt', []),
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
    if ($value = $this->getTargetFieldValue($form_state)) {
      $config = $this->getConfiguration();
      $output = (string) $this->sendChat($config['prompt'] . $value . '"');
      $cleanedResult = trim(str_replace('````', "", trim($output)));
      $cleanedResult = trim(str_replace('```', "", trim($cleanedResult)));
      $message = (!empty($cleanedResult)) ? $cleanedResult : $this->t('No result could be generated.');
    }
    else {
      $message = $this->t('The selected field has no text. Please supply content to evaluate.');
    }
    $form[$this->getPluginId()]['response']['response']['#context']['response']['response'] = [
      '#markup' => $message,
      '#weight' => 100,
    ];
  }

}
