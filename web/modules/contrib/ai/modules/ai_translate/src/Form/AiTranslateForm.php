<?php

namespace Drupal\ai_translate\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\ai\AiProviderPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the class for Ai Translate Form.
 */
class AiTranslateForm extends FormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_translate.settings';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The AI Provider service.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;

  /**
   * Constructor for the class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\ai\AiProviderPluginManager $provider_manager
   *   The AI provider manager.
   */
  final public function __construct(EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, AiProviderPluginManager $provider_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->providerManager = $provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('ai.provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_translate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?array $build = NULL) {
    _ai_translate_check_default_provider_and_model();
    $form_state->set('entity', $build['#entity']);

    $overview = $build['content_translation_overview'];

    $form['#title'] = $this->t('Translations of @title', ['@title' => $build['#entity']->label()]);

    // Inject our additional column into the header.
    array_splice($overview['#header'], -1, 0, [$this->t('AI Translations')]);

    // Make this a tableselect form.
    $form['languages'] = [
      '#type' => 'tableselect',
      '#header' => $overview['#header'],
      '#options' => [],
    ];
    $languages = $this->languageManager->getLanguages();

    $entity = $build['#entity'];
    $entity_id = $entity->id();
    $entity_type = $entity->getEntityTypeId();
    $lang_from = $entity->getUntranslated()->language()->getId();

    $config = $this->config(static::CONFIG_NAME);

    foreach ($languages as $langcode => $language) {
      $option = array_shift($overview['#rows']);

      if ($lang_from !== $langcode && !$entity->hasTranslation($langcode)) {
        $model = $config->get($langcode . '_model') ?? '';
        $parts = explode('__', $model);
        if (empty($parts[0])) {
          $default_model = $this->providerManager->getSimpleDefaultProviderOptions('chat');
          $parts1 = explode('__', $default_model);
          $ai_model = $parts1[1];
        }
        else {
          $ai_model = $parts[1];
        }
        $additional = Link::createFromRoute($this->t('Translate using @ai', ['@ai' => $ai_model]),
          'ai_translate.translate_content', [
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'lang_from' => $lang_from,
            'lang_to' => $langcode,
          ]
        )->toString();
      }
      else {
        $additional = $this->t('NA');
      }

      // Inject the additional column into the array.
      // The generated form structure has changed, support both an additional
      // 'data' key (that is not supported by tableselect) and the old version
      // without.
      if (isset($option['data'])) {
        array_splice($option['data'], -1, 0, [$additional]);
        // Append the current option array to the form.
        $form['languages']['#options'][$langcode] = $option['data'];
      }
      else {
        array_splice($option, -1, 0, [$additional]);
        // Append the current option array to the form.
        $form['languages']['#options'][$langcode] = $option;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Function to call the translate API and get the result.
   */
  public function aiTranslateResult(array &$form, FormStateInterface $form_state) {

  }

}
