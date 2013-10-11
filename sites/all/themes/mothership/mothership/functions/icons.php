<?php 
/*
removes the <img> tag and puts the icon into a css class
*/
function mothership_feed_icon($variables) {
  $text = t('Subscribe to @feed-title', array('@feed-title' => $variables['title']));
  return '<div class="feed">' . l($text, $variables['url'], array('html' => TRUE, 'attributes' => array('class' => array('feed-icon'), 'title' => $text))) . '</div>';
}

/*Calendar module evil hardcoded gif begone!*/
function mothership_calendar_ical_icon($vars) {
  $url = $vars['url'];
  return '<a href="' . check_url($url) . '" class="ical-icon" title="ical"><div>iCal luv</div></a>';
}


/*file icons*/
function mothership_file_link($variables) {
  $file = $variables['file'];
 // $icon_directory = $variables['icon_directory'];

  $url = file_create_url($file->uri);
  $icon = theme('file_icon', array('file' => $file));

  // Set options as per anchor format described at
  // http://microformats.org/wiki/file-format-examples
  $options = array(
    'attributes' => array(
      'type' => $file->filemime . '; length=' . $file->filesize,
    ),
  );

  // Use the description as the link text if available.
  if (empty($file->description)) {
    $link_text = $file->filename;
  }
  else {
    $link_text = $file->description;
    $options['attributes']['title'] = check_plain($file->filename);
  }
  
  $mimeclass = 'mime-' . drupal_html_class($file->filemime) ;
  $options['attributes']['class'] = array('file-icon',$mimeclass);

  
  return '<div class="file">' . l($link_text, $url, $options) . '</div>';
}

/*
function mothership_file_icon($variables) {
  $file = $variables['file'];
  $mime = drupal_html_class($file->filemime);
  return '<div class="file-icon mime-' . $mime . '"></div>';  
}*/