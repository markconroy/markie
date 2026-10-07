<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\ai_automators\Service\Automate;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\field_widget_actions\FieldWidgetActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Abstract base class for automator chain actions.
 *
 * Runs an automator chain (an automator_chain bundle) from a field widget
 * button: the configured source fields on the host entity are passed to the
 * chain's required input fields, the chain runs via the
 * ai_automator.automate service, and the value of the configured output
 * field is written into the field widget the button is attached to.
 *
 * Extension contract for new field-type variants: add an attribute-only
 * subclass declaring the widget_types / field_types it attaches to
 * (mirror the matching single-automator plugin, e.g. Text or Boolean),
 * override transformFormInput() / setFormInput() only when the widget's
 * user input shape differs from the field's storage shape, and add a config
 * schema entry of type field_widget_action_automator_chain_base for the new
 * plugin ID.
 */
abstract class AutomatorChainBaseAction extends AutomatorBaseAction {

  /**
   * The automate service.
   *
   * @var \Drupal\ai_automators\Service\Automate
   */
  protected Automate $automate;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Memoized chain options for the current field definition.
   *
   * @var array|null
   */
  protected ?array $chainOptions = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->automate = $container->get('ai_automator.automate');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'settings' => [
        'automator_chain_type' => '',
        'chain_settings' => [],
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, $action_id = NULL) {
    $settings = $this->getConfiguration()['settings'] ?? [];
    // Skip AutomatorBaseAction::buildConfigurationForm(): its required
    // automator_id select does not apply to chains.
    $element = FieldWidgetActionBase::buildConfigurationForm($form, $form_state, $action_id);
    $element['enabled']['#title'] = $this->t('Enable Automator Chains');
    $element['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Automator Chain'),
    ];
    $field_definition = $this->getFieldDefinition();
    if (empty($field_definition)) {
      return $element;
    }
    // The form display UI keys each action subform by its action ID (a UUID
    // for actions added through the UI), falling back to the plugin ID.
    $selector_base = 'fields[' . $field_definition->getName() . '][settings_edit_form][third_party_settings][field_widget_actions][' . ($action_id ?: $this->getPluginId()) . ']';
    $element['settings']['#states'] = [
      'visible' => [
        ':input[name="' . $selector_base . '[enabled]"]' => ['checked' => TRUE],
      ],
    ];

    $chain_options = $this->getChainOptions();
    $element['settings']['automator_chain_type'] = [
      '#title' => $this->t('Automator chain'),
      '#description' => $this->t('The automator chain to execute when the button is clicked.'),
      '#type' => 'select',
      '#options' => $chain_options,
      '#required' => TRUE,
      '#empty_option' => $this->t('- Pick an automator chain -'),
      '#default_value' => $settings['automator_chain_type'] ?? '',
    ];

    foreach ($chain_options as $chain_id => $chain_label) {
      $chain_settings = $settings['chain_settings'][$chain_id] ?? [];
      $chain_definitions = $this->entityFieldManager->getFieldDefinitions('automator_chain', $chain_id);
      $element['settings']['chain_settings'][$chain_id] = [
        '#type' => 'details',
        '#title' => $this->t('%chain settings', ['%chain' => $chain_label]),
        '#open' => TRUE,
        '#states' => [
          'visible' => [
            ':input[name="' . $selector_base . '[settings][automator_chain_type]"]' => ['value' => $chain_id],
          ],
        ],
      ];
      foreach ($this->automate->getRequiredFields($chain_id) as $chain_field => $chain_field_label) {
        $chain_input = $chain_definitions[$chain_field] ?? NULL;
        $element['settings']['chain_settings'][$chain_id]['input_mapping'][$chain_field] = [
          '#type' => 'select',
          '#title' => $this->t('Source field for %input', ['%input' => $chain_field_label]),
          '#description' => $this->t('The field on this entity whose value is passed to the %input input of the chain. Expects a @type value.', [
            '%input' => $chain_field_label,
            '@type' => $chain_input ? $chain_input->getType() : $this->t('unknown'),
          ]),
          '#options' => $this->getSourceFieldOptions($field_definition, $chain_input),
          '#empty_option' => $this->t('- None -'),
          '#default_value' => $chain_settings['input_mapping'][$chain_field] ?? '',
        ];
      }
      $element['settings']['chain_settings'][$chain_id]['output_field'] = [
        '#type' => 'select',
        '#title' => $this->t('Chain output field'),
        '#description' => $this->t('The field on the chain whose generated value is written into this field.'),
        '#options' => $this->automate->getAutomatedFields($chain_id, $this->getFieldTypes()),
        '#empty_option' => $this->t('- Pick an output field -'),
        '#default_value' => $chain_settings['output_field'] ?? '',
      ];
    }
    // Prune the settings of unselected chains on save. Must be a static
    // callable: the form display form is AJAX-enabled and form-cached, so
    // an instance callable would serialize the plugin and its services.
    $element['settings']['#element_validate'] = [[static::class, 'validateChainSettings']];
    return $element;
  }

  /**
   * Element validate callback: keeps only the selected chain's settings.
   *
   * All per-chain settings blocks must render so the #states-driven chain
   * switching works without AJAX, but only the selected chain's settings
   * should be persisted — otherwise every compatible chain ends up in the
   * exported form display config.
   *
   * @param array $element
   *   The settings element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateChainSettings(array &$element, FormStateInterface $form_state): void {
    $values = $form_state->getValue($element['#parents']) ?? [];
    $selected = $values['automator_chain_type'] ?? '';
    $values['chain_settings'] = array_intersect_key($values['chain_settings'] ?? [], [$selected => TRUE]);
    $form_state->setValueForElement($element, $values);
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    // If the field definition is not set, its not the setup form.
    if (!$this->getFieldDefinition()) {
      return TRUE;
    }
    // Only show if a chain with a compatible output field exists.
    return count($this->getChainOptions()) > 0;
  }

  /**
   * {@inheritdoc}
   *
   * Running a chain consumes AI provider resources, so the button is
   * gated by a dedicated permission. The check lives here rather than in
   * isAvailable(), which is also called in the form display config UI and
   * would hide the settings from site builders. An unrendered button is
   * not a recognized Form API triggering element, so this also covers
   * direct POSTs of the button name.
   */
  protected function actionButton(array &$form, FormStateInterface $form_state, array $context = []) {
    if (!$this->currentUser->hasPermission('use automator chain widget actions')) {
      return;
    }
    parent::actionButton($form, $form_state, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function populateAutomatorValues(array &$form, FormStateInterface $form_state, string $form_key, ?int $key = NULL): array {
    // Re-check the permission gating the button: the form may have been
    // cached before the permission was revoked, or a subclass may render
    // the button differently.
    if (!$this->currentUser->hasPermission('use automator chain widget actions')) {
      $this->loggerFactory->get('ai_automators')->warning('User @uid tried to run an automator chain widget action on field @field without permission.', [
        '@uid' => $this->currentUser->id(),
        '@field' => $form_key,
      ]);
      $this->messenger->addError($this->t('You do not have permission to run automator chains.'));
      return $form[$form_key] ?? [];
    }

    $entity = $this->buildEntity($form, $form_state);
    if (!$entity instanceof ContentEntityInterface) {
      $this->loggerFactory->get('ai_automators')->warning('Automator chain widget action for field @field was triggered on a form that is not a content entity form.', [
        '@field' => $form_key,
      ]);
      $this->messenger->addError($this->t('The automator chain could not determine the entity being edited.'));
      return $form[$form_key] ?? [];
    }

    $settings = $this->getConfiguration()['settings'] ?? [];
    $chain_type = $settings['automator_chain_type'] ?? '';
    $chain_settings = $settings['chain_settings'][$chain_type] ?? [];
    $output_field = $chain_settings['output_field'] ?? '';
    // Check that the chain type still exists and the output field is still
    // automated, so a deleted chain degrades gracefully instead of causing
    // a fatal error.
    if (!$chain_type || !isset($this->automate->getWorkflows()[$chain_type])) {
      $this->loggerFactory->get('ai_automators')->warning('Automator chain type @type not found for field widget action. The chain may have been deleted.', [
        '@type' => $chain_type,
      ]);
      $this->messenger->addError($this->t('The automator chain is no longer available. Please check the field widget action configuration.'));
      return $form[$form_key] ?? [];
    }
    if (!$output_field || !isset($this->automate->getAutomatedFields($chain_type)[$output_field])) {
      $this->loggerFactory->get('ai_automators')->warning('Automator chain output field @field is not automated on chain type @type.', [
        '@field' => $output_field,
        '@type' => $chain_type,
      ]);
      $this->messenger->addError($this->t('The automator chain output field is no longer available. Please check the field widget action configuration.'));
      return $form[$form_key] ?? [];
    }

    $inputs = $this->buildChainInputs($entity, $chain_type, $chain_settings['input_mapping'] ?? []);
    // Run the chain. Catch any failure so the user sees a real error
    // message instead of a silent no-op.
    try {
      $result = $this->automate->run($chain_type, $inputs);
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('ai_automators')->error('Automator chain @type failed for field @field: @msg', [
        '@type' => $chain_type,
        '@field' => $form_key,
        '@msg' => $e->getMessage(),
      ]);
      $this->messenger->addError($this->t('The automator chain failed to run. Please try again or check the logs for details.'));
      return $form[$form_key] ?? [];
    }

    $values = $this->massageValuesForField($result[$output_field] ?? [], $entity->get($form_key)->getFieldDefinition());
    if ($this->clearEntity) {
      $entity->get($form_key)->filterEmptyItems();
    }
    $form_state->setValue($form_key, NULL);
    $entity->set($form_key, $values);

    if (!$entity->get($form_key)->isEmpty()) {
      $this->setFormInput($entity, $form_state, $form_key);
      $this->updateItemsCount($form, $form_state, $form_key, $entity->get($form_key)->count());
      $form_state->setRebuild();
    }
    else {
      $this->messenger->addWarning($this->t('The automator chain produced no output.'));
    }
    return $this->saveFormValues($form, $form_key, $entity, $key);
  }

  /**
   * Builds the chain inputs from the mapped source fields.
   *
   * Each mapped value is massaged into the chain input field's storage
   * shape, so a compatible-but-different source type (e.g. a text field
   * feeding a plain string chain input) cannot fatal on setValue().
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The host entity built from the form state.
   * @param string $chain_type
   *   The automator chain type ID.
   * @param array $input_mapping
   *   Mapping of chain input field names to host entity field names.
   *
   * @return array
   *   The inputs keyed by chain field name.
   */
  protected function buildChainInputs(FieldableEntityInterface $entity, string $chain_type, array $input_mapping): array {
    $inputs = [];
    $chain_definitions = $this->entityFieldManager->getFieldDefinitions('automator_chain', $chain_type);
    foreach ($input_mapping as $chain_field => $source_field) {
      if ($source_field && $entity->hasField($source_field) && isset($chain_definitions[$chain_field])) {
        $inputs[$chain_field] = $this->massageValuesForField($entity->get($source_field)->getValue(), $chain_definitions[$chain_field]);
      }
    }
    return $inputs;
  }

  /**
   * Massages values into the given field definition's storage shape.
   *
   * The two sides of a mapping — chain output into the target field, or a
   * host source field into a chain input — may be of different (but
   * compatible) types, e.g. a text_long value written into a string
   * field. Strips item properties the field does not know about (like
   * format) and respects the field's cardinality.
   *
   * @param array $values
   *   The raw field values.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition the values are written into.
   *
   * @return array
   *   The massaged values.
   */
  protected function massageValuesForField(array $values, FieldDefinitionInterface $field_definition): array {
    $storage_definition = $field_definition->getFieldStorageDefinition();
    $properties = $storage_definition->getPropertyDefinitions();
    foreach ($values as $delta => $item) {
      if (is_array($item)) {
        $values[$delta] = array_intersect_key($item, $properties);
      }
    }
    $cardinality = $storage_definition->getCardinality();
    if ($cardinality > 0) {
      $values = array_slice($values, 0, $cardinality);
    }
    return array_values($values);
  }

  /**
   * Gets the chains that have an output field compatible with this plugin.
   *
   * @return array
   *   The chain options keyed by automator chain type ID.
   */
  protected function getChainOptions(): array {
    if ($this->chainOptions !== NULL) {
      return $this->chainOptions;
    }
    $options = [];
    foreach ($this->automate->getWorkflows() as $chain_id => $chain_label) {
      if (count($this->automate->getAutomatedFields($chain_id, $this->getFieldTypes())) > 0) {
        $options[$chain_id] = $chain_label;
      }
    }
    $this->chainOptions = $options;
    return $options;
  }

  /**
   * Gets the source field options on the host entity type and bundle.
   *
   * When a chain input definition is given, only fields whose storage
   * main property matches the chain input's are offered — this keeps
   * compatible-but-different types interchangeable (the whole text family
   * shares the value property; file and image fields share target_id, so
   * e.g. a PDF file field can feed a file chain input) while excluding
   * pairings that cannot produce usable input. Fields without a main
   * property fall back to an exact field type match.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition of the field the action is attached to.
   * @param \Drupal\Core\Field\FieldDefinitionInterface|null $chain_input
   *   The chain input field definition to filter compatible sources for,
   *   or NULL to offer all form-configurable fields.
   *
   * @return array
   *   The source field options keyed by field name.
   */
  protected function getSourceFieldOptions(FieldDefinitionInterface $field_definition, ?FieldDefinitionInterface $chain_input = NULL): array {
    $options = [];
    $definitions = $this->entityFieldManager->getFieldDefinitions(
      $field_definition->getTargetEntityTypeId(),
      $field_definition->getTargetBundle()
    );
    $main_property = $chain_input?->getFieldStorageDefinition()->getMainPropertyName();
    foreach ($definitions as $field_name => $definition) {
      if (!$definition->isDisplayConfigurable('form')) {
        continue;
      }
      if ($chain_input) {
        if ($main_property !== NULL) {
          if ($definition->getFieldStorageDefinition()->getMainPropertyName() !== $main_property) {
            continue;
          }
        }
        elseif ($definition->getType() !== $chain_input->getType()) {
          continue;
        }
      }
      $options[$field_name] = $this->t('@label (@name)', [
        '@label' => $definition->getLabel(),
        '@name' => $field_name,
      ]);
    }
    return $options;
  }

}
