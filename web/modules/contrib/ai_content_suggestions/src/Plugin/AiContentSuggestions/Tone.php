<?php

declare(strict_types=1);

namespace Drupal\ai_content_suggestions\Plugin\AiContentSuggestions;

use Drupal\ai_content_suggestions\AiContentSuggestionsPluginBase;
use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the ai_content_suggestions.
 *
 * @AiContentSuggestions(
 *   id = "tone",
 *   label = @Translation("Alter tone"),
 *   description = @Translation("Allow an LLM to provide tone suggestions about the content."),
 *   operation_type = "chat"
 * )
 */
final class Tone extends AiContentSuggestionsPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'prompt' => 'Change the tone of the following text to be {{ tone }} using the same language as the following text:',
      'taxonomy_enabled' => FALSE,
      'taxonomy' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
    );
  }

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AiProviderPluginManager $providerPluginManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $providerPluginManager);
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, array $fields): void {
    $form[$this->getPluginId()] = $this->getAlterFormTemplate($fields);
    $config = $this->getConfiguration();
    $options = [
      'friendly' => $this->t('Friendly'),
      'professional' => $this->t('Professional'),
      'helpful' => $this->t('Helpful'),
      'easier for a high school educated reader' => $this->t('High school level reader'),
      'easier for a college educated reader' => $this->t('College level reader'),
      'explained to a five year old' => $this->t("Explain like I'm 5"),
    ];
    if ($config['taxonomy_enabled'] && !empty($config['taxonomy'])) {
      $terms = $this->getTerms($config['taxonomy']);
      $terms = array_combine($terms, $terms);
      $options = $terms;
    }
    $form[$this->getPluginId()]['tone'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose tone'),
      '#description' => $this->t('Selecting one of the options will adjust/reword the body content to be appropriate for the target audience.'),
      '#options' => $options,
      '#weight' => 0,
    ];
    $form[$this->getPluginId()][$this->getPluginId() . '_submit']['#value'] = $this->t('Adjust Tone');
  }

  /**
   * Get the terms in array format.
   *
   * @param string $source_vocabulary
   *   The source vocabulary.
   *
   * @return array|false
   *   The array of the terms.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTerms(string $source_vocabulary): array|bool {

    // Use the loadTree to avoid loading all the terms.
    /** @var \Drupal\taxonomy\TermStorage $terms_storage */
    $terms_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $terms_tree = $terms_storage->loadTree($source_vocabulary);

    // Now run an extra entity query, to ensure access check.
    $query = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->getQuery();
    $query->condition('vid', $source_vocabulary);
    $query->accessCheck();

    $accessible_terms = $query->execute();

    $terms = [];
    foreach ($terms_tree as $term) {
      $tid = $term->tid;
      if (!in_array($tid, $accessible_terms)) {
        continue;
      }
      $terms[] = $term->name;
    }
    return $terms;
  }

  /**
   * {@inheritdoc}
   */
  public function updateFormWithResponse(array &$form, FormStateInterface $form_state): void {
    $config = $this->getConfiguration();
    $prompt = $config['prompt'];
    if ($value = $this->getTargetFieldValue($form_state)) {
      if ($tone = $this->getFormFieldValue('tone', $form_state)) {
        $prompt = str_replace('{{ tone }}', $tone, $prompt);
        $message = $this->sendChat($prompt . $value . '"');
      }
      else {
        $message = $this->t('Please select a tone for the LLM to suggest.');
      }
    }
    else {
      $message = $this->t('The selected field has no text. Please supply content to the field.');
    }

    $form[$this->getPluginId()]['response']['response']['#context']['response']['response'] = [
      '#markup' => $message,
      '#weight' => 100,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $config = $this->getConfiguration();
    $form['prompt'] = [
      '#title' => $this->t('Tone of voice prompt', []),
      '#type' => 'textarea',
      '#required' => TRUE,
      '#default_value' => $config['prompt'],
      '#states' => [
        'visible' => [
          ':input[name="plugins[' . $this->getPluginId() . '][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();
    $vocabulary_options = [];
    foreach ($vocabularies as $vocabulary) {
      $terms_exist = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()
        ->condition('vid', $vocabulary->id())
        ->range(0, 1)
        ->accessCheck()
        ->execute();

      if (!empty($terms_exist)) {
        $vocabulary_options[$vocabulary->id()] = $vocabulary->label();
      }
    }

    if (!empty($vocabulary_options)) {
      $form['taxonomy_enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Choose own vocabulary for tone of voice options.'),
        '#description' => $this->t('Keeping this unselected falls back to default tone of voice options (Friendly, Professional, High school, College, Five year old).'),
        '#default_value' => $config['taxonomy_enabled'],
        '#states' => [
          'visible' => [
            ':input[name="plugins[' . $this->getPluginId() . '][enabled]"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['taxonomy'] = [
        '#type' => 'select',
        '#title' => $this->t('Choose vocabulary for tone options'),
        '#options' => $vocabulary_options,
        '#description' => $this->t('Select the vocabulary that contains tone options.'),
        '#default_value' => $config['taxonomy'],
        '#states' => [
          'visible' => [
            ':input[name="plugins[' . $this->getPluginId() . '][enabled]"]' => ['checked' => TRUE],
            ':input[name="plugins[' . $this->getPluginId() . '][taxonomy_enabled]"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }
    return $form;
  }

}
