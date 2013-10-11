<?php

//TODO remove classes from the <a>

//kill of the <ul class="menu" around the menues
//we already have the menu-block-wrapper that adds a <nav tag
function mothership_menu_tree($variables) {
  if(theme_get_setting('mothership_classes_menu_wrapper')){
    return '<ul>' . $variables['tree'] . '</ul>';
  }else{
    return '<ul class="menu">' . $variables['tree'] . '</ul>';
  }
}

/*
walk through each menu link and kill the classes we dont want
*/
function mothership_menu_link(array $variables) {
  //clean up the classes

  //  $remove = array('first','last','leaf','collapsed','expanded','expandable');
  $remove = array();
  if(theme_get_setting('mothership_classes_menu_items_firstlast')){
    $remove[] .= "first";
    $remove[] .= "last";
  }
  if(theme_get_setting('mothership_classes_menu_leaf')){
    $remove[] .= "leaf";
  }
  if(theme_get_setting('mothership_classes_menu_collapsed')){
    $remove[] .= "collapsed";
    $remove[] .= "expanded";
    $remove[] .= "expandable";
  }

  if(theme_get_setting('mothership_classes_menu_items_active')){
    $remove[] .= "active-trail";
    $remove[] .= "active";
  }

  if(!empty($variables['element']['#attributes']['class'])){

    if($remove){
        $variables['element']['#attributes']['class'] = array_diff($variables['element']['#attributes']['class'],$remove);
    }

    //Remove thee menu-mlid-[NUMBER]
    if(theme_get_setting('mothership_classes_menu_items_mlid')){
      $variables['element']['#attributes']['class'] = preg_grep('/^menu-mlid-/', $variables['element']['#attributes']['class'], PREG_GREP_INVERT);
     }
    //if we wanna remove the class for realz so nozing passes
    if(theme_get_setting('mothership_classes_menu_items')){
      unset($variables['element']['#attributes']['class']);
    }

    //test if we even have to have class defined
    if($variables['element']['#attributes']['class']){
  //    dpr($variables['element']['#attributes']['class']);
    }else{
      unset($variables['element']['#attributes']['class']);
    }
  }

  $element = $variables['element'];

  $sub_menu = '';

  if (!empty($element['#below'])) {
    $sub_menu = drupal_render($element['#below']);
  }
//  dpr($variables['element']['#attributes']);

  $output = l($element['#title'], $element['#href'], $element['#localized_options']);
  return '<li' . drupal_attributes($element['#attributes']) . '>' . $output . $sub_menu . "</li>\n";
}


// TODO: book nav.
//http://api.drupal.org/api/drupal/modules--book--book.module/function/template_preprocess_book_navigation/7

/*
  @hook_pager
we rewrites this so we can get shorter class names
Remove all the pager- prefixes classes we dont need this the pager have the pager class on the ul
pager-first & pager-last removed we use the css :first-child instead

we add a daddy item (whos your daddy) so the wrapper item_list gets an idea who called it
*/

function mothership_pager($variables) {

  $tags = $variables['tags'];
  $element = $variables['element'];
  $parameters = $variables['parameters'];
  $quantity = $variables['quantity'];
  global $pager_page_array, $pager_total;

  // Calculate various markers within this pager piece:
  // Middle is used to "center" pages around the current page.
  $pager_middle = ceil($quantity / 2);
  // current is the page we are currently paged to
  $pager_current = $pager_page_array[$element] + 1;
  // first is the first page listed by this pager piece (re quantity)
  $pager_first = $pager_current - $pager_middle + 1;
  // last is the last page listed by this pager piece (re quantity)
  $pager_last = $pager_current + $quantity - $pager_middle;
  // max is the maximum page number
  $pager_max = $pager_total[$element];
  // End of marker calculations.

  // Prepare for generation loop.
  $i = $pager_first;
  if ($pager_last > $pager_max) {
    // Adjust "center" if at end of query.
    $i = $i + ($pager_max - $pager_last);
    $pager_last = $pager_max;
  }
  if ($i <= 0) {
    // Adjust "center" if at start of query.
    $pager_last = $pager_last + (1 - $i);
    $i = 1;
  }
  // End of generation loop preparation.

  $li_first = theme('pager_first', array('text' => (isset($tags[0]) ? $tags[0] : t('« first')), 'element' => $element, 'parameters' => $parameters));
  $li_previous = theme('pager_previous', array('text' => (isset($tags[1]) ? $tags[1] : t('‹ previous')), 'element' => $element, 'interval' => 1, 'parameters' => $parameters));
  $li_next = theme('pager_next', array('text' => (isset($tags[3]) ? $tags[3] : t('next ›')), 'element' => $element, 'interval' => 1, 'parameters' => $parameters));
  $li_last = theme('pager_last', array('text' => (isset($tags[4]) ? $tags[4] : t('last »')), 'element' => $element, 'parameters' => $parameters));

  if ($pager_total[$element] > 1) {
    if ($li_first) {
      $items[] = array(
     //   'class' => array('first'),
        'data' => $li_first,
      );
    }
    if ($li_previous) {
      $items[] = array(
        'class' => array('previous'),
        'data' => $li_previous,
      );
    }

    // When there is more than one page, create the pager list.
    if ($i != $pager_max) {
      if ($i > 1) {
        $items[] = array(
          'class' => array('ellipsis'),
          'data' => '…',
        );
      }
      // Now generate the actual pager piece.
      for (; $i <= $pager_last && $i <= $pager_max; $i++) {
        if ($i < $pager_current) {
          $items[] = array(
//            'class' => array('pager-item'),
            'data' => theme('pager_previous', array('text' => $i, 'element' => $element, 'interval' => ($pager_current - $i), 'parameters' => $parameters)),
          );
        }
        if ($i == $pager_current) {
          $items[] = array(
            'class' => array('current'),
            'data' => $i,
          );
        }
        if ($i > $pager_current) {
          $items[] = array(
          //  'class' => array('pager-item'),
            'data' => theme('pager_next', array('text' => $i, 'element' => $element, 'interval' => ($i - $pager_current), 'parameters' => $parameters)),
          );
        }
      }
      if ($i < $pager_max) {
        $items[] = array(
          'class' => array('ellipsis'),
          'data' => '…',
        );
      }
    }
    // End generation.
    if ($li_next) {
      $items[] = array(
        'class' => array('next'),
        'data' => $li_next,
      );
    }
    if ($li_last) {
      $items[] = array(
//        'class' => array('last'),
        'data' => $li_last,
      );
    }
  //we wrap this in *gasp* so
    return '<h2 class="element-invisible">' . t('Pages') . '</h2>' . theme('item_list', array(
      'items' => $items,
      'attributes' => array('class' => array('pager') ),
      'daddy' => 'pager'
    ));
  }
}

/*
views pagers
  theme_views_mini_pager
  original: /views/theme/theme.inc
*/

function mothership_views_mini_pager($vars) {
  global $pager_page_array, $pager_total;

  $tags = $vars['tags'];
  $element = $vars['element'];
  $parameters = $vars['parameters'];

  // Calculate various markers within this pager piece:
  // current is the page we are currently paged to
  $pager_current = $pager_page_array[$element] + 1;
  // max is the maximum page number
  $pager_max = $pager_total[$element];
  // End of marker calculations.


  $li_previous = theme('pager_previous',
    array(
      'text' => (isset($tags[1]) ? $tags[1] : t('‹‹')),
      'element' => $element,
      'interval' => 1,
      'parameters' => $parameters,
    )
  );
  if (empty($li_previous)) {
    $li_previous = "&nbsp;";
  }

  $li_next = theme('pager_next',
    array(
      'text' => (isset($tags[3]) ? $tags[3] : t('››')),
      'element' => $element,
      'interval' => 1,
      'parameters' => $parameters,
    )
  );
  if (empty($li_next)) {
    $li_next = "&nbsp;";
  }

  if ($pager_total[$element] > 1) {
    $items[] = array(
      'class' => array('previous'),
      'data' => $li_previous,
    );

    $items[] = array(
      'class' => array('current'),
      'data' => t('@current of @max', array('@current' => $pager_current, '@max' => $pager_max)),
    );

    $items[] = array(
      'class' => array('next'),
      'data' => $li_next,
    );
    return theme('item_list',
      array(
        'items' => $items,
        'title' => NULL,
        'type' => 'ul',
        'attributes' => array('class' => array('pager')),
        'daddy' => 'pager'
      )
    );
  }
}


/*
the non saying item-list class haw now added an -daddy element
so if the theme that calls the itemlist adds an 'daddy' => '-pager' to the theme call
the item list haves an idea of what it is
*/

function mothership_item_list($variables) {
  $items = $variables['items'];
  $title = $variables['title'];
  $type  = $variables['type'];
  $attributes = $variables['attributes'];
  $output = '';

  //get the daddy if its set and add it is item-list-$daddy
  if(isset($variables['daddy'])){
    $wrapperclass = "item-list-" . $variables['daddy'];
  }else{
    $wrapperclass = "";
  }

  if(!empty($wrapperclass)){
    $output = '<div class="'. $wrapperclass .'">';
  }


  if (isset($title)) {
    $output .= '<h3>' . $title . '</h3>';
  }

  if (!empty($items)) {
    $output .= "<$type" . drupal_attributes($attributes) . '>';
    $num_items = count($items);
    foreach ($items as $i => $item) {
      $attributes = array();
      $children = array();
      $data = '';
      if (is_array($item)) {
        foreach ($item as $key => $value) {
          if ($key == 'data') {
            $data = $value;
          }
          elseif ($key == 'children') {
            $children = $value;
          }
          else {
            $attributes[$key] = $value;
          }
        }
      }
      else {
        $data = $item;
      }
      if (count($children) > 0) {
        // Render nested list.
        $data .= theme_item_list(array('items' => $children, 'title' => NULL, 'type' => $type, 'attributes' => $attributes));
      }
      if ($i == 0) {
        //TODO remove first
        $attributes['class'][] = 'first';
      }
      if ($i == $num_items - 1) {
        //TODO remove last
        $attributes['class'][] = 'last';
      }
      $output .= '<li' . drupal_attributes($attributes) . '>' . $data . "</li>\n";
    }
    $output .= "</$type>";
  }
  if(!empty($wrapperclass)){
    $output .= '</div>';
  }
  return $output;
}

