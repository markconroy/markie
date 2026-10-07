<?php

namespace Drupal\field_widget_actions;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\field_widget_actions\Ajax\FillEditorCommand;
use Drupal\field_widget_actions\Ajax\FillSimpleFieldCommand;

/**
 * Base class for field widget actions with interactive refinement support.
 *
 * Actions extending this class present their generated content in the
 * standard field widget action modal form. When refinement is enabled in the
 * action configuration, the modal additionally contains a refinement prompt
 * and a Refine button. Choosing to refine rebuilds the modal form with the
 * refined content, similar to how validation errors refresh the modal, so
 * Drupal's form and dialog APIs do all the heavy lifting and no custom
 * JavaScript is needed.
 *
 * Subclasses must implement:
 * - generateContent(): produce the initial content when the modal opens.
 * - refineContent(): revise the current content from the user's refinement
 *   instructions.
 *
 * When refinement is disabled and the plugin provides its own AJAX callback
 * (see FieldWidgetActionBase::getAjaxCallback()), the action button keeps the
 * original direct field-fill behavior and no modal is shown.
 */
abstract class FieldWidgetRefinableFormActionBase extends FieldWidgetFormActionBase implements RefinementAwareInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'enable_refinement' => FALSE,
      'refinement_modal_title' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, $action_id = NULL) {
    $element = parent::buildConfigurationForm($form, $form_state, $action_id);
    $configuration = $this->getConfiguration();
    // Refinement options are FWA-level configuration, independent of any
    // plugin-specific settings, so they live next to the other base options.
    $enabled_selector = $action_id
      ? ':input[name*="[' . $action_id . '][enabled]"]'
      : ':input[name*="[enabled]"]';
    $element['enable_refinement'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable interactive refinement'),
      '#description' => $this->t('Show the generated content in a dialog where users can iteratively refine it with additional instructions before inserting it.'),
      '#default_value' => $configuration['enable_refinement'] ?? FALSE,
      '#states' => [
        'visible' => [
          $enabled_selector => ['checked' => TRUE],
        ],
      ],
    ];
    $refinement_selector = $action_id
      ? ':input[name*="[' . $action_id . '][enable_refinement]"]'
      : ':input[name*="[enable_refinement]"]';
    $element['refinement_modal_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Refinement dialog title'),
      '#default_value' => $configuration['refinement_modal_title'] ?? '',
      '#description' => $this->t('Title shown in the refinement dialog. Leave empty to use the action label.'),
      '#states' => [
        'visible' => [
          $refinement_selector => ['checked' => TRUE],
        ],
      ],
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function isRefinementEnabled(): bool {
    return !empty($this->configuration['enable_refinement']);
  }

  /**
   * {@inheritdoc}
   */
  protected function getModalTitle(): string {
    if ($this->isRefinementEnabled() && !empty($this->configuration['refinement_modal_title'])) {
      return (string) $this->configuration['refinement_modal_title'];
    }
    return parent::getModalTitle();
  }

  /**
   * {@inheritdoc}
   */
  protected function actionButton(array &$form, FormStateInterface $form_state, array $context = []) {
    // When refinement is disabled and the plugin provides its own AJAX
    // callback, keep the original direct field-fill behavior of
    // FieldWidgetActionBase instead of opening the modal form.
    if (!$this->isRefinementEnabled() && $this->getAjaxCallback()) {
      FieldWidgetActionBase::actionButton($form, $form_state, $context);
      return;
    }
    parent::actionButton($form, $form_state, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $context = $form_state->get('field_widget_action_context_data');
    if (!empty($context['submit_route_name']) && isset($form['actions'])) {
      // Give the cancel button a unique name so it can be detected server
      // side and close the dialog without inserting anything.
      $form['actions']['cancel']['#name'] = 'cancel';
      $form['actions']['cancel']['#weight'] = 10;
      if ($this->isRefinementEnabled()) {
        $form['actions']['refine'] = [
          '#type' => 'submit',
          '#name' => 'refine',
          '#value' => $this->t('Refine'),
          '#weight' => -1,
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
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildModalForm(array $form, FormStateInterface $form_state, ContentEntityInterface|NULL $entity): array {
    $context_data = $form_state->get('field_widget_action_context_data') ?? [];
    if (!$entity instanceof ContentEntityInterface) {
      $entity = $context_data['current_entity'] ?? NULL;
    }

    // Only generate content when the modal is first opened. On refine or
    // insert round trips the current content travels with the form values.
    $user_input = $form_state->getUserInput();
    $is_round_trip = ($user_input['form_id'] ?? '') === 'field_widget_action_wrapper_form';
    $content = $is_round_trip
      ? $this->contentToString($user_input['content'] ?? '')
      : $this->generateContent($entity, $context_data);
    $iteration = $is_round_trip ? max(1, (int) ($user_input['refinement_iteration'] ?? 1)) : 1;

    if ($this->targetIsFormattedText($context_data)) {
      // Formatted-text target (e.g. CKEditor): edit the content as rich text so
      // markup survives Generate, Refine and Accept. A text_format element
      // carries the value as ['value' => HTML, 'format' => …].
      $form['content'] = [
        '#type' => 'text_format',
        '#title' => $this->t('Generated content'),
        '#description' => $this->t('You can edit the content directly before inserting it.'),
        '#default_value' => $content,
        '#weight' => 0,
      ];
      // Mirror the target field's allowed formats when restricted; otherwise
      // let the element default to the same format the target widget uses.
      $allowed_formats = array_values(array_filter((array) ($context_data['target_element_field_settings']['allowed_formats'] ?? [])));
      if ($allowed_formats) {
        $form['content']['#allowed_formats'] = $allowed_formats;
      }
      // Preserve the user's chosen format across refine round trips.
      if ($is_round_trip && is_array($user_input['content'] ?? NULL) && !empty($user_input['content']['format'])) {
        $form['content']['#format'] = $user_input['content']['format'];
      }
    }
    else {
      $form['content'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Generated content'),
        '#description' => $this->t('You can edit the content directly before inserting it.'),
        '#default_value' => $content,
        '#rows' => 8,
        '#weight' => 0,
      ];
    }

    if ($this->isRefinementEnabled()) {
      $form['refinement_prompt'] = [
        '#type' => 'textarea',
        '#title' => $this->t('How would you like to refine this content?'),
        '#description' => $this->t('For example: make it shorter, use a more formal tone, add an example.'),
        '#rows' => 3,
        '#weight' => 5,
      ];
      $form['refinement_iteration'] = [
        '#type' => 'hidden',
        '#default_value' => $iteration,
      ];
      if ($iteration > 1) {
        $form['refinement_info'] = [
          '#markup' => '<p><small>' . $this->t('Refinement iteration @iteration', ['@iteration' => $iteration]) . '</small></p>',
          '#weight' => 8,
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitModalFormAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $user_input = $form_state->getUserInput();
    $triggering_element_name = $user_input['_triggering_element_name'] ?? '';

    // Cancel closes the dialog without touching the field.
    if ($triggering_element_name === 'cancel') {
      $response = new AjaxResponse();
      $response->addCommand(new CloseModalDialogCommand());
      $this->cleanUpModalFormState($form_state);
      return $response;
    }

    // Refine rebuilds the modal form with the refined content, the same way
    // validation errors re-display the form.
    if ($triggering_element_name === 'refine' && $this->isRefinementEnabled()) {
      return $this->refineModalFormAjax($form, $form_state);
    }

    return parent::submitModalFormAjax($form, $form_state);
  }

  /**
   * Handles the Refine submission by rebuilding the modal form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response replacing the modal form with the rebuilt version.
   */
  protected function refineModalFormAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $values = $form_state->getValues();
    $content = $this->contentToString($values['content'] ?? '');
    $refinement_prompt = trim((string) ($values['refinement_prompt'] ?? ''));

    if ($refinement_prompt === '') {
      // The status_messages element of the modal form renders this error in
      // the rebuilt modal.
      $this->messenger->addError($this->t('Please enter refinement instructions.'));
    }
    else {
      $context_data = $form_state->get('field_widget_action_context_data') ?? [];
      $entity = $context_data['current_entity'] ?? NULL;
      $content = $this->refineContent($content, $refinement_prompt, $entity, $context_data);

      // Push the refined values into the user input so the rebuilt form
      // renders them. Respect the element's value shape: a text_format element
      // round-trips as ['value' => …, 'format' => …], a textarea as a string.
      $user_input = $form_state->getUserInput();
      if (is_array($user_input['content'] ?? NULL)) {
        $user_input['content']['value'] = $content;
      }
      else {
        $user_input['content'] = $content;
      }
      $user_input['refinement_prompt'] = '';
      $user_input['refinement_iteration'] = (int) ($user_input['refinement_iteration'] ?? 1) + 1;
      $form_state->setUserInput($user_input);
    }

    // Rebuild the modal form through the form builder so the rebuilt form is
    // fully processed, including form tokens and AJAX bindings.
    $form_state->setRebuild();
    $rebuilt_form = $this->formBuilder->rebuildForm('field_widget_action_wrapper_form', $form_state, $form);

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#field_widget_actions_modal_form', $rebuilt_form));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  protected function submitModalFormFillFields(array $form, FormStateInterface $form_state, AjaxResponse $response): AjaxResponse {
    $context_data = $form_state->get('field_widget_action_context_data') ?? [];
    $target_element = $context_data['target_element'] ?? [];
    if (empty($target_element['#name'])) {
      return $response;
    }
    $selector = '[name="' . $target_element['#name'] . '"]';
    $value = $this->contentToString($form_state->getValue('content'));
    // FillEditorCommand falls back to a plain value assignment when no editor
    // instance is attached, so it is safe for any formatted-text target.
    if ($this->targetIsFormattedText($context_data)) {
      $response->addCommand(new FillEditorCommand($selector, $value));
    }
    else {
      $response->addCommand(new FillSimpleFieldCommand($selector, $value));
    }
    return $response;
  }

  /**
   * Determines whether the target element holds formatted (rich-text) content.
   *
   * This is a property-level check, not a field-level one: a text_with_summary
   * field stores a format, but its 'summary' property is plain text. The
   * decision is therefore based on the captured target element, not the field
   * settings.
   *
   * A text_format element exposes '#base_type' on its captured value child
   * (set while the text_format element is processed); plain textarea/textfield
   * elements — including the summary and plain string fields — do not. Unlike
   * '#format', '#base_type' is reliable even on a new entity, where no format
   * has been stored yet.
   *
   * @param array $context_data
   *   The field widget action context data.
   *
   * @return bool
   *   TRUE if the target element is a formatted-text element, FALSE otherwise.
   */
  protected function targetIsFormattedText(array $context_data): bool {
    $target_element = $context_data['target_element'] ?? [];
    return !empty($target_element['#base_type']);
  }

  /**
   * Extracts the content string from a raw value.
   *
   * The content element is a textarea (plain string value) for plain-text
   * targets and a text_format element (['value' => …, 'format' => …]) for
   * formatted-text targets. This converts both to the string content.
   *
   * @param mixed $raw
   *   The raw value from user input or form values.
   *
   * @return string
   *   The content as a string.
   */
  protected function contentToString(mixed $raw): string {
    if (is_array($raw)) {
      return (string) ($raw['value'] ?? '');
    }
    return (string) $raw;
  }

}
