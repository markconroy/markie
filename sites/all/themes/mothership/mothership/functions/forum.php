<?php

/*
adds a row variable for adding a css counter to the forum list
we need this to support ie8 that dont understand :nth-type :(
originates from system/forum/forum.module
*/
function mothership_preprocess_forum_list(&$variables) {

  global $user;
  $row = 0;
  $count = 0;
	$count2 = 0;

  // Sanitize each forum so that the template can safely print the data.
  foreach ($variables['forums'] as $id => $forum) {

    $variables['forums'][$id]->description = !empty($forum->description) ? filter_xss_admin($forum->description) : '';
    $variables['forums'][$id]->link = url("forum/$forum->tid");
    $variables['forums'][$id]->name = check_plain($forum->name);
    $variables['forums'][$id]->is_container = !empty($forum->container);
    $variables['forums'][$id]->zebra = $row % 2 == 0 ? 'odd' : 'even';
		$row++;

    //     $variables['forums'][$id]->depth == 1

    // make a count of the non container forums
    if($variables['forums'][$id]->depth){

			if(! $variables['forums'][$id]->is_container){
    		$variables['forums'][$id]->count = $count;
    		$count++;
    	}

    }else{

			if(! $variables['forums'][$id]->is_container){
    		$variables['forums'][$id]->count = $count2;
    		$count2++;
    	}


    }


    $variables['forums'][$id]->new_text = '';
    $variables['forums'][$id]->new_url = '';
    $variables['forums'][$id]->new_topics = 0;
    $variables['forums'][$id]->old_topics = $forum->num_topics;
    $variables['forums'][$id]->icon_class = 'default';
    $variables['forums'][$id]->icon_title = t('No new posts');
    if ($user->uid) {
      $variables['forums'][$id]->new_topics = _forum_topics_unread($forum->tid, $user->uid);
      if ($variables['forums'][$id]->new_topics) {
        $variables['forums'][$id]->new_text = format_plural($variables['forums'][$id]->new_topics, '1 new', '@count new');
        $variables['forums'][$id]->new_url = url("forum/$forum->tid", array('fragment' => 'new'));
        $variables['forums'][$id]->icon_class = 'new';
        $variables['forums'][$id]->icon_title = t('New posts');
      }
      $variables['forums'][$id]->old_topics = $forum->num_topics - $variables['forums'][$id]->new_topics;
    }
    $variables['forums'][$id]->last_reply = theme('forum_submitted', array('topic' => $forum->last_post));
  }

  // $tid = term id.
	if(isset ($variables['tid']) ){
		$variables['forum_id'] = $variables['tid'];
	  unset($variables['tid']);
		
	}
}


/*
we adda status so we can use that in each row in forum-topic-list.tpl
*/
function mothership_preprocess_forum_topic_list(&$variables) {

 if (!empty($variables['topics'])) {
  $row = 0;
  foreach ($variables['topics'] as $id => $topic) {
  
    $variables['topics'][$id]->status = "";

    /*
    $variables['topics'][$id]->status .=  ' '.$topic->new;
    $variables['topics'][$id]->status .=  ' comments'.$topic->comment_count;
    $variables['topics'][$id]->status .=  ' mode: '.$topic->comment_mode;

    $variables['topics'][$id]->status .=  ' '.$topic->first_new;
    */

    $variables['topics'][$id]->status .=  ' '.$topic->first_new;    
    $variables['topics'][$id]->status .=  ' count'.$topic->comment_count;
          
    if($topic->comment_mode == 0 OR $topic->comment_mode == 1 ){
      $variables['topics'][$id]->status .=  ' closed ';
    }
    if($topic->sticky == 1){
      $variables['topics'][$id]->status .=  ' sticky ';
    }

  }

  } 
}
