<?php

/*
template_preprocess_views_view
options to remove css classes from the view
*/
function mothership_preprocess_views_view(&$vars){
//    kpr($vars['classes_array']);

  if (theme_get_setting('mothership_classes_view')) {
    $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('view')));
  }
  if (theme_get_setting('mothership_classes_view_name')) {
    $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('view-'.$vars['name'])));
  }
  if (theme_get_setting('mothership_classes_view_view_id')) {
    $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('view-id-'.$vars['name'])));
    $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('view-display-id-'.$vars['display_id'])));
  }
}

/*removes the classes from the list*/
function mothership_preprocess_views_view_list(&$vars){
  //we need to go down to the unformatted preprocess to rip out the individual classes
  mothership_preprocess_views_view_unformatted($vars);
}

/*
  views list css classes
  options for renaming classes & removing em
*/
function mothership_preprocess_views_view_unformatted(&$vars) {
  //renaming classes
  if(theme_get_setting('mothership_classes_view_row_rename')){
    $row_first = "first";
    $row_last  = "last";
    $row_count = "count-";
  }else{
    $row_first = "views-row-first";
    $row_last  = "views-row-last";
    $row_count = "views-row-";
  }

  $view = $vars['view'];
  $rows = $vars['rows'];


  $vars['classes_array'] = array();
  $vars['classes'] = array();
  // Set up striping values.
  $count = 0;
  $max = count($rows);
  foreach ($rows as $id => $row) {
    $count++;

    if (!theme_get_setting('mothership_classes_view_row')) {
      $vars['classes'][$id][] = 'views-row';
    }
    if (!theme_get_setting('mothership_classes_view_row_count')) {
      $vars['classes'][$id][] = $row_count . $count;
      if(theme_get_setting('mothership_classes_view_row_rename')){
        $vars['classes'][$id][] =  '' . ($count % 2 ? 'odd' : 'even');
      }else{
        $vars['classes'][$id][] = $row_count . ($count % 2 ? 'odd' : 'even');
      }
    }
    if (!theme_get_setting('mothership_classes_view_row_first_last')) {
      if ($count == 1) {
        $vars['classes'][$id][] = $row_first;
      }
      if ($count == $max) {
        $vars['classes'][$id][] = $row_last;
      }
    }


    if ($row_class = $view->style_plugin->get_row_class($id)) {
      $vars['classes'][$id][] = $row_class;
    }

   // $vars['classes'][$id][] = '';
    if ( $vars['classes']  && array_key_exists($id, $vars['classes']) ){
      $vars['classes_array'][$id] = implode(' ', $vars['classes'][$id]);
    } else {
      $vars['classes_array'][$id] = '';
    }

    // Flatten the classes to a string for each row for the template file.
   // $vars['classes_array'][$id] = implode(' ', $vars['classes'][$id]);

  }

}

/*
function mothership_preprocess_views_view_field(&$vars) {
 // kpr($vars);
 $vars['output'] = $vars['field']->advanced_render($vars['row']);
}
*/

