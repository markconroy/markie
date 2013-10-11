<?php
/*
  Kills off the <div class="panel-seperator"> annoince
*/
function mothership_panels_default_style_render_region(&$vars) {
  if(theme_get_setting('mothership_panels_seperator')){
    $output = '';
    $output .= implode('', $vars['panes']);
    return $output;
  }
}


function mothership_preprocess_panels_pane(&$vars) {
  /*
    Grap all the menus & navigation from the blocks
    and give em a template suggestion panels-pane--menu
   */
  if ($vars['pane']->type == "block") {
    //check on menu
    if (strstr($vars['pane']->subtype, 'menu')) {
      $vars['theme_hook_suggestions'][] = 'panels_pane__menu';
    }

    if($vars['pane']->subtype == "system-management"){
      $vars['theme_hook_suggestions'][] = 'panels_pane__menu';
    }

    if($vars['pane']->subtype == "system-navigation"){
      $vars['theme_hook_suggestions'][] = 'panels_pane__menu';
    }

    //use the section template
    if($vars['pane']->subtype == "user-new" OR
       $vars['pane']->subtype == "user-online" OR
       $vars['pane']->subtype == "comment-recent"
    ){
      $vars['theme_hook_suggestions'][] = 'panels_pane__section';
    }

    //$vars['theme_hook_suggestions'][] = 'panels_pane'.strtolower(str_replace('-', '_', $vars['pane']->subtype));
  }

  //primary / secondary links
  if($vars['pane']->type == "page_primary_links" OR
    $vars['pane']->type == "page_secondary_links"
  ){
    $vars['theme_hook_suggestions'][] = 'panels_pane__menu';
  }

  if($vars['pane']->type == "page_content" AND $vars['pane']->subtype == "page_content"){
  //  $vars['theme_hook_suggestions'][] = 'panels_pane__title';
  }


  //kpr($vars['theme_hook_suggestions']);
  //kpr('type: ' . $vars['pane']->type);
  //kpr('subtype: ' . $vars['pane']->subtype);
}



