<?php

namespace Drupal\ai_automators\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\ai_automators\PluginManager\AiAutomatorTypeManager;
use Drupal\ai_automators\Traits\AutomatorInstructionTrait;
use Drupal\token\TokenEntityMapperInterface;
use Drupal\token\TreeBuilder;
use Http\Discovery\Exception\NotFoundException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The AI chain form.
 */
class AiChainForm extends FormBase {

  use AutomatorInstructionTrait;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The automator type manager.
   *
   * @var \Drupal\ai_automators\PluginManager\AiAutomatorTypeManager
   */
  protected $automatorTypeManager;

  /**
   * The token entity mapper service.
   *
   * @var \Drupal\token\TokenEntityMapperInterface
   */
  protected $tokenEntityMapper;

  /**
   * Token tree builder.
   *
   * @var \Drupal\token\TreeBuilder
   */
  protected $tokenTreeBuilder;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The entity type.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The bundle.
   *
   * @var string
   */
  protected $bundle;

  /**
   * AiChainForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\ai_automators\PluginManager\AiAutomatorTypeManager $automator_type_manager
   *   The automator type manager.
   * @param \Drupal\token\TokenEntityMapperInterface $token_entity_mapper
   *   The token entity mapper.
   * @param \Drupal\token\TreeBuilder $token_tree_builder
   *   The token tree builder.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    AiAutomatorTypeManager $automator_type_manager,
    TokenEntityMapperInterface $token_entity_mapper,
    TreeBuilder $token_tree_builder,
    RouteMatchInterface $route_match,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->automatorTypeManager = $automator_type_manager;
    $this->tokenEntityMapper = $token_entity_mapper;
    $this->tokenTreeBuilder = $token_tree_builder;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.ai_automator'),
      $container->get('token.entity_mapper'),
      $container->get('token.tree_builder'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_chain_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get entity type from the route.
    [, , $entity_type] = explode(".", $this->routeMatch->getRouteName());
    if (empty($entity_type)) {
      throw new NotFoundException('Entity type and bundle are required.');
    }
    $this->entityType = $entity_type;

    // Load the bundle dynamically.
    $bundle = NULL;
    $parameters = $this->routeMatch->getParameters()->all();
    foreach ($parameters as $parameter => $value) {
      if ($parameter !== 'entity_type') {
        $bundle = $value;
      }
    }

    if (empty($bundle)) {
      $bundle = $entity_type;
    }
    $this->bundle = $bundle;

    // Get all fields, including base fields for the entity type and bundle.
    try {
      $definitions = $this->getAutomatorInstructions($entity_type, $bundle);
    }
    catch (\Exception $e) {
      throw new NotFoundException('Entity type and bundle are required.');
    }

    // Resort definitions on weight.
    uasort($definitions, function ($a, $b) {
      return $a->get('weight') <=> $b->get('weight');
    });

    $header = [
      'label' => $this->t('Instruction Name'),
      'field' => $this->t('Manipulated Field'),
      'automator_type' => $this->t('Automator Type'),
      'source_type' => $this->t('Source Type'),
      'inputs' => $this->t('Source Field(s)'),
      'weight' => $this->t('Weight'),
      'operations' => $this->t('Operations'),
    ];

    $form['items'] = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('No fields found'),
      '#rows' => [],
      '#attributes' => [
        'id' => 'fields-table',
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'item-order-weight',
        ],
      ],
    ];

    foreach ($definitions as $definition) {
      $form['items'][$definition->id()]['#attributes']['class'][] = 'draggable';
      $form['items'][$definition->id()]['#weight'] = $definition->get('weight');

      $form['items'][$definition->id()]['label'] = [
        '#plain_text' => $definition->label(),
      ];

      $form['items'][$definition->id()]['field'] = [
        '#plain_text' => $this->fieldNameToLabel($definition->get('field_name')),
      ];

      $form['items'][$definition->id()]['automator_type'] = [
        '#plain_text' => $this->automatorTypeManager->getDefinition($definition->get('rule'))['label'],
      ];

      $form['items'][$definition->id()]['source_type'] = [
        '#plain_text' => $definition->get('input_mode') == 'base' ? 'Base Field' : 'Token',
      ];

      $form['items'][$definition->id()]['inputs'] = [
        '#plain_text' => $this->calculateInput($definition),
      ];

      $form['items'][$definition->id()]['weight'] = [
        '#type' => 'weight',
        '#delta' => 1000,
        '#default_value' => $definition->get('weight'),
        '#title' => $this->t('Weight for @label', ['@label' => $definition->label()]),
        '#title_display' => 'invisible',
        '#attributes' => ['class' => ['item-order-weight']],
      ];

      $form['items'][$definition->id()]['operations'] = [
        '#type' => 'operations',
        '#links' => [
          'edit' => [
            'title' => $this->t('Edit'),
            'url' => $definition->toUrl('edit-form'),
          ],
          'delete' => [
            'title' => $this->t('Delete'),
            'url' => $definition->toUrl('delete-form'),
          ],
        ],
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Resort'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // First get the lowest values.
    $weight = NULL;
    foreach ($form_state->getValues()['items'] as $instruction => $new_weight) {
      if (is_null($weight) || $new_weight['weight'] < $weight) {
        $weight = $new_weight['weight'];
      }
    }

    // Now loop through the instructions and update the weight.
    foreach ($form_state->getValues()['items'] as $instruction => $new_weight) {
      /** @var \Drupal\ai_automators\Entity\AiAutomator */
      $definition = $this->entityTypeManager->getStorage('ai_automator')->load($instruction);
      $definition->set('weight', (int) $weight);
      $definition->save();
      $weight++;
    }

    // Set a message.
    $this->messenger()->addMessage($this->t('Instructions have been resorted.'));
  }

  /**
   * Calculate the input.
   *
   * @param \Drupal\ai_automators\Entity\AiAutomator $definition
   *   The definition.
   *
   * @return string
   *   The input.
   */
  protected function calculateInput($definition) {
    $input = $definition->get('input_mode');
    if ($input == 'base') {
      return $this->fieldNameToLabel($definition->get('base_field'));
    }
    elseif ($input == 'token') {
      // Extract all the tokens from the prompt.
      $tokens = [];
      // Extra everything within [].
      preg_match_all('/\[(.*?)\]/', $definition->get('token'), $tokens);
      if (!empty($tokens[1])) {
        $tokens = $tokens[1];
        $labels = [];
        foreach ($tokens as $token) {
          $labels[] = $this->tokenToLabel($token);
        }
        return implode(', ', $labels);
      }
      return '';
    }
    return '';
  }

  /**
   * Field name to field label.
   *
   * @param string $field_name
   *   The field name.
   *
   * @return string
   *   The field label.
   */
  protected function fieldNameToLabel($field_name) {
    // Load the field name from the entity type.
    $field_data = $this->entityFieldManager->getFieldDefinitions($this->entityType, $this->bundle);
    return isset($field_data[$field_name]) ? $field_data[$field_name]->getLabel() : 'Unknown Field';
  }

  /**
   * Token to token label.
   *
   * @param string $token
   *   The token.
   *
   * @return string
   *   The token label.
   */
  protected function tokenToLabel($token) {
    [$entity_type, $info] = explode(':', $token);
    // Current user.
    $info = $this->tokenTreeBuilder->buildTree('current-user');
    if (isset($info['[' . $token . ']'])) {
      return $info['[' . $token . ']']['name'];
    }

    $info = $this->tokenTreeBuilder->buildTree($entity_type);
    if (isset($info['[' . $token . ']'])) {
      return $info['[' . $token . ']']['name'];
    }

    return 'Unknown Token';
  }

}
