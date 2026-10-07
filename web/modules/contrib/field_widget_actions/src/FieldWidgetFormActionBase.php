<?php

namespace Drupal\field_widget_actions;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormValidatorInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The base class for FieldWidgetAction plugins.
 */
abstract class FieldWidgetFormActionBase extends FieldWidgetActionBase implements FieldWidgetFormActionInterface {

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
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The temp store factory.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The uuid service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository
   *   The entity display repository.
   * @param \Drupal\Core\Form\FormValidatorInterface $formValidator
   *   The form validator.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MessengerInterface $messenger,
    protected FormBuilderInterface $formBuilder,
    protected PrivateTempStoreFactory $tempStoreFactory,
    protected UuidInterface $uuid,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
    protected FormValidatorInterface $formValidator,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $messenger);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('messenger'),
      $container->get('form_builder'),
      $container->get('tempstore.private'),
      $container->get('uuid'),
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('form_validator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'field_widget_actions_modal_form_' . $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#parents'] = [];
    $form['#prefix'] = '<div id="field_widget_actions_modal_form">';
    $form['#suffix'] = '</div>';

    // The status messages that will contain any form errors if the modal form
    // is rebuilt rather than successfully submitted.
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    // Make the plugin ID available to the wrapper form.
    // @see \Drupal\field_widget_actions\Form\FieldWidgetActionFormWrapper::submitModalFormAjax().
    $form_state->set('field_widget_action_plugin', $this->getPluginId());

    // Build the entity as context so the modal form can react to the current
    // state of the entity with its unsaved values.
    $entity = $this->buildEntity($form, $form_state);

    // Delegate the building of the modal form fields to the plugin instance.
    $form = $this->buildModalForm($form, $form_state, $entity);

    // Change the submission to the Field Widget Actions Form Wrapper. This
    // avoids the entity form from being triggered, similar to how the
    // Media Library UI works.
    $context = $form_state->get('field_widget_action_context_data');
    if (!empty($context['submit_route_name'])) {
      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['send'] = [
        '#type' => 'submit',
        '#name' => 'op',
        '#value' => $this->t('Insert'),
        '#attributes' => [
          'class' => [
            'button--primary',
          ],
        ],
        '#ajax' => [
          'callback' => '\Drupal\field_widget_actions\Form\FieldWidgetActionFormWrapper::submitModalFormAjax',
          'event' => 'click',
          'url' => Url::fromRoute(
            $context['submit_route_name'],
            $context['submit_route_parameters'] ?? [],
          ),
          'wrapper' => 'field_widget_actions_modal_form',
        ],
      ];
      $form['actions']['cancel'] = [
        '#type' => 'submit',
        '#name' => 'op',
        '#value' => $this->t('Cancel'),
        '#attributes' => [
          'class' => [
            'button--secondary',
          ],
        ],
        '#ajax' => [
          'event' => 'click',
          'url' => Url::fromRoute(
            $context['submit_route_name'],
            $context['submit_route_parameters'] ?? [],
          ),
          'wrapper' => 'field_widget_actions_modal_form',
        ],
      ];
    }
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }

  /**
   * Build the modal form fields.
   *
   * This is called by the general modal build form which sets up the submit
   * and cancel buttons, and provides the location for any validation status
   * messages to be shown. Here you should present the options the user has to
   * choose from and any decisions they need to make. The submission values
   * be passed to the <code>::submitModalFormFillFields()</code> method.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   *   The entity in its current state with the form values copied into it.
   *
   * @return array
   *   The updated form.
   */
  abstract public function buildModalForm(array $form, FormStateInterface $form_state, ContentEntityInterface|NULL $entity): array;

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Implement this to add validation to your modal form.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Submit handler is ignored since the submission is processed via Ajax.
  }

  /**
   * {@inheritdoc}
   */
  public function submitModalFormAjax(array &$form, FormStateInterface $form_state): AjaxResponse {

    $response = new AjaxResponse();
    if ($form_state->hasAnyErrors()) {

      // If there are any form errors, re-display the form.
      $response->addCommand(new ReplaceCommand('#field_widget_actions_modal_form', $form));
    }
    else {

      // Close the modal on success and delegate the filling of fields to a
      // sub-method for ease of per-plugin implementation.
      $response->addCommand(new CloseModalDialogCommand());
      $response = $this->submitModalFormFillFields($form, $form_state, $response);
      $this->cleanUpModalFormState($form_state);
    }

    return $response;
  }

  /**
   * Cleans up the form state and temp store after the modal is done.
   *
   * If something goes wrong before this point, the temp store will be cleaned
   * up automatically after 1 week by default.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function cleanUpModalFormState(FormStateInterface $form_state): void {
    $context_data = $form_state->get('field_widget_action_context_data');
    if (!empty($context_data['tempstore_id'])) {
      /** @var \Drupal\Core\TempStore\PrivateTempStore $store */
      $store = $this->tempStoreFactory->get('field_widget_actions_form_collection');
      $store->delete($context_data['tempstore_id']);
    }
    $form_state->set('field_widget_action_context_data', []);
    $form_state->set('field_widget_action_plugin', NULL);
  }

  /**
   * The fill field actions resulting from the submission.
   *
   * Plugins must implement this to decide how to convert their form data into
   * field values for the different field types they support.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The ajax response.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The updated ajax response.
   */
  abstract protected function submitModalFormFillFields(array $form, FormStateInterface $form_state, AjaxResponse $response): AjaxResponse;

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
    parent::actionButton($form, $form_state, $context);
    $fieldName = $context['items']->getFieldDefinition()->getName();
    $widgetId = $this->getActionButtonWidgetId($fieldName, $context);

    // Convert the button to an ajax link since the modal form should not
    // trigger a form state change.
    $form[$widgetId]['#type'] = 'button';
    $form[$widgetId]['#title'] = $form[$widgetId]['#value'];
    $form[$widgetId]['#attributes']['class'][] = 'use-ajax';
    $form[$widgetId]['#attributes']['class'][] = 'button';
    $form[$widgetId]['#attributes']['data-dialog-options'] = Json::encode([
      'width' => '80%',
    ]);
    $form[$widgetId]['#attached']['library'] = array_merge($form[$widgetId]['#attached']['library'] ?? [], [
      'core/drupal.ajax',
      'core/drupal.dialog.ajax',
      'field_widget_actions/commands',
      'field_widget_actions/gin_compatibility_fix',
      'field_widget_actions/widget_button',
    ]);

    $form[$widgetId]['#ajax'] = [
      'callback' => [$this, 'openModalCallback'],
      'event' => 'click',
    ];
    $form[$widgetId]['#submit'] = [[$this, 'suppressSave']];
  }

  /**
   * Returns the title of the modal dialog.
   *
   * @return string
   *   The modal title.
   */
  protected function getModalTitle(): string {
    return $this->getLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function openModalForm(): AjaxResponse {
    $response = new AjaxResponse();

    // Get the modal form for the current plugin using the form builder.
    $modal_form = $this->formBuilder->getForm(
      '\Drupal\field_widget_actions\Form\FieldWidgetActionFormWrapper',
      $this->getPluginId(),
    );

    // Add an AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenModalDialogCommand($this->getModalTitle(), $modal_form, [
      'width' => '80%',
    ]));

    return $response;
  }

  /**
   * Ajax callback: Opens the modal.
   */
  public function openModalCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $entity = $this->buildEntity($form, $form_state);
    $field_name = $this->getTargetElementFieldName($form, $form_state);
    $field_definition = $entity->getFieldDefinition($field_name);

    // Create a lightweight context array.
    // This array is safe to serialize.
    $context_data = [
      'current_entity' => $entity,
      'target_element' => $this->getTargetElement($form, $form_state),
      'target_element_field_name' => $field_name,
      'target_element_field_settings' => $field_definition->getSettings(),
      'target_element_widget_settings' => $this->entityDisplayRepository
        ->getFormDisplay($entity->getEntityTypeId(), $entity->bundle())
        ->getComponent($field_name),
      // Pass the action configuration along so the plugin instance created
      // by the form wrapper on modal round trips is configured the same way
      // as the instance that opened the modal.
      'action_configuration' => $this->getConfiguration(),
    ];

    // Generate a unique ID for this interaction.
    $tempstore_id = $this->getPluginId() . '-' . $this->uuid->generate();

    // Build the Wrapper Form.
    $context_data['submit_route_name'] = 'field_widget_actions.modal_form_action';
    $context_data['submit_route_parameters'] = [
      'plugin_id' => $this->getPluginId(),
      'tempstore_id' => $tempstore_id,
    ];

    // Save to the Private TempStore. We need to do this to separate the main
    // form submission from the modal window submission, otherwise the entity
    // form builder jumps in when we do not want it to.
    /** @var \Drupal\Core\TempStore\PrivateTempStore $store */
    $store = $this->tempStoreFactory->get('field_widget_actions_form_collection');
    $store->set($tempstore_id, $context_data);

    $modal_form = $this->formBuilder->getForm(
      '\Drupal\field_widget_actions\Form\FieldWidgetActionFormWrapper',
      $this->getPluginId(),
      $context_data,
    );

    // 3. Open Modal
    $response->addCommand(new OpenModalDialogCommand($this->getModalTitle(), $modal_form, [
      'width' => '80%',
    ]));

    return $response;
  }

  /**
   * Submit handler to prevent main entity save.
   */
  public function suppressSave(array &$form, FormStateInterface $form_state) {
    // Do nothing. Rebuild happens automatically via Ajax.
  }

}
