<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to prompt AI for db.
 */
class VectorDbGenerationForm extends FormBase {

  /**
   * The AI LLM Provider Helper.
   *
   * @var \Drupal\ai\AiProviderHelper
   */
  protected $aiProviderHelper;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The Explorer Helper.
   *
   * @var \Drupal\ai_api_explorer\ExplorerHelper
   */
  protected $explorerHelper;

  /**
   * The AI Provider.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_api_explorer_vector_db';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->aiProviderHelper = $container->get('ai.form_helper');
    $instance->requestStack = $container->get('request_stack');
    $instance->explorerHelper = $container->get('ai_api_explorer.helper');
    $instance->providerManager = $container->get('ai.provider');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Check so a search api index exists.
    $options = [];
    try {
      foreach ($this->entityTypeManager->getStorage('search_api_index')->loadMultiple() as $index) {
        $options[$index->id()] = $index->label() . ' (' . $index->id() . ')';
      };
      // Throw error if empty.
      if (empty($options)) {
        $form['markup'] = [
          'You need to create a search api index before you can use this Explorer.',
        ];
        return $form;
      }
    }
    catch (\Exception $e) {
      return ['#markup' => 'You need to install the AI Search module and setup a vector database provider and create an index before you can use this Explorer.'];
    }

    $form['#attached']['library'][] = 'ai_api_explorer/explorer';

    $form['prompt'] = [
      '#prefix' => '<div class="ai-left-side">',
      '#type' => 'textarea',
      '#title' => $this->t('Enter your prompt here. When submitted, your provider will generate a response. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#required' => TRUE,
    ];

    $form['index'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the search api index to use'),
      '#options' => $options,
      '#required' => TRUE,
    ];

    $form['result_nr'] = [
      '#type' => 'number',
      '#title' => $this->t('Results'),
      '#description' => $this->t('The number of results to return.'),
      '#default_value' => 20,
      '#required' => TRUE,
      '#min' => 1,
      '#max' => 100,
    ];

    $form['group_results'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Get All Chunks'),
      '#description' => $this->t('Get all chunks instead of best chunk per entity.'),
      '#default_value' => 0,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run DB Query'),
      '#ajax' => [
        'callback' => '::getResponse',
        'wrapper' => 'ai-db-response',
      ],
      '#suffix' => '</div>',
    ];

    $form['response'] = [
      '#prefix' => '<div id="ai-db-response" class="ai-right-side">',
      '#suffix' => '</div>',
      '#type' => 'inline_template',
      '#template' => '{{ db|raw }}',
      '#weight' => 101,
      '#context' => [
        'db' => '<h2>Database results will appear here.</h2>',
      ],
    ];

    $form['markup_end'] = [
      '#markup' => '<div class="ai-break"></div>',
      '#weight' => 1001,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state) {
    $amount = 0;
    try {
      /** @var \Drupal\search_api\Entity\Index */
      $index = $this->entityTypeManager->getStorage('search_api_index')->load($form_state->getValue('index'));
      if (!$index) {
        throw new \Exception('Index not found.');
      }
      $query = $index->query([
        'limit' => $form_state->getValue('result_nr'),
      ]);
      $query->setOption('search_api_bypass_access', TRUE);
      $query->setOption('search_api_ai_get_chunks_result', $form_state->getValue('group_results'));
      $query->keys([$form_state->getValue('prompt')]);
      $results = $query->execute();
      if ($results->getResultCount() === 0) {
        throw new \Exception('No results found.');
      }

      $response = "";
      foreach ($results as $result) {
        $content = $result->getExtraData('content');
        $response .= "<strong>Score: </strong>" . $result->getScore() . "<br>";
        $response .= "<strong>Chunk: </strong>" . ($content ? nl2br($content) : '') . "<br><br>";
        $response .= '----------------------------------------' . "<br><br>";
      }
      $amount = $results->getResultCount();
    }
    catch (\Exception $e) {
      $response = $this->explorerHelper->renderException($e);
    }

    $form['response']['#context'] = [
      'db' => '<h2>Found ' . $amount . ' results</h2>' . $response,
    ];
    return $form['response'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
