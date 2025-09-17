<?php

namespace Drupal\contact\Hook;

use Drupal\contact\Plugin\rest\resource\ContactMessageResource;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\Entity\User;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for contact.
 */
class ContactHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.contact':
        $menu_page = \Drupal::moduleHandler()->moduleExists('menu_ui') ? Url::fromRoute('entity.menu.collection')->toString() : '#';
        $block_page = \Drupal::moduleHandler()->moduleExists('block') ? Url::fromRoute('block.admin_display')->toString() : '#';
        $contact_page = Url::fromRoute('entity.contact_form.collection')->toString();
        $output = '';
        $output .= '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('The Contact module allows visitors to contact registered users on your site, using the personal contact form, and also allows you to set up site-wide contact forms. For more information, see the <a href=":contact">online documentation for the Contact module</a>.', [':contact' => 'https://www.drupal.org/documentation/modules/contact']) . '</p>';
        $output .= '<h2>' . $this->t('Uses') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Using the personal contact form') . '</dt>';
        $output .= '<dd>' . $this->t("Site visitors can email registered users on your site by using the personal contact form, without knowing or learning the email address of the recipient. When a site visitor is viewing a user profile, the viewer will see a <em>Contact</em> tab or link, which leads to the personal contact form. The personal contact link is not shown when you are viewing your own profile, and users must have both <em>View user information</em> (to see user profiles) and <em>Use users' personal contact forms</em> permission to see the link. The user whose profile is being viewed must also have their personal contact form enabled (this is a user account setting); viewers with <em>Administer users</em> permission can bypass this setting.") . '</dd>';
        $output .= '<dt>' . $this->t('Configuring contact forms') . '</dt>';
        $output .= '<dd>' . $this->t('On the <a href=":contact_admin">Contact forms page</a>, you can configure the fields and display of the personal contact form, and you can set up one or more site-wide contact forms. Each site-wide contact form has a machine name, a label, and one or more defined recipients; when a site visitor submits the form, the field values are sent to those recipients.', [':contact_admin' => $contact_page]) . '</dd>';
        $output .= '<dt>' . $this->t('Linking to contact forms') . '</dt>';
        $output .= '<dd>' . $this->t('One site-wide contact form can be designated as the default contact form. If you choose to designate a default form, the <em>Contact</em> menu link in the <em>Footer</em> menu will link to it. You can modify this link from the <a href=":menu-settings">Menus page</a> if you have the Menu UI module installed. You can also create links to other contact forms; the URL for each form you have set up has format <em>contact/machine_name_of_form</em>.', [':menu-settings' => $menu_page]) . '</p>';
        $output .= '<dt>' . $this->t('Adding content to contact forms') . '</dt>';
        $output .= '<dd>' . $this->t('From the <a href=":contact_admin">Contact forms page</a>, you can configure the fields to be shown on contact forms, including their labels and help text. If you would like other content (such as text or images) to appear on a contact form, use a block. You can create and edit blocks on the <a href=":blocks">Block layout page</a>, if the Block module is installed.', [':blocks' => $block_page, ':contact_admin' => $contact_page]) . '</dd>';
        $output .= '</dl>';
        return $output;
    }
    return NULL;
  }

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types) : void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    $entity_types['user']->setLinkTemplate('contact-form', '/user/{user}/contact');
  }

  /**
   * Implements hook_entity_extra_field_info().
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo(): array {
    $fields = [];
    foreach (array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo('contact_message')) as $bundle) {
      $fields['contact_message'][$bundle]['form']['name'] = ['label' => $this->t('Sender name'), 'description' => $this->t('Text'), 'weight' => -50];
      $fields['contact_message'][$bundle]['form']['mail'] = ['label' => $this->t('Sender email'), 'description' => $this->t('Email'), 'weight' => -40];
      if ($bundle == 'personal') {
        $fields['contact_message'][$bundle]['form']['recipient'] = ['label' => $this->t('Recipient username'), 'description' => $this->t('User'), 'weight' => -30];
      }
      $fields['contact_message'][$bundle]['form']['preview'] = [
        'label' => $this->t('Preview sender message'),
        'description' => $this->t('Preview'),
        'weight' => 40,
      ];
      $fields['contact_message'][$bundle]['form']['copy'] = [
        'label' => $this->t('Send copy to sender'),
        'description' => $this->t('Option'),
        'weight' => 50,
      ];
    }
    $fields['user']['user']['form']['contact'] = [
      'label' => $this->t('Contact settings'),
      'description' => $this->t('Contact module form element.'),
      'weight' => 5,
    ];
    return $fields;
  }

  /**
   * Implements hook_menu_local_tasks_alter().
   *
   * Hides the 'Contact' tab on the user profile if the user does not have an
   * email address configured.
   */
  #[Hook('menu_local_tasks_alter')]
  public function menuLocalTasksAlter(&$data, $route_name): void {
    if ($route_name == 'entity.user.canonical' && isset($data['tabs'][0])) {
      foreach ($data['tabs'][0] as $href => $tab_data) {
        if ($href == 'entity.user.contact_form') {
          $link_params = $tab_data['#link']['url']->getRouteParameters();
          $account = User::load($link_params['user']);
          if (!$account->getEmail()) {
            unset($data['tabs'][0]['entity.user.contact_form']);
          }
        }
      }
    }
  }

  /**
   * Implements hook_mail().
   */
  #[Hook('mail')]
  public function mail($key, &$message, $params): void {
    $contact_message = $params['contact_message'];
    /** @var \Drupal\user\UserInterface $sender */
    $sender = $params['sender'];
    $language = \Drupal::languageManager()->getLanguage($message['langcode']);
    $variables = [
      '@site-name' => \Drupal::config('system.site')->get('name'),
      '@subject' => $contact_message->getSubject(),
      '@form' => !empty($params['contact_form']) ? $params['contact_form']->label() : '',
      '@form-url' => Url::fromRoute('<current>', [], [
        'absolute' => TRUE,
        'language' => $language,
      ])->toString(),
      '@sender-name' => $sender->getDisplayName(),
    ];
    if ($sender->isAuthenticated()) {
      $variables['@sender-url'] = $sender->toUrl('canonical', ['absolute' => TRUE, 'language' => $language])->toString();
    }
    else {
      $variables['@sender-url'] = $params['sender']->getEmail() ?? '';
    }
    $options = ['langcode' => $language->getId()];
    switch ($key) {
      case 'page_mail':
      case 'page_copy':
        $message['subject'] .= $this->t('[@form] @subject', $variables, $options);
        $message['body'][] = $this->t("@sender-name (@sender-url) sent a message using the contact form at @form-url.", $variables, $options);
        $build = \Drupal::entityTypeManager()->getViewBuilder('contact_message')->view($contact_message, 'mail');
        $message['body'][] = \Drupal::service('renderer')->renderInIsolation($build);
        break;

      case 'page_autoreply':
        $message['subject'] .= $this->t('[@form] @subject', $variables, $options);
        $message['body'][] = $params['contact_form']->getReply();
        break;

      case 'user_mail':
      case 'user_copy':
        $variables += [
          '@recipient-name' => $params['recipient']->getDisplayName(),
          '@recipient-edit-url' => $params['recipient']->toUrl('edit-form', [
            'absolute' => TRUE,
            'language' => $language,
          ])->toString(),
        ];
        $message['subject'] .= $this->t('[@site-name] @subject', $variables, $options);
        $message['body'][] = $this->t('Hello @recipient-name,', $variables, $options);
        $message['body'][] = $this->t("@sender-name (@sender-url) has sent you a message via your contact form at @site-name.", $variables, $options);
        // Only include the opt-out line in the original email and not in the
        // copy to the sender. Also exclude this if the email was sent from a
        // user administrator because they can always send emails even if the
        // contacted user has disabled their contact form.
        if ($key === 'user_mail' && !$params['sender']->hasPermission('administer users')) {
          $message['body'][] = $this->t("If you don't want to receive such messages, you can change your settings at @recipient-edit-url.", $variables, $options);
        }
        $build = \Drupal::entityTypeManager()->getViewBuilder('contact_message')->view($contact_message, 'mail');
        $message['body'][] = \Drupal::service('renderer')->renderInIsolation($build);
        break;
    }
  }

  /**
   * Implements hook_rest_resource_alter().
   */
  #[Hook('rest_resource_alter')]
  public function restResourceAlter(&$definitions): void {
    $definitions['entity:contact_message']['class'] = ContactMessageResource::class;
  }

}
