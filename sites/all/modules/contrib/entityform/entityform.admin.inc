<?php

/**
 * @file
 * Entityform editing UI.
 *
 * We make very little use of the EntityAPI interface for this - preferring instead to use
 * views. That offers more flexibility to change a UI that will, more often than not,
 * be end-user facing.
 */

/**
 * UI controller.
 */
class EntityformUIController extends EntityDefaultUIController {

  /**
   * Overrides hook_menu() defaults. Main reason for doing this is that
   * parent class hook_menu() is optimized for entity type administration.
   */
  public function hook_menu() {
    //$entityform_base_path = variable_get('entityform_base_path', $)
    $items = array();
    $id_count = count(explode('/', $this->path));
    $front_id_count = count(explode('/', $this->entityInfo['admin ui']["front path"]));
    $wildcard = isset($this->entityInfo['admin ui']['menu wildcard']) ? $this->entityInfo['admin ui']['menu wildcard'] : '%' . $this->entityType;

    $items[$this->path] = array(
      'title' => 'Entityforms',
      'description' => 'Add edit and update entityforms.',
      'page callback' => 'system_admin_menu_block_page',
      'access arguments' => array('access administration pages'),
      'file path' => drupal_get_path('module', 'system'),
      'file' => 'system.admin.inc',
    );

    // Change the overview menu type for the list of entityforms.
    $items[$this->path]['type'] = MENU_LOCAL_TASK;
    $items['eform/submit/%entityform_empty'] = array(
      'title callback' => 'entityform_page_title',
      'title arguments' => array(2, 1),
      'page callback' => 'entityform_form_wrapper',
      'page arguments' => array(2, 'submit'),
      'access callback' => 'entityform_access',
      'access arguments' => array('submit', 2),
      'file' => 'entityform.admin.inc',
      'file path' => drupal_get_path('module', $this->entityInfo['module']),
      'type' => MENU_CALLBACK,
    );
    $items['eform/%entityform_type/confirm'] = array(
      'title callback' => 'entityform_type_page_title',
      'title arguments' => array(1, 'confirm'),
      'description' => '',
      'page callback' => 'entityform_confirm_page',
      'page arguments' => array(1, 3),
      'access callback' => 'entityform_access',
      'access arguments' => array('confirm', 1),
    );

    $items['eform/%entityform_type/draft'] = array(
      'title' => 'Form Saved',
      'description' => '',
      'page callback' => 'entityform_draft_page',
      'page arguments' => array(1),
      'access callback' => TRUE,
      'access arguments' => array('draft_save', 1),
    );
    $items['eform/%entityform_type/submissions'] = array(
        'title callback' => 'entityform_type_page_title',
        'title arguments' => array(1, 'view submissions'),
        'description' => '',
        'page callback' => 'entityform_submission_page',
        'page arguments' => array(1, 3, 'user'),
        'access arguments' => array('view own entityform'),
    );
    $types = array_keys(entityform_get_types());
    $controller = entity_ui_controller('entityform');

    // Loading and editing entityform entities
    $items['entityform/' . $wildcard] = array(
      'page callback' => 'entityform_form_wrapper',
      'page arguments' => array($id_count + 1),
      'access callback' => 'entityform_access',
      'access arguments' => array('edit', $id_count + 1),
      'weight' => 0,
      'context' => MENU_CONTEXT_PAGE | MENU_CONTEXT_INLINE,
      'file' => 'entityform.admin.inc',
      'file path' => drupal_get_path('module', $this->entityInfo['module']),
    );
    $items['entityform/' . $wildcard . '/edit'] = array(
      'title' => 'Edit',
      'page callback' => 'entityform_form_wrapper',
      'page arguments' => array(1, 'edit'),
      'access callback' => 'entityform_access',
      'access arguments' => array('edit', 1),
      'weight' => 0,
      'type' => MENU_LOCAL_TASK,
      'context' => MENU_CONTEXT_PAGE | MENU_CONTEXT_INLINE,
      'file' => 'entityform.admin.inc',
      'file path' => drupal_get_path('module', $this->entityInfo['module']),
    );

    $items['entityform/' . $wildcard . '/delete'] = array(
      'title' => 'Delete',
      'page callback' => 'entityform_delete_form_wrapper',
      'page arguments' => array(1),
      'access callback' => 'entityform_access',
      'access arguments' => array('delete', 1),
      'type' => MENU_LOCAL_TASK,
      'context' => MENU_CONTEXT_PAGE | MENU_CONTEXT_INLINE,
      'weight' => 10,
      'file' => 'entityform.admin.inc',
      'file path' => drupal_get_path('module', $this->entityInfo['module']),
    );

    // Menu item for viewing entityforms
    $items['entityform/' . $wildcard] = array(
      //'title' => 'Title',
      'title callback' => 'entityform_page_title',
      'title arguments' => array(1),
      'page callback' => 'entityform_page_view',
      'page arguments' => array(1),
      'access callback' => 'entityform_access',
      'access arguments' => array('view', 1),
      'type' => MENU_CALLBACK,
    );
    $items['entityform/' . $wildcard . '/view']  = array(
      'title' => 'View',
      'type' => MENU_DEFAULT_LOCAL_TASK,
      'weight' => -10,
    );
    return $items;
  }


  /**
   * Create the markup for the add Entityform Entities page within the class
   * so it can easily be extended/overriden.
   */
  public function addPage() {
    $item = menu_get_item();
    $content = system_admin_menu_block($item);

    if (count($content) == 1) {
      $item = array_shift($content);
      drupal_goto($item['href']);
    }

    return theme('entityform_add_list', array('content' => $content));
  }
  public function confirm_path($bundle, $entityform_id = NULL) {
    module_load_include('inc', 'entityform', 'entityform_type.admin');
    $entityform_type = entityform_type_load($bundle);
    if (!empty($entityform_id)) {
      $redirect_path = $entityform_type->get_redirect_path(entityform_load($entityform_id));
    }
    else {
      $redirect_path = $entityform_type->get_redirect_path();
    }
    if (!empty($redirect_path)) {
      return $redirect_path;
    }
    $path = _entityform_type_get_confirm_url($bundle);
    $path = drupal_get_path_alias($path);
    if ($entityform_id) {
      return array(
        $path,
        array(
          'query' => array(
            'entityform_id' => $entityform_id,
          ),
        ),
      );
    }
    return array($path);
  }
  public function draft_path($bundle) {
    $bundle = str_replace('_', '-', $bundle);
    return "eform/$bundle/draft";
  }
  public function submit_path($bundle) {
    module_load_include('inc', 'entityform', 'entityform_type.admin');
    $path = _entityform_type_get_submit_url($bundle);
    $path = drupal_get_path_alias($path);
    return $path;
  }


}


/**
 * Form callback wrapper: create or edit a entityform.
 *
 * @param $entityform
 *   The entityform object being edited by this form.
 * @param $mode
 * 	 Current mode for this form
 * 	 Submit - user is submitting the form
 * 	 Edit - user with permission is editingform
 * @param $form_context
 *   How is form being used shown?
 *     'page' - on submit page
 *     'embedded' - called form EntityformTypeController->view()
 * @see entityform_edit_form()
 */
function entityform_form_wrapper($entityform, $mode = 'submit', $form_context = 'page') {
  $make_form = TRUE;
  $entityform_type = entityform_type_load($entityform->type);
  if ($form_context == 'page') {
    drupal_set_title($entityform_type->label);
  }
  if (!empty($entityform->is_new)) {
    $draft = FALSE;
    if ($entityform_type->data['draftable']) {
      $draft = entityform_user_draft($entityform->type);
      if (!empty($draft)) {
        $entityform = $draft;
      }
    }
    if (!$draft) {
      // @todo add logic or other resubmit action
      $old_submission = entityform_user_previous_submission($entityform_type->type);
      if ($old_submission) {
        switch ($entityform_type->data['resubmit_action']) {
          case 'old':
             $entityform = $old_submission;
            break;
          case 'confirm':
            $confirm_path = entity_ui_controller('entityform')->confirm_path($old_submission->type, $old_submission->entityform_id);
            drupal_goto($confirm_path[0], $confirm_path[1]);
            break;
          case 'disallow':
            // @TODO how should this be handled
            drupal_set_message(t('You have already submitted this form.'));
            $make_form = FALSE;
            break;
        }
      }
    }
  }
  $output = array();
  if ($mode == 'submit' && (user_access('view own entityform')|| user_access('view any entityform'))) {
    //Only show link if this user has submission that will show up in the selected View
    if (!empty($entityform_type->data['user_submissions_view'])) {
      $results = views_get_view_result($entityform_type->data['user_submissions_view'], NULL, $entityform_type->type);
      if (!empty($results)) {
        $output['submissions_link'] = array(
          '#type' => 'markup',
          '#markup' => "<div class='submissions-link' >" . l(t('View your previous submissions'), "eform/{$entityform_type->type}/submissions") . "</div>",
          '#weight' => -100,
        );
      }
    }
  }
  // Instructions get printed even if form is not created
  if (!empty($entityform_type->data['instruction_pre'])) {
    $output['intro'] = array(
        '#type' => 'markup',
        '#markup' => "<div class='pre-intructions' >" . _entityform_format_text($entityform_type->data['instruction_pre'], array('entityform_type' => $entityform_type)) . "</div>",
        '#weight' => -100,
    );
  }
  if ($make_form) {
    $rule_returns = entityform_invoke_rules($entityform, 'access_rules');
    if (_entityform_rules_all_pass($rule_returns)) {
      $form = drupal_get_form($entityform->type . '_entityform_edit_form', $entityform, $mode);
      $form['#attributes']['class'][] = 'entityform';
      if (!empty($entityform->type)) {
        $form['#attributes']['class'][] = 'entitytype-'. $entityform->type . '-form';
      }
      $output += $form;
    }
  }
  if (user_access('administer entityform types')) {
    //Make contextual likes if user has access
    $contextual_links = array();
    $contextual_links['entityform_edit']= array(
      'admin/structure/entityform_types/manage',
      array($entityform_type->type),
    );
    $output['#contextual_links'] = $contextual_links;
  }
  return $output;
}


/**
 * Form callback wrapper: delete a entityform.
 *
 * @param $entityform
 *   The entityform object being edited by this form.
 *
 * @see entityform_edit_form()
 */
function entityform_delete_form_wrapper($entityform) {
  return drupal_get_form('entityform_delete_form', $entityform);
}


/**
 * Form callback: create or edit a entityform.
 *
 * @param $entityform
 *   The entityform object to edit or for a create form an empty entityform object
 *     with only a entityform type defined.
 */
function entityform_edit_form($form, &$form_state, $entityform, $mode = 'submit') {
  global $user;
  $entityform_type = entityform_type_load($entityform->type);
  // Add the field related form elements.
  $form_state['entityform'] = $entityform;
  $form_state['entityform_form_mode'] = $mode;
  field_attach_form('entityform', $entityform, $form, $form_state);

  $form['actions'] = array(
    '#type' => 'container',
    '#attributes' => array('class' => array('form-actions')),
    '#weight' => 400,
  );

  // We add the form's #submit array to this button along with the actual submit
  // handler to preserve any submit handlers added by a form callback_wrapper.
  $submit = array();

  if (!empty($form['#submit'])) {
    $submit += $form['#submit'];
  }
  if ($mode == 'submit') {
    //If form is draftable and user is logged in add draft button
    if ($entityform_type->data['draftable'] && !empty($user->uid)) {
      $form['actions']['save'] = array(
        '#type' => 'submit',
        '#value' => empty($entityform_type->data['draft_button_text'])?t('Save Draft'):t($entityform_type->data['draft_button_text']),
        //'#limit_validation_errors' => array(), //cause erros
        '#entityform_draft' => '1',
        '#submit' => $submit + array('entityform_edit_form_submit'),
      );
    }
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => empty($entityform_type->data['submit_button_text'])?t('Submit Form'):t($entityform_type->data['submit_button_text']),
      '#entityform_draft' => 0,
      '#submit' => $submit + array('entityform_edit_form_submit'),
    );
  }
  else {
    $form['user_info'] =  array(
            '#type' => 'markup',
            '#markup' => _entityform_get_submit_info($entityform),
            '#weight' => -200,
    );
    $form['actions']['save'] = array(
          '#type' => 'submit',
          '#value' => t('Save Changes'),
          '#entityform_draft' => '0',
          '#submit' => $submit + array('entityform_edit_form_submit'),
    );
  }
  if (!empty($entityform->name)) {
    $form['actions']['delete'] = array(
      '#type' => 'submit',
      '#value' => t('Delete entityform'),
      '#suffix' => l(t('Cancel'), 'admin/structure/entityforms'),
      '#submit' => $submit + array('entityform_form_submit_delete'),
      '#weight' => 45,
    );
  }

  // We append the validate handler to #validate in case a form callback_wrapper
  // is used to add validate handlers earlier.
  $form['#validate'][] = 'entityform_edit_form_validate';
  // Make sure the proper files are loaded if they is form is being called embedded with an ajax callback
  // This happens in image field upload for instance.
  $form['#after_build'][] = 'entityform_load_required_entityform_admin';
  $form['#after_build'][] = 'entityform_clear_required';
  $form['#validate'][] = 'entityform_set_required';
  return $form;
}
/**
 * Called after_build on entityforms
 * This makes sure that required fields aren't require when saving a Draft.
 * A Draft is not complete so shouldn't enforce required fields
 * @param array $form
 * @param array $form_state
 * @return array
 */
function entityform_clear_required($form, &$form_state) {
  if (!empty($form_state['input']) && isset($form_state['values']['save']) && count($form_state['input']) > 1 && isset($form_state['input']['op']) && $form_state['values']['save'] == $form_state['input']['op']  ) {
    _entityform_set_all_nested_props($form, array('#required' => FALSE));
  }
  return $form;
}
/**
 * @param array $form
 * @param array $props
 *   properties to set
 * @param boolean $must_match
 */
function _entityform_set_all_nested_props(&$form, $props, $must_match = FALSE) {
  $matched = TRUE;
  if ($must_match) {
    $matched = isset($form['#entityform_required']);
  }
  if ($matched) {
    foreach ($props as $key => $value) {
      if (isset($form[$key]) && $form[$key] != $value) {
        $form[$key] = $value;
        if (!$must_match) {
          $form['#entityform_required'] = TRUE;
        }
        else {
          unset($form['#entityform_required']);
        }
      }
    }
  }

  foreach (element_children($form) as $key) {
    if (is_array($form[$key])) {
      _entityform_set_all_nested_props($form[$key], $props, $must_match);
    }
  }
}
/*
 * Set form elements back to required
 */
function entityform_set_required(&$form, &$form_state) {
  _entityform_set_all_nested_props($form, array('#required' => TRUE), TRUE);
}
/**
 * Form API validate callback for the entityform form
 */
function entityform_edit_form_validate(&$form, &$form_state) {
  $entityform = $form_state['entityform'];

  // Notify field widgets to validate their data.
  field_attach_form_validate('entityform', $entityform, $form, $form_state);
  $errors = form_get_errors();
  // If fields validate then run Rules validation.
  if (empty($errors)) {
      $rule_returns = entityform_invoke_rules($entityform, 'validation_rules');
      if (!_entityform_rules_all_pass($rule_returns)) {
        form_error($form['actions']);
      }
  }
}


/**
 * Form API submit callback for the entityform form.
 *
 * @todo remove hard-coded link
 */
function entityform_edit_form_submit(&$form, &$form_state) {

  $entityform = entity_ui_controller('entityform')->entityFormSubmitBuildEntity($form, $form_state);
  $entityform->draft = $form_state['clicked_button']['#entityform_draft'];
  // Save the entityform and go back to the list of entityforms
  $entityform_type = entityform_type_load($entityform->type);
  // Add in created and changed times.
  if ($entityform->is_new = isset($entityform->is_new) ? $entityform->is_new : 0) {
    global $user;
    $entityform->created = time();

    $entityform->uid = !empty($user->uid) ? $user->uid : 0;
  }

  $entityform->changed = time();

  $entityform->save();
  if ($form_state['entityform_form_mode'] == 'submit') {
    if (empty($entityform->draft)) {
      $redirect_path = $entityform_type->get_redirect_path($entityform);
      if (!empty($redirect_path) ) {
        $form_state['redirect'] = $redirect_path;
      }
      else {
        global $user;
        if (empty($user->uid)) {
          //for anonymous users we must store the id of their submussion in the session
          drupal_session_start();
          $_SESSION['entityform_submission'] = $entityform->entityform_id;
        }
        $confirm_path = entity_ui_controller('entityform')->confirm_path($entityform->type, $entityform->entityform_id);
        $form_state['redirect'] = array($confirm_path[0], $confirm_path[1]);
      }
      drupal_set_message(t('Your submission has been saved.'));
    }
    else {
      $form_state['redirect'] = entity_ui_controller('entityform')->draft_path($entityform->type);
      drupal_set_message(t('Your draft submission has been saved.'));
    }
  }
  else {
    $info = entity_get_info('entityform_type');
    $form_state['redirect'] = $info['admin ui']['path'] . "/manage/{$entityform_type->type}/submissions";
    drupal_set_message(t('The submission has been saved.'));
  }
}

/**
 * Form API submit callback for the delete button.
 *
 * @todo Remove hard-coded path
 */
function entityform_form_submit_delete(&$form, &$form_state) {
  $form_state['redirect'] = 'admin/structure/entityforms/entityform/' . $form_state['entityform']->entityform_id . '/delete';
}


/**
 * Form callback: confirmation form for deleting a entityform.
 *
 * @param $entityform
 *   The entityform to delete
 *
 * @see confirm_form()
 */
function entityform_delete_form($form, &$form_state, $entityform) {
  $form_state['entityform'] = $entityform;

  $form['#submit'][] = 'entityform_delete_form_submit';

  $form = confirm_form($form,
  t('Are you sure you want to delete this Submission for %type?', array('%type' => $entityform->getTypeName())),
    "admin/structure/entityform_types/manage/{$entityform->type}/submissions",
    '<p>' . t('This action cannot be undone.') . '</p>',
  t('Delete'),
  t('Cancel'),
    'confirm'
  );

  return $form;
}

/**
 * Submit callback for entityform_delete_form
 */
function entityform_delete_form_submit($form, &$form_state) {
  $entityform = $form_state['entityform'];

  entityform_delete($entityform);

  drupal_set_message(t('The Submission for %name has been deleted.', array('%name' => $entityform->getTypeName())));
  watchdog('entityform', 'Deleted Submission for %name.', array('%name' => $entityform->getTypeName()));

  $form_state['redirect'] = "admin/structure/entityform_types/manage/{$entityform->type}/submissions";
}



/**
 * Page to add Entityform Entities.
 *
 * @todo Pass this through a proper theme function
 */
function entityform_add_page() {
  $controller = entity_ui_controller('entityform');
  return $controller->addPage();
}


/**
 * Displays the list of available entityform types for entityform creation.
 *
 * @ingroup themeable
 */
function theme_entityform_add_list($variables) {
  $content = $variables['content'];
  $output = '';
  if ($content) {
    $output = '<dl class="entityform-type-list">';
    foreach ($content as $item) {
      $output .= '<dt>' . l($item['title'], $item['href']) . '</dt>';
      $output .= '<dd>' . filter_xss_admin($item['description']) . '</dd>';
    }
    $output .= '</dl>';
  }
  else {
    if (user_access('administer entityform types')) {
      $output = '<p>' . t('Entityform Entities cannot be added because you have not created any entityform types yet. Go to the <a href="@create-entityform-type">entityform type creation page</a> to add a new entityform type.', array('@create-entityform-type' => url('admin/structure/entityform_types/add'))) . '</p>';
    }
    else {
      $output = '<p>' . t('No entityform types have been created yet for you to use.') . '</p>';
    }
  }

  return $output;
}

