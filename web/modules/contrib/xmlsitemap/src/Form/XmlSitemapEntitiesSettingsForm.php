<?php

namespace Drupal\xmlsitemap\Form;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\State\StateInterface;

/**
 * Configure what entities will be included in sitemap.
 */
class XmlSitemapEntitiesSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'xmlsitemap_config_entities_settings_form';
  }

  /**
   * Constructs a XmlSitemapEntitiesSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\State\StateInterface $state
   *   The object State.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, StateInterface $state) {
    parent::__construct($config_factory);

    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['xmlsitemap.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $entity_types = $this->entityTypeManager->getDefinitions();
    $labels = [];
    $default = [];
    $bundles = $this->entityTypeBundleInfo->getAllBundleInfo();

    foreach ($entity_types as $entity_type_id => $entity_type) {
      if (!$entity_type instanceof ContentEntityTypeInterface || !isset($bundles[$entity_type_id])) {
        continue;
      }

      $labels[$entity_type_id] = $entity_type->getLabel() ?: $entity_type_id;
    }

    asort($labels);

    $form['#labels'] = $labels;

    $form['entity_types'] = [
      '#title' => $this->t('Custom sitemap entities settings'),
      '#type' => 'checkboxes',
      '#options' => $labels,
      '#default_value' => $default,
    ];

    $form['settings'] = ['#tree' => TRUE];

    foreach ($labels as $entity_type_id => $label) {
      $entity_type = $entity_types[$entity_type_id];

      $form['settings'][$entity_type_id] = [
        '#type' => 'container',
        '#entity_type' => $entity_type_id,
        '#bundle_label' => $entity_type->getBundleLabel() ? $entity_type->getBundleLabel() : $label,
        '#title' => $entity_type->getBundleLabel() ? $entity_type->getBundleLabel() : $label,
        '#states' => [
          'visible' => [
            ':input[name="entity_types[' . $entity_type_id . ']"]' => ['checked' => TRUE],
          ],
        ],

        'types' => [
          '#type' => 'table',
          '#tableselect' => TRUE,
          '#default_value' => [],
          '#header' => [
            [
              'data' => $entity_type->getBundleLabel() ? $entity_type->getBundleLabel() : $label,
              'class' => ['bundle'],
            ],
            [
              'data' => $this->t('Sitemap settings'),
              'class' => ['operations'],
            ],
          ],
          '#empty' => $this->t('No content available.'),
        ],
      ];

      foreach ($bundles[$entity_type_id] as $bundle => $bundle_info) {
        $form['settings'][$entity_type_id][$bundle]['settings'] = [
          '#type' => 'item',
          '#label' => $bundle_info['label'],
        ];

        $form['settings'][$entity_type_id]['types'][$bundle] = [
          'bundle' => [
            '#markup' => $bundle_info['label'],
          ],
          'operations' => [
            '#type' => 'operations',
            '#links' => [
              'configure' => [
                'title' => $this->t('Configure'),
                'url' => Url::fromRoute('xmlsitemap.admin_settings_bundle', [
                  'entity' => $entity_type_id,
                  'bundle' => $bundle,
                ]),
                'query' => $this->getDestinationArray(),
              ],
            ],
          ],
        ];
        $form['settings'][$entity_type_id]['types']['#default_value'][$bundle] = xmlsitemap_link_bundle_check_enabled($entity_type_id, $bundle);

        if (xmlsitemap_link_bundle_check_enabled($entity_type_id, $bundle)) {
          $default[$entity_type_id] = $entity_type_id;
        }
      }
    }
    $form['entity_types']['#default_value'] = $default;
    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#value'] = $this->t('Save');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $bundles = $this->entityTypeBundleInfo->getAllBundleInfo();
    $values = $form_state->getValues();
    $entity_values = $values['entity_types'];
    foreach ($entity_values as $key => $value) {
      if ($value) {
        foreach ($bundles[$key] as $bundle_key => $bundle_value) {
          if (!$values['settings'][$key]['types'][$bundle_key]) {
            xmlsitemap_link_bundle_delete($key, $bundle_key, TRUE);
          }
          elseif (!xmlsitemap_link_bundle_check_enabled($key, $bundle_key)) {
            xmlsitemap_link_bundle_enable($key, $bundle_key);
          }
        }
      }
      else {
        foreach ($bundles[$key] as $bundle_key => $bundle_value) {
          xmlsitemap_link_bundle_delete($key, $bundle_key, TRUE);
        }
      }
    }
    $this->state->set('xmlsitemap_regenerate_needed', TRUE);
    parent::submitForm($form, $form_state);
  }

}
