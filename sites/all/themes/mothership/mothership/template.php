<?php
/**
 * include template overwrites
 */
$path_mothership = drupal_get_path('theme', 'mothership');

  include_once './' . $path_mothership . '/functions/css.php';
  include_once './' . $path_mothership . '/functions/js.php';
  include_once './' . $path_mothership . '/functions/icons.php';
  include_once './' . $path_mothership . '/functions/form.php';
  include_once './' . $path_mothership . '/functions/table.php';
  include_once './' . $path_mothership . '/functions/views.php';
  include_once './' . $path_mothership . '/functions/menu.php';
  include_once './' . $path_mothership . '/functions/system.php';
  include_once './' . $path_mothership . '/functions/date.php';
  include_once './' . $path_mothership . '/functions/misc.php';
  include_once './' . $path_mothership . '/functions/forum.php';
  include_once './' . $path_mothership . '/functions/blockify.php';
  include_once './' . $path_mothership . '/functions/panels.php';
//load in the login
if (theme_get_setting('mothership_goodies_login')) {
  include_once './' . $path_mothership . '/goodies/login.inc';
}

// Auto-rebuild the theme registry during theme development.
if (theme_get_setting('mothership_rebuild_registry')) {
  system_rebuild_theme_data();
}

/**
 * Implements HOOK_theme().
 */
function mothership_theme(){
  return array(
    'nomarkup' => array (
      'render element' => 'element',
       'function' => 'theme_nomarkup',
     ),
  );
}

/*
  all the preprocess magic
*/
function mothership_preprocess(&$vars, $hook) {
  global $theme;
  global $base_url;
  $path = drupal_get_path('theme', $theme);
  $path_mothership = drupal_get_path('theme', 'mothership');

  //http://api.drupal.org/api/drupal/includes--theme.inc/function/template_preprocess_html/7
  $vars['mothership_poorthemers_helper'] = "";
  //For third-generation iPad with high-resolution Retina display
  $appletouchicon = '<link rel="apple-touch-icon" sizes="144x144" href="' . $base_url .'/'. $path . '/apple-touch-icon-144x144.png">';
  //For iPhone with high-resolution Retina display
  $appletouchicon .= '<link rel="apple-touch-icon" sizes="114x114" href="' . $base_url .'/'. $path . '/apple-touch-icon-114x114.png">'. "\n";
  //For first- and second-generation iPad:
  $appletouchicon .= '<link rel="apple-touch-icon" sizes="72x72" href="' . $base_url .'/'.  $path . '/apple-touch-icon-72x72.png">' . "\n";
  //For non-Retina iPhone, iPod Touch, and Android 2.1+ devices
  $appletouchicon .=  '<link rel="apple-touch-icon" href="' . $base_url .'/'.  $path . '/apple-touch-icon.png">' . "\n";
  $appletouchicon .=  '<link rel="apple-touch-startup-image" href="' . $base_url .'/'.  $path . '/apple-startup.png">' . "\n";
  /*
    Go through all the hooks of drupal and give em epic love
  */

  if ( $hook == "html" ) {
    // =======================================| HTML |========================================

    //get the path for the site
    $vars['mothership_path'] = $base_url .'/'. $path_mothership;


    //lets make it a tiny bit more readable in the html.tpl.php
    //gets processed in mothership_process_html
    $vars['html_attributes_array'] = array(
      'lang' => $vars['language']->language,
      'dir' => $vars['language']->dir,
    );

    $metatags = array(
      '#tag' => 'meta',
      '#attributes' => array(
        'name' => 'Generator',
        'content' => 'Drupal Mothership',
      ),
    );
    drupal_add_html_head($metatags, 'my_meta');

    //custom 403/404
    $headers = drupal_get_http_header();
    if(theme_get_setting('mothership_404') AND isset($headers['status']) ){
      if($headers['status'] == '404 Not Found'){
        $vars['theme_hook_suggestions'][] = 'html__404';
      }
    }

    /*
    if(theme_get_setting('mothership_403')){
      if($headers['status'] == '403 Forbidden'){
        $vars['theme_hook_suggestions'][] = 'html__403';
      }
    }
    */

    /*
      Adds optional reset css files that the sub themes might wanna use.
      reset.css - eric meyer ftw
      reset-html5.css - html5doctor.com/html-5-reset-stylesheet/
      defaults.css cleans some of the defaults from drupal
      mothership.css - adds css for use with icons & other markup fixes
    */
    if (theme_get_setting('mothership_css_reset')) {
      drupal_add_css( $path_mothership . '/css/reset.css', array('group' => CSS_THEME, 'every_page' => TRUE, 'weight' => -20));
    }
    if (theme_get_setting('mothership_css_reset_html5')) {
      drupal_add_css( $path_mothership . '/css/reset-html5.css', array('group' => CSS_THEME, 'every_page' => TRUE, 'weight' => -20));
    }
    if (theme_get_setting('mothership_css_normalize')) {
      drupal_add_css( $path_mothership . '/css/normalize.css', array('group' => CSS_THEME, 'every_page' => TRUE, 'weight' => -20));
    }
    if (theme_get_setting('mothership_css_default')) {
      drupal_add_css( $path_mothership . '/css/mothership-default.css', array('group' => CSS_THEME, 'every_page' => TRUE, 'weight' => -15));
    }
    if (theme_get_setting('mothership_css_layout')) {
      drupal_add_css( $path_mothership . '/css/mothership-layout.css', array('group' => CSS_THEME, 'every_page' => TRUE, 'weight' => -14));
    }
    if (theme_get_setting('mothership_css_mothershipstyles')) {
      drupal_add_css( $path_mothership . '/css/mothership.css', array('group' => CSS_THEME, 'every_page' => TRUE, 'weight' => -10));
    }

    if (theme_get_setting('mothership_mediaquery_indicator')) {
      drupal_add_css( $path_mothership . '/css/mothership-devel-mediaqueries.css', array('group' => CSS_THEME, 'every_page' => TRUE, 'weight' => 0));
    }

    //LIBS
    //We dont wanna add modules just to put in a goddamn js file so were adding em here instead

    //--- modernizr love CDN style for the lazy ones
    if (theme_get_setting('mothership_modernizr')) {
      drupal_add_js('http://cdnjs.cloudflare.com/ajax/libs/modernizr/2.0.6/modernizr.min.js', 'external');
    }

    //---- selectivizr
    $vars['selectivizr'] = '';
    if(theme_get_setting('mothership_selectivizr')) {
      $vars['selectivizr'] .= '<!--[if (gte IE 6)&(lte IE 8)]>' . "\n";;
      $vars['selectivizr'] .= '<script type="text/javascript" src="http://cdnjs.cloudflare.com/ajax/libs/selectivizr/1.0.2/selectivizr-min.js"></script>' . "\n";;
      $vars['selectivizr'] .= '<![endif]-->' . "\n";;
    }

    //---html5 fix
    $vars['html5iefix'] = '';
    if(theme_get_setting('mothership_html5')) {
      $vars['html5iefix'] .= '<!--[if lt IE 9]>';
      $vars['html5iefix'] .= '<script src="' . $path_mothership . '/js/html5.js"></script>';
      $vars['html5iefix'] .= '<![endif]-->';
    }

    $vars['appletouchicon'] = $appletouchicon;

    //-----<body> CSS CLASSES  -----------------------------------------------------------------------------------------------
    //Remove & add cleasses body

    if (theme_get_setting('mothership_classes_body_html')) {
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('html')));
    }

    if (theme_get_setting('mothership_classes_body_front')) {
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('not-front')));
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('front')));
    }

    if (theme_get_setting('mothership_classes_body_loggedin')) {
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('logged-in')));
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('not-logged-in')));
    }

    if (theme_get_setting('mothership_classes_body_layout')) {
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('two-sidebars')));
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('one-sidebar sidebar-first')));
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('one-sidebar sidebar-second')));
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('no-sidebars')));
    }

    if (theme_get_setting('mothership_classes_body_toolbar')) {
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('toolbar')));
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('toolbar-drawer')));
    }

    if (theme_get_setting('mothership_classes_body_pagenode')) {
      $vars['classes_array'] = preg_grep('/^page-node/', $vars['classes_array'], PREG_GREP_INVERT);
    }

    if (theme_get_setting('mothership_classes_body_nodetype')) {
      $vars['classes_array'] = preg_grep('/^node-type/', $vars['classes_array'], PREG_GREP_INVERT);
    }

    if (theme_get_setting('mothership_classes_body_path')) {
      $path_all = drupal_get_path_alias($_GET['q']);
      $vars['classes_array'][] = drupal_html_class('path-' . $path_all);
    }

    if (theme_get_setting('mothership_classes_body_path_first')) {
      $path = explode('/', $_SERVER['REQUEST_URI']);
      if($path['1']){
        $vars['classes_array'][] = drupal_html_class('pathone-' . $path['1']);
      }
    }

    if (theme_get_setting('mothership_test')) {
      $vars['classes_array'][] = "test";
    }

    if(isset($headers['status']) AND theme_get_setting('mothership_classes_body_status') ){
      $vars['classes_array'][] = "status-". $headers['status'];
    }

    //freeform css class killing
    $remove_class_body = explode(", ", theme_get_setting('mothership_classes_body_freeform'));
    $vars['classes_array'] = array_values(array_diff($vars['classes_array'],$remove_class_body));

  }elseif ( $hook == "page" ) {
    // =======================================| PAGE |========================================

    //Test for expected modules - we really love blockify
    //TODO: should this be an option to remove annoing options?
    if (theme_get_setting('mothership_expectedmodules')) {
      //test to see if blockify is installed
      if(!module_exists('blockify')){
        print_r('Tema use the blockify module - so you can move the logo, title, taps where you wants to & makes the page.tpl easier to work with: <a href="http://drupal.org/project/blockify">Download</a>');
      }
    }

    //NEw template suggestions

    // page--nodetype.tpl.php
    if ( isset($vars['node']) ){
      $vars['theme_hook_suggestions'][] = 'page__' . $vars['node']->type;
    }

    //custom 404/404
    $headers = drupal_get_http_header();

    if (isset($headers['status'])) {
      if($headers['status'] == '404 Not Found'){
        $vars['theme_hook_suggestions'][] = 'page__404';
      }

    }

    //remove the "theres no content default yadi yadi" from the frontpage
    if(theme_get_setting('mothership_frontpage_default_message')){
      unset($vars['page']['content']['system_main']['default_message']);
    }

    // Remove the block template wrapper from the main content block.
    if (theme_get_setting('mothership_content_block_wrapper') AND
      !empty($vars['page']['content']['system_main']) AND
      isset($vars['page']['content']['system_main']['#theme_wrappers']) AND
      is_array($vars['page']['content']['system_main']['#theme_wrappers'])
    ) {
      $vars['page']['content']['system_main']['#theme_wrappers'] = array_diff($vars['page']['content']['system_main']['#theme_wrappers'], array('block'));
    }


    /*-
      USER ACCOUNT TABS
      Removes the tabs from user  login, register & password
      fixes the titles to so no more "user account" all over
    */
    if (theme_get_setting('mothership_goodies_login')) {
      switch (current_path()) {
        case 'user':
          $vars['title'] = t('Login');
          unset( $vars['tabs'] );
          break;
        case 'user/register':
          $vars['title'] = t('New account');
          unset( $vars['tabs'] );
          break;
        case 'user/password':
          $vars['title'] = t('I forgot my password');
          unset( $vars['tabs'] );
          break;

        default:
          # code...
          break;
      }
    }

  }elseif ( $hook == "region" ) {
    // =======================================| region |========================================

    if (theme_get_setting('mothership_classes_region')) {
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('region')));
    }

  }elseif ( $hook == "block" ) {

    // =======================================| block |========================================
    //block-subject should be called title so it actually makes sence...
    //  $vars['title'] = $block->subject;
    $vars['id_block'] = "";
    if (theme_get_setting('mothership_classes_block')) {
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('block')));
    }

    if (theme_get_setting('mothership_classes_block')) {
      $vars['classes_array'] = preg_grep('/^block-/', $vars['classes_array'], PREG_GREP_INVERT);
    }

    if (theme_get_setting('mothership_classes_block_contextual')) {
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('contextual-links-region')));
    }

    if (!theme_get_setting('mothership_classes_block_id')) {
      $vars['id_block'] = ' id="' . $vars['block_html_id'] . '"';
    }

    if (theme_get_setting('mothership_classes_block_id_as_class')) {
      $vars['classes_array'][] = $vars['block_html_id'];
    }

    //freeform css class killing
    $remove_class_block = explode(", ", theme_get_setting('mothership_classes_block_freeform'));
    $vars['classes_array'] = array_values(array_diff($vars['classes_array'],$remove_class_block));

//    $vars['classes_array'] = preg_grep('/^block-/', $vars['classes_array'], PREG_GREP_INVERT);
//    print_r($vars['classes_array']);

    //adds title class to the block ... OMG!
    $vars['title_attributes_array']['class'][] = 'title';
    $vars['content_attributes_array']['class'][] = 'block-content';

    //add a theme suggestion to block--menu.tpl so we dont have create a ton of blocks with <nav>
    if(
      ($vars['elements']['#block']->module == "system" AND $vars['elements']['#block']->delta == "navigation") OR
      ($vars['elements']['#block']->module == "system" AND $vars['elements']['#block']->delta == "main-menu") OR
      ($vars['elements']['#block']->module == "system" AND $vars['elements']['#block']->delta == "user-menu") OR
      ($vars['elements']['#block']->module == "admin" AND $vars['elements']['#block']->delta == "menu") OR
       $vars['elements']['#block']->module == "menu_block"
    ){
      $vars['theme_hook_suggestions'][] = 'block__menu';
    }

    //add a theme hook suggestion to the bean so its combinated with its reagion
    if($vars['elements']['#block']->module == "bean" AND $vars['elements']['bean']){
      $vars['theme_hook_suggestions'][] = 'block__bean_'. $vars['elements']['#block']->region;
    }

  }elseif ( $hook == "node" ) {
    // =======================================| NODE |========================================
    // kpr($vars);

    //Template suggestions
    //add new theme hook suggestions based on type & wiewmode
    // a default catch all theaser are set op as node--nodeteaser.tpl.php
    //kpr($vars['theme_hook_suggestions']);

    //one unified node teaser template
    if($vars['view_mode'] == "teaser"){
      $vars['theme_hook_suggestions'][] = 'node__nodeteaser';
    }

    if($vars['view_mode'] == "teaser" AND $vars['promote']){
      $vars['theme_hook_suggestions'][] = 'node__nodeteaser__promote';
    }

    if($vars['view_mode'] == "teaser" AND $vars['sticky']){
      $vars['theme_hook_suggestions'][] = 'node__nodeteaser__sticky';
    }

    if($vars['view_mode'] == "teaser" AND $vars['is_front']){
      $vars['theme_hook_suggestions'][] = 'node__nodeteaser__front';
    }

    //$vars['theme_hook_suggestions'][] = 'node__' . $vars['type'] ;

    //fx node--gallery--teaser.tpl
    $vars['theme_hook_suggestions'][] = 'node__' . $vars['type'] . '__' . $vars['view_mode'];

    //add a noderef to the list
    if(isset($vars['referencing_field'])){
      $vars['theme_hook_suggestions'][] = 'node__noderef';
      $vars['theme_hook_suggestions'][] = 'node__noderef__' . $vars['type'];
      $vars['theme_hook_suggestions'][] = 'node__noderef__' . $vars['type'] . '__' . $vars['view_mode'];
    }



    $vars['id_node'] ="";

    if (theme_get_setting('mothership_classes_node')) {
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('node')));
    }

    if (theme_get_setting('mothership_classes_node_state')) {
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('node-sticky')));
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('node-unpublished')));
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('node-promoted')));
    }
    /*
      change node-xxx to a more generalised name so we can use the same class other places
      fx in the comments
    */

    if (theme_get_setting('mothership_classes_state')) {
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('node-sticky','node-unpublished', 'node-promoted')));
      if($vars['promote']){
        $vars['classes_array'][] = 'promote';
      }
      if($vars['sticky']){
        $vars['classes_array'][] = 'sticky';
      }
      if($vars['status'] =="0"){
        $vars['classes_array'][] = 'unpublished';
      }
    }
    if (isset($vars['preview'])) {
      $vars['classes_array'][] = 'node-preview';
    }

    //freeform css class killing
    $remove_class_node = explode(", ", theme_get_setting('mothership_classes_node_freeform'));
    $vars['classes_array'] = array_values(array_diff($vars['classes_array'],$remove_class_node));

    // css id for the node
    if (theme_get_setting('mothership_classes_node_id')) {
      $vars['id_node'] =  'node-'. $vars['nid'];
    }

    /*
    remove class from the ul that holds the links
    <ul class="inline links">
    this is generated in the node_build_content() function in the node.module
    */
    if (theme_get_setting('mothership_classes_node_links_inline') AND isset($vars['content']['links']['#attributes']['class'])) {
      $vars['content']['links']['#attributes']['class'] = array_values(array_diff($vars['content']['links']['#attributes']['class'],array('inline')));
    }

    if (theme_get_setting('mothership_classes_node_links_links') AND (isset($vars['content']['links']['#attributes']['class']))) {
      $vars['content']['links']['#attributes']['class'] = array_values(array_diff($vars['content']['links']['#attributes']['class'],array('links')));
    }
    // TODO: add a field to push in whatever class names we want to
    // $vars['content']['links']['#attributes']['class'][] = "hardrock hallelulia";

    //  remove the class attribute it its empty
    if(isset($vars['content']['links']['#attributes']['class'])){
      if(isset($vars['content']['links']['#attributes']['class']) && !$vars['content']['links']['#attributes']['class']){
        unset($vars['content']['links']['#attributes']['class']);
      }
    }

    //HELPERS
    //print out all the fields that we can hide/render
    if(theme_get_setting('mothership_poorthemers_helper')){
      $vars['mothership_poorthemers_helper'] .= " ";
      //foreach ($vars['theme_hook_suggestions'] as $key => $value){
        // $value = str_replace('_','-',$value);
        //$vars['mothership_poorthemers_helper'] .= "<!-- * " . $value . ".tpl.php -->\n" ;
      //}

      foreach ($vars['content'] as $key => $value){
        $vars['mothership_poorthemers_helper'] .= "\n <!-- hide(\$content['". $key ."']); --> \n";
        $vars['mothership_poorthemers_helper'] .= "\n <!-- render(\$content['". $key ."']); --> \n";
      }
    }

   // kpr($vars['theme_hook_suggestions']);

  }elseif ( $hook == "comment" ) {
    // =======================================| COMMENT |========================================
    if (isset($vars['elements']['#comment']->new) && $vars['elements']['#comment']->new){
      $vars['classes_array'][] = ' new';
    }

    if ($vars['status'] == "comment-unpublished"){
       $vars['classes_array'][] = ' unpublished';
    }

    //remove inline class from the ul links
    if (theme_get_setting('mothership_classes_node_links_inline')) {
      $vars['content']['links']['#attributes']['class'] = array_values(array_diff($vars['content']['links']['#attributes']['class'],array('inline')));
    }

  }elseif ( $hook == "field" ) {
    // =======================================| FIELD |========================================
    if (theme_get_setting('mothership_classes_field_field')) {
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('field')));
    }



    //freeform css class killing
    $remove_class_field = explode(", ", theme_get_setting('mothership_classes_field_freeform'));
    $vars['classes_array'] = array_values(array_diff($vars['classes_array'],$remove_class_field));

    //kill the field-name-xxxx class
    if (theme_get_setting('mothership_classes_field_name')) {
      $vars['classes_array'] = preg_grep('/^field-name-/', $vars['classes_array'], PREG_GREP_INVERT);
    }
    //kill the field-type-xxxx class
    if (theme_get_setting('mothership_classes_field_type')) {
      $vars['classes_array'] = preg_grep('/^field-type-/', $vars['classes_array'], PREG_GREP_INVERT);
    }

    //label
    if (theme_get_setting('mothership_classes_field_label')) {
      $vars['classes_array'] = preg_grep('/^field-label-/', $vars['classes_array'], PREG_GREP_INVERT);
      $vars['classes_array'] = array_values(array_diff($vars['classes_array'],array('clearfix')));
    }

   // $vars['theme_hook_suggestions'][] = 'node__' . $vars['type'] . '__' . $vars['view_mode'];

  }elseif ( $hook == "maintenance_page" ) {
    // =======================================| maintenance page |========================================

    $vars['path'] = $path;
    $vars['appletouchicon'] = $appletouchicon;
    $vars['selectivizr'] = $selectivizr;
    $vars['theme_hook_suggestions'][] = 'static__maintenance';

  }

//--- POOR THEMERS HELPER
  if(theme_get_setting('mothership_poorthemers_helper')){
    $vars['mothership_poorthemers_helper'] .= "";
    //theme hook suggestions
    $vars['mothership_poorthemers_helper'] .= "\n <!-- theme hook suggestions: -->";
    $vars['mothership_poorthemers_helper'] .= "\n <!-- hook:" . $hook ." --> \n  ";
    foreach ($vars['theme_hook_suggestions'] as $key => $value){
        $value = str_replace('_','-',$value);
        $vars['mothership_poorthemers_helper'] .= "<!-- tpl file: * " . $value . ".tpl.php -->\n" ;
    }
    // $vars['mothership_poorthemers_helper'] .= "[*]file:" . $vars['template_file'];
    $vars['mothership_poorthemers_helper'] .= "";
  }else{
    $vars['mothership_poorthemers_helper'] ="";
  }


}

function mothership_process_html(&$vars, $hook) {
  $vars['html_attributes'] = drupal_attributes($vars['html_attributes_array']);
}



/*
  // Purge needless XHTML stuff.
  nathan ftw! -> http://sonspring.com/journal/html5-in-drupal-7
*/
function mothership_process_html_tag(&$vars) {
  $el = &$vars['element'];

  // Remove type="..." and CDATA prefix/suffix.
  unset($el['#attributes']['type'], $el['#value_prefix'], $el['#value_suffix']);

  // Remove media="all" but leave others unaffected.
  if (isset($el['#attributes']['media']) && $el['#attributes']['media'] === 'all') {
    unset($el['#attributes']['media']);
  }
}

/*
freeform class killing
*/
function mothership_class_killer(&$vars){
  $remove_class_node = explode(", ", theme_get_setting('mothership_classes_node_freeform'));
  $vars['classes_array'] = array_values(array_diff($vars['classes_array'],$remove_class_node));
  $vars['classes'] = "";

//  kpr($vars['classes_array']);
 // return $vars;
}


/**
 * Implements hook_theme_registry_alter().
 */
function mothership_theme_registry_alter(&$theme_registry) {
//  kpr($theme_registry);
  //enough of this bull lets kill em classes
  $theme_registry['node']['preprocess functions'][] = 'mothership_class_killer';

/*
  // Kill the next/previous forum topic navigation links.
  foreach ($theme_registry['forum_topic_navigation']['preprocess functions'] as $key => $value) {
    if ($value = 'template_preprocess_forum_topic_navigation') {
      unset($theme_registry['forum_topic_navigation']['preprocess functions'][$key]);
    }
  }
*/
}

