<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\ai_translate\Traits\TextTranslatorStubTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests translating when the optional AI Disclosure module is not installed.
 *
 * @group ai_translate
 */
#[RunTestsInSeparateProcesses]
#[Group('ai_translate')]
class TranslationDisclosureWithoutRecorderKernelTest extends EntityKernelTestBase {

  use NodeCreationTrait;
  use TextTranslatorStubTrait;
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
   * Tests that translating works and the service stays inactive.
   */
  public function testTranslationWorksWithoutRecorder(): void {
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
    NodeType::create(['type' => 'page', 'name' => 'page'])->save();
    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->container->get('content_translation.manager')->setEnabled('node', 'page', TRUE);
    $this->setUpCurrentUser(['name' => 'translator']);
    $this->stubTextTranslator();

    $this->assertFalse($this->container->has('ai_disclosure.recorder'));
    $this->assertFalse($this->container->get('ai_translate.translation_disclosure')->isEnabled());

    $node = $this->createNode(['type' => 'page', 'title' => 'Hello world', 'langcode' => 'en']);
    $result = $this->container->get('ai_translate.translation_orchestrator')
      ->translateEntity($node, 'en', 'fr');

    $this->assertTrue($result->isSuccess());
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $this->assertTrue($storage->load($node->id())->hasTranslation('fr'));
  }

}
