<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Event\AiExceptionEvent;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;

/**
 * Tests request metadata propagation through ProviderProxy::wrapperCall().
 *
 * Callers attach directed context to the input's request metadata bag
 * (e.g. ai_ckeditor's entity_context). The proxy must seed the pre event
 * from the input, carry subscriber changes forward to the post event,
 * attach the metadata to streamed iterators, and expose it on the
 * exception event so failover subscribers know the request context.
 *
 * @coversDefaultClass \Drupal\ai\Plugin\ProviderProxy
 *
 * @group ai
 */
class ProviderProxyRequestMetadataTest extends KernelTestBase {

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
   * The entity context used as request metadata in the tests.
   *
   * @var array
   */
  protected array $entityContext = [
    'entity_type' => 'node',
    'bundle' => 'blog_post',
    'id' => '42',
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
   * Builds a chat input carrying entity context request metadata.
   */
  protected function getInputWithMetadata(): ChatInput {
    $input = new ChatInput([
      new ChatMessage('user', 'Hello there.'),
    ]);
    $input->setRequestMetadataValue('entity_context', $this->entityContext);
    return $input;
  }

  /**
   * Input metadata is seeded to the pre event and carried to the post event.
   *
   * @covers ::wrapperCall
   */
  public function testMetadataSeededToPreEventAndCarriedToPostEvent(): void {
    $pre_metadata = NULL;
    $post_metadata = NULL;
    $dispatcher = $this->container->get('event_dispatcher');
    $dispatcher->addListener(
      PreGenerateResponseEvent::EVENT_NAME,
      function (PreGenerateResponseEvent $event) use (&$pre_metadata): void {
        $pre_metadata = $event->getMetadata('entity_context');
        // Subscribers may add their own metadata during the pre event; the
        // post event must carry it forward.
        $event->setMetadata('added_by_subscriber', 'added-value');
      },
    );
    $dispatcher->addListener(
      PostGenerateResponseEvent::EVENT_NAME,
      function (PostGenerateResponseEvent $event) use (&$post_metadata): void {
        $post_metadata = $event->getAllMetadata();
      },
    );

    $provider = $this->container->get('ai.provider')->createInstance('echoai');
    $provider->chat($this->getInputWithMetadata(), 'test');

    // The pre event was seeded from the input's request metadata, with the
    // complex structure kept intact.
    $this->assertSame($this->entityContext, $pre_metadata);
    // The post event carries both the seeded and the subscriber-added
    // metadata.
    $this->assertIsArray($post_metadata);
    $this->assertSame($this->entityContext, $post_metadata['entity_context']);
    $this->assertSame('added-value', $post_metadata['added_by_subscriber']);
  }

  /**
   * Inputs without request metadata produce events with empty metadata.
   *
   * @covers ::wrapperCall
   */
  public function testNoMetadataWhenInputHasNone(): void {
    $pre_metadata = ['not-called'];
    $this->container->get('event_dispatcher')->addListener(
      PreGenerateResponseEvent::EVENT_NAME,
      function (PreGenerateResponseEvent $event) use (&$pre_metadata): void {
        $pre_metadata = $event->getAllMetadata();
      },
    );

    $provider = $this->container->get('ai.provider')->createInstance('echoai');
    $input = new ChatInput([
      new ChatMessage('user', 'Hello there.'),
    ]);
    $provider->chat($input, 'test');

    $this->assertSame([], $pre_metadata);
  }

  /**
   * Streamed chat iterators receive the request metadata.
   *
   * @covers ::wrapperCall
   * @covers ::attachStreamMetadata
   */
  public function testStreamedIteratorReceivesMetadata(): void {
    $provider = $this->container->get('ai.provider')->createInstance('echoai');
    $input = $this->getInputWithMetadata();
    $input->setStreamedOutput(TRUE);
    $response = $provider->chat($input, 'test')->getNormalized();

    $this->assertInstanceOf(StreamedChatMessageIteratorInterface::class, $response);
    $metadata = $response->getMetadata();
    $this->assertSame($this->entityContext, $metadata['entity_context']);
  }

  /**
   * The exception event carries the request metadata for failover handling.
   *
   * @covers ::wrapperCall
   */
  public function testExceptionEventCarriesMetadata(): void {
    $exception_metadata = NULL;
    $this->container->get('event_dispatcher')->addListener(
      AiExceptionEvent::class,
      function (AiExceptionEvent $event) use (&$exception_metadata): void {
        $exception_metadata = $event->getAllMetadata();
      },
    );
    $provider = $this->container->get('ai.provider')->createInstance('echoai');
    try {
      // The test_exception model makes the echo provider throw during the
      // provider invocation, after the pre event has been dispatched.
      $provider->chat($this->getInputWithMetadata(), 'test_exception');
      $this->fail('An exception should have been thrown by the provider.');
    }
    catch (AiResponseErrorException $e) {
      // The exception event must have been dispatched with the request
      // metadata attached.
      $this->assertIsArray($exception_metadata);
      $this->assertSame($this->entityContext, $exception_metadata['entity_context']);
    }
  }

}
