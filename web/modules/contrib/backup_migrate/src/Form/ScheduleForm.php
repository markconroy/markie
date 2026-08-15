<?php

namespace Drupal\backup_migrate\Form;

use Drupal\backup_migrate\Drupal\Config\DrupalConfigHelper;
use Drupal\backup_migrate\Entity\Schedule;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the schedule form class.
 *
 * @package Drupal\backup_migrate\Form
 */
class ScheduleForm extends EntityForm {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ScheduleForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $backup_migrate_schedule = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Schedule Name'),
      '#maxlength' => 255,
      '#default_value' => $backup_migrate_schedule->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $backup_migrate_schedule->id(),
      '#machine_name' => [
        'exists' => '\Drupal\backup_migrate\Entity\Schedule::load',
      ],
      '#disabled' => !$backup_migrate_schedule->isNew(),
    ];

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Schedule enabled'),
      '#default_value' => $backup_migrate_schedule->get('enabled'),
    ];

    $bam = backup_migrate_get_service_object([], ['nobrowser' => TRUE]);

    $form['source_id'] = DrupalConfigHelper::getSourceSelector(
      $bam,
      $this->t('Backup Source'),
      $backup_migrate_schedule->get('source_id')
    );

    $form['destination_id'] = DrupalConfigHelper::getDestinationSelector(
      $bam,
      $this->t('Backup Destination'),
      $backup_migrate_schedule->get('destination_id')
    );

    $form['settings_profile_id'] = DrupalConfigHelper::getSettingsProfileSelector(
      $this->t('Settings Profile'),
      $backup_migrate_schedule->get('settings_profile_id')
    );

    $period = Schedule::secondsToPeriod($backup_migrate_schedule->get('period'));

    $form['period_container'] = [
      // Reset #parents so the additional container does not appear.
      '#parents' => [],
      '#type' => 'fieldset',
      '#title' => $this->t('Frequency'),
      '#field_prefix' => $this->t('Run every'),
      '#attributes' => [
        'class' => [
          'container-inline',
          'fieldgroup',
          'form-composite',
        ],
      ],
    ];

    $form['period_container']['period_number'] = [
      '#type' => 'number',
      '#default_value' => $period['number'],
      '#min' => 1,
      '#title' => $this->t('Period number'),
      '#title_display' => 'invisible',
      '#size' => 2,
    ];

    $form['period_container']['period_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Period type'),
      '#title_display' => 'invisible',
      '#options' => [],
      '#default_value' => $period['type'],
    ];

    foreach (Schedule::getPeriodTypes() as $key => $type) {
      $form['period_container']['period_type']['#options'][$key] = $type['title'];
    }

    $form['keep'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Number to keep'),
      '#default_value' => $backup_migrate_schedule->get('keep') ?: '',
      '#description' => $this->t('The number of backups to retain. Once this number is reached, the oldest backup will be deleted to make room for the most recent backup. Leave blank to keep all backups.'),
      '#size' => 10,
    ];

    $form['encrypt'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Backup Encryption'),
      '#description' => class_exists('\Defuse\Crypto\File') ? '' : t('In order to encrypt backup files, please install the Defuse PHP-encryption library via Composer with the following command: <code>composer require defuse/php-encryption</code>. See the <a href="@docs">Defuse PHP Encryption Documentation Page</a> for more information.',
        [
          '@docs' => 'https://www.drupal.org/node/3185484',
        ]
      ),
      '#collapsed' => TRUE,
      '#collapsible' => FALSE,
    ];

    $form['encrypt']['encrypt'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Encrypt File'),
      '#default_value' => $backup_migrate_schedule->get('encrypt'),
      '#disabled' => !class_exists('\Defuse\Crypto\File'),
      // Force this to submit as a top-level value so it maps to the
      // entity's "encrypt" property, instead of nesting under
      // ['encrypt']['encrypt'] and overwriting it with an array.
      '#parents' => ['encrypt'],
    ];

    if ($backup_migrate_schedule->get('encrypt')) {
      $form['encrypt']['override_password'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Change password'),
        '#default_value' => FALSE,
        '#disabled' => !class_exists('\Defuse\Crypto\File'),
        '#states' => [
          'visible' => [
            ':input[name="encrypt"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    $form['encrypt']['encrypt_password'] = [
      '#type' => 'password',
      '#title' => $this->t('Encryption Password'),
      '#description' => $this->t('Password for encrypting / decrypting the file'),
      // Force this to submit as a top-level value so it maps to the
      // entity's "encrypt_password" property; otherwise it's nested
      // under ['encrypt']['encrypt_password'] and never reaches the
      // entity at all.
      '#parents' => ['encrypt_password'],
      '#states' => [
        'visible' => [
          ':input[name="encrypt"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="encrypt"]' => ['checked' => TRUE],
        ],
      ],
    ];

    if ($backup_migrate_schedule->get('encrypt')) {
      $form['encrypt']['existing_password'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Encryption Password'),
        '#default_value' => str_repeat('•', strlen($backup_migrate_schedule->get('encrypt_password'))),
        '#disabled' => TRUE,
        '#states' => [
          'visible' => [
            ':input[name="encrypt"]' => ['checked' => TRUE],
            ':input[name="override_password"]' => ['checked' => FALSE],
          ],
          'required' => [
            ':input[name="encrypt"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['encrypt']['encrypt_password']['#states']['visible'] = [
        ':input[name="override_password"]' => ['checked' => TRUE],
      ];

      $form['encrypt']['encrypt_password']['#states']['disabled'] = [
        ':input[name="override_password"]' => ['checked' => FALSE],
      ];

    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    // Save period.
    $type = Schedule::getPeriodType($form_state->getValue('period_type'));
    $seconds = Schedule::periodToSeconds([
      'number' => $form_state->getValue('period_number'),
      'type' => $type,
    ]);

    $form_state->setValue('period', $seconds);
    $keep = $form_state->getValue('keep');
    $form_state->setValue('keep', $keep === '' ? 0 : (int) $keep);

    return parent::buildEntity($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $backup_migrate_schedule = $this->entity;

    if (empty($backup_migrate_schedule->get('encrypt_password')) && $backup_migrate_schedule->get('encrypt')) {
      // Prevent password being overridden with empty value.
      $backup_migrate_schedule_original = $this->entityTypeManager->getStorage('backup_migrate_schedule')->loadUnchanged($backup_migrate_schedule->id());
      $backup_migrate_schedule->set('encrypt_password', $backup_migrate_schedule_original->get('encrypt_password'));
    }

    $status = $backup_migrate_schedule->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Schedule.', [
          '%label' => $backup_migrate_schedule->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Schedule.', [
          '%label' => $backup_migrate_schedule->label(),
        ]));
    }

    $form_state->setRedirectUrl($backup_migrate_schedule->toUrl('collection'));
    return $status;
  }

}
