<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel;

use Drupal\ai\OperationType\Embeddings\EmbeddingsCollectionInput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsCollectionOutput;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Event\PreGenerateResponseEvent;

/**
 * Tests that embeddingsCollection() routes through the ProviderProxy pipeline.
 *
 * Because embeddingsCollection is a first-class operation type, calling it
 * through the provider proxy must dispatch the PreGenerateResponseEvent (so
 * logging, guardrails and other subscribers run) like single embeddings.
 *
 * @group ai
 */
class EmbeddingsCollectionProxyEventTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'system',
  ];

  /**
   * The batch embeddings call dispatches the pre-generate response event.
   */
  public function testEmbeddingsCollectionDispatchesPreGenerateEvent(): void {
    $operations = [];
    $this->container->get('event_dispatcher')->addListener(
      PreGenerateResponseEvent::EVENT_NAME,
      function (PreGenerateResponseEvent $event) use (&$operations) {
        $operations[] = $event->getOperationType();
      },
    );

    $provider = $this->container->get('ai.provider')->createInstance('echoai');
    $output = $provider->embeddingsCollection(
      new EmbeddingsCollectionInput(['a', 'bb', 'ccc']),
      'test',
      [],
    );

    // The event fired for the embeddings_collection operation type.
    $this->assertContains('embeddings_collection', $operations);
    // And the call still returns one vector per input.
    $this->assertInstanceOf(EmbeddingsCollectionOutput::class, $output);
    $this->assertCount(3, $output->getNormalized());
  }

}
