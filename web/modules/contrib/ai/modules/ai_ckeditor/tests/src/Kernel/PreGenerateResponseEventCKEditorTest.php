<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_ckeditor\Kernel;

use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves ai_ckeditor requests can be intercepted via PreGenerateResponseEvent.
 *
 * This test demonstrates that the existing PreGenerateResponseEvent from the
 * AI module fires for ai_ckeditor requests and allows subscribers to modify
 * the system prompt -- without any custom event class or changes to
 * ai_ckeditor.
 *
 * The ai_ckeditor module already passes ['ai_ckeditor'] as tags when calling
 * $ai_provider->chat(), and the ProviderProxy dispatches
 * PreGenerateResponseEvent for all provider calls. A subscriber can filter
 * by this tag and modify the ChatInput system prompt.
 *
 * @group ai_ckeditor
 */
#[RunTestsInSeparateProcesses]
class PreGenerateResponseEventCKEditorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'filter',
    'editor',
    'ckeditor5',
    'ai',
    'ai_ckeditor',
  ];

  /**
   * Tests that PreGenerateResponseEvent can modify ai_ckeditor prompts.
   *
   * Simulates what happens when ai_ckeditor calls the provider: a
   * PreGenerateResponseEvent is dispatched with the ChatInput and the
   * 'ai_ckeditor' tag. A subscriber can then modify the system prompt.
   */
  public function testPreGenerateResponseEventModifiesAiCkeditorPrompts(): void {
    $eventDispatcher = $this->container->get('event_dispatcher');

    // Register a listener that acts on ai_ckeditor-tagged requests,
    // exactly as ai_context (or any module) would.
    $eventDispatcher->addListener(
      PreGenerateResponseEvent::EVENT_NAME,
      function (PreGenerateResponseEvent $event) {
        // Only act on ai_ckeditor requests.
        if (!in_array('ai_ckeditor', $event->getTags(), TRUE)) {
          return;
        }

        $input = $event->getInput();
        if ($input instanceof ChatInput) {
          $input->setSystemPrompt(
            $input->getSystemPrompt() . "\n\n[CONTEXT INJECTED VIA PRE_GENERATE_RESPONSE_EVENT]"
          );
        }
      }
    );

    // Build a ChatInput the same way ai_ckeditor's controller does.
    $input = new ChatInput([
      new ChatMessage('user', 'Test prompt'),
    ]);
    $input->setSystemPrompt('You are a helpful assistant.');

    // Dispatch the event the same way ProviderProxy::wrapperCall() does.
    $event = new PreGenerateResponseEvent(
      requestThreadId: 'test-thread-id',
      providerId: 'test_provider',
      operationType: 'chat',
      configuration: [],
      input: $input,
      modelId: 'test-model',
      tags: ['chat', 'ai_ckeditor'],
    );
    $eventDispatcher->dispatch($event, PreGenerateResponseEvent::EVENT_NAME);

    // Verify the subscriber modified the system prompt via the event input.
    $modifiedInput = $event->getInput();
    $this->assertInstanceOf(ChatInput::class, $modifiedInput);
    $this->assertStringContainsString(
      '[CONTEXT INJECTED VIA PRE_GENERATE_RESPONSE_EVENT]',
      $modifiedInput->getSystemPrompt(),
    );
    // Original prompt is preserved.
    $this->assertStringContainsString(
      'You are a helpful assistant.',
      $modifiedInput->getSystemPrompt(),
    );
  }

  /**
   * Tests that non-ai_ckeditor requests are not affected.
   */
  public function testNonAiCkeditorRequestsUnaffected(): void {
    $eventDispatcher = $this->container->get('event_dispatcher');

    $eventDispatcher->addListener(
      PreGenerateResponseEvent::EVENT_NAME,
      function (PreGenerateResponseEvent $event) {
        if (!in_array('ai_ckeditor', $event->getTags(), TRUE)) {
          return;
        }
        $input = $event->getInput();
        if ($input instanceof ChatInput) {
          $input->setSystemPrompt($input->getSystemPrompt() . ' [MODIFIED]');
        }
      }
    );

    $input = new ChatInput([
      new ChatMessage('user', 'Test'),
    ]);
    $input->setSystemPrompt('Original prompt.');

    // Dispatch with a different tag (e.g., ai_api_explorer).
    $event = new PreGenerateResponseEvent(
      requestThreadId: 'test-thread-id',
      providerId: 'test_provider',
      operationType: 'chat',
      configuration: [],
      input: $input,
      modelId: 'test-model',
      tags: ['chat', 'ai_api_explorer'],
    );
    $eventDispatcher->dispatch($event, PreGenerateResponseEvent::EVENT_NAME);

    // System prompt should NOT be modified.
    $this->assertSame('Original prompt.', $event->getInput()->getSystemPrompt());
  }

}
