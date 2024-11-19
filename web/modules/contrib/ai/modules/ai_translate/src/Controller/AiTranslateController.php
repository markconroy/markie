<?php

namespace Drupal\ai_translate\Controller;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\Core\Url;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_translate\TextExtractorInterface;
use Drupal\filter\Entity\FilterFormat;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Defines an AI Translate Controller.
 */
class AiTranslateController extends ControllerBase {

  use DependencySerializationTrait;

  /**
   * AI module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $aiConfig;

  /**
   * AI provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;

  /**
   * Twig engine.
   *
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected TwigEnvironment $twig;

  /**
   * Text extractor service.
   *
   * @var \Drupal\ai_translate\TextExtractorInterface
   */
  protected TextExtractorInterface $textExtractor;

  /**
   * The module handler for the hooks.
   *
   * @var Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->languageManager = $container->get('language_manager');
    $instance->aiConfig = $container->get('config.factory')->get('ai.settings');
    $instance->aiProviderManager = $container->get('ai.provider');
    $instance->twig = $container->get('twig');
    $instance->textExtractor = $container->get('ai_translate.text_extractor');
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * Add requested entity translation to the original content.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $entity_id
   *   Entity ID.
   * @param string $lang_from
   *   Source language code.
   * @param string $lang_to
   *   Target language code.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   the function will return a RedirectResponse to the translation
   *   overview page by showing a success or error message.
   */
  public function translate(string $entity_type, string $entity_id, string $lang_from, string $lang_to) {
    static $langNames;
    if (empty($langNames)) {
      $langNames = $this->languageManager->getNativeLanguages();
    }
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);

    // From UI, translation is always request from default entity language,
    // but nothing stops users from using different $lang_from.
    if ($entity->language()->getId() !== $lang_from
      && $entity->hasTranslation($lang_from)) {
      $entity = $entity->getTranslation($lang_from);
    }

    $redirectUrl = Url::fromRoute("entity.$entity_type.content_translation_overview",
      ['entity_type_id' => $entity_type, $entity_type => $entity_id]);
    $response = new RedirectResponse($redirectUrl
      ->setAbsolute(TRUE)->toString());

    // @todo support updating existing translations.
    if ($entity->hasTranslation($lang_to)) {
      $this->messenger()->addMessage('Translation already exists.');
      $response->send();
      return $response;
    }

    $textMetadata = $this->textExtractor->extractTextMetadata($entity);

    // Creates a batch builder to translate text metadata.
    $batchBuilder = (new BatchBuilder())
      ->setTitle($this->t('Translating entity content with AI'))
      ->setInitMessage($this->t('Batch is starting'))
      ->setErrorMessage($this->t('Batch has encountered an error'));

    foreach ($textMetadata as $singleMeta) {
      $batchBuilder->addOperation([$this, 'translateSingleText'], [
        $singleMeta,
        $langNames[$lang_from],
        $langNames[$lang_to],
      ]);
    }
    $batchBuilder->addOperation([$this, 'insertTranslation'], [$entity, $lang_to]);
    batch_set($batchBuilder->toArray());
    return batch_process($redirectUrl);
  }

  /**
   * Get the text translated by AI API call.
   *
   * @param string $input_text
   *   Input prompt for the LLm.
   * @param \Drupal\Core\Language\LanguageInterface $langFrom
   *   Source language.
   * @param \Drupal\Core\Language\LanguageInterface $langTo
   *   Destination language.
   *
   * @return string
   *   Translated content.
   */
  public function translateContent(
    string $input_text,
    LanguageInterface $langFrom,
    LanguageInterface $langTo,
  ) {
    $preferred_model = $this->config('ai_translate.settings')->get($langTo->id() . '_model');
    $provider_config = $this->getSetProvider($preferred_model, 'chat');
    $provider = $provider_config['provider_id'];
    $prompt = $this->config('ai_translate.settings')->get($langTo->id() . '_prompt');
    if (empty($prompt)) {
      $prompt = $this->config('ai_translate.settings')->get('prompt');
    }
    $promptText = $this->twig->renderInline($prompt, [
      'source_lang' => $langFrom->getId(),
      'source_lang_name' => $langFrom->getName(),
      'dest_lang' => $langTo->getId(),
      'dest_lang_name' => $langTo->getName(),
      'input_text' => $input_text,
    ]);
    try {
      $messages = new ChatInput([
        new chatMessage('system', 'You are helpful translator. '),
        new chatMessage('user', $promptText),
      ]);

      // Allow other modules to take over.
      $this->moduleHandler->alter('ai_translate_translation', $messages, $provider, $provider_config['model_id']);

      /** @var /Drupal\ai\OperationType\Chat\ChatOutput $message */
      $message = $provider->chat($messages, $provider_config['model_id'])->getNormalized();
    }
    catch (GuzzleException $exception) {
      // Error handling for the API call.
      return $exception->getMessage();
    }
    $cleaned = trim(trim($message->getText(), '```'), ' ');
    return trim($cleaned, '"');
  }

  /**
   * Get the preferred provider if configured, else take the default one.
   *
   * @param string $preferred_model
   *   The preferred model as a string.
   * @param string $operationType
   *   The operation type (like chat).
   *
   * @return array|null
   *   An array with the model and provider.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   An exception.
   */
  public function getSetProvider($preferred_model, $operationType) {
    // Check if there is a preferred model.
    $provider = NULL;
    $model = NULL;
    if ($preferred_model) {
      $provider = $this->aiProviderManager->loadProviderFromSimpleOption($preferred_model);
      $model = $this->aiProviderManager->getModelNameFromSimpleOption($preferred_model);
    }
    else {
      // Get the default provider.
      $default_provider = $this->aiProviderManager->getDefaultProviderForOperationType($operationType);
      if (empty($default_provider['provider_id'])) {
        // If we got nothing return NULL.
        return NULL;
      }
      $provider = $this->aiProviderManager->createInstance($default_provider['provider_id']);
      $model = $default_provider['model_id'];
    }
    return [
      'provider_id' => $provider,
      'model_id' => $model,
    ];
  }

  /**
   * Finished operation.
   */
  public static function finish($success, $results, $operations, $duration) {
    $messenger = \Drupal::messenger();
    if ($success) {
      $messenger->addMessage(t('All terms have been processed.'));
    }
  }

  /**
   * Batch callback - translate a single text.
   *
   * @param array $singleText
   *   Chunk of text metadata to translate.
   * @param \Drupal\Core\Language\LanguageInterface $langFrom
   *   The source language.
   * @param \Drupal\Core\Language\LanguageInterface $langTo
   *   The target language.
   * @param array $context
   *   The batch context.
   */
  public function translateSingleText(
    array $singleText,
    LanguageInterface $langFrom,
    LanguageInterface $langTo,
    array &$context,
  ) {
    // Translate the content.
    $translated_text = $this->translateContent(
      $singleText['value'], $langFrom, $langTo
    );

    // Checks if the field allows HTML and decodes the HTML entities.
    if (isset($singleText['format'])) {
      $format = $singleText['format'];
      if (FilterFormat::load($format)) {
        $translated_text = html_entity_decode($translated_text);
      }
    }

    $singleText['translated'] = $translated_text;
    $context['results']['processedTranslations'][] = $singleText;
  }

  /**
   * Batch callback - insert processed texts back into the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity to translate.
   * @param string $lang_to
   *   Language code of translation.
   * @param array $context
   *   Text metadata containing both source values and translation.
   */
  public function insertTranslation(
    ContentEntityInterface $entity,
    string $lang_to,
    array &$context,
  ) {
    $translation = $entity->addTranslation($lang_to);
    $this->textExtractor->insertTextMetadata($translation,
      $context['results']['processedTranslations']);
    try {
      $translation->save();
      $this->messenger()->addStatus($this->t('Content translated successfully.'));
    }
    catch (\Throwable $exception) {
      $this->getLogger('ai_translate')->warning($exception->getMessage());
      $this->messenger()->addError($this->t('There was some issue with content translation.'));
    }
  }

}
