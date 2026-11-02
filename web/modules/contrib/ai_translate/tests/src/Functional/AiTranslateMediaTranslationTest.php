<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Functional;

use Drupal\Core\Language\LanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\media\Entity\Media;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests AI translation of media entities.
 *
 * @group ai_translate
 */
#[Group('ai_translate')]
#[RunTestsInSeparateProcesses]
class AiTranslateMediaTranslationTest extends BrowserTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai_translate',
    'ai',
    'language',
    'content_translation',
    'help',
    'ai_test',
    'media',
    'media_test_source',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * The media entity to translate.
   *
   * @var \Drupal\media\MediaInterface
   */
  protected $media;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->createMediaType('test', [
      'id' => 'test_media',
      'queue_thumbnail_downloads' => FALSE,
    ]);
    \Drupal::service('content_translation.manager')->setEnabled('media', 'test_media', TRUE);

    $prompt = \Drupal::service('ai.prompt_manager')->upsertPrompt([
      'id' => 'ai_translate_media_test',
      'label' => 'AI Translate media test',
      'prompt' => 'Translate {inputText} from {sourceLangName} to {destLangName}.',
      'type' => 'ai_translate',
    ]);
    \Drupal::configFactory()->getEditable('ai_translate.settings')
      ->set('prompt', $prompt->id())
      ->save();

    $config = \Drupal::configFactory()->getEditable('ai.settings');
    $default_providers = $config->get('default_providers') ?? [];
    $default_providers['chat'] = [
      'provider_id' => 'echoai',
      'model_id' => 'gpt-test',
    ];
    $default_providers['translate_text'] = [
      'provider_id' => 'chat_translation',
      'model_id' => 'gpt-test',
    ];
    $config->set('default_providers', $default_providers)->save();

    $this->media = Media::create([
      'bundle' => 'test_media',
      'langcode' => 'en',
      'name' => 'Source media name',
      'field_media_test' => 'Source media text',
    ]);
    $this->media->save();
  }

  /**
   * Ensures that media translations use the requested language.
   */
  public function testMediaTranslationUsesRequestedLanguage(): void {
    $account = $this->drupalCreateUser([
      'create ai content translation',
      'edit any test_media media',
      'translate test_media media',
      'translate editable entities',
    ]);
    $this->drupalLogin($account);

    // Follow the link from the Translate tab, the way a user does. The route
    // is CSRF protected, so the token has to come from a rendered link.
    $this->drupalGet($this->media->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->statusCodeEquals(200);
    $link = $this->getSession()->getPage()->find(
      'css',
      'a[href*="/ai_translate/translate/media/' . $this->media->id() . '/en/fr"]'
    );
    $this->assertNotNull($link, 'The Translate tab offers an AI translation link for French.');
    // drupalGet(), unlike click(), follows the batch's meta refresh chain.
    $this->drupalGet($this->getAbsoluteUrl($link->getAttribute('href')));

    $storage = \Drupal::entityTypeManager()->getStorage('media');
    $storage->resetCache([$this->media->id()]);
    /** @var \Drupal\media\MediaInterface $media */
    $media = $storage->load($this->media->id());
    $this->assertTrue($media->hasTranslation('fr'));
    $this->assertSame('fr', $media->getTranslation('fr')->language()->getId());
    $this->assertFalse($media->hasTranslation(LanguageInterface::LANGCODE_NOT_SPECIFIED));
    $this->assertSame('en', $media->getUntranslated()->language()->getId());

    // The translation must record the language it was translated from. Left at
    // its 'und' default, content_translation resolves the source translation
    // with an invalid langcode and entity types that read it back while saving
    // throw InvalidArgumentException.
    $this->assertSame(
      'en',
      \Drupal::service('content_translation.manager')
        ->getTranslationMetadata($media->getTranslation('fr'))
        ->getSource(),
      'The translation records English as its source language.'
    );
  }

}
