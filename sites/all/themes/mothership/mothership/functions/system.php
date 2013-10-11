<?php
/*
Removes the <strong>that wraps the <label> .. 
*/
function mothership_system_modules_fieldset($variables) {
  $form = $variables['form'];

  // Individual table headers.
  $rows = array();
  // Iterate through all the modules, which are
  // children of this fieldset.
  foreach (element_children($form) as $key) {
    // Stick it into $module for easier accessing.
    $module = $form[$key];
    $row = array();
    unset($module['enable']['#title']);
    $row[] = array('class' => array('checkbox'), 'data' => drupal_render($module['enable']));
    $label = '<label';
    if (isset($module['enable']['#id'])) {
      $label .= ' for="' . $module['enable']['#id'] . '"';
    }
    $row[] = $label . '>' . drupal_render($module['name']) . '</label>';
    $row[] = drupal_render($module['version']);
    // Add the description, along with any modules it requires.
    $description = drupal_render($module['description']);
    if ($module['#requires']) {
      $description .= '<div class="admin-requirements">' . t('Requires: !module-list', array('!module-list' => implode(', ', $module['#requires']))) . '</div>';
    }
    if ($module['#required_by']) {
      $description .= '<div class="admin-requirements">' . t('Required by: !module-list', array('!module-list' => implode(', ', $module['#required_by']))) . '</div>';
    }
    $row[] = array('data' => $description, 'class' => array('description'));
    // Display links (such as help or permissions) in their own columns.
    foreach (array('help', 'permissions', 'configure') as $key) {
      $row[] = array('data' => drupal_render($module['links'][$key]), 'class' => array($key));
    }
    $rows[] = $row;
  }

  return theme('table', array('header' => $form['#header'], 'rows' => $rows));
}

/*
Removes the <strong>that wraps the <label> ..
*/

function mothership_system_modules_uninstall($variables) {
  $form = $variables['form'];

  // No theming for the confirm form.
  if (isset($form['confirm'])) {
    return drupal_render($form);
  }

  // Table headers.
  $header = array(t('Uninstall'),
    t('Name'),
    t('Description'),
  );

  // Display table.
  $rows = array();
  foreach (element_children($form['modules']) as $module) {
    if (!empty($form['modules'][$module]['#required_by'])) {
      $disabled_message = format_plural(count($form['modules'][$module]['#required_by']),
        'To uninstall @module, the following module must be uninstalled first: @required_modules',
        'To uninstall @module, the following modules must be uninstalled first: @required_modules',
        array('@module' => $form['modules'][$module]['#module_name'], '@required_modules' => implode(', ', $form['modules'][$module]['#required_by'])));
      $disabled_message = '<div class="error warning admin-requirements">' . $disabled_message . '</div>';
    }
    else {
      $disabled_message = '';
    }
    $rows[] = array(
      array('data' => drupal_render($form['uninstall'][$module]), 'align' => 'center'),
      '<label for="' . $form['uninstall'][$module]['#id'] . '">' . drupal_render($form['modules'][$module]['name']) . '</label>',
      array('data' => drupal_render($form['modules'][$module]['description']) . $disabled_message, 'class' => array('description')),
    );
  }

  $output  = theme('table', array('header' => $header, 'rows' => $rows, 'empty' => t('No modules are available to uninstall.')));
  $output .= drupal_render_children($form);

  return $output;
}