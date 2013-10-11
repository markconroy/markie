<?php

/* Nukes the css from drupal core */
function mothership_css_alter(&$css) {
  $mothership_csscore_path = drupal_get_path('theme', 'mothership') . '/css-drupalcore/';
  $mothership_cssmodules_path = drupal_get_path('theme', 'mothership') . '/css-modules/';


  //book
	if( theme_get_setting('mothership_css_nuke_book') AND module_exists('book') ) {

	  $css = drupal_add_css($mothership_csscore_path . 'book.admin.css', array('group' => CSS_SYSTEM));
	  $css = drupal_add_css($mothership_csscore_path . 'book.theme.css', array('group' => CSS_SYSTEM));
	  foreach ($css as $file => $value) {
	    if (strpos($file, 'book.css') !== FALSE) {
	      unset($css[$file]);
	    }
	  }

	}

  //theme.css
  if(theme_get_setting('mothership_css_nuke_theme')){
    foreach ($css as $file => $value) {
      //first check those css files we DONT wanna remove
      if (
        //these exits in the css-core shift em to the themes version
        strpos($file, 'toolbar.theme.css') == FALSE AND
        strpos($file, 'shortcut.theme.css') == FALSE
      ) {
        if (strpos($file, '.theme.css') !== FALSE) {
          unset($css[$file]);
        }
      }
    }
  }

  //admin.css
  if(theme_get_setting('mothership_css_nuke_admin')){
    foreach ($css as $file => $value) {
      if (strpos($file, '.admin.css') !== FALSE) {
        unset($css[$file]);
      }
    }
  }

  //modules: contrib module
  if(theme_get_setting('mothership_css_nuke_module_contrib')){
    foreach ($css as $file => $value) {
      //first check those css files we DONT wanna remove
      if (
        strpos($file, '.base.css') == FALSE
      ){
        if (strpos($file, '/modules/') !== FALSE) {
          unset($css[$file]);
        }
      }
    }
  }

  //modules: all modules
  if(theme_get_setting('mothership_css_nuke_module_all')){
    foreach ($css as $file => $value) {
      //first check those css files we DONT wanna remove
      if (
        strpos($file, 'admin.') == FALSE AND
        strpos($file, 'admin_menu') == FALSE AND
        strpos($file, '.base.css') == FALSE AND
        strpos($file, 'contextual.css') == FALSE AND
        strpos($file, 'overlay-parent.css') == FALSE AND
        strpos($file, 'overlay-child.css') == FALSE AND
        strpos($file, 'system.base.css') == FALSE AND
        strpos($file, 'system.messages.css') == FALSE AND
        strpos($file, 'system.menus.css') == FALSE AND
        strpos($file, 'system.theme.css') == FALSE AND
        strpos($file, 'shortcut.css') == FALSE AND
        strpos($file, 'toolbar.css') == FALSE

      ) {
        if (strpos($file, 'modules/') !== FALSE) {
          unset($css[$file]);
        }
      }
    }
  }

  //system message folder
  if(theme_get_setting('mothership_css_nuke_systemtoolbar')){
    foreach ($css as $file => $value) {
      if (
        strpos($file, 'shortcut.css') !== FALSE OR
        strpos($file, 'toolbar.css') !== FALSE
      ) {
        unset($css[$file]);
      }
    }
  }

  //system message folder
  if(theme_get_setting('mothership_css_nuke_system_message')){
    foreach ($css as $file => $value) {
      if (strpos($file, 'system.messages.css') !== FALSE) {
        unset($css[$file]);
      }
    }
  }

  //system menu
  if(theme_get_setting('mothership_css_nuke_system_menus')){
    foreach ($css as $file => $value) {
      if (strpos($file, 'system.menus.css') !== FALSE) {
        unset($css[$file]);
      }
    }
  }
  //system theme
  if(theme_get_setting('mothership_css_nuke_system_theme')){
    foreach ($css as $file => $value) {
      if (strpos($file, 'system.theme.css') !== FALSE) {
        unset($css[$file]);
      }
    }
  }


  //vertical tabs
  if(theme_get_setting('mothership_css_nuke_vertical')){
    foreach ($css as $file => $value) {
      if (strpos($file, 'vertical-tabs.css') !== FALSE) {
        unset($css[$file]);
      }
    }
  }

  //freeform css class killing :)
  $css_kill_list = explode("\n", theme_get_setting('mothership_css_freeform'));

  //grap the css and run through em
  if(theme_get_setting('mothership_css_freeform')){
    foreach ($css as $file => $value) {
      //grap the kill list and do that on each file
      foreach ($css_kill_list as $key => $cssfilemustdie) {
        if (strpos($file, $cssfilemustdie) !== FALSE) {
         unset($css[$file]);
        }
      }
    }
  }


  //Here we go smack all css files into 1 (one) - we like less http request
  if(theme_get_setting('mothership_css_onefile')){
    $theme = drupal_get_path('theme', 'mothership');
    //its all screen honey all screen
    foreach ($css as $path => $value) {
      if ($css[$path]['media'] == 'all') {
        $css[$path]['media'] = 'screen';
      }
    }

    //grap the css and punch it into one file
    //credits to metaltoad http://www.metaltoad.com/blog/drupal-7-taking-control-css-and-js-aggregation
    uasort($css, 'drupal_sort_css_js');
    $i = 0;
    foreach ($css as $name => $style) {
      $css[$name]['weight'] = $i++;
      $css[$name]['group'] = CSS_DEFAULT;
      $css[$name]['every_page'] = FALSE;
    }
  }

}

