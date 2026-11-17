<?php

namespace Drupal\key\Plugin\KeyProvider;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\key\Attribute\KeyProvider;
use Drupal\key\KeyInterface;
use Drupal\key\Plugin\KeyPluginFormInterface;
use Drupal\key\Plugin\KeyPluginInterface;
use Drupal\key\Plugin\KeyProviderBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds a key provider that allows a key to be stored in a file.
 */
#[KeyProvider(
  id: 'file',
  label: new TranslatableMarkup('File'),
  description: new TranslatableMarkup('The File key provider allows a key to be stored in a file, preferably outside of the web root.'),
  tags: [
    'file',
  ],
  key_value: [
    'accepted' => FALSE,
    'required' => FALSE,
  ],
)]
class FileKeyProvider extends KeyProviderBase implements KeyPluginFormInterface {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Constructs a FileKeyProvider object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\File\FileSystemInterface|null $file_system
   *   The file system service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ?FileSystemInterface $file_system = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    if ($file_system === NULL) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $file_system argument is deprecated in key:1.23.0 and it will be required in key:2.0.0. See https://www.drupal.org/node/3559966', E_USER_DEPRECATED);
      // @phpstan-ignore-next-line
      $file_system = \Drupal::service('file_system');
    }
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // @phpstan-ignore-next-line
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file_system'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'file_location' => '',
      'base64_encoded' => FALSE,
      'strip_line_breaks' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['file_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File location'),
      '#description' => $this->t('The location of the file in which the key will be stored. The path may be absolute (e.g., %abs), relative to the Drupal directory (e.g., %rel), or defined using a stream wrapper (e.g., %str).', [
        '%abs' => '/etc/keys/foobar.key',
        '%rel' => '../keys/foobar.key',
        '%str' => 'private://keys/foobar.key',
      ]),
      '#required' => TRUE,
      '#default_value' => $this->getConfiguration()['file_location'],
    ];

    $form['strip_line_breaks'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Strip trailing line breaks'),
      '#description' => $this->t('Check this to remove any trailing line breaks from the file. Leave unchecked if there is a chance that a line break could be a valid character in the key.'),
      '#default_value' => $this->getConfiguration()['strip_line_breaks'],
    ];

    $form_object = $form_state->getFormObject();
    assert($form_object instanceof EntityFormInterface);
    $key = $form_object->getEntity();
    assert($key instanceof KeyInterface);
    $key_type = $key->getKeyType();
    assert($key_type instanceof KeyPluginInterface);

    // If this key type is for an encryption key.
    if ($key_type->getPluginDefinition()['group'] == 'encryption') {
      // Add an option to indicate that the value is stored Base64-encoded.
      $form['base64_encoded'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Base64-encoded'),
        '#description' => $this->t('Check this if the key in the file is Base64-encoded.'),
        '#default_value' => $this->getConfiguration()['base64_encoded'],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $key_provider_settings = $form_state->getValues();
    $file = $key_provider_settings['file_location'];

    // Does the file exist?
    if (!is_file($file)) {
      $form_state->setErrorByName('file_location', $this->t('There is no file at the specified location.'));
      return;
    }

    // Is the file readable?
    if ((!is_readable($file))) {
      $form_state->setErrorByName('file_location', $this->t('The file at the specified location is not readable.'));
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
    $file = $this->configuration['file_location'];

    // Resolve local stream wrapper URIs (e.g. private://) to an absolute
    // path. Before the kernel's pre-handle middleware has run, stream wrappers
    // are not yet registered with PHP, so is_file() would fail on the URI.
    // Remote stream wrappers return FALSE and the URI is used as-is.
    $realpath = $this->fileSystem->realpath($file);
    if ($realpath) {
      $file = $realpath;
    }

    // Make sure the file exists and is readable.
    if (!is_file($file) || !is_readable($file)) {
      return NULL;
    }

    $key_value = file_get_contents($file);
    if ($key_value === FALSE) {
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

}
