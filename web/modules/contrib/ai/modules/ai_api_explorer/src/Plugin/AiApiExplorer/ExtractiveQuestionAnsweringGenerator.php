<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Plugin\AiApiExplorer;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringInput;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai_api_explorer\AiApiExplorerPluginBase;
use Drupal\ai_api_explorer\Attribute\AiApiExplorer;

/**
 * Plugin implementation of the ai_api_explorer.
 */
#[AiApiExplorer(
  id: 'extractive_question_answering_generator',
  title: new TranslatableMarkup('Extractive Question Answering Explorer'),
  description: new TranslatableMarkup('Contains a form where you can experiment and test extractive question answering by providing a question and a context passage.'),
)]
final class ExtractiveQuestionAnsweringGenerator extends AiApiExplorerPluginBase {

  /**
   * {@inheritDoc}
   */
  public function isActive(): bool {
    return $this->providerManager->hasProvidersForOperationType('extractive_question_answering');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = $this->getFormTemplate($form, 'extractive-qa-response');

    $form['left']['question'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Enter your question here. When submitted, the provider will extract an answer from the context passage below. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your question, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#default_value' => '',
      '#required' => TRUE,
    ];

    $form['left']['context'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Context Passage'),
      '#description' => $this->t('Enter the text passage from which the answer should be extracted.'),
      '#default_value' => '',
      '#required' => TRUE,
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form['left'], $form_state, 'extractive_question_answering', 'ext_qa', AiProviderFormHelper::FORM_CONFIGURATION_FULL);
    $form['left']['ext_qa_ai_provider']['#ajax']['callback'] = $this::class . '::loadModelsAjaxCallback';

    $form['left']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Extract Answer'),
      '#ajax' => [
        'callback' => $this->getAjaxResponseId(),
        'wrapper' => 'extractive-qa-response',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state): array {
    try {
      $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'extractive_question_answering', 'ext_qa');

      $question = $form_state->getValue('question');
      $context = $form_state->getValue('context');

      if (empty($question) || empty($context)) {
        $form['right']['response']['#context']['ai_response'] = [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('Missing Input'),
          ],
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('Please provide both a question and a context passage.'),
            '#attributes' => [
              'class' => ['ai-text-response', 'ai-error-message'],
            ],
          ],
        ];
        $form_state->setRebuild();
        return $form['right'];
      }

      $input = new ExtractiveQuestionAnsweringInput($question, $context);
      $answers = $provider->extractiveQuestionAnswering($input, $form_state->getValue('ext_qa_ai_model'), ['extractive_question_answering_explorer'])->getNormalized();

      if ($answers) {
        $form['right']['response']['#context']['ai_response']['table'] = [
          '#type' => 'table',
          '#header' => [
            'answer' => $this->t('Answer'),
            'score' => $this->t('Score'),
            'start' => $this->t('Start'),
            'end' => $this->t('End'),
          ],
          '#rows' => [],
          '#empty' => $this->t('There was an issue retrieving answers.'),
        ];
        foreach ($answers as $row) {
          $form['right']['response']['#context']['ai_response']['table']['#rows'][] = [
            $this->t('<strong>:answer</strong>', [
              ':answer' => $row->getAnswer(),
            ]),
            $this->t('<em>:score</em>', [
              ':score' => $row->getScore(),
            ]),
            $row->getStart(),
            $row->getEnd(),
          ];
        }

        $form['right']['response']['#context']['ai_response']['code'] = $this->normalizeCodeExample($provider, $form_state, $question, $context);
      }
      else {
        $form['right']['response']['#context']['ai_response'] = [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('No Answer Found'),
          ],
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('The provider could not extract an answer from the given context. Please check your input and try again.'),
            '#attributes' => [
              'class' => ['ai-text-response', 'ai-error-message'],
            ],
          ],
        ];
      }
    }
    catch (\TypeError $e) {
      $form['right']['response']['#context']['ai_response'] = [
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Configuration Error'),
        ],
        'message' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('The AI provider could not be used. Please make sure a model is selected and the provider is properly configured.'),
          '#attributes' => [
            'class' => ['ai-text-response', 'ai-error-message'],
          ],
        ],
      ];
    }
    catch (\Exception $e) {
      $form['right']['response']['#context']['ai_response'] = [
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Error'),
        ],
        'message' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->explorerHelper->renderException($e),
          '#attributes' => [
            'class' => ['ai-text-response', 'ai-error-message'],
          ],
        ],
      ];
    }

    $form_state->setRebuild();
    return $form['right'];
  }

  /**
   * Gets the normalized code example.
   *
   * @param \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy $provider
   *   The provider.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $question
   *   The question.
   * @param string $context
   *   The context passage.
   *
   * @return array
   *   The normalized code example.
   */
  public function normalizeCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state, string $question, string $context): array {
    $code = $this->getCodeExampleTemplate();
    if (count($provider->getConfiguration())) {
      $code['code']['#value'] .= $this->addProviderCodeExample($provider);
    }
    $code['code']['#value'] .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('ext_qa_ai_provider') . "');<br>";
    if (count($provider->getConfiguration())) {
      $code['code']['#value'] .= "\$ai_provider->setConfiguration(\$config);<br>";
    }
    $code['code']['#value'] .= "// Normalize the input.<br>";
    $code['code']['#value'] .= "\$question = '" . $question . "';<br>";
    $code['code']['#value'] .= "\$context = '" . $context . "';<br>";
    $code['code']['#value'] .= "\$input = new \\Drupal\\ai\\OperationType\\ExtractiveQuestionAnswering\\ExtractiveQuestionAnsweringInput(\$question, \$context);<br><br>";
    $code['code']['#value'] .= "// Run the extractive question answering.<br>";
    $code['code']['#value'] .= "\$response = \$ai_provider->extractiveQuestionAnswering(\$input, '" . $form_state->getValue('ext_qa_ai_model') . "', ['your_module_name']);<br><br>";
    $code['code']['#value'] .= "// Output is an array of ExtractiveQuestionAnsweringItem objects.<br>";
    $code['code']['#value'] .= "\$answers = \$response->getNormalized();<br>";

    return $code;
  }

}
