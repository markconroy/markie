<?php
//alternatvie to field when we need some goddamn clean content
//use it in a tpl file like
//$content['field_NAME']['#theme'] = "nomarkup";
function theme_nomarkup(&$variables) {
  $output = '';

  if( !empty($variables['items'])){

    foreach ($variables['items'] as $delta => $item) {
      $output .=  drupal_render($item);
    }

  }

  return $output;
}
/*
comments blocks
changes the span to <time> adds a datetime
changes the item-list class to item-list-comments
*/
function mothership_comment_block() {
  $items = array();
  $number = variable_get('comment_block_count', 10);

  foreach (comment_get_recent($number) as $comment) {
    //kpr($comment->changed);
    //print date('Y-m-d H:i', $comment->changed);
    $items[] =
    '<h3>' . l($comment->subject, 'comment/' . $comment->cid, array('fragment' => 'comment-' . $comment->cid)) . '</h3>' .
    ' <time datetime="'.date('Y-m-d H:i', $comment->changed).'">' . t('@time ago', array('@time' => format_interval(REQUEST_TIME - $comment->changed))) . '</time>';
  }

  if ($items) {
    return theme('item_list', array('items' => $items, 'daddy' => 'comments'));
  }
  else {
    return t('No comments available.');
  }
}

