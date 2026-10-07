<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin\AiGuardrail;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\Guardrail\Result\StopResult;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use Drupal\ai_test\Entity\AIMockProviderResult;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the Moderation guardrail end-to-end with the echoai provider.
 *
 * @group ai
 * @covers \Drupal\ai\Plugin\AiGuardrail\ModerationGuardrail
 */
class ModerationGuardrailKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('ai_mock_provider_result');
  }

  /**
   * Registers a mock moderation response keyed by the moderated text.
   *
   * The echoai provider matches moderation calls against the ModerationInput
   * array (['prompt' => $text]) and reads 'flagged' / 'information' from the
   * response to drive the outcome.
   *
   * @param string $text
   *   The exact text the guardrail will send to the moderation endpoint.
   * @param bool $flagged
   *   Whether the moderation endpoint should report the content as flagged.
   * @param array<string, mixed> $information
   *   The verbose information returned alongside the flag.
   */
  protected function registerMockResponse(string $text, bool $flagged, array $information = []): void {
    AIMockProviderResult::create([
      'label' => 'Moderation guardrail test response',
      'operation_type' => 'moderation',
      'mock_enabled' => TRUE,
      'request' => Yaml::dump(['prompt' => $text]),
      'response' => Yaml::dump([
        'flagged' => $flagged,
        'information' => $information,
      ]),
    ])->save();
  }

  /**
   * Builds a configured Moderation guardrail plugin instance.
   *
   * @param array<string, mixed> $configuration
   *   Extra configuration merged over the defaults.
   *
   * @return \Drupal\ai\Guardrail\AiGuardrailInterface
   *   The plugin instance, with the AI plugin manager already injected by the
   *   guardrail plugin manager.
   */
  protected function createGuardrail(array $configuration = []) {
    $plugin_manager = \Drupal::service('plugin.manager.ai_guardrail');
    return $plugin_manager->createInstance('moderation', $configuration + [
      'moderation_model' => 'echoai__gpt-test',
      'flagged_message' => 'Blocked by moderation.',
    ]);
  }

  /**
   * Non-chat input is passed through without moderation.
   */
  public function testNonChatInputPasses(): void {
    $result = $this->createGuardrail()->processInput(new TextToImageInput('a picture of a cat'));
    $this->assertInstanceOf(PassResult::class, $result);
    $this->assertFalse($result->stop());
  }

  /**
   * Empty input text is passed through without moderation.
   */
  public function testEmptyMessagePasses(): void {
    $result = $this->createGuardrail()->processInput(new ChatInput([new ChatMessage('user', '')]));
    $this->assertInstanceOf(PassResult::class, $result);
  }

  /**
   * Content the moderation endpoint flags is stopped with the message.
   */
  public function testFlaggedInputStops(): void {
    $this->registerMockResponse('something harmful', TRUE, ['categories' => ['violence']]);
    $result = $this->createGuardrail()->processInput(new ChatInput([
      new ChatMessage('user', 'something harmful'),
    ]));

    $this->assertInstanceOf(StopResult::class, $result);
    $this->assertTrue($result->stop());
    $this->assertEquals('Blocked by moderation.', $result->getMessage());
    $this->assertSame(['categories' => ['violence']], $result->getContext()['moderation_information']);
  }

  /**
   * Content the moderation endpoint clears passes.
   */
  public function testSafeInputPasses(): void {
    $this->registerMockResponse('a friendly hello', FALSE);
    $result = $this->createGuardrail()->processInput(new ChatInput([
      new ChatMessage('user', 'a friendly hello'),
    ]));

    $this->assertInstanceOf(PassResult::class, $result);
    $this->assertFalse($result->stop());
  }

  /**
   * By default only the most recent user message is moderated.
   *
   * An earlier flagged user message must not stop the request when the last
   * user message is clean and "scan all user messages" is disabled.
   */
  public function testLastUserMessageOnlyByDefault(): void {
    $this->registerMockResponse('something harmful', TRUE, ['categories' => ['violence']]);
    $this->registerMockResponse('a friendly hello', FALSE);

    $result = $this->createGuardrail()->processInput(new ChatInput([
      new ChatMessage('user', 'something harmful'),
      new ChatMessage('assistant', 'ok'),
      new ChatMessage('user', 'a friendly hello'),
    ]));

    $this->assertInstanceOf(PassResult::class, $result);
    $this->assertFalse($result->stop());
  }

  /**
   * With scan-all enabled, a flagged earlier user message stops the request.
   */
  public function testScanAllUserMessagesStopsOnEarlierFlag(): void {
    $this->registerMockResponse('something harmful', TRUE, ['categories' => ['violence']]);
    $this->registerMockResponse('a friendly hello', FALSE);

    $result = $this->createGuardrail(['scan_all_user_messages' => TRUE])->processInput(new ChatInput([
      new ChatMessage('user', 'something harmful'),
      new ChatMessage('assistant', 'ok'),
      new ChatMessage('user', 'a friendly hello'),
    ]));

    $this->assertInstanceOf(StopResult::class, $result);
    $this->assertTrue($result->stop());
    $this->assertEquals('Blocked by moderation.', $result->getMessage());
  }

  /**
   * With no provider configured and no default, the guardrail fails closed.
   */
  public function testNoProviderFailsClosed(): void {
    $guardrail = $this->createGuardrail([
      'moderation_model' => '',
    ]);
    $result = $guardrail->processInput(new ChatInput([
      new ChatMessage('user', 'check me'),
    ]));

    $this->assertInstanceOf(StopResult::class, $result);
    $this->assertStringContainsString('No moderation provider', $result->getMessage());
  }

  /**
   * Output processing always passes.
   */
  public function testOutputAlwaysPasses(): void {
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $output = $provider->moderation('anything', 'gpt-test');
    $result = $this->createGuardrail()->processOutput($output);
    $this->assertInstanceOf(PassResult::class, $result);
    $this->assertFalse($result->stop());
  }

  /**
   * The plugin can be loaded and is available via the plugin manager.
   */
  public function testPluginDiscovery(): void {
    $plugin = \Drupal::service('plugin.manager.ai_guardrail')->createInstance('moderation', []);
    $this->assertEquals('Moderation', $plugin->label());
    $this->assertTrue($plugin->isAvailable());
  }

}
