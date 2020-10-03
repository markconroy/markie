<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org Action trait.
 */
trait SchemaActionTrait {

  use SchemaPersonOrgTrait, SchemaOfferTrait, SchemaThingTrait, SchemaPlaceTrait, SchemaEventTrait, SchemaEntryPointTrait, SchemaPivotTrait {
    SchemaPlaceTrait::placeForm insteadof SchemaEventTrait;
    SchemaPlaceTrait::postalAddressForm insteadof SchemaEventTrait;
    SchemaPlaceTrait::geoForm insteadof SchemaEventTrait;
    SchemaPivotTrait::pivotForm insteadof SchemaPersonOrgTrait;
    SchemaPivotTrait::pivotForm insteadof SchemaOfferTrait;
    SchemaPivotTrait::pivotForm insteadof SchemaThingTrait;
    SchemaPivotTrait::pivotForm insteadof SchemaPlaceTrait;
    SchemaPivotTrait::pivotForm insteadof SchemaEventTrait;
    SchemaPivotTrait::pivotForm insteadof SchemaEntryPointTrait;
  }

  /**
   * Return the SchemaMetatagManager.
   *
   * @return \Drupal\schema_metatag\SchemaMetatagManager
   *   The Schema Metatag Manager service.
   */
  abstract protected function schemaMetatagManager();

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

    $all_types = static::getAllActions(TRUE);
    $all_options = array_combine($all_types, $all_types);

    $input_values += $this->SchemaMetatagManager()->defaultInputValues();
    $value = $input_values['value'];

    $form['#type'] = 'fieldset';
    $form['#title'] = $input_values['title'];
    $form['#description'] = $input_values['description'];
    $form['#tree'] = TRUE;

    // The assumption is that the list will be limited to specific actions
    // by the values passed in here. Missing information means display nothing.
    $actions = $input_values['actions'];
    if (empty($actions)) {
      return [];
    }
    // Get the id for the nested @type element.
    $selector = ':input[name="' . $input_values['visibility_selector'] . '[@type]"]';
    $visibility = ['invisible' => [$selector => ['value' => '']]];
    $selector2 = $this->SchemaMetatagManager()->altSelector($selector);
    $visibility2 = ['invisible' => [$selector2 => ['value' => '']]];
    $visibility['invisible'] = [$visibility['invisible'], $visibility2['invisible']];

    $form['#type'] = 'fieldset';
    $form['#title'] = $input_values['title'];
    $form['#description'] = $input_values['description'];
    $form['#tree'] = TRUE;

    // Add a pivot option to the form.
    $form['pivot'] = $this->pivotForm($value);
    $form['pivot']['#states'] = $visibility;

    $options = [];
    foreach ($all_options as $type => $label) {
      if (in_array($type, $actions)) {
        $options[$type] = $all_options[$type];
      }
    }
    $form['@type'] = [
      '#type' => 'select',
      '#title' => $this->t('@type'),
      '#default_value' => !empty($value['@type']) ? $value['@type'] : '',
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => $options,
      '#required' => $input_values['#required'],
      '#weight' => -10,
    ];

    // Build the form one object type at a time, using the visibility settings
    // to hide/show only form elements for the selected type.
    foreach ($options as $type => $label) {

      // Properties specific to an action type appear only for that type.
      // Weight these properties ahead of general action properties.
      $parent_type = static::getActionType($type);
      $properties = static::actionProperties($parent_type);
      foreach ($properties as $key => $property) {

        if (empty($property['form'])) {
          $form[$key] = [
            '#type' => 'textfield',
            '#title' => $key,
            '#default_value' => !empty($value[$key]) ? $value[$key] : '',
            '#empty_option' => t('- None -'),
            '#empty_value' => '',
            '#required' => $input_values['#required'],
            '#description' => $property['description'],
            '#states' => $visibility,
            '#weight' => 0,
          ];
        }
        else {
          $sub_values = [
            'title' => $key,
            'description' => $property['description'],
            'value' => !empty($value[$key]) ? $value[$key] : [],
            '#required' => $input_values['#required'],
            'visibility_selector' => $input_values['visibility_selector'] . '[' . $key . ']',
          ];
          $method = $property['form'];
          $form[$key] = self::$method($sub_values);
          $form[$key]['#states'] = $visibility;
          $form[$key]['#weight'] = 0;
        }
      }
    }

    // Properties common to all actions appear for any action type.
    // Weight these after the action-specific properties.
    $properties = static::actionProperties('All');
    foreach ($properties as $key => $property) {

      if (empty($property['form'])) {
        $form[$key] = [
          '#type' => 'textfield',
          '#title' => $key,
          '#default_value' => !empty($value[$key]) ? $value[$key] : '',
          '#empty_option' => t('- None -'),
          '#empty_value' => '',
          '#required' => $input_values['#required'],
          '#description' => $property['description'],
          '#states' => $visibility,
          '#weight' => 5,
        ];
      }
      else {
        $sub_values = [
          'title' => $key,
          'description' => $property['description'],
          'value' => !empty($value[$key]) ? $value[$key] : [],
          '#required' => $input_values['#required'],
          'visibility_selector' => $input_values['visibility_selector'] . '[' . $key . ']',
        ];
        $method = $property['form'];
        $form[$key] = self::$method($sub_values);
        $form[$key]['#states'] = $visibility;
        $form[$key]['#weight'] = 5;
      }
    }

    return $form;

  }

  /**
   * Get an array of all actions, grouped by action type.
   *
   * @param string $flattened
   *   Set TRUE to return a single-level array of values.
   *
   * @return array
   *   An array of all actions. If not $flattened, the list is grouped by type.
   */
  public static function getAllActions($flattened = FALSE) {
    $list = $flattened ? ['Action'] : [];
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
            'form' => 'placeForm',
            'description' => "A sub property of location. The original location of the object or the agent before the action.",
          ],
          'toLocation' => [
            'class' => 'SchemaPlaceBase',
            'form' => 'placeForm',
            'description' => "A sub property of location. The final location of the object or the agent after the action.",
          ],
        ];

      case 'TradeAction':
        return [
          'target' => [
            'class' => 'SchemaEntryPointBase',
            'form' => 'entryPointForm',
            'description' => "Indicates a target EntryPoint for an Action.",
          ],
        ];

      case 'ConsumeAction':
        return [
          'target' => [
            'class' => 'SchemaEntryPointBase',
            'form' => 'entryPointForm',
            'description' => "Indicates a target EntryPoint for an Action.",
          ],
          'expectsAcceptanceOf' => [
            'class' => 'SchemaOfferBase',
            'form' => 'offerForm',
            'description' => "An Offer which must be accepted before the user can perform the Action. For example, the user may need to buy a movie before being able to watch it.",
          ],
        ];

      case 'OrganizeAction':
        return [
          'target' => [
            'class' => 'SchemaEntryPointBase',
            'form' => 'entryPointForm',
            'description' => "Indicates a target EntryPoint for an Action.",
          ],
          'result' => [
            'class' => 'SchemaThingBase',
            'form' => 'thingForm',
            'description' => "The result produced in the action. e.g. John wrote a book.",
          ],
        ];

      case 'InteractAction':
      case 'PlayAction':
        return [
          'event' => [
            'class' => 'SchemaEventBase',
            'form' => 'eventForm',
            'description' => "Upcoming or past event associated with this place, organization, or action.",
          ],
        ];

      case 'SearchAction':
        return [
          'query' => [
            'class' => 'SchemaNameBase',
            'form' => '',
            'description' => "The query used on this action, i.e. https://query.example.com/search?q={search_term_string}.",
          ],
          'query-input' => [
            'class' => 'SchemaNameBase',
            'form' => '',
            'description' => "The placeholder for the query, i.e. required name=search_term_string.",
          ],
          'target' => [
            'class' => 'SchemaEntryPointBase',
            'form' => 'entryPointForm',
            'description' => "Indicates a target EntryPoint for an Action.",
          ],
        ];

      case 'UpdateAction':
        return [
          'targetCollection' => [
            'class' => 'SchemaThingBase',
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
            'form' => '',
            'description' => "The offer price of a product, or of a price component.",
          ],
          'buyer' => [
            'class' => 'SchemaPersonOrgBase',
            'form' => 'personOrgForm',
            'description' => "The participant/person/organization that bought the object.",
          ],
          'seller' => [
            'class' => 'SchemaPersonOrgBase',
            'form' => 'personOrgForm',
            'description' => "An entity which offers (sells / leases / lends / loans) the services / goods. A seller may also be a provider.",
          ],
          'recipient' => [
            'class' => 'SchemaPersonOrgBase',
            'form' => 'personOrgForm',
            'description' => "The participant who is at the receiving end of the action.",
          ],
          'target' => [
            'class' => 'SchemaEntryPointBase',
            'form' => 'entryPointForm',
            'description' => "Indicates a target EntryPoint for an Action.",
          ],
          'result' => [
            'class' => 'SchemaThingBase',
            'form' => 'thingForm',
            'description' => "The result produced in the action. e.g. John wrote a book.",
          ],
          'agent' => [
            'class' => 'SchemaPersonOrgBase',
            'form' => 'personOrgForm',
            'description' => "The direct performer or driver of the action (animate or inanimate). e.g. John wrote a book.",
          ],
          'instrument' => [
            'class' => 'SchemaThingBase',
            'form' => 'thingForm',
            'description' => "The object that helped the agent perform the action. e.g. John wrote a book with a pen.",
          ],
          'participant' => [
            'class' => 'SchemaPersonOrgBase',
            'form' => 'personOrgForm',
            'description' => "Other co-agents that participated in the action indirectly. e.g. John wrote a book with Steve.",
          ],
          'object' => [
            'class' => 'SchemaThingBase',
            'form' => 'thingForm',
            'description' => "The object upon which the action is carried out, whose state is kept intact or changed. Also known as the semantic roles patient, affected or undergoer (which change their state) or theme (which doesn't). e.g. John read a book.",
          ],
          'error' => [
            'class' => 'SchemaThingBase',
            'form' => 'thingForm',
            'description' => "For failed actions, more information on the cause of the failure.",
          ],
          'location' => [
            'class' => 'SchemaPlaceBase',
            'form' => 'placeForm',
            'description' => "The location of for example where the event is happening, an organization is located, or where an action takes place.",
          ],
          'startTime' => [
            'class' => 'SchemaNameBase',
            'form' => '',
            'description' => "The startTime of something. For a reserved event or service (e.g. FoodEstablishmentReservation), the time that it is expected to start. For actions that span a period of time, when the action was performed. e.g. John wrote a book from January to December.",
          ],
          'endTime' => [
            'class' => 'SchemaNameBase',
            'form' => '',
            'description' => "The endTime of something. For a reserved event or service (e.g. FoodEstablishmentReservation), the time that it is expected to end. For actions that span a period of time, when the action was performed. e.g. John wrote a book from January to December.",
          ],
        ];

      default:
        return [];
    }
  }

}
