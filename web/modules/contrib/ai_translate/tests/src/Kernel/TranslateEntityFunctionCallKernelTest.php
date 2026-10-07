<?php

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai\Service\FunctionCalling\StructuredExecutableFunctionCallInterface;
use Drupal\ai_translate\Plugin\AiFunctionCall\TranslateEntity;
use Drupal\node\Entity\Node;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Kernel tests for the entity translation function call plugin.
 *
 * @group ai_translate
 */
class TranslateEntityFunctionCallKernelTest extends EntityTranslationOrchestratorKernelTest {

  use NodeCreationTrait;
  use UserCreationTrait;

  /**
   * The function call manager.
   *
   * @var \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager
   */
  protected FunctionCallPluginManager $functionCallManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->functionCallManager = $this->container->get('plugin.manager.ai.function_calls');
  }

  /**
   * Tests that the plugin definition is available.
   */
  public function testTranslateEntityFunctionCallDefinitionExists(): void {
    $definitions = $this->functionCallManager->getDefinitions();
    $this->assertArrayHasKey('ai_translate:translate_entity', $definitions);

    $structuredDefinitions = $this->functionCallManager->getStructuredExecutableDefinitions();
    $this->assertArrayHasKey('ai_translate:translate_entity', $structuredDefinitions);
  }

  /**
   * Tests that the function call translates an entity successfully.
   */
  public function testTranslateEntityFunctionCallExecutes(): void {
    $translator = $this->createUser([
      'create ai content translation',
      'bypass node access',
      'administer nodes',
      'translate any entity',
      'create content translations',
    ], 'translator-user');
    $this->setCurrentUser($translator);

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

    $function = $this->functionCallManager->createInstance('ai_translate:translate_entity');
    $this->assertInstanceOf(TranslateEntity::class, $function);
    $this->assertInstanceOf(StructuredExecutableFunctionCallInterface::class, $function);

    $function->setContextValue('entity_type', 'node');
    $function->setContextValue('entity_id', $node->id());
    $function->setContextValue('target_language', 'fr');
    $function->setContextValue('source_language', 'en');
    $function->execute();

    $output = $function->getStructuredOutput();
    $this->assertSame('success', $output['status']);
    $this->assertSame('Content translated successfully.', $output['message']);
    $this->assertSame('node', $output['entity_type']);
    $this->assertSame((string) $node->id(), $output['entity_id']);
    $this->assertSame('en', $output['source_language']);
    $this->assertSame('fr', $output['target_language']);
    $this->assertSame('[fr] Hello world', $output['translated_entity_label']);
    $this->assertSame([], $output['failures']);
    $this->assertStringContainsString('Entity translation status: success', $function->getReadableOutput());

    $reloaded = Node::load($node->id());
    $translation = $reloaded->getTranslation('fr');
    $this->assertSame('[fr] Hello world', $translation->label());
    $this->assertSame('[fr] Body text', $translation->get('body')->value);
  }

  /**
   * Tests that the function call denies access without update permission.
   */
  public function testTranslateEntityFunctionCallDeniesAccess(): void {
    $translator = $this->createUser([
      'create ai content translation',
      'bypass node access',
      'administer nodes',
      'translate any entity',
      'create content translations',
    ], 'translator-user');
    $this->setCurrentUser($translator);

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

    $deniedUser = $this->createUser([], 'denied-user');
    $this->setCurrentUser($deniedUser);

    $function = $this->functionCallManager->createInstance('ai_translate:translate_entity');
    $function->setContextValue('entity_type', 'node');
    $function->setContextValue('entity_id', $node->id());
    $function->setContextValue('target_language', 'fr');
    $function->execute();

    $output = $function->getStructuredOutput();
    $this->assertSame('access_denied', $output['status']);
    $this->assertStringContainsString('Access denied', $output['message']);
    $this->assertStringContainsString('access_denied', $function->getReadableOutput());

    $reloaded = Node::load($node->id());
    $this->assertFalse($reloaded->hasTranslation('fr'));
  }

  /**
   * Tests that the function call reports existing translations.
   */
  public function testTranslateEntityFunctionCallReportsExistingTranslation(): void {
    $translator = $this->createUser([
      'create ai content translation',
      'bypass node access',
      'administer nodes',
      'translate any entity',
      'create content translations',
    ], 'translator-user');
    $this->setCurrentUser($translator);

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

    $function = $this->functionCallManager->createInstance('ai_translate:translate_entity');
    $function->setContextValue('entity_type', 'node');
    $function->setContextValue('entity_id', $node->id());
    $function->setContextValue('target_language', 'fr');
    $function->setContextValue('source_language', 'en');
    $function->execute();

    $function = $this->functionCallManager->createInstance('ai_translate:translate_entity');
    $function->setContextValue('entity_type', 'node');
    $function->setContextValue('entity_id', $node->id());
    $function->setContextValue('target_language', 'fr');
    $function->setContextValue('source_language', 'en');
    $function->execute();

    $output = $function->getStructuredOutput();
    $this->assertSame('skipped', $output['status']);
    $this->assertSame('Translation already exists.', $output['message']);
  }

}
