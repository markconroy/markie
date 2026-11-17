<?php

namespace Drupal\key\Plugin\KeyType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\key\Attribute\KeyType;

/**
 * Defines a key that combines a username and password.
 */
#[KeyType(
  id: 'user_password',
  label: new TranslatableMarkup('User/password'),
  description: new TranslatableMarkup('A key type to store a user/password pair as a JSON object, with "username" and "password" attributes. Example: {"username": "myusername", "password": "mysecretpassword"}'),
  group: 'authentication',
  key_value: [
    'plugin' => 'textarea_field',
  ],
  multivalue: [
    'enabled' => TRUE,
    'fields' => [
      'username' => new TranslatableMarkup('User name'),
      'password' => new TranslatableMarkup('Password'),
    ],
  ],
)]
class UserPasswordKeyType extends AuthenticationMultivalueKeyType {}
