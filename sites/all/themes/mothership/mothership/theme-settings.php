<?php
function mothership_form_system_theme_settings_alter(&$form, $form_state) {

  /* ----------------------------- DEVELOPMENT ----------------------------- */
  $form['mothership_info'] = array(
    '#prefix' => '<h3>M &#9881; t h e r s h i p</h3> ',
    '#weight'=> -25
  );

  $form['development'] = array(
    '#type'          => 'fieldset',
    '#title'         => '<b>&#9874;</b> ' . t('Theme Development'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#weight'=> -20
  );

  $form['development']['mothership_poorthemers_helper'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('the Poor Themers Helper - Experimental!'),
    '#default_value' => theme_get_setting('mothership_poorthemers_helper'),
    '#description'   => t('Adds a html comment in block, node, regions fields etc with suggested theme hooks'),
  );

  $form['development']['mothership_rebuild_registry'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Rebuild theme registry on every page.'),
    '#default_value' => theme_get_setting('mothership_rebuild_registry'),
    '#description'   => t('During theme development, it can be very useful to continuously <a href="!link">rebuild the theme registry</a>. <b>Turn this off on live sites!</b>', array('!link' => 'http://drupal.org/node/173880#theme-registry')),
  );

  $form['development']['mothership_test'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('create a test class in  &lt;body&gt;'),
    '#description'   => t('So its easy to check out grids or do something like this in you local version body.test .page{background:pink} - now you know which version your looking at ;) '),
    '#default_value' => theme_get_setting('mothership_test'),
  );

  $form['development']['mothership_mediaquery_indicator'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('show mediaqueris indicator'),
    '#description'   => t(' adds a text on top of you page that indicates the size of the viewport
      fires off at: 320 - 480 - 600 - 768 - 993 - 1383  '),
    '#default_value' => theme_get_setting('mothership_mediaquery_indicator'),
  );


  /* ----------------------------- JS files ----------------------------- */
    $form['js'] = array(
        '#type'          => 'fieldset',
        '#title'         => '&#9985; ' . t('javascript'),
        '#description'   => t('Settings for javascript in the mothership'),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#weight'=> -19
    );

    $form['js']['mothership_script_place_footer'] = array(
      '#type'          => 'checkbox',
      '#title'         => t('Put Javascript down to the bottom of the page') ,
      '#default_value' => theme_get_setting('mothership_script_place_footer')
    );

    $form['js']['mothership_js_onefile'] = array(
      '#type'          => 'checkbox',
      '#title'         => t('Compress all javascripts into one file') ,
      '#description'   => t('This will compress all you js files into one file, for less http request '),
      '#default_value' => theme_get_setting('mothership_js_onefile')
    );

    $form['js']['jquery'] = array(
      '#type'          => 'fieldset',
      '#title'         => '&#10006; ' .t('jQuery'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#description'   => t('')
    );

    $form['js']['jquery']['mothership_js_jquery_latest'] = array(
      '#type'          => 'checkbox',
      '#title'         => t('jQuery 1.8.2') ,
      '#description'   => t('This will have potential problems with Drupal backend. jQuery is located in mothership/js/'),
      '#default_value' => theme_get_setting('mothership_js_jquery_latest')
    );

    $form['js']['jquery']['mothership_js_jquerycdn'] = array(
      '#type'          => 'checkbox',
      '#title'         => t('Replace Drupals local jquery width a CDN version') ,
      '#description'   => t('use ajax.googleapis.com/ajax/libs/jquery/[X.X.X]/jquery.min.js'),
      '#default_value' => theme_get_setting('mothership_js_jquerycdn')
    );

    $form['js']['jquery']['mothership_js_jquerycdn_version'] = array(
      '#type'          => 'textfield',
      '#title'         => t('jQuery CDN version'),
      '#default_value' => theme_get_setting('mothership_js_jquerycdn_version'),
      '#description'   => t('write the version number fx 1.4.4 or 1.8.1 '),
    );



    /* ----------------------------- LIBRARIES  ----------------------------- */
    //libaries stuff
    $form['js']['Libraries'] = array(
      '#type'          => 'fieldset',
      '#title'         => '&#10006; ' .t('Javascript Libraries'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#description'   => t('')
    );

    $form['js']['Libraries']['mothership_respondjs'] = array(
      '#type'          => 'checkbox',
      '#title'         => t('Respond  '),
      '#default_value' => theme_get_setting('mothership_respondjs'),
      '#description'   => t('IE6-IE8 support for mediaqueries with <a href="!link">Respond</a> - <b>Only works with css aggregation turned on!</b>
      ', array('!link' => 'https://github.com/scottjehl/Respond')),
    );

    $form['js']['Libraries']['mothership_modernizr'] = array(
      '#type'          => 'checkbox',
      '#title'         => t('Modernizr2 Love (CDN)'),
      '#default_value' => theme_get_setting('mothership_modernizr'),
      '#description'   => t('adds modernizr2 lib from the CDN
      <a href="http://cdnjs.cloudflare.com/ajax/libs/modernizr/2.0.6/modernizr.min.js">http://cdnjs.cloudflare.com/ajax/libs/modernizr/2.0.6/modernizr.min.js</a>
      <br>
      You should offcourse build a custom modernizr js file for you site! <br>
      Were all about removing the crap- remember...<br>
  		<a hre="http://www.modernizr.com/news/modernizr-and-cdns">modenizr.com: modernizr-and-cdns</a>
  		<br>
      anyway this will enable<br>
      <a href="http://modernizr.com">Custom Build modernizr</a>'),
    );

    $form['js']['Libraries']['mothership_selectivizr'] = array(
      '#type'          => 'checkbox',
      '#title'         => t('Selectivizr Love (CDN)'),
      '#default_value' => theme_get_setting('mothership_selectivizr'),
      '#description'   => t('Add the <a href="!link">Selectivizr</a> library - from CDN:
      <a href="http://cdnjs.cloudflare.com/ajax/libs/selectivizr/1.0.2/selectivizr-min.js">http://cdnjs.cloudflare.com/ajax/libs/selectivizr/1.0.2/selectivizr-min.js</a>
      - <b>Only works with css aggregation turned on!</b>
      ', array('!link' => 'http://selectivizr.com')),
    );

    $form['js']['nuke'] = array(
        '#type'          => 'fieldset',
        '#title'         => '&#9985; ' . t('Remove .js files'),
        '#description'   => t('Settings to remove js files from Drupal'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE
    );

    $form['js']['nuke']['mothership_js_nuke_module'] = array(
       '#type'          => 'checkbox',
       '#title'         => t('Remove .js files from core modules'),
       '#default_value' => theme_get_setting('mothership_js_nuke_module')
     );

    $form['js']['nuke']['mothership_js_nuke_module_contrib'] = array(
       '#type'          => 'checkbox',
       '#title'         => t('Remove .js files from contrib modules'),
       '#default_value' => theme_get_setting('mothership_js_nuke_module_contrib')
     );

     $form['js']['nuke']['mothership_js_nuke_misc'] = array(
       '#type'          => 'checkbox',
       '#title'         => t('Remove all .js from core misc folder'),
       '#default_value' => theme_get_setting('mothership_js_nuke_misc')
     );

     $form['js']['nuke']['mothership_js_nuke_misc'] = array(
       '#type'          => 'checkbox',
       '#title'         => t('Remove all .js from core misc folder'),
       '#default_value' => theme_get_setting('mothership_js_nuke_misc')
     );


     //Get all the posible css files that drupal could spit out
     //generate the $css_files_from_modules
     $result = db_query("SELECT * FROM {system} WHERE type = 'module' AND status = 1");
     foreach ($result as $module) {
       $module_path = pathinfo($module->filename, PATHINFO_DIRNAME);
       $js_files = file_scan_directory($module_path, '/.*\.js$/');
       foreach((array)$js_files as $key => $file) {
         $js_files_drupal[] = $module_path . "/" . $file->filename ;
       }
     }

      //list all js from misc
      $js_misc_files = file_scan_directory('misc', '/.*\.js$/');
      foreach((array)$js_misc_files as $key => $file) {
        $js_files_drupal[] = 'misc' . "/" . $file->filename ;
      }
      //kpr($js_files_from_misc);

      //let sort em
      asort($js_files_drupal);

      $form['js']['nuke']['stripper'] = array(
         '#type'          => 'fieldset',
         '#title'         => t('Javascript File Stripping: ') . sizeof($js_files_drupal). ' Files',
         '#collapsible' => TRUE,
         '#collapsed' => TRUE,
       );

      $form['js']['nuke']['stripper']['mothership_js_freeform'] = array(
        '#type'          => 'textarea',
        '#title'         => t('Path to the CSS files thats gonna be stripped '),
        '#default_value' => theme_get_setting('mothership_js_freeform'),
        '#description'   => t('The whole path to the file(s) that should be removed from the theme, on pr line. <br>this list dosnt account for the BAT removal, will come in a later release'),
        '#suffix'       => '<strong>CSS file paths, based on the modules loaded in you Drupal setup</strong><br>'.  implode('<br> ', $js_files_drupal )
      );

      //list of css files with links
      global $base_url;




  /* ----------------------------- CSS FILES  ----------------------------- */
  $form['css'] = array(
    '#type'          => 'fieldset',
    '#title'         => '&#9985; ' . t('.CSS Files'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#description'   => t('control the css load '),
    '#weight'=> -15
  );

   $form['css']['mothership_css_onefile'] = array(
      '#type'          => 'checkbox',
      '#title'         => t('One css file!'),
      '#description'   => t('All the css files will be put into 1 (only one) file after aggregation. '),
      '#default_value' => theme_get_setting('mothership_css_onefile')
   );



  /* ----------------------------- CSS FILES  RESET ----------------------------- */

  $form['css']['reset'] = array(
     '#type'          => 'fieldset',
     '#title'         => t('CSS Reset'),
     '#collapsible' => TRUE,
     '#collapsed' => FALSE,
   );

   /* ----------------------------- CSS FILES DEFAULTS ----------------------------- */
   $form['css']['defaults'] = array(
      '#type'          => 'fieldset',
      '#title'         => t('CSS Default files'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    );

    $form['css']['defaults']['mothership_css_layout'] = array(
       '#type'          => 'checkbox',
       '#title'         => t('Add <a href="!link" taget="_blank">mothership/css/mothership-layout.css</a> for a basic 3 column layout - responsive, content first & buzz word proven', array('!link' => '/' . drupal_get_path('theme', 'mothership').'/css/mothership-layout.css')),
       '#default_value' => theme_get_setting('mothership_css_layout')
     );

   $form['css']['defaults']['mothership_css_default'] = array(
      '#type'          => 'checkbox',
      '#title'         => t('Add <a href="!link" taget="_blank">mothership/css/mothership-default.css</a> ', array('!link' => '/' . drupal_get_path('theme', 'mothership').'/css/mothership-default.css')),
      '#description'   => t('css for Drupal elements'),
      '#default_value' => theme_get_setting('mothership_css_default')

    );

   $form['css']['defaults']['mothership_css_mothershipstyles'] = array(
      '#type'          => 'checkbox',
      '#title'         => t('Add <a href="!link" taget="_blank">mothership/css/mothership.css</a>', array('!link' => '/' . drupal_get_path('theme', 'mothership').'/css/mothership.css')),
      '#description'   => t('Styles for the markup changes that mothership "fixes" Icons n stuff'),
      '#default_value' => theme_get_setting('mothership_css_mothershipstyles')
   );


   $form['css']['reset']['mothership_css_reset'] = array(
      '#type'          => 'checkbox',
      '#title'         => t('Add reset.css'),
      '#description'   => t('<a href="!link"> Mr. Eric Meyer style v2.0</a>', array('!link' => 'http://meyerweb.com/eric/tools/css/reset/')),
      '#default_value' => theme_get_setting('mothership_css_reset')
    );

    $form['css']['reset']['mothership_css_reset_html5'] = array(
       '#type'          => 'checkbox',
       '#title'         => t('Add reset-html5.css'),
      '#description'   => t('<a href="!link">Delivered from the good html5doctor v1.6.1</a>', array('!link' => 'http://html5doctor.com/html-5-reset-stylesheet/')),
       '#default_value' => theme_get_setting('mothership_css_reset_html5')
     );

     $form['css']['reset']['mothership_css_normalize'] = array(
        '#type'          => 'checkbox',
        '#title'         => t('Add normalize.css:'),
        '#description'   => t('<a href="!link">normalize.css info</a>', array('!link' => 'https://github.com/necolas/normalize.css')),
          '#default_value' => theme_get_setting('mothership_css_normalize')
     );

  /* ----------------------------- CSS FILES  NUKE ----------------------------- */
  //Get all the posible css files that drupal could spit out
  //generate the $css_files_from_modules
  $result = db_query("SELECT * FROM {system} WHERE type = 'module' AND status = 1");
  foreach ($result as $module) {
    $module_path = pathinfo($module->filename, PATHINFO_DIRNAME);
    $css_files = file_scan_directory($module_path, '/.*\.css$/');
    foreach((array)$css_files as $key => $file) {
      $css_files_from_modules[] = $module_path . "/" . $file->filename ;
    }
  }
  //let sort em
  asort($css_files_from_modules);

  $form['css']['nuke'] = array(
    '#type'         => 'fieldset',
    '#title'        => t('Remove CSS Files'),
    '#collapsible'  => TRUE,
    '#collapsed'    => FALSE,
  );

  $form['css']['nuke']['mothership_css_nuke_theme'] = array(
     '#type'          => 'checkbox',
     '#title'         => t('Remove [foo].theme.css'),
     '#default_value' => theme_get_setting('mothership_css_nuke_theme')
   );
  $form['css']['nuke']['mothership_css_nuke_admin'] = array(
     '#type'          => 'checkbox',
     '#title'         => t('Remove [foo].admin.css'),
     '#default_value' => theme_get_setting('mothership_css_nuke_admin')
   );
  $form['css']['nuke']['mothership_css_nuke_module_contrib'] = array(
     '#type'          => 'checkbox',
     '#title'         => t('Remove the css from contrib modules (sites/all/modules/xxx etc)'),
     '#default_value' => theme_get_setting('mothership_css_nuke_module_contrib')
   );
  $form['css']['nuke']['mothership_css_nuke_module_all'] = array(
     '#type'          => 'checkbox',
     '#title'         => t('Remove all css from core Modules'),
     '#description'   => t('keeps the base.css, contextual, overlay, system & toolbar'),
     '#default_value' => theme_get_setting('mothership_css_nuke_module_all')
   );

  $form['css']['nuke']['mothership_css_nuke_book'] = array(
     '#type'          => 'checkbox',
     '#title'         => t('change book.css & .base.css & .theme.css'),
     '#default_value' => theme_get_setting('mothership_css_nuke_book')
   );


  $form['css']['nuke']['mothership_css_nuke_systemtoolbar'] = array(
     '#type'          => 'checkbox',
     '#title'         => t('Remove toolbar css'),
     '#default_value' => theme_get_setting('mothership_css_nuke_systemtoolbar')
   );
  $form['css']['nuke']['mothership_css_nuke_system_message'] = array(
     '#type'          => 'checkbox',
     '#title'         => t('Remove system.messages.css'),
     '#default_value' => theme_get_setting('mothership_css_nuke_system_message')
   );
  $form['css']['nuke']['mothership_css_nuke_system_menus'] = array(
     '#type'          => 'checkbox',
     '#title'         => t('Remove system.menus.css'),
     '#default_value' => theme_get_setting('mothership_css_nuke_system_menus')
   );
   $form['css']['nuke']['mothership_css_nuke_system_theme'] = array(
      '#type'          => 'checkbox',
      '#title'         => t('Remove system.theme.css'),
      '#default_value' => theme_get_setting('mothership_css_nuke_system_theme')
    );



  //remove the css thats already remove by BAT
  foreach ($css_files_from_modules as $file => $value) {

    switch (theme_get_setting('mothership_nuke_css')) {
      //full
      case 'mothership_css_nuke_theme_full':
        if (strpos($value, 'theme.css') !== FALSE) {
          unset($css_files_from_modules[$file]);
        }
      break;
      //theme.css
      case 'mothership_css_nuke_theme':
        if (strpos($value, 'theme.css') !== FALSE) {
          unset($css_files_from_modules[$file]);
        }
        break;

      case 'mothership_css_nuke_admin':
        if (strpos($value, 'admin.css') !== FALSE) {
          unset($css_files_from_modules[$file]);
        }
        break;

      case 'mothership_css_nuke_theme_admin':
        if (strpos($value, 'theme.css') !== FALSE) {
          unset($css_files_from_modules[$file]);
        }
        if (strpos($value, 'admin.css') !== FALSE) {
          unset($css_files_from_modules[$file]);
        }
        break;

      case 'mothership_css_nuke_module':
        if (strpos($value, 'module') !== FALSE) {
          unset($css_files_from_modules[$file]);
        }

        break;

      case 'mothership_css_nuke_epic':
          unset($css_files_from_modules);
        break;

      default:
        # code...
        break;
    }
  }

  //now that we have cleared up from the BAT its time for some freeform removing :)

  /* ----------------------------- STYLE STRIPPING ----------------------------- */
  $form['css']['nuke']['stylestripper'] = array(
     '#type'          => 'fieldset',
     '#title'         => t('CSS File Stripping ') . sizeof($css_files_from_modules). ' CSS Files',
     '#collapsible' => TRUE,
     '#collapsed' => TRUE,
   );

  $form['css']['nuke']['stylestripper']['mothership_css_freeform'] = array(
    '#type'          => 'textarea',
    '#title'         => t('Path to the CSS files thats gonna be stripped '),
    '#default_value' => theme_get_setting('mothership_css_freeform'),
    '#description'   => t('The whole path to the file(s) that should be removed from the theme, on pr line. <br>this list dosnt account for the BAT removal, will come in a later release'),
    '#suffix'       => '<strong>CSS file paths, based on the modules loaded in you Drupal setup</strong><br>'.  implode('<br> ', $css_files_from_modules )
  );

  //list of css files with links
  global $base_url;

  //foreach ($css_files_from_modules as $value){
  //  print '<a href=" ' .  $base_url . $value . ' ">' . $base_url .' *** ' . $value . '</a><br>';
  //}



  /* ----------------------------- CSS CLASSES ----------------------------- */
  $form['classes'] = array(
      '#type'          => 'fieldset',
      '#title'         => '&#9881; ' . t('Classes & Markup'),
      '#description'   => t('Settings to change classes & markup that Drupal drags around'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#weight'=> -11
  );

  //---------------- BODY
  $form['classes']['body'] = array(
      '#type'          => 'fieldset',
      '#title'         => t('body classes'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description'   => t('Modifies the css in the body tag <b> &lt;body class="html logged-in front sidebar toolbar page-node"&gt; </b> html.tpl.php'),
  );

  $form['classes']['body']['mothership_classes_body_html'] = array(
      '#type'          => 'checkbox',
      '#title'         => t('Remove .html'),
      '#default_value' => theme_get_setting('mothership_classes_body_html')
  );

  $form['classes']['body']['mothership_classes_body_loggedin'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .logged-in if a user is logged in'),
    '#default_value' => theme_get_setting('mothership_classes_body_loggedin')
  );

  $form['classes']['body']['mothership_classes_body_front'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove Frontpage Status (.front / .not-front)'),
    '#default_value' => theme_get_setting('mothership_classes_body_front')
  );

  $form['classes']['body']['mothership_classes_body_layout'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove the layout classes (.one-sidebar | .sidebar-first | .sidebar-last)'),
    '#default_value' => theme_get_setting('mothership_classes_body_layout')
  );

  $form['classes']['body']['mothership_classes_body_toolbar'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove Toolbar (.toolbar & .toolbar-drawer )'),
    '#default_value' => theme_get_setting('mothership_classes_body_toolbar')
  );

  $form['classes']['body']['mothership_classes_body_pagenode'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .page-node-[...]'),
    '#default_value' => theme_get_setting('mothership_classes_body_pagenode')
  );

  $form['classes']['body']['mothership_classes_body_nodetype'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .node-type'),
    '#default_value' => theme_get_setting('mothership_classes_body_nodetype')
  );




  $form['classes']['body']['mothership_classes_body_path'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Adds a .path-$path class'),
    '#default_value' => theme_get_setting('mothership_classes_body_path')
  );

  $form['classes']['body']['mothership_classes_body_path_first'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Adds .pathone-$path'),
    '#default_value' => theme_get_setting('mothership_classes_body_path_first'),
    '#description'   => t('This will add the first path of the url. If you path looks like sitename.com/foo/bar then it will add .pathone-foo'),
  );

  $form['classes']['body']['mothership_classes_body_status'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Adds a .status-[headerstatus] class'),
    '#default_value' => theme_get_setting('mothership_classes_body_status'),
    '#description'   => t('Adds status-404'),
  );




  $form['classes']['body']['mothership_classes_body_freeform'] = array(
    '#type'          => 'textfield',
    '#title'         => t('Kill css classes from the body tag'),
    '#default_value' => theme_get_setting('mothership_classes_body_freeform'),
    '#description'   => t('Format is comma seperated: foo, bar, baz <br> If you dont wanna do all the click click click, just add the classes you want to remove.'),
  );


  //---------------- Region
  $form['classes']['region'] = array(
    '#type'          => 'fieldset',
    '#title'         => t('Region Wrapper'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#description'   => t('Settings for region.tpl.php ( <b> &lt;div class="region"&gt; ...</b> )')
  );

  $form['classes']['region']['mothership_region_wrapper'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove the region div wrapper'),
    '#description'   => t('&lt;div class="region ..."&gt; ...&lt;/div&gt; thats defined in region.tpl<br> This means that we remove the region wrapper completely -yea :)'),
    '#default_value' => theme_get_setting('mothership_region_wrapper')
  );

  $form['classes']['region']['mothership_classes_region'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove the class="region" from the div wrapper'),
    '#default_value' => theme_get_setting('mothership_classes_region')
  );

 $form['classes']['region']['mothership_classes_region_freeform'] = array(
    '#type'          => 'textfield',
    '#title'         => t('Kill the css classes that can be defined inside the region'),
    '#default_value' => theme_get_setting('mothership_classes_region_freeform'),
    '#description'   => t('Format: foo, bar, baz <br> If you dont wanna do all the click click click, just add the classes you want to remove.'),
  );

  //---------------- Block
  $form['classes']['block'] = array(
    '#type'          => 'fieldset',
    '#title'         => t('block wrapper'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#description'   => t('Modify the div wrappers style <b> &lt;div id="#block-id" class="block  contextual-links-region block-id"&gt; </b> ')
  );

  $form['classes']['block']['mothership_classes_block'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .block'),
    '#default_value' => theme_get_setting('mothership_classes_block')
  );

  $form['classes']['block']['mothership_classes_block_id'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove #block-$id'),
    '#default_value' => theme_get_setting('mothership_classes_block_id'),
        '#description'   => t('')
  );

  $form['classes']['block']['mothership_classes_block_id_as_class'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Add the #block-$id as a class instead'),
    '#default_value' => theme_get_setting('mothership_classes_block_id_as_class')
  );

  $form['classes']['block']['mothership_classes_block_contentdiv'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove the &lt;div class=&quot;content&quot;&gt; from the block.tpl.php - Keeps it in a custom text block (block-block)'),
    '#default_value' => theme_get_setting('mothership_classes_block_contentdiv')
  );

  $form['classes']['block']['mothership_classes_block_freeform'] = array(
    '#type'          => 'textarea',
    '#title'         => t('Kill the css classes:'),
    '#default_value' => theme_get_setting('mothership_classes_block_freeform'),
    '#description'   => t('Format: foo, bar, baz <br> If you dont wanna do all the click click click, just add the classes you want to remove.'),
  );



  //---------------- NODE
  $form['classes']['node'] = array(
    '#type'          => 'fieldset',
    '#title'         => t('Node Classes & $links'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#description'   => t('Modify the styles <b> &lt;div id="#node-id" class="node  node-[status] "&gt; </b> node.tpl.php')
  );

  $form['classes']['node']['mothership_classes_node'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .node'),
    '#default_value' => theme_get_setting('mothership_classes_node')
  );

  $form['classes']['node']['mothership_classes_node_state'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove the node publishing state classes (.node-sticky | .node-unpublished | .node-promoted)'),
    '#default_value' => theme_get_setting('mothership_classes_node_state')
  );

  $form['classes']['node']['mothership_classes_node_id'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Add the node-id as an id (#node-$id)'),
    '#default_value' => theme_get_setting('mothership_classes_node_id')
  );

  $form['classes']['node']['mothership_classes_node_freeform'] = array(
    '#type'          => 'textfield',
    '#title'         => t('Kill the css classes:'),
    '#default_value' => theme_get_setting('mothership_classes_node_freeform'),
    '#description'   => t('Format: foo, bar, baz <br> If you dont wanna do all the click click click, just add the classes you want to remove.'),
  );


  $form['classes']['node']['mothership_classes_node_links_inline'] = array(
    '#prefix'        => '<h3>The $links</h3><div>changes for the &lt;ul class=&quot;&quot;&gt;...</div>',
    '#type'          => 'checkbox',
    '#title'         => t('Remove "inline" class'),
    '#default_value' => theme_get_setting('mothership_classes_node_links_inline'),
  );

  $form['classes']['node']['mothership_classes_node_links_links'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove "links" class'),
    '#default_value' => theme_get_setting('mothership_classes_node_links_links')
  );


  //--------------------- FIELD
  $form['classes']['field'] = array(
    '#type'          => 'fieldset',
    '#title'         => t('Field classes'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['classes']['field']['mothership_classes_field_field'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .field from the field wrapper '),
    '#default_value' => theme_get_setting('mothership_classes_field_field')
  );


  $form['classes']['field']['mothership_classes_field_name'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove the field name class: .field-name-whatever'),
    '#default_value' => theme_get_setting('mothership_classes_field_name')
  );


  $form['classes']['field']['mothership_classes_field_type'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove type class: .field-type-text, .field-type-image ...'),
    '#default_value' => theme_get_setting('mothership_classes_field_type')
  );

  $form['classes']['field']['mothership_classes_field_label'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove the label status class (.field-label-above, field-label-inline)'),
    '#default_value' => theme_get_setting('mothership_classes_field_label')
  );

  $form['classes']['field']['mothership_classes_field_freeform'] = array(
    '#type'          => 'textarea',
    '#title'         => t('Remove css classes:'),
    '#default_value' => theme_get_setting('mothership_classes_field_freeform'),
    '#description'   => t('Format: foo, bar, baz <br> If you dont wanna do all the click click click, just add the classes you want to remove.'),
  );

 //Form
  $form['classes']['form'] = array(
    '#type'          => 'fieldset',
    '#title'         => t('Forms'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );


	//--- containers

	$form['classes']['form']['container'] = array(
	  '#type'          => 'fieldset',
	  '#title'         => t('Outer Field Container'),
	  '#description'   => t('the div thats wrapped around each of the form field(s) div label & input'),
	  '#collapsible' => FALSE,
	);

  $form['classes']['form']['container']['mothership_classes_form_container_wrapper'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .form-wrapper class'),
    '#default_value' => theme_get_setting('mothership_classes_form_container_wrapper')
  );


	$form['classes']['form']['container']['mothership_classes_form_container_type'] = array(
	  '#type'          => 'checkbox',
	  '#title'         => t('Remove .field-type-[...]'),
	  '#default_value' => theme_get_setting('mothership_classes_form_container_type')
	);

	$form['classes']['form']['container']['mothership_classes_form_container_name'] = array(
		'#type'          => 'checkbox',
		'#title'         => t('Remove .field-name-field-[fieldname] '),
		'#default_value' => theme_get_setting('mothership_classes_form_container_name')
	);

  $form['classes']['form']['container']['mothership_classes_form_container_widget'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .field-widget-[...]'),
    '#default_value' => theme_get_setting('mothership_classes_form_container_widget')
  );


  $form['classes']['form']['container']['mothership_classes_form_container_id'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove the #id of the wrapper'),
    '#default_value' => theme_get_setting('mothership_classes_form_container_id')
  );


	$form['classes']['form']['wrap'] = array(
	  '#type'          => 'fieldset',
	  '#title'         => t('Field Wrapper'),
	  '#description'   => t('control the classes in the div thats wrapped around the label & input'),
	  '#collapsible' => FALSE,
	);

  $form['classes']['form']['wrap']['mothership_classes_form_wrapper_formitem'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .form-item  '),
    '#prefix'         => t('Form field wrapper classes:<br>'),
    '#default_value' => theme_get_setting('mothership_classes_form_wrapper_formitem')
  );

  $form['classes']['form']['wrap']['mothership_classes_form_wrapper_formtype'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .form-type-[type]'),
    '#default_value' => theme_get_setting('mothership_classes_form_wrapper_formtype')
  );

  $form['classes']['form']['wrap']['mothership_classes_form_wrapper_formname'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .form-type-[name]-x'),
    '#default_value' => theme_get_setting('mothership_classes_form_wrapper_formname')
  );

  $form['classes']['form']['wrap']['mothership_classes_form_freeform'] = array(
    '#type'          => 'textarea',
    '#title'         => t('Kill the css classes:'),
    '#default_value' => theme_get_setting('mothership_classes_form_freeform'),
    '#description'   => t('Format: foo, bar, baz <br> If you dont wanna do all the click click click, just add the classes you want to remove.'),
  );

  $form['classes']['form']['mothership_classes_form_label'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .option from label'),
    '#description'   => t('changes &lt;label class=&quot;option&quot;&gt; to &lt;label&gt; an instant win ;)'),
    '#default_value' => theme_get_setting('mothership_classes_form_label')
  );

  $form['classes']['form']['mothership_classes_form_input'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove form-[type] class from form field '),
    '#description'   => t('Use input[type="..."] instead in the css'),
    '#default_value' => theme_get_setting('mothership_classes_form_input')
  );

  $form['classes']['form']['mothership_form_required'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Field required: Removes the hardcoded "*" - add it with css magic () in the class .field-required to the label instead'),
    '#default_value' => theme_get_setting('mothership_form_required')
  );

  $form['classes']['form']['mothership_classes_form_description'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('change the &lt;div class=&quot;description&quot;&gt; to &lt;small&gt; '),
    '#default_value' => theme_get_setting('mothership_classes_form_description')
  );

  $form['classes']['form']['placeholder'] = array(
    '#type'          => 'fieldset',
    '#title'         => t('Placeholders'),
    '#description'   => t(''),
    '#collapsible' => FALSE,
    '#collapsed' => FALSE,
  );


	$form['classes']['form']['placeholder']['mothership_classes_form_placeholder_label'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Use the field Label as placeholder content'),
    '#default_value' => theme_get_setting('mothership_classes_form_placeholder_label')
  );

	$form['classes']['form']['placeholder']['mothership_classes_form_placeholder_link'] = array(
    '#type'          => 'textfield',
    '#title'         => t('links:'),
    '#description'   => t('custom placeholder text for link fields -requires a <a href="http://drupal.org/project/html5_tools">html5 tools</a>'),
    '#default_value' => theme_get_setting('mothership_classes_form_placeholder_link')
  );

	$form['classes']['form']['placeholder']['mothership_classes_form_placeholder_email'] = array(
    '#type'          => 'textfield',
    '#title'         => t('email:'),
    '#description'   => t('custom text for email fields -requires a <a href="http://drupal.org/project/html5_tools">html5 tools</a>'),
    '#default_value' => theme_get_setting('mothership_classes_form_placeholder_email')
  );


  $form['classes']['menu'] = array(
    '#type'          => 'fieldset',
    '#title'         => t('Menus'),
   '#description'   => t('Modifies the stuff that drupal wraps around the menu ul & li tags'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );


  $form['classes']['menu']['mothership_classes_menu_items_mlid'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove the menu-mlid-[mid] class'),
    '#default_value' => theme_get_setting('mothership_classes_menu_items_mlid')
  );


  $form['classes']['menu']['mothership_classes_menu_wrapper'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove the .menu-wrapper class on the &lt;ul class="menu-wrapper" &gt; menustuff &lt;/ul&gt; '),
    '#default_value' => theme_get_setting('mothership_classes_menu_wrapper')
  );


  $form['classes']['menu']['mothership_classes_menu_items_firstlast'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .first & .last class from the li '),
    '#default_value' => theme_get_setting('mothership_classes_menu_items_firstlast')
  );

  $form['classes']['menu']['mothership_classes_menu_items_active'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .active & .active-trail from the li '),
    '#default_value' => theme_get_setting('mothership_classes_menu_items_active')
  );

  $form['classes']['menu']['mothership_classes_menu_collapsed'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .collapsed, .expandable & .expanded from the li '),
    '#default_value' => theme_get_setting('mothership_classes_menu_collapsed')
  );

  $form['classes']['menu']['mothership_classes_menu_leaf'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .leaf from the li '),
    '#default_value' => theme_get_setting('mothership_classes_menu_leaf')
  );



  //---------------- Views
  $form['classes']['view'] = array(
    '#type'          => 'fieldset',
    '#title'         => t('view classes '),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );
  $form['classes']['view']['mothership_classes_view'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .view'),
    '#default_value' => theme_get_setting('mothership_classes_view')
  );
  $form['classes']['view']['mothership_classes_view_name'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .$viewname'),
    '#default_value' => theme_get_setting('mothership_classes_view_name')
  );
  $form['classes']['view']['mothership_classes_view_view_id'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .view-id-$viewname & .view-display-id-$viewname'),
   '#description'   => t('You dont wanna do this is your wanna use the ajax pagination - just saying'),
    '#default_value' => theme_get_setting('mothership_classes_view_view_id')
  );

  $form['classes']['view']['mothership_classes_view_row'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .view-row'),
    '#default_value' => theme_get_setting('mothership_classes_view_row')
  );
  $form['classes']['view']['mothership_classes_view_row_count'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .view-row-$count'),
    '#default_value' => theme_get_setting('mothership_classes_view_row_count')
  );
  $form['classes']['view']['mothership_classes_view_row_first_last'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove .view-row-first & .view-row-last'),
    '#default_value' => theme_get_setting('mothership_classes_view_row_first_last')
  );
  $form['classes']['view']['mothership_classes_view_row_rename'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Rename .view-row-$count,  .view-row-first & .view-row-last to : count-$count, .first & .last'),
    '#description'   => t('To make sure that we use .first & .last classes all over the site'),
    '#default_value' => theme_get_setting('mothership_classes_view_row_rename')
  );

    //---------------- Panels
  $form['classes']['panels'] = array(
    '#type'          => 'fieldset',
    '#title'         => t('Panels'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['classes']['panels']['panels_seperator'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove the div .panel-seperator'),
    '#description'   => t(''),
    '#default_value' => theme_get_setting('mothership_panels_seperator')
  );


  //---------------- misc
  $form['misc'] = array(
    '#type'          => 'fieldset',
    '#title'         => '&#9733; ' . t('Motherships Misc goodie bag'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#weight'=> -10
  );



  $form['misc']['mothership_classes_state'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove the node- prefix from stat classes (.sticky | .unpublished | .promoted) can be used in the comment. This will give us one class for published stated & that is not tainted by beeing node') ,
    '#default_value' => theme_get_setting('mothership_classes_state')
  );


  $form['misc']['mothership_404'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('custom 404 page template'),
    '#default_value' => theme_get_setting('mothership_404'),
    '#description'   => t('Overwrites the html.tpl.php with html--404.tpl.php'),
  );


  $form['misc']['mothership_frontpage_default_message'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove the frontpage "No front page content has been created yet.Add new content" default message'),
    '#default_value' => theme_get_setting('mothership_frontpage_default_message'),
    '#description'   => t(''),
  );
/*
  $form['misc']['mothership_frontpage_regions'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove Regions from the frontpage'),
    '#default_value' => theme_get_setting('mothership_frontpage_regions'),
    '#description'   => t('This will remove the sidebar_first, sidebar_last & content region from the frontpage'),
  );
*/
  $form['misc']['mothership_content_block_wrapper'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Remove wrapper around the main content'),
    '#default_value' => theme_get_setting('mothership_content_block_wrapper'),
    '#description'   => t('remove the &lt;div class=&quot;block-system &quot;&gt; around our $content region in the page.tpl.php'),
  );

  $form['misc']['mothership_goodies_login'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('<del>Alternative</del> Better! user login block'),
    '#description'   => t('Changes the design of the login block: Register link first, then the username & password, then forgot password & last submit button. Removes the tabs as well'),
    '#default_value' => theme_get_setting('mothership_goodies_login'),
  );

  //---------------- Mobile
  $form['mobile'] = array(
    '#type'          => 'fieldset',
    '#title'         => '&#9733; ' . t('Motherships Mobile'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#weight'=> -5
  );


  $form['mobile']['mothership_mobile'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Mobile Meta tags'),
    '#default_value' => theme_get_setting('mothership_mobile'),
    '#description'   => t('meta name="MobileOptimized" content="width" + meta name="HandheldFriendly" content="true" '),
  );


  $form['mobile']['mothership_viewport'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Standard Viewport'),
    '#default_value' => theme_get_setting('mothership_viewport'),
    '#description'   => t('meta name="viewport" content="width=device-width, initial-scale=1.0"'),
  );

  $form['mobile']['mothership_viewport_maximumscale'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Viewport maximum scale'),
    '#default_value' => theme_get_setting('mothership_viewport_maximumscale'),
    '#description'   => t('meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0"'),
  );



}

