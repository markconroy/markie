<?php

namespace Drupal\key\Plugin\KeyProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\key\Attribute\KeyProvider;
use Drupal\key\KeyInterface;
use Drupal\key\Plugin\KeyPluginFormInterface;
use Drupal\key\Plugin\KeyProviderBase;

/**
 * A key provider that allows a key to be stored in an environment variable.
 */
#[KeyProvider(
  id: 'env',
  label: new TranslatableMarkup('Environment'),
  description: new TranslatableMarkup('The Environment key provider allows a key to be retrieved from an environment variable.'),
  tags: [
    'env',
  ],
  key_value: [
    'accepted' => FALSE,
    'required' => FALSE,
  ],
)]
class EnvKeyProvider extends KeyProviderBase implements KeyPluginFormInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'env_variable' => '',
      'base64_encoded' => FALSE,
      'strip_line_breaks' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['env_variable'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Environment variable'),
      '#description' => $this->t('Name of the environment variable, without a leading <em>$</em>. The variable must be available to PHP when Drupal runs, so set it outside of the codebase: for example in the web server or PHP-FPM configuration, or in the settings of your hosting platform or container. If the key is also used from the command line (for example by Drush or cron), the variable must be set in that environment too.'),
      '#required' => TRUE,
      '#default_value' => $this->getConfiguration()['env_variable'],
    ];

    $form['strip_line_breaks'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Strip trailing line breaks'),
      '#description' => $this->t('Check this to remove any trailing line breaks from the variable. Leave unchecked if there is a chance that a line break could be a valid character in the key.'),
      '#default_value' => $this->getConfiguration()['strip_line_breaks'],
    ];

    // Add an option to indicate that the value is stored Base64-encoded.
    $form['base64_encoded'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Base64-encoded'),
      '#description' => $this->t('Check this if the key in the variable is Base64-encoded. <em>Note: Naturally Base64-encoded values, such as RSA keys, do not need to be marked as Base64-encoded unless they have been additionally encoded for another reason.</em>'),
      '#default_value' => $this->getConfiguration()['base64_encoded'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $key_provider_settings = $form_state->getValues();
    $env_variable = $key_provider_settings['env_variable'];
    $key_value = $this->getEnvironmentVariable($env_variable);

    // Does the env variable exist.
    if ($key_value === FALSE || $key_value === '') {
      $form_state->setErrorByName('env_variable', $this->t('The environment variable does not exist or it is empty.'));
      return;
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->setConfiguration($form_state->getValues());
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyValue(KeyInterface $key) {
    $env_variable = $this->configuration['env_variable'];
    $key_value = $this->getEnvironmentVariable($env_variable);

    if ($key_value === FALSE || $key_value === '') {
      return NULL;
    }

    if (isset($this->configuration['strip_line_breaks']) && $this->configuration['strip_line_breaks'] == TRUE) {
      $key_value = rtrim($key_value, "\n\r");
    }

    if (isset($this->configuration['base64_encoded']) && $this->configuration['base64_encoded'] == TRUE) {
      $key_value = base64_decode($key_value);
    }

    return $key_value;
  }

  /**
   * Gets the value of an environment variable.
   *
   * The value is read with getenv() first. If that finds nothing, the $_ENV
   * superglobal is checked, because tools such as symfony/dotenv populate
   * $_ENV without calling putenv() by default. $_SERVER is deliberately not
   * consulted, as it also holds client-supplied request headers.
   *
   * @param string $env_variable
   *   The name of the environment variable.
   *
   * @return string|false
   *   The value of the environment variable, or FALSE if it is not set.
   */
  protected function getEnvironmentVariable(string $env_variable): string|false {
    $key_value = getenv($env_variable);
    if ($key_value === FALSE && isset($_ENV[$env_variable]) && is_scalar($_ENV[$env_variable])) {
      $key_value = (string) $_ENV[$env_variable];
    }
    return $key_value;
  }

}
