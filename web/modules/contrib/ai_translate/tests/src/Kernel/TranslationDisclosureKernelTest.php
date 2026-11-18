<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\ai_disclosure\Plugin\Field\FieldType\AiDisclosureItem;
use Drupal\ai_disclosure\DisclosureRecorderInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;
use Drupal\ai_translate\EntityTranslationOrchestrator;
use Drupal\ai_translate\Plugin\FieldTextExtractor\ReferenceFieldExtractor;
use Drupal\ai_translate\TranslationDisclosure;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\ai_translate\Traits\TextTranslatorStubTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that AI translations are recorded in AI Disclosure.
 *
 * @group ai_translate
 * @requires module ai_disclosure
 */
#[RunTestsInSeparateProcesses]
#[Group('ai_translate')]
class TranslationDisclosureKernelTest extends EntityKernelTestBase {

  use NodeCreationTrait;
  use TextTranslatorStubTrait;
  use UserCreationTrait;

  /**
   * Name of the AI Disclosure field on the page bundle.
   */
  protected const FIELD_NAME = 'field_ai_disclosure';

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
    'key',
    'ai',
    'ai_disclosure',
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
      'ai_disclosure',
      'ai_translate',
    ]);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('ai_disclosure', ['ai_disclosure_suggestion']);

    NodeType::create(['type' => 'page', 'name' => 'page'])->save();
    ConfigurableLanguage::createFromLangcode('fr')->save();

    FieldStorageConfig::create([
      'field_name' => static::FIELD_NAME,
      'entity_type' => 'node',
      'type' => 'ai_disclosure',
      'cardinality' => 1,
    ])->save();
    $this->addDisclosureField('page', TRUE);

    FieldStorageConfig::create([
      'field_name' => 'field_related',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_related',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Related',
      'translatable' => FALSE,
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => ['target_bundles' => ['page' => 'page']],
      ],
    ])->setThirdPartySetting('ai_translate', 'translate_references', ReferenceFieldExtractor::TRANSLATE_REFERENCE_YES)
      ->save();

    $this->container->get('content_translation.manager')->setEnabled('node', 'page', TRUE);

    $this->setUpCurrentUser(['name' => 'translator'], ['bypass node access']);
    $this->stubTextTranslator();
  }

  /**
   * Adds the AI Disclosure field to a bundle.
   *
   * @param string $bundle
   *   The bundle.
   * @param bool $translatable
   *   Whether the field is translatable.
   */
  protected function addDisclosureField(string $bundle, bool $translatable): void {
    FieldConfig::create([
      'field_name' => static::FIELD_NAME,
      'entity_type' => 'node',
      'bundle' => $bundle,
      'label' => 'AI disclosure',
      'translatable' => $translatable,
    ])->save();
  }

  /**
   * Creates an English page.
   *
   * @param array $values
   *   Extra field values.
   */
  protected function createPage(array $values = []): NodeInterface {
    return $this->createNode($values + [
      'type' => 'page',
      'title' => 'Hello world',
      'langcode' => 'en',
      'status' => TRUE,
    ]);
  }

  /**
   * Translates a node to French through the orchestrator.
   */
  protected function translate(NodeInterface $node): void {
    $result = $this->container->get('ai_translate.translation_orchestrator')
      ->translateEntity($node, 'en', 'fr');
    $this->assertTrue($result->isSuccess());
  }

  /**
   * Loads a fresh copy of a node.
   */
  protected function reload(NodeInterface $node): NodeInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$node->id()]);
    return $storage->load($node->id());
  }

  /**
   * Gets the provenance row recorded for a translation.
   *
   * @return array|null
   *   The row, or NULL when there is none.
   */
  protected function provenance(NodeInterface $node, string $langcode): ?array {
    return $this->container->get('ai_disclosure.suggestion_storage')
      ->loadBySource('node', (string) $node->id(), $langcode, 'ai_translate');
  }

  /**
   * Tests that the translation is disclosed and the source is not.
   */
  public function testTranslationIsDisclosedSourceIsNot(): void {
    $node = $this->createPage();
    $this->translate($node);

    $reloaded = $this->reload($node);
    $item = $reloaded->getTranslation('fr')->get(static::FIELD_NAME)->first();
    $this->assertSame(AiDisclosureItem::MODE_CUSTOM, $item->get('mode')->getValue());
    $this->assertSame('ai_translated', $item->get('grade')->getValue());
    $this->assertEmpty($item->get('human_review')->getValue());

    $this->assertTrue($reloaded->getUntranslated()->get(static::FIELD_NAME)->isEmpty());
    $this->assertNull($this->provenance($node, 'en'));
  }

  /**
   * Tests the provenance row recorded with the disclosure.
   */
  public function testTranslationRecordsProvenance(): void {
    $node = $this->createPage();
    $this->translate($node);

    $row = $this->provenance($node, 'fr');
    $this->assertNotNull($row);
    $this->assertSame('ai_translated', $row['grade']);
    $this->assertSame('Translated from English.', $row['note']);
  }

  /**
   * Tests that switching the setting off leaves the translation alone.
   */
  public function testSettingOffRecordsNothing(): void {
    $this->config('ai_translate.settings')->set('ai_disclosure_apply', FALSE)->save();
    $node = $this->createPage();
    $this->translate($node);

    $reloaded = $this->reload($node);
    $this->assertTrue($reloaded->hasTranslation('fr'));
    $this->assertTrue($reloaded->getTranslation('fr')->get(static::FIELD_NAME)->isEmpty());
    $this->assertNull($this->provenance($node, 'fr'));
  }

  /**
   * Tests that a more severe disclosure on the source is not downgraded.
   */
  public function testMoreSevereSourceKeepsItsGrade(): void {
    $node = $this->createPage([
      static::FIELD_NAME => [
        'mode' => AiDisclosureItem::MODE_CUSTOM,
        'grade' => 'ai_generated_autonomous',
      ],
    ]);
    $this->translate($node);

    $item = $this->reload($node)->getTranslation('fr')->get(static::FIELD_NAME)->first();
    $this->assertSame('ai_generated_autonomous', $item->get('grade')->getValue());
    $this->assertEmpty($item->get('human_review')->getValue());
    // The AI translated write is refused, so the source grade row tells it.
    $this->assertSame('Grade of the source content. Translated from English.', $this->provenance($node, 'fr')['note']);
  }

  /**
   * Tests that the human review of the source is not inherited.
   */
  public function testHumanReviewIsNotInherited(): void {
    $node = $this->createPage([
      static::FIELD_NAME => [
        'mode' => AiDisclosureItem::MODE_CUSTOM,
        'grade' => 'ai_assisted_hitl',
        'human_review' => TRUE,
        'editorial_responsibility' => 'The editors',
        'description' => 'Reviewed line by line.',
      ],
    ]);
    $this->translate($node);

    $reloaded = $this->reload($node);
    $item = $reloaded->getTranslation('fr')->get(static::FIELD_NAME)->first();
    $this->assertSame(AiDisclosureItem::MODE_CUSTOM, $item->get('mode')->getValue());
    $this->assertSame('ai_assisted_hitl', $item->get('grade')->getValue());
    $this->assertEmpty($item->get('human_review')->getValue());
    $this->assertEmpty($item->get('description')->getValue());
    $this->assertEmpty($item->get('editorial_responsibility')->getValue());

    // The source keeps its attestation.
    $source = $reloaded->getUntranslated()->get(static::FIELD_NAME)->first();
    $this->assertTrue((bool) $source->get('human_review')->getValue());
    $this->assertSame('Reviewed line by line.', $source->get('description')->getValue());
  }

  /**
   * Tests that a less severe source grade is raised to AI translated.
   */
  public function testLessSevereSourceBecomesAiTranslated(): void {
    $node = $this->createPage([
      static::FIELD_NAME => [
        'mode' => AiDisclosureItem::MODE_CUSTOM,
        'grade' => 'human_only',
        'human_review' => TRUE,
      ],
    ]);
    $this->translate($node);

    $item = $this->reload($node)->getTranslation('fr')->get(static::FIELD_NAME)->first();
    $this->assertSame('ai_translated', $item->get('grade')->getValue());
    $this->assertEmpty($item->get('human_review')->getValue());
    $this->assertSame('Translated from English.', $this->provenance($node, 'fr')['note']);
  }

  /**
   * Tests that a field shared between translations is left alone.
   */
  public function testNonTranslatableFieldIsSkipped(): void {
    FieldConfig::loadByName('node', 'page', static::FIELD_NAME)
      ->set('translatable', FALSE)
      ->save();
    $node = $this->createPage();
    $this->translate($node);

    $reloaded = $this->reload($node);
    $this->assertTrue($reloaded->hasTranslation('fr'));
    $this->assertTrue($reloaded->getUntranslated()->get(static::FIELD_NAME)->isEmpty());
    $this->assertNull($this->provenance($node, 'fr'));
  }

  /**
   * Tests that a bundle without the field is skipped silently.
   */
  public function testBundleWithoutFieldIsSkipped(): void {
    NodeType::create(['type' => 'article', 'name' => 'article'])->save();
    $this->container->get('content_translation.manager')->setEnabled('node', 'article', TRUE);
    $node = $this->createNode([
      'type' => 'article',
      'title' => 'Plain',
      'langcode' => 'en',
    ]);
    $this->translate($node);

    $this->assertTrue($this->reload($node)->hasTranslation('fr'));
    $this->assertNull($this->provenance($node, 'fr'));
  }

  /**
   * Tests that a referenced entity translated through AI is disclosed too.
   */
  public function testReferencedEntityTranslationIsDisclosed(): void {
    $referenced = $this->createPage(['title' => 'Referenced']);
    $node = $this->createPage([
      'title' => 'Parent',
      'field_related' => ['target_id' => $referenced->id()],
    ]);
    $this->translate($node);

    $reloaded = $this->reload($referenced);
    $this->assertTrue($reloaded->hasTranslation('fr'));
    $this->assertSame('[fr] Referenced', $reloaded->getTranslation('fr')->label());
    $item = $reloaded->getTranslation('fr')->get(static::FIELD_NAME)->first();
    $this->assertSame('ai_translated', $item->get('grade')->getValue());
    $this->assertTrue($reloaded->getUntranslated()->get(static::FIELD_NAME)->isEmpty());
    $this->assertNotNull($this->provenance($referenced, 'fr'));
  }

  /**
   * Tests that an existing referenced translation keeps its own disclosure.
   */
  public function testExistingReferencedTranslationKeepsItsAttestation(): void {
    $referenced = $this->createPage(['title' => 'Referenced']);
    $referenced->addTranslation('fr', [
      'title' => 'Deja la',
      static::FIELD_NAME => [
        'mode' => AiDisclosureItem::MODE_CUSTOM,
        'grade' => 'ai_assisted_hitl',
        'human_review' => TRUE,
      ],
    ]);
    $referenced->save();
    $node = $this->createPage([
      'title' => 'Parent',
      'field_related' => ['target_id' => $referenced->id()],
    ]);
    $this->translate($node);

    $item = $this->reload($referenced)->getTranslation('fr')->get(static::FIELD_NAME)->first();
    $this->assertSame('ai_assisted_hitl', $item->get('grade')->getValue());
    $this->assertTrue((bool) $item->get('human_review')->getValue());
  }

  /**
   * Tests that a recording failure is logged and the translation is saved.
   */
  public function testRecorderFailureIsLoggedAndTranslationSaved(): void {
    $recorder = $this->createMock(DisclosureRecorderInterface::class);
    $recorder->method('apply')->willThrowException(new \RuntimeException('db down'));
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->atLeastOnce())
      ->method('warning')
      ->with($this->stringContains('Recording the AI disclosure failed'));
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->with('ai_translate')->willReturn($logger);
    $disclosure = new TranslationDisclosure(
      $this->container->get('config.factory'),
      $recorder,
      $this->container->get('ai_disclosure.resolver'),
      $loggerFactory,
    );
    $orchestrator = new EntityTranslationOrchestrator(
      $this->container->get('ai_translate.text_extractor'),
      $this->container->get('ai_translate.text_translator'),
      $this->container->get('language_manager'),
      $this->container->get('config.factory'),
      $this->container->get('logger.factory'),
      $this->container->get('entity_type.manager'),
      $this->container->get('content_translation.manager'),
      $disclosure,
    );
    $node = $this->createPage();

    $result = $orchestrator->translateEntity($node, 'en', 'fr');

    $this->assertTrue($result->isSuccess());
    $this->assertTrue($this->reload($node)->hasTranslation('fr'));
  }

}
