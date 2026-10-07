<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin\AiGuardrail;

use Drupal\ai\Entity\AiGuardrail;
use Drupal\ai\Entity\AiGuardrailSet;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai\Guardrail\StreamableGuardrailInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for streaming guardrails using a real guardrail plugin.
 *
 * Exercises the full streaming guardrail lifecycle end-to-end: guardrail entity
 * creation, guardrail set configuration, EchoAI streamed output, and
 * StreamedChatMessageIterator guardrail integration.
 *
 * @group ai
 * @covers \Drupal\ai\Plugin\AiGuardrail\SensitiveContentStream
 * @covers \Drupal\ai\OperationType\Chat\StreamedChatMessageIterator::processStreamingGuardrails
 *
 * @see https://git.drupalcode.org/project/ai/-/work_items/3584951
 */
#[RunTestsInSeparateProcesses]
class StreamingGuardrailKernelTest extends KernelTestBase {

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
   * The AI provider instance.
   *
   * @var \Drupal\ai\AiProviderInterface
   */
  protected $provider;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('ai_mock_provider_result');

    // Create a guardrail entity using the SensitiveContentStream plugin.
    $guardrail = AiGuardrail::create([
      'id' => 'test_sensitive_stream',
      'label' => 'Test Sensitive Stream',
      'description' => 'Blocks content between [SENSITIVE] markers during streaming.',
      'guardrail' => 'sensitive_content_stream',
      'guardrail_settings' => [
        'start_marker' => '[SENSITIVE]',
        'stop_marker' => '[/SENSITIVE]',
        'replacement_message' => '[Content removed.]',
      ],
    ]);
    $guardrail->save();

    // Create a guardrail set with the plugin on the post-generate list.
    // Streaming guardrails are registered as post-generate guardrails but
    // the event subscriber detects StreamableGuardrailInterface and routes
    // them to the stream iterator instead of the normal post-generate path.
    $guardrail_set = AiGuardrailSet::create([
      'id' => 'test_streaming_set',
      'label' => 'Test Streaming Set',
      'description' => 'Guardrail set for streaming guardrail tests.',
      'stop_threshold' => 1.0,
      'pre_generate_guardrails' => ['plugin_id' => []],
      'post_generate_guardrails' => ['plugin_id' => ['test_sensitive_stream']],
    ]);
    $guardrail_set->save();

    $this->provider = \Drupal::service('ai.provider')->createInstance('echoai');
  }

  /**
   * Tests that the SensitiveContentStream plugin is discoverable.
   */
  public function testPluginDiscovery(): void {
    /** @var \Drupal\ai\AiGuardrailPluginManager $manager */
    $manager = \Drupal::service('plugin.manager.ai_guardrail');
    $plugin = $manager->createInstance('sensitive_content_stream', [
      'start_marker' => '[X]',
      'stop_marker' => '[/X]',
      'replacement_message' => 'Blocked.',
    ]);
    $this->assertEquals('Sensitive Content Stream Filter', $plugin->label());
    $this->assertTrue($plugin->isAvailable());
  }

  /**
   * Tests that the streaming guardrail is registered on the iterator.
   *
   * When a guardrail set containing a StreamableGuardrailInterface plugin is
   * attached to a streamed chat request, the GuardrailsEventSubscriber must
   * register it on the StreamedChatMessageIterator via addStreamingGuardrail()
   * so it evaluates chunks in real-time during iteration.
   */
  public function testStreamingGuardrailIsRegisteredOnIterator(): void {
    $input = new ChatInput([
      new ChatMessage('user', 'Hello world'),
    ]);
    $input->setStreamedOutput(TRUE);
    $guardrail_helper = \Drupal::service('ai.guardrail_helper');
    $input = $guardrail_helper->applyGuardrailSetToChatInput('test_streaming_set', $input);

    $result = $this->provider->chat($input, 'gpt-test', ['test']);
    $this->assertInstanceOf(ChatOutput::class, $result);

    $iterator = $result->getNormalized();
    $this->assertInstanceOf(StreamedChatMessageIteratorInterface::class, $iterator);

    // The GuardrailsEventSubscriber should have added the streaming guardrail.
    $guardrails = $iterator->getStreamingGuardrails();
    $this->assertCount(1, $guardrails);
    $this->assertInstanceOf(StreamableGuardrailInterface::class, $guardrails[0]);
  }

  /**
   * Tests stream without sensitive markers passes through unchanged.
   *
   * When the EchoAI response contains no [SENSITIVE] markers, the full
   * response should stream through without any content being suppressed.
   */
  public function testCleanStreamPassesThrough(): void {
    $input = new ChatInput([
      new ChatMessage('user', 'Clean input'),
    ]);
    $input->setStreamedOutput(TRUE);
    $guardrail_helper = \Drupal::service('ai.guardrail_helper');
    $input = $guardrail_helper->applyGuardrailSetToChatInput('test_streaming_set', $input);

    $result = $this->provider->chat($input, 'gpt-test', ['test']);
    $iterator = $result->getNormalized();
    $this->assertInstanceOf(StreamedChatMessageIteratorInterface::class, $iterator);

    // Consume the stream and concatenate all chunks.
    $full_output = '';
    foreach ($iterator as $chunk) {
      $full_output .= $chunk->getText();
    }

    // EchoAI echoes "Hello world! Input: {text}. Config: {json}.".
    $this->assertStringContainsString('Clean input', $full_output);
    // No replacement text should be present.
    $this->assertStringNotContainsString('[Content removed.]', $full_output);
  }

  /**
   * Tests that non-streamed requests with a streaming guardrail still work.
   *
   * When the output is not streamed, the StreamableGuardrailInterface plugin
   * should be silently skipped (it only works on stream iterators). The
   * non-streamed response should pass through without errors.
   */
  public function testNonStreamedRequestIgnoresStreamingGuardrail(): void {
    // Explicitly do NOT enable streaming.
    $input = new ChatInput([
      new ChatMessage('user', 'Non-streamed input'),
    ]);
    $guardrail_helper = \Drupal::service('ai.guardrail_helper');
    $input = $guardrail_helper->applyGuardrailSetToChatInput('test_streaming_set', $input);

    $result = $this->provider->chat($input, 'gpt-test', ['test']);
    $this->assertInstanceOf(ChatOutput::class, $result);

    // For non-streamed, getNormalized() returns a ChatMessage, not an iterator.
    $normalized = $result->getNormalized();
    $this->assertInstanceOf(ChatMessage::class, $normalized);
    $this->assertStringContainsString('Non-streamed input', $normalized->getText());
  }

  /**
   * Tests max guardrail buffer size getter/setter on the iterator.
   */
  public function testMaxGuardrailBufferSizeConfiguration(): void {
    $input = new ChatInput([
      new ChatMessage('user', 'Test buffer'),
    ]);
    $input->setStreamedOutput(TRUE);
    $guardrail_helper = \Drupal::service('ai.guardrail_helper');
    $input = $guardrail_helper->applyGuardrailSetToChatInput('test_streaming_set', $input);

    $result = $this->provider->chat($input, 'gpt-test', ['test']);
    $iterator = $result->getNormalized();
    $this->assertInstanceOf(StreamedChatMessageIteratorInterface::class, $iterator);

    // Default should be 8192.
    $this->assertEquals(8192, $iterator->getMaxGuardrailBufferSize());

    // Should be configurable.
    $iterator->setMaxGuardrailBufferSize(16384);
    $this->assertEquals(16384, $iterator->getMaxGuardrailBufferSize());
  }

  /**
   * Tests that multiple streaming guardrail sets can coexist.
   *
   * Creates two guardrail entities with different markers and verifies both
   * are registered on the iterator when part of the same guardrail set.
   */
  public function testMultipleStreamingGuardrailsRegistered(): void {
    // Create a second guardrail with different markers.
    $guardrail2 = AiGuardrail::create([
      'id' => 'test_secret_stream',
      'label' => 'Test Secret Stream',
      'description' => 'Blocks content between [SECRET] markers.',
      'guardrail' => 'sensitive_content_stream',
      'guardrail_settings' => [
        'start_marker' => '[SECRET]',
        'stop_marker' => '[/SECRET]',
        'replacement_message' => '[Secret removed.]',
      ],
    ]);
    $guardrail2->save();

    // Update the guardrail set to include both.
    $guardrail_set = AiGuardrailSet::load('test_streaming_set');
    $guardrail_set->set('post_generate_guardrails', [
      'plugin_id' => ['test_sensitive_stream', 'test_secret_stream'],
    ]);
    $guardrail_set->save();

    $input = new ChatInput([
      new ChatMessage('user', 'Multi-guardrail test'),
    ]);
    $input->setStreamedOutput(TRUE);
    $guardrail_helper = \Drupal::service('ai.guardrail_helper');
    $input = $guardrail_helper->applyGuardrailSetToChatInput('test_streaming_set', $input);

    $result = $this->provider->chat($input, 'gpt-test', ['test']);
    $iterator = $result->getNormalized();
    $this->assertInstanceOf(StreamedChatMessageIteratorInterface::class, $iterator);

    // Both streaming guardrails should be registered.
    $guardrails = $iterator->getStreamingGuardrails();
    $this->assertCount(2, $guardrails);
  }

}
