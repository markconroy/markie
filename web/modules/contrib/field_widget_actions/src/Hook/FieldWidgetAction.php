<?php

namespace Drupal\field_widget_actions\Hook;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Uuid\Uuid;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\FieldWidgetActionInterface;
use Drupal\field_widget_actions\FieldWidgetActionManagerInterface;
use Drupal\field_widget_actions\Plugin\ConfigAction\SetupFieldWidgetAction;

/**
 * Class for hooks from field_widget_actions module.
 */
class FieldWidgetAction {

  use StringTranslationTrait;

  /**
   * Constructs hook class.
   *
   * @param \Drupal\field_widget_actions\FieldWidgetActionManagerInterface $fieldWidgetActionManager
   *   The field widget actions manager.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The uuid service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    protected FieldWidgetActionManagerInterface $fieldWidgetActionManager,
    protected UuidInterface $uuid,
    protected ModuleHandlerInterface $moduleHandler,
  ) {

  }

  /**
   * Returns the list of widget plugin IDs that do not render child elements.
   *
   * Widgets in this list are wrapped in a container by the form alter hooks so
   * that Field Widget Action buttons can appear alongside them. Contrib modules
   * can extend the list via
   * hook_field_widget_actions_childless_widgets_alter().
   *
   * @return string[]
   *   An array of widget plugin IDs.
   */
  public function getChildlessWidgets(): array {
    $childless_widgets = [
      'tagify_select_widget',
      'select2',
      'options_select',
    ];
    $this->moduleHandler->alter('field_widget_actions_childless_widgets', $childless_widgets);
    return $childless_widgets;
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() {
    return [
      'field_widget_actions_suggestions' => [
        'variables' => [
          'suggestions' => [],
        ],
      ],
    ];
  }

  /**
   * Implements hook_field_widget_third_party_settings_form().
   */
  #[Hook('field_widget_third_party_settings_form')]
  public function fieldWidgetThirdPartySettingsForm(WidgetInterface $plugin, FieldDefinitionInterface $field_definition, $form_mode, array $form, FormStateInterface $form_state) {
    $element = [];
    $has_unavailable = FALSE;
    $unavailable_ids = [];
    $allowed_field_widget_actions = $this->fieldWidgetActionManager->getAllowedFieldWidgetActions($plugin->getPluginId(), $field_definition->getType());
    foreach ($allowed_field_widget_actions as $plugin_id => $allowed_field_widget_action) {
      /** @var \Drupal\field_widget_actions\FieldWidgetActionInterface $plugin_instance */
      try {
        $plugin_instance = $this->fieldWidgetActionManager->createInstance($plugin_id);
      }
      catch (\Throwable $e) {
        unset($allowed_field_widget_actions[$plugin_id]);
        $unavailable_ids[$plugin_id] = TRUE;
        $has_unavailable = TRUE;
        continue;
      }
      // Only set the field definition for configurable (bundle-scoped) fields.
      // An action's isAvailable() may rely on the field's bundle (e.g. an AI
      // Automator action), and base fields do not provide one.
      if ($field_definition instanceof FieldConfigInterface) {
        $plugin_instance->setFieldDefinition($field_definition);
      }
      // Filter out actions that are not available for this field, e.g. an AI
      // Automator action whose Automator has not been configured yet. Each
      // plugin decides its own availability through isAvailable().
      if (!$plugin_instance->isAvailable()) {
        unset($allowed_field_widget_actions[$plugin_id]);
        $unavailable_ids[$plugin_id] = TRUE;
        $has_unavailable = TRUE;
      }
    }
    // Detect a configured action that has become unavailable, e.g. its AI
    // Automator was deleted after the action was enabled. Such an action is
    // hidden below, so we warn the user instead of dropping it silently. This
    // is computed up front so the warning can be shown whether or not the field
    // still has any available actions.
    $enabled_unavailable = FALSE;
    foreach ($plugin->getThirdPartySettings('field_widget_actions') as $configuration) {
      if (!empty($configuration['plugin_id']) && isset($unavailable_ids[$configuration['plugin_id']])) {
        $enabled_unavailable = TRUE;
        break;
      }
    }
    if (!empty($allowed_field_widget_actions)) {
      $wrapper_id = 'field-widget-actions-' . $field_definition->getName();
      $element = [
        '#type' => 'details',
        '#title' => $this->t('Field Widget Actions'),
        '#description' => $this->t('Add action buttons to this field. Select an action type from the dropdown and click <em>Add action</em> to create a new button. Each configured button appears as a separate section below.'),
        '#prefix' => '<div id="' . $wrapper_id . '">',
        '#suffix' => '</div>',
        '#attached' => [
          'library' => [
            'field_widget_actions/admin_ui',
          ],
        ],
        '#attributes' => [
          'class' => [
            'field-widget-actions-wrapper',
          ],
        ],
      ];
      $options = [];
      foreach ($allowed_field_widget_actions as $plugin_id => $allowed_field_widget_action) {
        if (empty($allowed_field_widget_action['category'])) {
          $allowed_field_widget_action['category'] = $this->t('Other');
        }
        $category = (string) $allowed_field_widget_action['category'];
        $options[$category][$plugin_id] = $allowed_field_widget_action['label'];
      }
      $element['new'] = [
        '#tree' => TRUE,
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'field-widget-actions-add-new',
          ],
        ],
        '#parents' => [],
      ];
      $element['new']['action'] = [
        '#type' => 'select',
        '#title' => $this->t('Add New Action'),
        '#description' => $this->t('The list displays only the actions that are available for current field (type and widget type).'),
        '#options' => $options,
        '#empty_option' => $this->t('- None -'),
        '#wrapper_attributes' => [
          'class' => [
            'field-widget-actions-select',
          ],
        ],
      ];
      $element['new']['add'] = [
        '#type' => 'button',
        '#name' => $field_definition->getName() . '_add_field_widget_actions',
        '#value' => $this->t('Add action'),
        '#ajax' => [
          'callback' => [static::class, 'addAction'],
          'event' => 'click',
          'wrapper' => $wrapper_id,
        ],
        '#suffix' => '<p>' . $this->t('Sort the actions with drag and drop (titles of the blocks below are draggable with mouse)') . '</p>',
      ];
      $element['new']['unavailable'] = [
        '#access' => $has_unavailable,
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('Some of the actions are unavailable for the current field. For example if you want to attach AI Automator action, the AI Automator itself should be added to the field first.') . '</p>',
      ];
      $enabled_plugins = $plugin->getThirdPartySettings('field_widget_actions');
      $triggering_element = $form_state->getTriggeringElement();
      if (!empty($triggering_element)) {
        $parents = $triggering_element['#parents'];
        $values = $form_state->getValues();
        array_pop($parents);
        // If it is a button to add new action, prepare the new form element.
        if ($triggering_element['#name'] === $field_definition->getName() . '_add_field_widget_actions') {
          $element['#open'] = TRUE;
          $parents[] = 'action';
          $action_plugin_id = NestedArray::getValue($values, $parents);
          $uuid = $this->uuid->generate();
          $enabled_plugins[$uuid] = ['plugin_id' => $action_plugin_id, 'open' => TRUE];
        }
        // If it is a button to remove an action, delete the corresponding form
        // element.
        if (str_contains($triggering_element['#name'], $field_definition->getName() . '_remove_field_widget_action')) {
          $element['#open'] = TRUE;
          $action_to_remove = array_pop($parents);
          $enabled_plugins = NestedArray::getValue($values, $parents);
          if (isset($enabled_plugins['new'])) {
            unset($enabled_plugins['new']);
          }
          if (isset($enabled_plugins[$action_to_remove])) {
            unset($enabled_plugins[$action_to_remove]);
          }
        }
        // In case the form display form is saved, make sure only needed actions
        // are in the list.
        if ($triggering_element['#name'] === $field_definition->getName() . '_plugin_settings_update') {
          array_pop($parents);
          $parents[] = 'third_party_settings';
          $parents[] = 'field_widget_actions';
          $enabled_plugins = NestedArray::getValue($values, $parents);
          if (isset($enabled_plugins['new'])) {
            unset($enabled_plugins['new']);
          }
        }
      }
      if (empty($enabled_plugins)) {
        // NULL (can be a result of NestedArray::getValue) is also empty, but we
        // need to make sure that it is possible to iterate through this
        // variable as it is used in foreach later.
        $enabled_plugins = [];
        // If there are no actions, no need to show the information about their
        // sorting.
        $element['new']['add']['#suffix'] = '';
      }
      $i = 0;
      foreach ($enabled_plugins as $action_id => $configuration) {
        if (empty($configuration['plugin_id'])) {
          continue;
        }
        try {
          /** @var \Drupal\field_widget_actions\FieldWidgetActionInterface $allowed_field_widget_action */
          $allowed_field_widget_action = $this->fieldWidgetActionManager->createInstance($configuration['plugin_id'], $configuration);
        }
        catch (PluginNotFoundException $e) {
          continue;
        }
        // Check if the action is a valid field widget action.
        if (!$allowed_field_widget_action instanceof FieldWidgetActionInterface) {
          continue;
        }
        // The field definition does not have bundle set by default.
        if (!$field_definition->getTargetBundle() && $form['#bundle']) {
          // Set the bundle to the field definition.
          $field_definition->setTargetBundle($form['#bundle']);
        }
        $allowed_field_widget_action->setFieldDefinition($field_definition);
        // Check so the field is available for the action. Unavailable
        // configured actions are hidden here; the warning about them is added
        // below, driven by $enabled_unavailable computed above.
        if (!$allowed_field_widget_action->isAvailable()) {
          continue;
        }
        $allowed_field_widget_action->setWidget($plugin);
        $element[$action_id] = $allowed_field_widget_action->buildConfigurationForm($form, $form_state, $action_id);
        $element[$action_id]['#type'] = 'details';
        if ($i == 0) {
          // Wrap only action elements, for proper sorting. There is an issue
          // with `filter` property of Sortable, that removes the default on
          // click event and this prevents from using dropdown list of actions.
          $element[$action_id]['#prefix'] = '<div class="field-widget-actions-sortable">';
        }
        // Element for new action. Let's have it opened by default, so it is
        // easily accessible.
        if (!empty($configuration['open'])) {
          $element[$action_id]['#open'] = TRUE;
          $element[$action_id]['weight']['#default_value'] = $i;
        }
        $title = $allowed_field_widget_action->getButtonLabel();
        if ($title != $allowed_field_widget_action->getLabel()) {
          $title .= ' (' . $allowed_field_widget_action->getLabel() . ')';
        }
        $element[$action_id]['#title'] = $title;
        $element[$action_id]['#description'] = $allowed_field_widget_action->getDescription();
        $element[$action_id]['#attributes']['class'][] = 'field-widget-action-element';
        // Do not display "Remove Action" for newly added items, as it is
        // confusing.
        if (empty($configuration['open'])) {
          $element[$action_id]['remove'] = [
            '#type' => 'button',
            '#name' => $field_definition->getName() . '_remove_field_widget_action_' . $action_id,
            '#value' => $this->t('Remove Action'),
            '#after_build' => [[static::class, 'removeButtonAfterBuild']],
            '#ajax' => [
              'callback' => [static::class, 'removeAction'],
              'event' => 'click',
              'wrapper' => $wrapper_id,
            ],
          ];
        }
        $i++;
        if ($i == count($enabled_plugins)) {
          $element[$action_id]['#suffix'] = '</div>';
        }
      }
      if ($enabled_unavailable) {
        $element['enabled_unavailable'] = [
          '#type' => 'markup',
          '#weight' => -100,
          '#markup' => '<p>' . $this->t('A configured action is currently unavailable and has been hidden. For example, an AI Automator action becomes unavailable when its Automator is removed from the field.') . '</p>',
        ];
      }
    }
    elseif ($enabled_unavailable) {
      $element = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('A configured action is currently unavailable and has been hidden. For example, an AI Automator action becomes unavailable when its Automator is removed from the field.') . '</p>',
      ];
    }
    elseif ($has_unavailable) {
      $element = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('There are no available Field Widget Actions for the current field, but there are plugins that can be used with some pre-configuration. For example, if you want to attach AI Automator action, the AI Automator itself should be added to the field first.') . '</p>',
      ];
    }
    return $element;
  }

  /**
   * Implements hook_field_widget_settings_summary_alter().
   */
  #[Hook('field_widget_settings_summary_alter')]
  public function fieldWidgetSettingsSummaryAlter(array &$summary, array $context) {
    /** @var \Drupal\Core\Field\WidgetInterface $widget */
    $widget = $context['widget'];
    $enabled_plugins = $widget->getThirdPartySettings('field_widget_actions') ?? [];
    $labels = [];

    foreach ($enabled_plugins as $configuration) {
      if (empty($configuration['plugin_id']) || empty($configuration['enabled'])) {
        continue;
      }

      try {
        $action = $this->fieldWidgetActionManager->createInstance($configuration['plugin_id'], $configuration);
      }
      catch (PluginNotFoundException $e) {
        continue;
      }

      $title = $action->getButtonLabel();
      if ($title != $action->getLabel()) {
        $title .= ' (' . $action->getLabel() . ')';
      }
      $labels[] = $title;
    }

    if (!empty($labels)) {
      $summary[] = $this->formatPlural(count($labels), 'Action: @labels', 'Actions: @labels', ['@labels' => implode(', ', $labels)]);
    }
  }

  /**
   * Adds new action.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The actions element.
   */
  public static function addAction(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    array_pop($array_parents);
    array_pop($array_parents);
    return NestedArray::getValue($form, $array_parents);
  }

  /**
   * Remove action.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The actions element.
   */
  public static function removeAction(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    array_pop($array_parents);
    array_pop($array_parents);
    return NestedArray::getValue($form, $array_parents);
  }

  /**
   * After-build callback for the "Remove Action" button.
   *
   * The button lives inside the third-party settings sub-form, so the Form API
   * stores its value next to the action settings. Field UI copies those
   * settings verbatim into the form display when "Update" is pressed, without
   * calling \Drupal\Core\Form\FormState::cleanValues(), which is what
   * normally strips button values. Unsetting the value here keeps a 'remove'
   * key out of the saved configuration. Declaring the button with
   * '#input' => FALSE is not an option: the Form API then never detects it as
   * the triggering element and attributes the click to the first button in the
   * form instead.
   *
   * @param array $element
   *   The button element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The unchanged button element.
   */
  public static function removeButtonAfterBuild(array $element, FormStateInterface $form_state): array {
    $form_state->unsetValue($element['#parents']);
    return $element;
  }

  /**
   * Implements hook_field_widget_complete_form_alter().
   */
  #[Hook('field_widget_complete_form_alter')]
  public function fieldWidgetCompleteFormAlter(array &$field_widget_complete_form, FormStateInterface $form_state, array $context) {
    $allowed_actions = $this->fieldWidgetActionManager->getAllowedFieldWidgetActions($context['widget']->getPluginId(), $context['items']->getFieldDefinition()->getType());
    $actions = $context['widget']->getThirdPartySettings('field_widget_actions') ?? [];
    foreach ($actions as $action_id => $action) {
      if (empty($action['plugin_id'])) {
        continue;
      }
      if (empty($action['enabled'])) {
        continue;
      }
      $plugin_id = $action['plugin_id'];
      if (empty($allowed_actions[$plugin_id])) {
        continue;
      }
      $field_widget_action = $this->fieldWidgetActionManager->createInstance($plugin_id, $action);
      if (isset($context['items'])) {
        $field_widget_action->setFieldDefinition($context['items']->getFieldDefinition());
      }
      if ($field_widget_action instanceof FieldWidgetActionInterface && $field_widget_action->isAvailable()) {
        $context['action_id'] = $action_id;
        $field_widget_action->completeFormAlter($field_widget_complete_form, $form_state, $context);
      }
    }

    // Some widgets do not render children like select or tagify. In that case,
    // the buttons added above are ignored unless we wrap the widget and actions
    // in a container.
    $widget_id = $context['widget']->getPluginId();
    $element_type = $field_widget_complete_form['#type'] ?? '';
    if ($element_type === 'select' || in_array($widget_id, $this->getChildlessWidgets())) {

      // Find all field widget action buttons.
      $actions_found = [];
      foreach ($field_widget_complete_form['widget'] ?? [] as $key => $field) {
        if (is_array($field) && !empty($field['#field_widget_action_field_name'])) {
          $actions_found[$key] = $field;
        }
      }

      // Nest the widget within a container.
      if ($field_widget_complete_form['#type'] !== 'container') {
        $original_widget = $field_widget_complete_form['widget'];
        $field_widget_complete_form = [
          '#type' => 'container',
          '#attributes' => ['class' => ['field-widget-actions-container']],
        ];
        $field_widget_complete_form['widget'] = $original_widget;
      }

      // Ensure the widget retains its original #parents so form submission
      // values are mapped correctly.
      if (isset($original_widget['#parents'])) {
        $field_widget_complete_form['widget']['#parents'] = $original_widget['#parents'];
      }

      // Move the actions to the top level.
      foreach ($actions_found as $key => $field) {
        $field_widget_complete_form[$key] = $field;
        unset($field_widget_complete_form['widget'][$key]);
      }
    }
  }

  /**
   * Implements hook_field_widget_single_element_form_alter().
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(array &$element, FormStateInterface $form_state, array $context) {
    $allowed_actions = $this->fieldWidgetActionManager->getAllowedFieldWidgetActions($context['widget']->getPluginId(), $context['items']->getFieldDefinition()->getType());
    $actions = $context['widget']->getThirdPartySettings('field_widget_actions') ?? [];
    foreach ($actions as $action_id => $action) {
      if (empty($action['plugin_id'])) {
        continue;
      }
      if (empty($action['enabled'])) {
        continue;
      }
      $plugin_id = $action['plugin_id'];
      if (empty($allowed_actions[$plugin_id])) {
        continue;
      }
      $field_widget_action = $this->fieldWidgetActionManager->createInstance($plugin_id, $action);
      if (isset($context['items'])) {
        $field_widget_action->setFieldDefinition($context['items']->getFieldDefinition());
      }
      if ($field_widget_action instanceof FieldWidgetActionInterface && $field_widget_action->isAvailable()) {
        $context['action_id'] = $action_id;
        $field_widget_action->singleElementFormAlter($element, $form_state, $context);
      }
    }

    // Move known childless widgets into a container.
    $widget_id = $context['widget']->getPluginId();
    if (in_array($widget_id, $this->getChildlessWidgets()) || (isset($element['#type']) && $element['#type'] === 'select')) {
      $actions_found = [];
      foreach ($element as $key => $child) {
        if (is_array($child) && !empty($child['#field_widget_action_field_name'])) {
          $actions_found[$key] = $child;
        }
      }

      if (!empty($actions_found)) {
        $original_element = $element;
        $element = [
          '#type' => 'container',
          '#attributes' => ['class' => ['field-widget-actions-container']],
          'widget' => $original_element,
        ];
        foreach ($actions_found as $key => $action_element) {
          $element[$key] = $action_element;
          unset($element['widget'][$key]);
        }
      }
    }
  }

  /**
   * Implements hook_config_actions_alter().
   */
  #[Hook('config_action_alter')]
  public function configActionAlter(array &$definitions) {
    if (empty($definitions['setComponentThirdPartySetting'])) {
      $definitions['setComponentThirdPartySetting'] = [
        'class' => SetupFieldWidgetAction::class,
        'provider' => 'field_widget_actions',
        'id' => 'setComponentThirdPartySetting',
        'admin_label' => new TranslatableMarkup('Setup Field Widget Actions'),
        'entity_types' => [
          'entity_form_display',
        ],
      ];
    }
  }

  /**
   * Implements hook_entity_form_display_presave().
   */
  #[Hook('entity_form_display_presave')]
  public function entityFormDisplayPresave(EntityFormDisplayInterface $form_display) {
    foreach ($form_display->getComponents() as $field => $component_settings) {
      // If the field widget actions for the component were saved as list, we
      // need to re-save it as associative array that has UUIDs as keys.
      if (!empty($component_settings['third_party_settings']['field_widget_actions']) && is_array($component_settings['third_party_settings']['field_widget_actions'])) {
        $keyed = [];
        foreach ($component_settings['third_party_settings']['field_widget_actions'] as $key => $item) {
          $new_key = $key;
          // Check that the key is valid UUID to avoid the case where settings
          // are a mixture of associative and non-associative items.
          if (!Uuid::isValid($key)) {
            $new_key = $this->uuid->generate();
          }
          $keyed[$new_key] = $item;
        }
        $component_settings['third_party_settings']['field_widget_actions'] = $keyed;
        $form_display->setComponent($field, $component_settings);
      }
    }
  }

}
