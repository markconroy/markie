<?php

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\ai_translate\TextTranslatorInterface;
use Drupal\ai_translate\TranslationException;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the entity translation orchestrator.
 *
 * @group ai_translate
 */
#[RunTestsInSeparateProcesses]
class EntityTranslationOrchestratorKernelTest extends EntityKernelTestBase {

  use NodeCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'language',
    'content_translation',
    'ai',
    'ai_translate',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig([
      'system',
      'user',
      'field',
      'filter',
      'node',
      'language',
      'content_translation',
      'ai',
      'ai_translate',
    ]);
    $this->installSchema('node', ['node_access']);

    NodeType::create([
      'type' => 'page',
      'name' => 'page',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'body',
      'type' => 'text_with_summary',
      'entity_type' => 'node',
      'cardinality' => 1,
      'persist_with_no_fields' => TRUE,
      'translatable' => TRUE,
    ])->save();
    $fieldStorage = FieldStorageConfig::loadByName('node', 'body');
    FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => 'page',
      'label' => 'Body',
      'translatable' => TRUE,
      'settings' => [
        'display_summary' => TRUE,
        'allowed_formats' => [],
      ],
    ])->save();

    ConfigurableLanguage::createFromLangcode('fr')->save();

    $this->container->get('content_translation.manager')->setEnabled('node', 'page', TRUE);

    $this->setUpCurrentUser(['name' => 'translator']);

    $this->container->set('ai_translate.text_translator', new class implements TextTranslatorInterface {

      /**
       * {@inheritdoc}
       */
      public function translateContent(string $input_text, LanguageInterface $langTo, ?LanguageInterface $langFrom = NULL, array $context = []): string {
        return '[' . $langTo->getId() . '] ' . $input_text;
      }

    });
  }

  /**
   * Tests that the orchestrator creates a translation.
   */
  public function testTranslateEntityCreatesTranslation(): void {
    $node = $this->createNode([
      'type' => 'page',
      'title' => 'Hello world',
      'body' => [
        'value' => 'Body text',
        'format' => 'plain_text',
      ],
      'langcode' => 'en',
      'status' => TRUE,
    ]);

    /** @var \Drupal\ai_translate\EntityTranslationOrchestratorInterface $orchestrator */
    $orchestrator = $this->container->get('ai_translate.translation_orchestrator');
    $result = $orchestrator->translateEntity($node, 'en', 'fr');

    $this->assertTrue($result->isSuccess());
    $this->assertSame([], $result->getFailures());

    $reloaded = $this->reloadNode($node->id());
    $this->assertTrue($reloaded->hasTranslation('fr'));

    $translation = $reloaded->getTranslation('fr');
    $this->assertSame('[fr] Hello world', $translation->label());
    $this->assertSame('[fr] Body text', $translation->get('body')->value);
  }

  /**
   * Tests draft-status handling in the orchestrator.
   */
  public function testTranslateEntityRespectsDraftStatusConfig(): void {
    $this->config('ai_translate.settings')->set('translation_status', 'create_draft')->save();

    $node = $this->createNode([
      'type' => 'page',
      'title' => 'Draft me',
      'body' => [
        'value' => 'Draft body',
        'format' => 'plain_text',
      ],
      'langcode' => 'en',
      'status' => TRUE,
    ]);

    /** @var \Drupal\ai_translate\EntityTranslationOrchestratorInterface $orchestrator */
    $orchestrator = $this->container->get('ai_translate.translation_orchestrator');
    $result = $orchestrator->translateEntity($node, 'en', 'fr');

    $this->assertTrue($result->isSuccess());

    $reloaded = $this->reloadNode($node->id());
    $translation = $reloaded->getTranslation('fr');
    $this->assertFalse($translation->isPublished());
  }

  /**
   * Tests that a failing field is reported but does not block the save.
   */
  public function testTranslateEntitySavesTranslationWithPartialFailures(): void {
    // Replace the stub translator with one that only fails on the body value,
    // so the entity ends up partially translated.
    $this->container->set('ai_translate.text_translator', new class implements TextTranslatorInterface {

      /**
       * {@inheritdoc}
       */
      public function translateContent(string $input_text, LanguageInterface $langTo, ?LanguageInterface $langFrom = NULL, array $context = []): string {
        if (str_contains($input_text, 'Broken')) {
          throw new TranslationException('Translation service unavailable.');
        }
        return '[' . $langTo->getId() . '] ' . $input_text;
      }

    });

    $node = $this->createNode([
      'type' => 'page',
      'title' => 'Hello world',
      'body' => [
        'value' => 'Broken body',
        'format' => 'plain_text',
      ],
      'langcode' => 'en',
      'status' => TRUE,
    ]);

    /** @var \Drupal\ai_translate\EntityTranslationOrchestratorInterface $orchestrator */
    $orchestrator = $this->container->get('ai_translate.translation_orchestrator');
    $result = $orchestrator->translateEntity($node, 'en', 'fr');

    // The translation is still created, with the failed field reported.
    $this->assertTrue($result->isSuccess());
    $this->assertSame(['body'], $result->getFailures());

    $reloaded = $this->reloadNode($node->id());
    $this->assertTrue($reloaded->hasTranslation('fr'));

    $translation = $reloaded->getTranslation('fr');
    $this->assertSame('[fr] Hello world', $translation->label());
    // The untranslated field keeps the source value.
    $this->assertSame('Broken body', $translation->get('body')->value);
  }

  /**
   * Reloads a node entity.
   *
   * @param int $nodeId
   *   The node ID.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The reloaded node.
   */
  protected function reloadNode(int $nodeId) {
    return \Drupal::entityTypeManager()->getStorage('node')->load($nodeId);
  }

}
