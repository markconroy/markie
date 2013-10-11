<?php
/*
  Alternative to drupals login block
  Register link before the login form
  password forgotten link is placed after the password field
*/

function mothership_form_user_login_block_alter(&$form, &$form_state, $form_id) {
  //hide the orignal links
  $form['links']['#access'] = FALSE ;
  if(user_register_access()){
    //register new account link
    $form['info']['#markup'] =  l(t('Register'), 'user/register', array('attributes' => array('class' => 'login-register', 'title' => t('Create a new user account'))));
    $form['info']['#weight'] = -1;
  }
  //yum placeholders
  $form['name']['#attributes']['placeholder'] = $form['name']['#title'];
  $form['pass']['#attributes']['placeholder'] = $form['pass']['#title'];
  //password reminder
  $form['pass']['#description'] =  l(t('Forgot password?'), 'user/password', array('attributes' => array('class' => 'login-password', 'title' => t('Get a new password'))));
}

/*
login form on /user
adds links from the tabs to the login page
*/

function mothership_form_user_login_alter(&$form, &$form_state, $form_id) {
  if(user_register_access()){
    $form['name']['#prefix'] =  '<b>' . l(t('Register a new account'), 'user/register', array('attributes' => array('class' => 'login-register', 'title' => t('Create a new user account')))) . '</b>';
  }

  $form['pass']['#suffix'] = '<b>' . l(t('Forgot password?'), 'user/password', array('attributes' => array('class' => 'login-password', 'title' => t('Get a new password')))) . '</b>';
}

/*
adds links from the tabs to the remember password page
*/

function mothership_form_user_pass_alter(&$form, &$form_state, $form_id) {
  $form['name']['#description'] =t('Enter your username or your e-mail address');
  $form['actions']['#suffix'] = '<b>' . l(t('Login'), 'user', array('attributes' => array('class' => 'login', 'title' => t('Login')))) . '</b>';
}

/*
adds links from the tabs to the remember password page
*/

function mothership_form_user_register_form_alter(&$form, &$form_state, $form_id) {
//  kpr($form);
   $form['actions']['#suffix'] = '<b>' . l(t('Login'), 'user', array('attributes' => array('class' => 'login', 'title' => t('Login')))) . '</b>';
}
