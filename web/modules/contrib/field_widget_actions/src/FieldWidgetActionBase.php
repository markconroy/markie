<?php

declare(strict_types=1);

namespace Drupal\field_widget_actions;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The base class for FieldWidgetAction plugins.
 */
abstract class FieldWidgetActionBase extends PluginBase implements FieldWidgetActionInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The target property of the form element.
   */
  const FORM_ELEMENT_PROPERTY = 'value';

  /**
   * Form state key prefix under which a plugin reports what it produced.
   *
   * Combined with the plugin id and the field name by resultKey(), so that two
   * action buttons on the same field cannot overwrite each other's result.
   */
  const RESULT_KEY = 'field_widget_actions_result__';

  /**
   * Element types recognized as direct fill targets.
   *
   * Used as a fallback when a form element has not been processed yet and
   * therefore does not carry the #input flag. Only actual input element types
   * belong here; structural types (container, details, fieldset, …) and button
   * types are deliberately absent.
   */
  private const TARGET_INPUT_TYPES = [
    'checkboxes',
    'checkbox',
    'color',
    'date',
    'datetime',
    'email',
    'entity_autocomplete',
    'language_select',
    'machine_name',
    'managed_file',
    'number',
    'password',
    'radios',
    'range',
    'search',
    'select',
    'tel',
    'textfield',
    'textarea',
    'url',
  ];

  /**
   * The widget plugin instance.
   *
   * @var \Drupal\Core\Field\WidgetInterface|null
   */
  protected ?WidgetInterface $widget = NULL;

  /**
   * The field definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface|null
   */
  protected ?FieldDefinitionInterface $fieldDefinition = NULL;

  /**
   * Constructs FieldWidgetActionBase instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MessengerInterface $messenger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->messenger = $messenger;
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'enabled' => FALSE,
      'automatic' => FALSE,
      'button_label' => $this->getLabel(),
      'multiple' => $this->getMultiple(),
      'show_result_message' => FALSE,
      'message_success' => NULL,
      'message_empty' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return (string) $this->getPluginDefinition()['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) ($this->getPluginDefinition()['description'] ?? '');
  }

  /**
   * {@inheritdoc}
   */
  public function getWidgetTypes(): array {
    return $this->pluginDefinition['widget_types'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldTypes(): array {
    return $this->pluginDefinition['field_types'];
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(): bool {
    return $this->pluginDefinition['multiple'] ?? TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, $action_id = NULL) {
    $element = [];
    $configuration = $this->getConfiguration();
    $multiple = $this->getFieldDefinition()->getFieldStorageDefinition()->isMultiple();
    $element['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $configuration['enabled'] ?? FALSE,
    ];
    $element['automatic'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatic'),
      '#description' => $this->t('When enabled, this action will be triggered automatically when the form loads instead of requiring a manual button click.'),
      '#default_value' => $configuration['automatic'] ?? FALSE,
      '#states' => [
        'visible' => [
          ':input[name*="[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $element['button_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button label'),
      '#default_value' => $configuration['button_label'] ?? '',
    ];
    $element['multiple'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Multiple'),
      '#description' => $this->t('If checked, an action button will appear for each item in the field. If not, an action will be performed for the entire field.'),
      '#default_value' => $configuration['multiple'] ?? $this->getMultiple(),
      '#access' => $multiple,
    ];
    $enabled_selector = $action_id
      ? ':input[name*="[' . $action_id . '][enabled]"]'
      : ':input[name*="[enabled]"]';
    $element['show_result_message'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show a message with the result'),
      '#description' => $this->t('Tell the author what the action produced. Without this, an action that returns nothing looks identical to one that was never clicked.'),
      '#default_value' => $configuration['show_result_message'] ?? TRUE,
      '#states' => [
        'visible' => [
          $enabled_selector => ['checked' => TRUE],
        ],
      ],
    ];
    $message_selector = $action_id
      ? ':input[name*="[' . $action_id . '][show_result_message]"]'
      : ':input[name*="[show_result_message]"]';
    $element['message_success'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message when a value is returned'),
      '#default_value' => $configuration['message_success'] ?? '',
      '#placeholder' => $this->t('A suggestion was added to @field.'),
      '#description' => $this->t('Leave empty to use the default shown above. The token @field is replaced with the field label.'),
      '#states' => [
        'visible' => [
          $message_selector => ['checked' => TRUE],
        ],
      ],
    ];
    $element['message_empty'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message when nothing is returned'),
      '#default_value' => $configuration['message_empty'] ?? '',
      '#placeholder' => $this->t('No suggestion was returned for @field.'),
      '#description' => $this->t('Leave empty to use the default shown above. The token @field is replaced with the field label.'),
      '#states' => [
        'visible' => [
          $message_selector => ['checked' => TRUE],
        ],
      ],
    ];
    $element['plugin_id'] = [
      '#type' => 'value',
      '#value' => $this->getPluginId(),
    ];
    $element['weight'] = [
      '#type' => 'hidden',
      '#default_value' => $configuration['weight'] ?? 0,
      '#attributes' => [
        'class' => ['field-widget-action-element-order-weight'],
      ],
    ];
    return $element;
  }

  /**
   * {@inheritDoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritDoc}
   */
  public function setConfiguration(array $configuration) {
    if (isset($configuration['weight'])) {
      $configuration['weight'] = (int) $configuration['weight'];
    }
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * Build the entity.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The entity this field is attached to or NULL.
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    $entity = NULL;
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof ContentEntityFormInterface) {
      // An empty multi-value widget (e.g. an unselected chosen_select) can
      // submit a literal NULL at its field's value path. core's
      // WidgetBase::extractFormValues() then passes that NULL to
      // massageFormValues(array), which throws a TypeError. Drop NULL field
      // values so extractFormValues() treats the field as absent — leaving it
      // empty — instead of crashing. Other shapes (e.g. a single-value
      // select's scalar) are normalized by element validators before this runs.
      $this->dropNullFieldValues($form, $form_state);
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $form_object->buildEntity($form, $form_state);
    }
    return $entity;
  }

  /**
   * Removes NULL field values from the submitted values before building.
   *
   * Scoped to the entity's own fields so the rest of the values tree is left
   * untouched; only values that are literally NULL are dropped (an empty array
   * or a populated value is left as-is).
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function dropNullFieldValues(array $form, FormStateInterface $form_state): void {
    $entity = $form_state->getFormObject()->getEntity();
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }
    $parents = $form['#parents'] ?? [];
    $values = $form_state->getValues();
    foreach (array_keys($entity->getFieldDefinitions()) as $field_name) {
      $path = array_merge($parents, [$field_name]);
      $key_exists = NULL;
      $value = NestedArray::getValue($values, $path, $key_exists);
      if ($key_exists && $value === NULL) {
        $form_state->unsetValue($path);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getWidget(): ?WidgetInterface {
    return $this->widget;
  }

  /**
   * {@inheritdoc}
   */
  public function setWidget(?WidgetInterface $widget): void {
    $this->widget = $widget;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition(): ?FieldDefinitionInterface {
    return $this->fieldDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldDefinition(?FieldDefinitionInterface $fieldDefinition): void {
    $this->fieldDefinition = $fieldDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return TRUE;
  }

  /**
   * Checks if the action is multiple.
   *
   * @return bool
   *   TRUE if the action is multiple, FALSE otherwise.
   */
  public function isMultiple(): bool {
    return $this->configuration['multiple'] ?? $this->getMultiple();
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(): array {
    return [];
  }

  /**
   * Gets the button label.
   *
   * @return string
   *   The button label.
   */
  public function getButtonLabel(): string {
    return $this->configuration['button_label'] ?: $this->getLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getAjaxCallback(): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function completeFormAlter(array &$form, FormStateInterface $form_state, array $context = []) {
    /** @var \Drupal\field\Entity\FieldConfig $field_definition */
    $field_definition = $context['items']->getFieldDefinition();
    if ($this->getAjaxCallback()) {
      if (!empty($form['widget'][0]['#group'])) {
        $form['widget']['#process'][] = [$this, 'processWidgetWithGroup'];
      }
      else {
        // Add wrapper.
        $prefix = $form['#prefix'] ?? '';
        $suffix = $form['#suffix'] ?? '';
        $form['#prefix'] = '<div id="field-widget-action-' . $field_definition->getName() . '" class="field-widget-action-element-wrapper">' . $prefix;
        $form['#suffix'] = $suffix . '</div>';
        $form['#attributes']['class'][] = 'field-widget-action-element';
      }
    }
    if (!$this->isMultiple()) {
      $this->actionButton($form, $form_state, $context);
    }
  }

  /**
   * Process the element with #group property.
   *
   * @param array $element
   *   The given element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $form
   *   The complete form.
   *
   * @return array
   *   The processed element.
   */
  public function processWidgetWithGroup(array $element, FormStateInterface $form_state, array &$form) {
    if (!empty($element[0]['#group'])) {
      $group = $element[0]['#group'];
      // Add wrapper.
      $prefix = $form[$group]['#prefix'] ?? '';
      $suffix = $form[$group]['#suffix'] ?? '';
      $form[$group]['#prefix'] = '<div id="field-widget-action-' . $group . '" class="field-widget-action-element-wrapper">' . $prefix;
      $form[$group]['#suffix'] = $suffix . '</div>';
      $form[$group]['#attributes']['class'][] = 'field-widget-action-element';
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function singleElementFormAlter(array &$form, FormStateInterface $form_state, array $context = []) {
    if ($this->isMultiple()) {
      $this->actionButton($form, $form_state, $context);
    }
  }

  /**
   * Returns the action button widget ID.
   *
   * @param string $fieldName
   *   The field name.
   * @param array $context
   *   The context.
   *
   * @return string
   *   The Widget ID.
   */
  protected function getActionButtonWidgetId(string $fieldName, array $context): string {
    if (!empty($context['action_id'])) {
      $widgetId = $context['action_id'];
    }
    else {
      $widgetId = $fieldName . '_field_widget_action_' . $this->getPluginId();
    }
    if (!empty($context['delta'])) {
      $widgetId .= '_' . $context['delta'];
    }
    return $widgetId;
  }

  /**
   * Returns the action button depending on the `multiple` value of definition.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param array $context
   *   The context.
   */
  protected function actionButton(array &$form, FormStateInterface $form_state, array $context = []) {
    $fieldName = $context['items']->getFieldDefinition()->getName();
    $widgetId = $this->getActionButtonWidgetId($fieldName, $context);
    $weight = $this->configuration['weight'] ?? 0;
    $automatic = !empty($this->configuration['automatic']);
    $form[$widgetId] = [
      '#type' => 'button',
      '#value' => $this->getButtonLabel(),
      '#weight' => $weight + 10,
      '#name' => $widgetId,
      '#attributes' => [
        'class' => [
          'button--secondary',
          'button--small',
          'field-widget-action-widget-button',
          'field-widget-action-' . $this->getPluginId(),
        ],
        'data-wrapper-id' => 'field-widget-action-' . $fieldName,
        'data-widget-id' => $this->getPluginId(),
        'data-widget-field' => $fieldName,
        'data-widget-delta' => $context['delta'] ?? '',
      ],
      '#field_widget_action_field_name' => $fieldName,
      // When called from hook_field_widget_complete_form, delta is not present.
      '#field_widget_action_field_delta' => $context['delta'] ?? NULL,
      '#field_widget_action_settings' => $this->getConfiguration(),
    ];
    if ($automatic) {
      $form[$widgetId]['#attributes']['data-fwa-automatic'] = 'true';
    }
    $form[$widgetId]['#attached'] = [
      'library' => array_merge($this->getLibraries(), ['field_widget_actions/widget_button']),
    ];
    if ($this->getAjaxCallback()) {
      // If form element is inside group ajax should reload the whole parent
      // container.
      if (!empty($form['#group'])) {
        $fieldName = $form['#group'];
      }
      // Route the AJAX response through the base class so the outcome of the
      // action can be reported to the author. The plugin's own callback is
      // still what does the work; see reportResultAjax().
      $form[$widgetId]['#ajax'] = [
        'callback' => [$this, 'reportResultAjax'],
        'wrapper' => 'field-widget-action-' . $fieldName,
        'prevent' => 'submit',
      ];
      // A button-level #validate array replaces the form-level one entirely
      // (see FormBuilder::doBuildForm()), so '::validateForm' must be
      // included explicitly here or the entity (and thus genuine field
      // constraints such as an invalid email format) never gets validated at
      // all on this request. Suppress required-field errors in a #validate
      // handler (runs after element validators have normalized values,
      // before submit handlers) so that clicking Generate never surfaces
      // required-field violations for fields the user has not filled in yet,
      // while submit handlers (e.g. AutomatorBaseAction::runAutomatorSubmit)
      // still fire. Genuine validation errors are preserved — see
      // clearErrorsForAction().
      $form[$widgetId]['#validate'] = ['::validateForm', [$this, 'clearErrorsForAction']];
    }
  }

  /**
   * Form validate handler attached to every action button.
   *
   * Drupal calls button-level #validate handlers instead of the form-level
   * ones when that button triggers the form, and it calls them AFTER all
   * element validators have run (so single-cardinality selects are already
   * normalized) and AFTER required-field errors have been collected — but
   * BEFORE the submit phase, and before errors are flushed to the messenger.
   *
   * The action button is not a real submission: the user asked to generate ONE
   * field, not to save the entity. This suppresses only "field is required"
   * errors for fields they have not filled in yet, but preserves every other
   * validation error (malformed value, out-of-range, custom constraint, …). A
   * blanket clear would let submit handlers (e.g.
   * AutomatorBaseAction::runAutomatorSubmit) run on genuinely invalid data and
   * silently discard real problems. Because required-but-empty errors are
   * removed here, before FormErrorHandler converts errors to messages, they
   * never reach the messenger, while genuine errors still surface as usual.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function clearErrorsForAction(array &$form, FormStateInterface $form_state): void {
    $required_keys = [];
    $this->collectRequiredButEmptyKeys($form, $required_keys);

    $errors = $form_state->getErrors();
    $form_state->clearErrors();
    // Re-record every error that is not a required-but-empty violation. Widen
    // the limit so each preserved error is actually recorded.
    $form_state->setLimitValidationErrors(NULL);
    foreach ($errors as $name => $message) {
      if (!in_array($name, $required_keys, TRUE)) {
        $form_state->setErrorByName($name, $message);
      }
    }
  }

  /**
   * AJAX callback wrapper that reports the outcome of the action.
   *
   * Drupal calls this instead of the plugin's own AJAX callback (see
   * actionButton()). It runs the plugin callback, inspects what came back, and
   * queues a status message so the author is told whether the click produced
   * anything. Without it an action that returns nothing is indistinguishable
   * from one that was never clicked: the widget is simply replaced with
   * identical markup.
   *
   * The message is queued through the messenger rather than added as a
   * command, so it renders inline above the replaced element — AjaxRenderer
   * prepends status messages to every AJAX response. For a response the plugin
   * built itself, the commands are inspected instead, because those bypass the
   * renderer.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array|\Drupal\Core\Ajax\AjaxResponse
   *   Whatever the plugin's callback returned.
   */
  public function reportResultAjax(array &$form, FormStateInterface $form_state) {
    // This assumes getAjaxCallback() names a real method on the plugin, which
    // is the same assumption the form API made when it invoked that method
    // directly. A plugin naming a method that does not exist raises an Error
    // here rather than the form API's callback-not-found handling.
    $callback = $this->getAjaxCallback();
    $result = $this->{$callback}($form, $form_state);

    if (empty($this->configuration['show_result_message'])) {
      return $result;
    }
    // A plugin that already spoke to the author is not second-guessed: it
    // knows more about what happened than this generic check does.
    if ($this->messengerHasMessages()) {
      return $result;
    }

    $produced = $this->actionProducedValue($result, $form, $form_state);
    // NULL means the outcome could not be determined; stay silent rather than
    // risk telling the author something untrue.
    if ($produced === NULL) {
      return $result;
    }

    $field_label = (string) ($this->getFieldDefinition()?->getLabel() ?? $this->t('the field'));
    // Configured text is admin-entered config, so it is escaped here before
    // substitution: FormattableMarkup escapes the @field replacement but NOT
    // the template string it is given. It is also not passed through t(), as a
    // non-literal string cannot be extracted for translation; such text is
    // translatable as configuration instead, via the 'label' type in the config
    // schema. Only the built-in defaults are literals the extractor picks up.
    if ($produced) {
      $custom = $this->configuration['message_success'] ?? '';
      $this->messenger->addStatus($custom
        ? new FormattableMarkup(Html::escape($custom), ['@field' => $field_label])
        : $this->t('A suggestion was added to @field.', ['@field' => $field_label]));
    }
    else {
      $custom = $this->configuration['message_empty'] ?? '';
      $this->messenger->addWarning($custom
        ? new FormattableMarkup(Html::escape($custom), ['@field' => $field_label])
        : $this->t('No suggestion was returned for @field.', ['@field' => $field_label]));
    }

    // A response the plugin built itself bypasses AjaxRenderer, so the queued
    // message would never be rendered. Attach it as a command instead.
    if ($result instanceof AjaxResponse) {
      foreach ($this->messenger->all() as $type => $items) {
        foreach ($items as $item) {
          $result->addCommand(new MessageCommand($item, NULL, ['type' => $type]));
        }
      }
      $this->messenger->deleteAll();
    }

    return $result;
  }

  /**
   * Checks whether anything is already queued for the author.
   *
   * This is request-scoped rather than action-scoped: a message queued earlier
   * in the same request by unrelated code also suppresses the result message.
   * That is broader than the intended case (the plugin reported its own
   * outcome, so it is not second-guessed), but an action button request is
   * short-lived and carries little else, so the trade is worth the simplicity.
   *
   * @return bool
   *   TRUE if the messenger holds at least one message.
   */
  protected function messengerHasMessages(): bool {
    foreach ($this->messenger->all() as $items) {
      if (!empty($items)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Determines whether the action produced a value for the field.
   *
   * Plugins report their result in two incompatible ways, so both are handled:
   *
   * - Command based: the value travels in a fill command on an AjaxResponse
   *   and never touches the entity. The command payload is the answer.
   * - Rebuild based: the plugin writes the value into the entity and user
   *   input during the submit phase and returns a render array (see the AI
   *   Automators actions). Nothing in the return value describes the outcome.
   *   The field could be read back off the rebuilt entity, but that answers
   *   the wrong question: it says whether the field holds a value, not whether
   *   this action is what put it there — a field the author filled in earlier
   *   is populated either way. Only the plugin knows, so it reports its own
   *   outcome through reportProducedValue() / reportNoValueProduced().
   *
   * @param array|\Drupal\Core\Ajax\AjaxResponse $result
   *   Whatever the plugin's AJAX callback returned.
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return bool|null
   *   TRUE if a value was produced, FALSE if the action came back empty, or
   *   NULL if the outcome cannot be determined and no message should be shown.
   */
  protected function actionProducedValue($result, array &$form, FormStateInterface $form_state): ?bool {
    if ($result instanceof AjaxResponse) {
      return $this->responseCarriesValue($result);
    }
    return $this->getReportedResult($form, $form_state);
  }

  /**
   * Checks whether a response carries a fill command with a value.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The response returned by the plugin.
   *
   * @return bool|null
   *   TRUE if a fill command carries a non-empty payload, FALSE if every fill
   *   command is empty, or NULL if the response contains no fill command at
   *   all (the plugin is doing something this check does not model, such as
   *   opening a dialog).
   */
  protected function responseCarriesValue(AjaxResponse $response): ?bool {
    $seen_fill_command = FALSE;
    foreach ($response->getCommands() as $command) {
      // Commands are already rendered to arrays at this point.
      $name = $command['command'] ?? '';
      if (!str_starts_with($name, 'fieldWidgetActionsFill')) {
        continue;
      }
      $seen_fill_command = TRUE;
      $payload = $command['data'] ?? $command['values'] ?? NULL;
      if (is_array($payload) ? !empty($payload) : (string) $payload !== '') {
        return TRUE;
      }
    }
    return $seen_fill_command ? FALSE : NULL;
  }

  /**
   * Collects the error keys of required fields the user left unfilled.
   *
   * The error key is implode('][', #parents), matching how core keys form
   * errors. See isUnfilledRequiredElement() for what counts as unfilled.
   *
   * @param array $element
   *   The form element to scan (recursively).
   * @param array $keys
   *   The collected error keys, by reference.
   */
  protected function collectRequiredButEmptyKeys(array $element, array &$keys): void {
    if (isset($element['#parents']) && $this->isUnfilledRequiredElement($element)) {
      $keys[] = implode('][', $element['#parents']);
    }
    foreach (Element::children($element) as $child) {
      if (is_array($element[$child] ?? NULL)) {
        $this->collectRequiredButEmptyKeys($element[$child], $keys);
      }
    }
  }

  /**
   * Determines whether an element is a required field left empty by the user.
   *
   * An error on such an element is a "field is required" violation that must
   * be suppressed on the action path (the user has not filled it in yet). An
   * error on a required element that DOES hold a value is a genuine value
   * problem and must be preserved.
   *
   * @param array $element
   *   The form element.
   *
   * @return bool
   *   TRUE if the element is required and unfilled.
   */
  protected function isUnfilledRequiredElement(array $element): bool {
    // Core flags required-but-empty elements during validation. This handles
    // text fields, checkboxes, the '0' edge case, and empty multi-value
    // selects.
    if (!empty($element['#required_but_empty'])) {
      return TRUE;
    }
    // Options widgets (single select, radios) run their own required check in
    // an #element_validate handler and set the error without that flag; an
    // unselected value is the '_none' marker. Compound elements (e.g. date/
    // time or autocomplete widgets) can also be #required with an array
    // #value, but their entries are not scalars to compare against the
    // marker, so only apply this check when every entry is scalar.
    if (!empty($element['#required']) && array_key_exists('#value', $element)) {
      $value = $element['#value'];
      if ($value === '_none') {
        return TRUE;
      }
      if (is_array($value) && count(array_filter($value, 'is_scalar')) === count($value) && !array_diff($value, [
        '_none', '',
      ])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns the target css selector for suggestions.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string
   *   The css selector to fill in with the selected suggestion.
   */
  protected function getSuggestionsTarget(array &$form, FormStateInterface $form_state): string {
    $target_element = $this->getTargetElement($form, $form_state);
    if (!$target_element) {
      return '';
    }

    // Safely try data-drupal-selector, fallback to id, or return empty.
    return $target_element['#attributes']['data-drupal-selector']
      ?? $target_element['#id']
      ?? '';
  }

  /**
   * Returns the target form element this action is attached to.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  protected function getTargetElement(array &$form, FormStateInterface $form_state): array {
    // Get the triggering element, the button is inside the same field widget.
    $triggering_element = $form_state->getTriggeringElement();
    $delta = $this->getTargetElementDelta($form, $form_state);
    $array_parents = $triggering_element['#array_parents'];

    // Remove the button key to reach the container the button lives in.
    array_pop($array_parents);

    $element = NestedArray::getValue($form, $array_parents) ?? [];

    // Childless widgets (select, tagify, select2) are wrapped in containers by
    // the module's form alters, so the input element can sit one or two
    // 'widget' levels below the button's container. Descend through those
    // wrappers until the input itself is reached.
    while (is_array($element) && !$this->isTargetElementInput($element) && isset($element['widget']) && is_array($element['widget'])) {
      $element = $element['widget'];
    }

    // For a whole-field action (no delta), the field_multiple_value_form
    // wrapper keys the widgets by numeric delta; enter the first one.
    if ($delta === NULL && is_array($element) && !$this->isTargetElementInput($element)) {
      foreach (Element::children($element) as $child) {
        if (is_numeric($child)) {
          $element = $element[$child];
          break;
        }
      }
    }

    // An explicit FORM_ELEMENT_PROPERTY override names a property child of the
    // element, and that child wins even when the element reached is itself an
    // input: the image widget element is a managed_file input whose 'alt' and
    // 'title' text fields are children of it.
    if ($this->hasTargetElementPropertyOverride() && is_array($element) && isset($element[static::FORM_ELEMENT_PROPERTY])) {
      return $element[static::FORM_ELEMENT_PROPERTY];
    }

    // Return the input element itself when the lookup reached it directly.
    if ($this->isTargetElementInput($element)) {
      return $element;
    }

    // Otherwise the input is a property child of the element, named after the
    // field storage's main property ('target_id', 'uri', …) with 'value' as
    // fallback.
    $property = $this->getTargetElementProperty();
    return is_array($element) && isset($element[$property]) ? $element[$property] : [];
  }

  /**
   * Determines whether a form element is itself a fillable input.
   *
   * A processed form marks input elements with #input = TRUE; for render
   * arrays that have not been processed yet, a positive list of element types
   * is consulted instead. Action buttons (which live inside the widget tree
   * next to the real input) and submit/button types are never inputs.
   *
   * @param array $element
   *   The form element.
   *
   * @return bool
   *   TRUE if the element is a fillable input, FALSE otherwise.
   */
  protected function isTargetElementInput(array $element): bool {
    // Action buttons are not fill targets even though they sit in the widget.
    if (!empty($element['#field_widget_action_field_name'])) {
      return FALSE;
    }
    $type = $element['#type'] ?? NULL;
    // Submit/button elements are not fill targets.
    if (in_array($type, ['button', 'submit', 'image_button'], TRUE)) {
      return FALSE;
    }
    // A processed form marks inputs with #input = TRUE.
    if (!empty($element['#input'])) {
      return TRUE;
    }
    // Fall back to a positive list of input types for unprocessed render
    // arrays.
    return $type !== NULL && in_array($type, self::TARGET_INPUT_TYPES, TRUE);
  }

  /**
   * Gets the property of the form element used as the fill target.
   *
   * A subclass overriding FORM_ELEMENT_PROPERTY targets a specific property
   * (e.g. 'summary' for text_with_summary or 'alt' for image widgets), and that
   * override always wins over the field storage's main property. Otherwise the
   * field storage's main property is used (entity reference 'target_id', link
   * 'uri', …), falling back to the generic 'value' property.
   *
   * @return string
   *   The property name.
   */
  protected function getTargetElementProperty(): string {
    if ($this->hasTargetElementPropertyOverride()) {
      return static::FORM_ELEMENT_PROPERTY;
    }
    $main_property = $this->fieldDefinition
      ? $this->fieldDefinition->getFieldStorageDefinition()->getMainPropertyName()
      : NULL;
    return $main_property ?: static::FORM_ELEMENT_PROPERTY;
  }

  /**
   * Determines whether the plugin class overrides FORM_ELEMENT_PROPERTY.
   *
   * @return bool
   *   TRUE if a subclass targets a specific property instead of the default.
   */
  protected function hasTargetElementPropertyOverride(): bool {
    return static::FORM_ELEMENT_PROPERTY !== self::FORM_ELEMENT_PROPERTY;
  }

  /**
   * Gets the delta of form element.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return int|null
   *   The delta of the form element or null if it is attached to the complete
   *   widget form.
   */
  protected function getTargetElementDelta(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    return $triggering_element['#field_widget_action_field_delta'] ?? NULL;
  }

  /**
   * Gets the field name that corresponds to form element.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string
   *   The field name for the form element.
   */
  protected function getTargetElementFieldName(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    return $triggering_element['#field_widget_action_field_name'] ?? '';
  }

  /**
   * Reports that the action produced a value for a field.
   *
   * Call this from a plugin that fills the field during the submit phase
   * rather than through a fill command, at the point where it knows what it
   * produced. Nothing in such an action's AJAX return value distinguishes a
   * successful run from an empty one, so the base class cannot work the
   * outcome out on its own and stays silent unless it is told.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $field_name
   *   The field the action filled.
   *
   * @see ::reportNoValueProduced()
   */
  protected function reportProducedValue(FormStateInterface $form_state, string $field_name): void {
    $form_state->set($this->resultKey($field_name), TRUE);
  }

  /**
   * Reports that the action ran but produced nothing for a field.
   *
   * The counterpart to reportProducedValue(). Reporting the empty case is what
   * turns a click that changed nothing from an apparently broken button into a
   * message telling the author there was no suggestion; a plugin that calls
   * neither method produces no message at all.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $field_name
   *   The field the action was run against.
   *
   * @see ::reportProducedValue()
   */
  protected function reportNoValueProduced(FormStateInterface $form_state, string $field_name): void {
    $form_state->set($this->resultKey($field_name), FALSE);
  }

  /**
   * Returns what the plugin reported about the field it acted on.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return bool|null
   *   TRUE or FALSE as reported by the plugin, or NULL if it reported nothing
   *   and no message should be shown.
   */
  protected function getReportedResult(array &$form, FormStateInterface $form_state): ?bool {
    $field_name = $this->getTargetElementFieldName($form, $form_state);
    if (!$field_name) {
      return NULL;
    }
    $reported = $form_state->get($this->resultKey($field_name));
    return is_bool($reported) ? $reported : NULL;
  }

  /**
   * Compares a field's value before and after an action ran.
   *
   * Plugins that fill the field during the submit phase decide their own
   * outcome by asking whether they changed anything, so the comparison lives
   * here rather than being rewritten in each of them.
   *
   * The comparison is deliberately loose. The same entity reference arrives as
   * the string '3' from user input and as the integer 3 from other paths, and
   * an untouched text field can round-trip through the widget as '' in one
   * read and NULL in the other. A strict comparison would call those a change
   * and report a suggestion the author never received — the failure this
   * reporting exists to prevent.
   *
   * @param array $before
   *   The field value before the action ran, as returned by
   *   FieldItemListInterface::getValue().
   * @param array $after
   *   The field value afterwards, read the same way.
   *
   * @return bool
   *   TRUE if the action changed the field.
   */
  protected function fieldValuesDiffer(array $before, array $after): bool {
    return $before != $after;
  }

  /**
   * Builds the form state key holding a plugin's reported result.
   *
   * Keyed by plugin as well as field so that two action buttons on the same
   * field do not overwrite each other's result.
   *
   * @param string $field_name
   *   The field the action acted on.
   *
   * @return string
   *   The form state key.
   */
  protected function resultKey(string $field_name): string {
    return static::RESULT_KEY . $this->getPluginId() . '__' . $field_name;
  }

  /**
   * Returns suggestions in a dialog.
   *
   * @param array|string $suggestions
   *   The content to display in a dialog.
   * @param string $selector
   *   The selector for inserting a suggestion.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  protected function returnSuggestions(array|string $suggestions, $selector = '') {
    $message = '';
    // If it is empty string or empty array, no suggestions were actually
    // provided, so the dialog should not show anything selectable.
    if (!empty($suggestions)) {
      if (!is_array($suggestions)) {
        $suggestions = [$suggestions];
      }
      $message = [
        '#theme' => 'field_widget_actions_suggestions',
        '#suggestions' => $suggestions,
        '#attached' => [
          'library' => [
            'field_widget_actions/suggestions',
          ],
        ],
      ];
    }

    $response = new AjaxResponse();
    // Collect all messages emitted so far. In case of validation errors we need
    // to display them as well right away.
    foreach ($this->messenger->all() as $type => $items) {
      foreach ($items as $item) {
        $response->addCommand(new MessageCommand($item, NULL, ['type' => $type]));
      }
    }
    // Remove all messages, as they will be displayed with ajax commands.
    $this->messenger->deleteAll();
    if (!empty($selector)) {
      $response->addCommand(new SettingsCommand(['fwa_suggestion_target' => ['target' => $selector]], TRUE));
    }
    if (empty($message)) {
      $message = $this->t('Unfortunately no suggestions were provided.');
    }
    $response->addCommand(new OpenModalDialogCommand($this->t('Suggestions'), $message, [
      'width' => '80%',
      'classes' => ['ui-dialog' => 'ui-dialog-fwa-suggestions'],
    ]));
    return $response;
  }

}
