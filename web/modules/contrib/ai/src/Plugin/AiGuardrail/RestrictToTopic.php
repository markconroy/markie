<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\AiGuardrail;

use Drupal\ai\Attribute\AiGuardrail;
use Drupal\ai\Guardrail\AiGuardrailPluginBase;
use Drupal\ai\Guardrail\NeedsAiPluginManagerTrait;
use Drupal\ai\Guardrail\NonDeterministicGuardrailInterface;
use Drupal\ai\Guardrail\NonStreamableGuardrailInterface;
use Drupal\ai\Guardrail\Result\GuardrailResultInterface;
use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\Guardrail\Result\StopResult;
use Drupal\ai\Guardrail\UserMessageSelectionTrait;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai\OperationType\OutputInterface;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\ai\Utility\Textarea;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the Restrict to Topic guardrail.
 */
#[AiGuardrail(
  id: 'restrict_to_topic',
  label: new TranslatableMarkup('Restrict to Topic'),
  description: new TranslatableMarkup(
    "Checks if text's main topic is specified within a list of valid topics."
  ),
)]
final class RestrictToTopic extends AiGuardrailPluginBase implements ConfigurableInterface, ContainerFactoryPluginInterface, PluginFormInterface, NonDeterministicGuardrailInterface, NonStreamableGuardrailInterface {

  use NeedsAiPluginManagerTrait;
  use StringTranslationTrait;
  use UserMessageSelectionTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    // Must stay protected and non-readonly so DependencySerializationTrait on
    // the base class can swap this service out for its ID when the guardrail
    // form is written to the form cache.
    protected PromptJsonDecoderInterface $promptJsonDecoder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.prompt_json_decode'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'matching_mode' => 'exact',
      'similarity_threshold' => 0.75,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ): array {
    $form['valid_topics'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Valid Topics'),
      '#description' => $this->t('List of valid topics, one per line.'),
      '#default_value' => $this->configuration['valid_topics'] ?? '',
      // This property will land into core soon, see
      // https://www.drupal.org/project/drupal/issues/3202631. It can stay
      // after this is added to Drupal core.
      '#normalize_newlines' => TRUE,
      // Until that the custom value callback is needed. Should be removed
      // after the issue mentioned above is merged into core and the minimum
      // supported Drupal version includes `#normalize_newlines` property.
      '#value_callback' => [Textarea::class, 'valueCallback'],
    ];

    $form['invalid_topics'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Invalid Topics'),
      '#description' => $this->t('List of invalid topics, one per line.'),
      '#default_value' => $this->configuration['invalid_topics'] ?? '',
      // This property will land into core soon, see
      // https://www.drupal.org/project/drupal/issues/3202631. It can stay
      // after this is added to Drupal core.
      '#normalize_newlines' => TRUE,
      // Until that the custom value callback is needed. Should be removed
      // after the issue mentioned above is merged into core and the minimum
      // supported Drupal version includes `#normalize_newlines` property.
      '#value_callback' => [Textarea::class, 'valueCallback'],
    ];

    $form['invalid_topics_present_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message to send if invalid topics are present'),
      '#default_value' => ($this->configuration['invalid_topics_present_message'] ?? '') ?: 'The text contains invalid topics',
      // This property will land into core soon, see
      // https://www.drupal.org/project/drupal/issues/3202631. It can stay
      // after this is added to Drupal core.
      '#normalize_newlines' => TRUE,
      // Until that the custom value callback is needed. Should be removed
      // after the issue mentioned above is merged into core and the minimum
      // supported Drupal version includes `#normalize_newlines` property.
      '#value_callback' => [Textarea::class, 'valueCallback'],
    ];

    $form['scan_all_user_messages'] = $this->buildScanAllUserMessagesElement(
      (string) $this->t('When enabled, every user message in the chat history is concatenated and sent to the classifier as one text. Useful when conversation history may have been imported, replayed, or scanned under different rules. When disabled (default) only the latest user message is classified, even if a tool result message is technically more recent.'),
      !empty($this->configuration['scan_all_user_messages']),
    );

    $form['valid_topics_missing_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message to send if no valid topics are found'),
      '#default_value' => ($this->configuration['valid_topics_missing_message'] ?? '') ?: 'The text does not contain any of the valid topics',
      // This property will land into core soon, see
      // https://www.drupal.org/project/drupal/issues/3202631. It can stay
      // after this is added to Drupal core.
      '#normalize_newlines' => TRUE,
      // Until that the custom value callback is needed. Should be removed
      // after the issue mentioned above is merged into core and the minimum
      // supported Drupal version includes `#normalize_newlines` property.
      '#value_callback' => [Textarea::class, 'valueCallback'],
    ];

    $form['matching_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Topic matching mode'),
      '#description' => $this->t('<em>Exact</em> requires the LLM to return topic names exactly as listed above. <em>Semantic</em> hardens the prompt and then fuzzy-matches any residual drift back to the closest configured topic.'),
      '#options' => [
        'exact' => $this->t('Exact (default)'),
        'semantic' => $this->t('Semantic'),
      ],
      '#default_value' => $this->configuration['matching_mode'] ?? 'exact',
    ];

    $form['similarity_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Similarity threshold'),
      '#description' => $this->t('Minimum similarity (0.0 – 1.0) between a returned topic and a configured topic for it to count as a match in semantic mode. Values below the threshold are recorded as unmatched.'),
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.01,
      '#default_value' => $this->configuration['similarity_threshold'] ?? 0.75,
      '#states' => [
        'visible' => [
          ':input[name="guardrail_settings[matching_mode]"]' => ['value' => 'semantic'],
        ],
      ],
    ];

    $default_ai_provider_value = [
      'provider' => $this->configuration['llm_provider'] ?? '',
      'model' => $this->configuration['llm_model'] ?? '',
      'config' => $this->configuration['llm_config'] ?? [],
      'use_default' => empty($this->configuration['llm_provider']),
    ];
    $form['llm_ai_provider'] = [
      '#type' => 'ai_provider_configuration',
      '#title' => $this->t('AI provider'),
      '#description' => $this->t('The AI provider and model used for internal LLM calls. Defaults to the site-wide default provider.'),
      '#operation_type' => 'chat',
      '#advanced_config' => TRUE,
      '#default_provider_allowed' => TRUE,
      '#default_value' => $default_ai_provider_value,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // The ai_provider_configuration element handles its own validation.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $values = $form_state->getValues();

    $values['llm_model'] = $values['llm_ai_provider']['model'];
    $values['llm_provider'] = $values['llm_ai_provider']['provider'];
    $values['llm_config'] = $values['llm_ai_provider']['config'];
    unset($values['llm_ai_provider']);

    $this->setConfiguration($values);
  }

  /**
   * {@inheritdoc}
   */
  public function processInput(InputInterface $input): GuardrailResultInterface {
    if (!$input instanceof ChatInput) {
      return new PassResult('Input is not a chat input, skipping topic restriction.', $this);
    }

    $scan_all = !empty($this->configuration['scan_all_user_messages']);
    $user_messages = $this->selectUserMessages($input, $scan_all);
    if ($user_messages === []) {
      return new PassResult('No user message found to analyze.', $this);
    }

    // The classifier is an LLM call, so concatenate the selected user
    // messages into a single payload instead of issuing one call per
    // message. The classifier returns the union of topics found across
    // the conversation, which is the right semantic for "is anything in
    // this chat off topic".
    $text = implode("\n\n", array_map(
      static fn (ChatMessage $message): string => $message->getText(),
      $user_messages,
    ));

    $valid_topics = array_filter(
      array_map(
        'mb_strtolower',
        array_map('trim', explode("\n", $this->configuration['valid_topics'] ?? ''))
      )
    );
    $invalid_topics = array_filter(
      array_map(
        'mb_strtolower',
        array_map('trim', explode("\n", $this->configuration['invalid_topics'] ?? ''))
      )
    );
    $all_topics = array_merge($valid_topics, $invalid_topics);
    $mode = $this->configuration['matching_mode'] ?? 'exact';
    $threshold = (float) ($this->configuration['similarity_threshold'] ?? 0.75);

    $prompt = $this->buildPrompt($text, $all_topics, $mode);

    $input = new ChatInput([
      new ChatMessage('user', $prompt),
    ]);

    $provider = $this->configuration['llm_provider'] ?? '';
    $model = $this->configuration['llm_model'] ?? '';

    if (empty($provider)) {
      $default = $this->getAiPluginManager()->getDefaultProviderForOperationType('chat');
      if ($default === NULL) {
        return new StopResult('No AI provider configured for topic classification. Please configure a default chat provider in the AI module settings.', $this);
      }
      $provider = $default['provider_id'];
      $model = $default['model_id'];
    }

    $ai_provider = $this->getAiPluginManager()->createInstance($provider);

    // @phpstan-ignore-next-line
    $ai_provider->setConfiguration($this->configuration['llm_config'] ?? []);

    // @phpstan-ignore-next-line
    $response = $ai_provider
      ->chat($input, $model, ['ai'])
      ->getNormalized();
    $response_decoded = $this->promptJsonDecoder->decode($response);
    if (!is_array($response_decoded)) {
      return new StopResult('Could not decode the AI response as JSON.', $this);
    }
    $topics_present = array_map(
      'mb_strtolower',
      $response_decoded['topics_present'] ?? []
    );

    $buckets = $this->bucketTopics($topics_present, $valid_topics, $invalid_topics, $mode, $threshold);
    $valid_topics_found = $buckets['valid_found'];
    $invalid_topics_found = $buckets['invalid_found'];
    $unmatched_topics = $buckets['unmatched'];

    if (\count($invalid_topics_found) > 0) {
      return new StopResult(
        ($this->configuration['invalid_topics_present_message'] ?? '') ?: 'The text contains invalid topics',
        $this,
        [
          'valid_topics' => $valid_topics,
          'invalid_topics_found' => $invalid_topics_found,
          'unmatched_topics' => $unmatched_topics,
        ],
      );
    }

    if (\count($valid_topics) > 0 && \count($valid_topics_found) === 0) {
      return new StopResult(
        ($this->configuration['valid_topics_missing_message'] ?? '') ?: 'The text does not contain any of the valid topics',
        $this,
        [
          'valid_topics' => $valid_topics,
          'invalid_topics_found' => $invalid_topics_found,
          'unmatched_topics' => $unmatched_topics,
        ],
      );
    }

    return new PassResult(
      'The text contains valid topics',
      $this,
      [
        'valid_topics' => $valid_topics,
        'valid_topics_found' => $valid_topics_found,
        'invalid_topics_found' => $invalid_topics_found,
        'unmatched_topics' => $unmatched_topics,
      ],
    );
  }

  /**
   * Build the LLM prompt that classifies which topics are present in the text.
   *
   * @param string $text
   *   The text being classified.
   * @param array $all_topics
   *   The merged list of configured valid + invalid topics.
   * @param string $mode
   *   Matching mode, `exact` (default prompt) or `semantic` (hardened prompt
   *   that instructs the model to return list strings verbatim).
   *
   * @return string
   *   The prompt to send to the LLM.
   */
  protected function buildPrompt(string $text, array $all_topics, string $mode): string {
    $all_topics_formatted = implode(',', $all_topics);

    if ($mode === 'semantic') {
      $instruction = 'For each topic present in the text, return the closest'
        . ' matching topic verbatim from the provided list. If no topic from'
        . ' the list matches, return an empty list. Do not invent, translate,'
        . " pluralize, or paraphrase topics \u{2014} use the list strings"
        . " exactly as given. Don't format the output in any other way, just"
        . ' return the list as JSON inside a ```json code block.';
    }
    else {
      $instruction = 'Given a text and a list of topics, return a valid json'
        . ' list of which topics are present in the text. If none, just'
        . " return an empty list. Don't format the output in any other way,"
        . ' just return the list as JSON inside a ```json code block.';
    }

    return <<<PROMPT
$instruction

Output Format:
-------------
```json
{"topics_present": []}
```

Output example when finding something relevant:
--------------
```json
{"topics_present": ["topic_4", "topic_6"]}
```

Text:
----
"$text"

Relevant Topics you can pick from:
------
$all_topics_formatted

Result:
------
PROMPT;
  }

  /**
   * Bucket LLM-returned topics into valid / invalid / unmatched lists.
   *
   * In `exact` mode this is an `in_array()` check with unknown topics
   * surfaced as unmatched. In `semantic` mode, topics that fail the exact
   * check are compared against each configured topic using `similar_text()`;
   * the best scorer wins if it clears the similarity threshold, otherwise
   * the topic is recorded as unmatched.
   *
   * @param array $topics_present
   *   The topics returned by the LLM (already lower-cased).
   * @param array $valid_topics
   *   The configured valid topic list (lower-cased).
   * @param array $invalid_topics
   *   The configured invalid topic list (lower-cased).
   * @param string $mode
   *   Matching mode, `exact` or `semantic`.
   * @param float $threshold
   *   Similarity threshold in the 0.0 – 1.0 range.
   *
   * @return array
   *   Keys: `valid_found`, `invalid_found`, `unmatched`.
   */
  protected function bucketTopics(array $topics_present, array $valid_topics, array $invalid_topics, string $mode, float $threshold): array {
    $valid_found = [];
    $invalid_found = [];
    $unmatched = [];

    foreach ($topics_present as $topic) {
      if (\in_array($topic, $valid_topics, TRUE)) {
        $valid_found[] = $topic;
        continue;
      }
      if (\in_array($topic, $invalid_topics, TRUE)) {
        $invalid_found[] = $topic;
        continue;
      }

      if ($mode !== 'semantic') {
        $unmatched[] = $topic;
        continue;
      }

      // Semantic fallback: map to the closest configured topic by string
      // similarity. `similar_text()` returns a 0-100 percentage via its
      // by-ref third argument.
      $best_score = 0.0;
      $best_bucket = NULL;
      foreach ($valid_topics as $candidate) {
        similar_text((string) $topic, (string) $candidate, $pct);
        if ($pct > $best_score) {
          $best_score = $pct;
          $best_bucket = 'valid';
        }
      }
      foreach ($invalid_topics as $candidate) {
        similar_text((string) $topic, (string) $candidate, $pct);
        if ($pct > $best_score) {
          $best_score = $pct;
          $best_bucket = 'invalid';
        }
      }

      if ($best_bucket !== NULL && ($best_score / 100) >= $threshold) {
        if ($best_bucket === 'valid') {
          $valid_found[] = $topic;
        }
        else {
          $invalid_found[] = $topic;
        }
        continue;
      }

      $unmatched[] = $topic;
    }

    return [
      'valid_found' => $valid_found,
      'invalid_found' => $invalid_found,
      'unmatched' => $unmatched,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function processOutput(OutputInterface $output): GuardrailResultInterface {
    // This guardrail only processes input, not output.
    return new PassResult('Output processing is not applicable for this guardrail.', $this);
  }

}
