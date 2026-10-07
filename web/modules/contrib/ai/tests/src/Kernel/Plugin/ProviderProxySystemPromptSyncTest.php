<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;

/**
 * Tests that system prompt changes made in the pre event reach the provider.
 *
 * Providers still consume the deprecated provider-level chat system role,
 * which ProviderProxy::wrapperCall() seeds from the input before dispatching
 * PreGenerateResponseEvent. A subscriber that modifies the input's system
 * prompt (e.g. ai_context appending directed context) therefore needs the
 * proxy to re-sync the chat system role after the event, or the change never
 * reaches the LLM call.
 *
 * @coversDefaultClass \Drupal\ai\Plugin\ProviderProxy
 *
 * @group ai
 */
class ProviderProxySystemPromptSyncTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'user',
    'field',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['ai', 'ai_test']);
    $this->installEntitySchema('ai_mock_provider_result');
  }

  /**
   * A subscriber-modified system prompt is synced back to the provider.
   *
   * @covers ::wrapperCall
   */
  public function testSubscriberModifiedSystemPromptReachesProvider(): void {
    $this->container->get('event_dispatcher')->addListener(
      PreGenerateResponseEvent::EVENT_NAME,
      function (PreGenerateResponseEvent $event): void {
        $input = $event->getInput();
        if ($input instanceof ChatInput) {
          $input->setSystemPrompt($input->getSystemPrompt() . ' INJECTED-BY-SUBSCRIBER');
        }
      },
    );

    $provider = $this->container->get('ai.provider')->createInstance('echoai');
    $input = new ChatInput([
      new ChatMessage('user', 'Hello there.'),
    ]);
    $input->setSystemPrompt('Original prompt.');
    $provider->chat($input, 'test');

    // The input carries the modification.
    $this->assertSame('Original prompt. INJECTED-BY-SUBSCRIBER', $input->getSystemPrompt());
    // The provider-level chat system role, which providers actually read
    // when building the request, must carry it too.
    $this->assertSame('Original prompt. INJECTED-BY-SUBSCRIBER', $provider->getChatSystemRole());
  }

  /**
   * The same sync applies on the streamed chat path.
   *
   * @covers ::wrapperCall
   */
  public function testSubscriberModifiedSystemPromptReachesProviderStreamed(): void {
    $this->container->get('event_dispatcher')->addListener(
      PreGenerateResponseEvent::EVENT_NAME,
      function (PreGenerateResponseEvent $event): void {
        $input = $event->getInput();
        if ($input instanceof ChatInput) {
          $input->setSystemPrompt($input->getSystemPrompt() . ' INJECTED-BY-SUBSCRIBER');
        }
      },
    );

    $provider = $this->container->get('ai.provider')->createInstance('echoai');
    $input = new ChatInput([
      new ChatMessage('user', 'Hello there.'),
    ]);
    $input->setSystemPrompt('Original prompt.');
    $input->setStreamedOutput(TRUE);
    $provider->chat($input, 'test');

    $this->assertSame('Original prompt. INJECTED-BY-SUBSCRIBER', $provider->getChatSystemRole());
  }

  /**
   * Without a subscriber the original system prompt is left untouched.
   *
   * @covers ::wrapperCall
   */
  public function testSystemPromptUnchangedWithoutSubscriber(): void {
    $provider = $this->container->get('ai.provider')->createInstance('echoai');
    $input = new ChatInput([
      new ChatMessage('user', 'Hello there.'),
    ]);
    $input->setSystemPrompt('Original prompt.');
    $provider->chat($input, 'test');

    $this->assertSame('Original prompt.', $input->getSystemPrompt());
    $this->assertSame('Original prompt.', $provider->getChatSystemRole());
  }

}
