<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;
use Drupal\Core\Form\FormStateInterface;

/**
 * Schema.org Action trait.
 */
trait SchemaActionTrait {

  use SchemaPersonOrgTrait, SchemaOfferTrait, SchemaThingTrait, SchemaPlaceTrait, SchemaEventTrait, SchemaEntryPointTrait, SchemaPivotTrait {
    SchemaPlaceTrait::placeFormKeys insteadof SchemaEventTrait;
    SchemaPlaceTrait::placeForm insteadof SchemaEventTrait;
    SchemaPlaceTrait::postalAddressFormKeys insteadof SchemaEventTrait;
    SchemaPlaceTrait::postalAddressForm insteadof SchemaEventTrait;
    SchemaPlaceTrait::geoFormKeys insteadof SchemaEventTrait;
    SchemaPlaceTrait::geoForm insteadof SchemaEventTrait;
    SchemaPlaceTrait::countryFormKeys insteadof SchemaOfferTrait;
    SchemaPlaceTrait::countryForm insteadof SchemaOfferTrait;
    SchemaPlaceTrait::countryFormKeys insteadof SchemaEventTrait;
    SchemaPlaceTrait::countryForm insteadof SchemaEventTrait;
    SchemaPivotTrait::pivotForm insteadof SchemaPersonOrgTrait;
    SchemaPivotTrait::pivotForm insteadof SchemaOfferTrait;
    SchemaPivotTrait::pivotForm insteadof SchemaThingTrait;
    SchemaPivotTrait::pivotForm insteadof SchemaPlaceTrait;
    SchemaPivotTrait::pivotForm insteadof SchemaEventTrait;
    SchemaPivotTrait::pivotForm insteadof SchemaEntryPointTrait;
  }

  /**
   * The keys for this form.
   *
   * @param string $action_type
   *   Optional, limit the keys to those that are required for a specific
   *   action type.
   *
   * @return array
   *   Return an array of the form keys.
   */
  public static function actionFormKeys($action_type = NULL) {
    $list = ['@type'];
    $types = static::actionTypes();
    foreach ($types as $type) {
      if ($type == $action_type || empty($action_type) || $type == 'All') {
        $list = array_merge($list, array_keys(static::actionProperties($type)));
      }
    }
    $list = array_merge($list, array_keys(static::actionProperties('All')));
    return $list;
  }

  /**
   * Create the form element.
   *
   * @param array $input_values
   *   An array of values passed from a higher level form element to this.
   *
   * @return array
   *   The form element.
   */
  public function actionForm(array $input_values) {

    $input_values += SchemaMetatagManager::defaultInputValues();
    $value = $input_values['value'];

    $form['#type'] = 'fieldset';
    $form['#title'] = $input_values['title'];
    $form['#description'] = $input_values['description'];
    $form['#tree'] = TRUE;

    $current_action = !empty($value['@type']) ? $value['@type'] : '';
    $current_type = !empty($current_action) ? static::getActionType($current_action) : '';

    $action_types = $input_values['actionTypes'];
    $options = array_combine($action_types, $action_types);
    $form['actionType'] = [
      '#type' => 'select',
      '#title' => $this->t('actionType'),
      '#default_value' => $current_type,
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => $options,
      '#required' => $input_values['#required'],
      '#weight' => -10,
    ];

    $selector = ':input[name="' . $input_values['visibility_selector'] . '[actionType]"]';
    $selector2 = SchemaMetatagManager::altSelector($selector);

    $visibility = ['invisible' => [$selector => ['value' => '']]];
    $visibility2 = ['invisible' => [$selector2 => ['value' => '']]];
    $visibility['invisible'] = [$visibility['invisible'], $visibility2['invisible']];

    $invisibility = ['visible' => [$selector => ['value' => 'Invalid']]];
    $invisibility2 = ['visible' => [$selector2 => ['value' => 'Invalid']]];
    $invisibility['visible'] = [$invisibility['visible'], $invisibility2['visible']];

    // Add a pivot option to the form.
    $form['pivot'] = $this->pivotForm($value);
    $form['pivot']['#states'] = $visibility;

    // Build the form one action type at a time, using the visibility settings
    // to hide/show only form elements for the selected type. The form values
    // for each action type are created as a nested form value.
    $types = $input_values['actionTypes'];
    foreach ($types as $type) {
      $options = [];
      foreach ($input_values['actions'] as $action) {
        if ($type == static::getActionType($action)) {
          $options[] = $action;
        }
      }
      $options = array_combine($options, $options);
      $action_type_visibility = ['visible' => [$selector => ['value' => $type]]];
      $action_type_visibility2 = ['visible' => [$selector2 => ['value' => $type]]];
      $action_type_visibility['visible'] = [$action_type_visibility['visible'], $action_type_visibility2['visible']];

      $all_action_visibility = ['invisible' => [$selector => ['value' => '']]];
      $all_action_visibility2 = ['invisible' => [$selector2 => ['value' => '']]];
      $all_action_visibility['invisible'] = [$all_action_visibility['invisible'], $all_action_visibility2['invisible']];

      $form[$type] = [
        '#type' => 'fieldset',
        '#title' => $type,
        '#states' => $action_type_visibility,
      ];

      // Once an action type is selected, choose the specific actions that
      // go with it. This element is just a way to break the long list of
      // actions up. This is a much shorter list than the list of all actions.
      $form[$type]['@type'] = [
        '#type' => 'select',
        '#title' => $this->t('@type'),
        '#default_value' => $current_action,
        '#empty_option' => t('- None -'),
        '#empty_value' => '',
        '#options' => $options,
        '#required' => $input_values['#required'],
        '#weight' => -20,
      ];

      // Properties specific to an action type appear only for that type.
      // Weight these properties ahead of general action properties.
      $properties = static::actionProperties($type);
      foreach ($properties as $key => $property) {
        if (empty($property['formKeys'])) {
          $form[$type][$key] = [
            '#type' => 'textfield',
            '#title' => $key,
            '#default_value' => !empty($value[$key]) ? $value[$key] : '',
            '#empty_option' => t('- None -'),
            '#empty_value' => '',
            '#required' => $input_values['#required'],
            '#description' => $property['description'],
            '#states' => $action_type_visibility,
            '#weight' => 0,
          ];
        }
        else {
          $sub_values = [
            'title' => $key,
            'description' => $property['description'],
            'value' => !empty($value[$key]) ? $value[$key] : [],
            '#required' => $input_values['#required'],
            'visibility_selector' => $input_values['visibility_selector'] . '[' . $type . '][' . $key . ']',
            'visibility_type' => '@type',
          ];
          $method = $property['form'];
          $form[$type][$key] = $this->$method($sub_values);
          $form[$type][$key]['#states'] = $action_type_visibility;
          $form[$type][$key]['#weight'] = 0;
        }
      }

      // Properties common to all actions appear for any action type.
      // Weight these after the action-specific properties.
      $properties = static::actionProperties('All');
      foreach ($properties as $key => $property) {
        if (empty($property['formKeys'])) {
          $form[$type][$key] = [
            '#type' => 'textfield',
            '#title' => $key,
            '#default_value' => !empty($value[$key]) ? $value[$key] : '',
            '#empty_option' => t('- None -'),
            '#empty_value' => '',
            '#required' => $input_values['#required'],
            '#description' => $property['description'],
            '#states' => $all_action_visibility,
            '#weight' => 5,
          ];
        }
        else {
          $sub_values = [
            'title' => $key,
            'description' => $property['description'],
            'value' => !empty($value[$key]) ? $value[$key] : [],
            '#required' => $input_values['#required'],
            'visibility_selector' => $input_values['visibility_selector'] . '[' . $type . '][' . $key . ']',
          ];
          $method = $property['form'];
          $form[$type][$key] = $this->$method($sub_values);
          $form[$type][$key]['#states'] = $all_action_visibility;
          $form[$type][$key]['#weight'] = 5;
        }
      }
    }

    // Create a hidden top-level form element with all the properties.
    // The '#element_validate' method, actionValidation(), will populate this
    // element from the selected action type, and it is also used by tests.
    $form['#element_validate'] = [[get_class($this), 'actionValidation']];

    $keys = static::actionFormKeys();
    foreach ($action_types as $type) {
      foreach ($keys as $key) {
        if (array_key_exists($key, $form[$type])) {
          $form[$key] = $form[$type][$key];
          $form[$key]['#states'] = $invisibility;
        }
      }
    }
    $actions = static::getAllActions(TRUE);
    $all_options = array_combine($actions, $actions);

    $form['@type'] = $form[$type]['@type'];
    $form['@type']['#states'] = $invisibility;
    $form['@type']['#options'] = $all_options;

    return $form;
  }

  /**
   * Validates my action form.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function actionValidation(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $value = $form_state->getValue($element['#parents']);
    if ($action = $value['actionType']) {
      if ($sub_form = $value[$action]) {
        $form_state->setValue($element['#parents'], $sub_form);
      }
    }
  }

  /**
   * Get an array of all actions, grouped by action type.
   *
   * @param string $flattened
   *   Set TRUE to return a single-level array of values.
   *
   * @return array
   *   An array of all actions, grouped by type.
   */
  public static function getAllActions($flattened = FALSE) {
    $list = [];
    $types = static::actionTypes();
    foreach ($types as $type) {
      if ($flattened) {
        $list = array_merge($list, static::actionObjects($type));
      }
      else {
        $list[$type] = static::actionObjects($type);
      }
    }
    return $list;
  }

  /**
   * Get the action type for a given action.
   *
   * @param string $action
   *   The action to assess.
   *
   * @return string
   *   The action type for the specified action.
   */
  public static function getActionType($action) {
    $actions = static::getAllActions();
    foreach ($actions as $type => $values) {
      if (in_array($action, $values)) {
        return $type;
      }
    }
    return FALSE;
  }

  /**
   * All action types.
   *
   * @return array
   *   An array of all possible action types.
   */
  public static function actionTypes() {
    return [
      'MoveAction',
      'TransferAction',
      'TradeAction',
      'ControlAction',
      'AchieveAction',
      'OrganizeAction',
      'AssessAction',
      'InteractAction',
      'ConsumeAction',
      'CreateAction',
      'PlayAction',
      'SearchAction',
      'FindAction',
      'UpdateAction',
    ];
  }

  /**
   * Return an array of all actions for an action type.
   *
   * @param string $action_type
   *   The type of action.
   *
   * @return array
   *   An array of all the actions for the specified type.
   */
  public static function actionObjects($action_type) {
    switch ($action_type) {

      case 'MoveAction':
        return [
          'MoveAction',
          'TravelAction',
          'DepartAction',
          'ArriveAction',
        ];

      case 'TransferAction':
        return [
          'TransferAction',
          'DownloadAction',
          'LendAction',
          'GiveAction',
          'ReceiveAction',
          'SendAction',
          'BorrowAction',
          'ReturnAction',
          'TakeAction',
        ];

      case 'TradeAction':
        return [
          'TradeAction',
          'BuyAction',
          'QuoteAction',
          'SellAction',
          'PayAction',
          'RentAction',
          'DonateAction',
          'OrderAction',
          'TipAction',
        ];

      case 'ControlAction':
        return [
          'ControlAction',
          'ResumeAction',
          'DeactivateAction',
          'ActivateAction',
          'SuspendAction',
        ];

      case 'AchieveAction':
        return [
          'AchieveAction',
          'WinAction',
          'LoseAction',
          'TieAction',
        ];

      case 'OrganizeAction':
        return [
          'OrganizeAction',
          'PlanAction',
          'CancelAction',
          'ReserveAction',
          'ScheduleAction',
          'ApplyAction',
          'AllocateAction',
          'AuthorizeAction',
          'AssignAction',
          'RejectAction',
          'AcceptAction',
          'BookmarkAction',
        ];

      case 'AssessAction':
        return [
          'AssessAction',
          'IgnoreAction',
          'ChooseAction',
          'VoteAction',
          'ReactAction',
          'LikeAction',
          'DisagreeAction',
          'EndorseAction',
          'AgreeAction',
          'DislikeAction',
          'WantAction',
          'ReviewAction',
        ];

      case 'InteractAction':
        return [
          'InteractAction',
          'BefriendAction',
          'SubscribeAction',
          'LeaveAction',
          'UnRegisterAction',
          'MarryAction',
          'RegisterAction',
          'JoinAction',
          'CommunicateAction',
          'CheckOutAction',
          'InviteAction',
          'CommentAction',
          'ReplyAction',
          'ShareAction',
          'InformAction',
          'RsvpAction',
          'ConfirmAction',
          'AskAction',
          'CheckInAction',
          'FollowAction',
        ];

      case 'ConsumeAction':
        return [
          'ConsumeAction',
          'ViewAction',
          'DrinkAction',
          'ListenAction',
          'WatchAction',
          'InstallAction',
          'UseAction',
          'WearAction',
          'ReadAction',
          'EatAction',
        ];

      case 'CreateAction':
        return [
          'CreateAction',
          'DrawAction',
          'FilmAction',
          'CookAction',
          'PhotographAction',
          'PaintAction',
          'WriteAction',
        ];

      case 'PlayAction':
        return [
          'PlayAction',
          'ExerciseAction',
          'PerformAction',
        ];

      case 'SearchAction':
        return [
          'SearchAction',
        ];

      case 'FindAction':
        return [
          'FindAction',
          'CheckAction',
          'DiscoverAction',
          'TrackAction',
        ];

      case 'UpdateAction':
        return [
          'UpdateAction',
          'AddAction',
          'InsertAction',
          'AppendAction',
          'PrependAction',
          'DeleteAction',
          'ReplaceAction',
        ];

      default:
        return [
          'Action',
        ];

    }

  }

  /**
   * Return an array of the unique properties for an action type.
   *
   * Some properties are commented out because there is no base
   * class for that property at this time.
   *
   * @param string $action_type
   *   The type of action. Use an action name for properties specific to that
   *   action type. Use 'All' for general properties that apply
   *   to all actions.
   *
   * @return array
   *   An array of all the unique properties for that type.
   */
  public static function actionProperties($action_type) {
    switch ($action_type) {

      case 'MoveAction':
      case 'TransferAction':
        return [
          'fromLocation' => [
            'class' => 'SchemaPlaceBase',
            'formKeys' => 'placeFormKeys',
            'form' => 'placeForm',
            'description' => "A sub property of location. The original location of the object or the agent before the action.",
          ],
          'toLocation' => [
            'class' => 'SchemaPlaceBase',
            'formKeys' => 'placeFormKeys',
            'form' => 'placeForm',
            'description' => "A sub property of location. The final location of the object or the agent after the action.",
          ],
        ];

      case 'TradeAction':
        return [
          'target' => [
            'class' => 'SchemaEntryPointBase',
            'formKeys' => 'entryPointFormKeys',
            'form' => 'entryPointForm',
            'description' => "Indicates a target EntryPoint for an Action.",
          ],
          //'priceSpecification' => [
          //  'class' => '',
          //  'formKeys' => '',
          //  'form' => '',
          //  'description' => "One or more detailed price specifications, indicating the unit price and delivery or payment charges.",
          //],
          //'deliveryMethod' => [
          //  'class' => '',
          //  'formKeys' => '',
          //  'form' => '',
          //  'description' => "A sub property of instrument. The method of delivery.",
          //],
        ];

      case 'ConsumeAction':
        return [
          'target' => [
            'class' => 'SchemaEntryPointBase',
            'formKeys' => 'entryPointFormKeys',
            'form' => 'entryPointForm',
            'description' => "Indicates a target EntryPoint for an Action.",
          ],
          'expectsAcceptanceOf' => [
            'class' => 'SchemaOfferBase',
            'formKeys' => 'offerFormKeys',
            'form' => 'offerForm',
            'description' => "An Offer which must be accepted before the user can perform the Action. For example, the user may need to buy a movie before being able to watch it.",
          ],
        ];

      case 'OrganizeAction':
        return [
          'target' => [
            'class' => 'SchemaEntryPointBase',
            'formKeys' => 'entryPointFormKeys',
            'form' => 'entryPointForm',
            'description' => "Indicates a target EntryPoint for an Action.",
          ],
          'result' => [
            'class' => 'SchemaThingBase',
            'formKeys' => 'thingFormKeys',
            'form' => 'thingForm',
            'description' => "The result produced in the action. e.g. John wrote a book.",
          ],
        ];

      case 'InteractAction':
      case 'PlayAction':
        return [
          //'audience' => [
          //  'class' => '',
          //  'formKeys' => '',
          //  'form' => '',
          //  'description' => "An intended audience, i.e. a group for whom something was created",
          //],
          'event' => [
            'class' => 'SchemaEventBase',
            'formKeys' => 'eventFormKeys',
            'form' => 'eventForm',
            'description' => "Upcoming or past event associated with this place, organization, or action.",
          ],
        ];

      case 'SearchAction':
        return [
          'query' => [
            'class' => 'SchemaNameBase',
            'formKeys' => '',
            'form' => '',
            'description' => "The query used on this action, i.e. https://query.example.com/search?q={search_term_string}.",
          ],
          'query-input' => [
            'class' => 'SchemaNameBase',
            'formKeys' => '',
            'form' => '',
            'description' => "The placeholder for the query, i.e. required name=search_term_string.",
          ],
          'target' => [
            'class' => 'SchemaEntryPointBase',
            'formKeys' => 'entryPointFormKeys',
            'form' => 'entryPointForm',
            'description' => "Indicates a target EntryPoint for an Action.",
          ],
        ];

      case 'UpdateAction':
        return [
          'targetCollection' => [
            'class' => 'SchemaThingBase',
            'formKeys' => 'thingFormKeys',
            'form' => 'thingForm',
            'description' => "The collection target of the action.",
          ],
        ];

      case 'All':
        return [];

      // General properties that apply to all actions.
      case 'Other':
        return [
          'price' => [
            'class' => 'SchemaNameBase',
            'formKeys' => '',
            'form' => '',
            'description' => "The offer price of a product, or of a price component.",
          ],
          'buyer' => [
            'class' => 'SchemaPersonOrgBase',
            'formKeys' => 'personOrgFormKeys',
            'form' => 'personOrgForm',
            'description' => "The participant/person/organization that bought the object.",
          ],
          'seller' => [
            'class' => 'SchemaPersonOrgBase',
            'formKeys' => 'personOrgFormKeys',
            'form' => 'personOrgForm',
            'description' => "An entity which offers (sells / leases / lends / loans) the services / goods. A seller may also be a provider.",
          ],
          'recipient' => [
            'class' => 'SchemaPersonOrgBase',
            'formKeys' => 'personOrgFormKeys',
            'form' => 'personOrgForm',
            'description' => "The participant who is at the receiving end of the action.",
          ],
          'target' => [
            'class' => 'SchemaEntryPointBase',
            'formKeys' => 'entryPointFormKeys',
            'form' => 'entryPointForm',
            'description' => "Indicates a target EntryPoint for an Action.",
          ],
          'result' => [
            'class' => 'SchemaThingBase',
            'formKeys' => 'thingFormKeys',
            'form' => 'thingForm',
            'description' => "The result produced in the action. e.g. John wrote a book.",
          ],
          //'actionStatus' => [
          //  'class' => '',
          //  'formKeys' => '',
          //  'form' => '',
          //  'description' => 'Indicates the current disposition of the Action.',
          //],
          'agent' => [
            'class' => 'SchemaPersonOrgBase',
            'formKeys' => 'personOrgFormKeys',
            'form' => 'personOrgForm',
            'description' => "The direct performer or driver of the action (animate or inanimate). e.g. John wrote a book.",
          ],
          'instrument' => [
            'class' => 'SchemaThingBase',
            'formKeys' => 'thingFormKeys',
            'form' => 'thingForm',
            'description' => "The object that helped the agent perform the action. e.g. John wrote a book with a pen.",
          ],
          'participant' => [
            'class' => 'SchemaPersonOrgBase',
            'formKeys' => 'personOrgFormKeys',
            'form' => 'personOrgForm',
            'description' => "Other co-agents that participated in the action indirectly. e.g. John wrote a book with Steve.",
          ],
          'object' => [
            'class' => 'SchemaThingBase',
            'formKeys' => 'thingFormKeys',
            'form' => 'thingForm',
            'description' => "The object upon which the action is carried out, whose state is kept intact or changed. Also known as the semantic roles patient, affected or undergoer (which change their state) or theme (which doesn't). e.g. John read a book.",
          ],
          'error' => [
            'class' => 'SchemaThingBase',
            'formKeys' => 'thingFormKeys',
            'form' => 'thingForm',
            'description' => "For failed actions, more information on the cause of the failure.",
          ],
          'location' => [
            'class' => 'SchemaPlaceBase',
            'formKeys' => 'placeFormKeys',
            'form' => 'placeForm',
            'description' => "The location of for example where the event is happening, an organization is located, or where an action takes place.",
          ],
          'startTime' => [
            'class' => 'SchemaNameBase',
            'formKeys' => '',
            'form' => '',
            'description' => "The startTime of something. For a reserved event or service (e.g. FoodEstablishmentReservation), the time that it is expected to start. For actions that span a period of time, when the action was performed. e.g. John wrote a book from January to December.",
          ],
          'endTime' => [
            'class' => 'SchemaNameBase',
            'formKeys' => '',
            'form' => '',
            'description' => "The endTime of something. For a reserved event or service (e.g. FoodEstablishmentReservation), the time that it is expected to end. For actions that span a period of time, when the action was performed. e.g. John wrote a book from January to December.",
          ],
        ];

      default:
        return [];
    }
  }

}
