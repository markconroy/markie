<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\ai_context\Model\AiContextConsumerRouteResult;
use Drupal\ai_context\Model\AiContextProviderRequestContext;
use Drupal\ai_context\Plugin\AiContextConsumerType\AiContextConsumerTypeBase;
use Drupal\ai_context\Service\AiContextSelectionFactory;
use Drupal\ai_translate\AiTranslateRequestTags;
use Drupal\ai_translate\Plugin\AiContextConsumerType\AiContextConsumerTypeTranslate;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Translate consumer type.
 *
 * @group ai_translate
 */
#[Group('ai_translate')]
#[CoversClass(AiContextConsumerTypeTranslate::class)]
#[RunTestsInSeparateProcesses]
final class AiContextConsumerTypeTranslateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'text',
    'filter',
    'options',
    'taxonomy',
    'key',
    'language',
    'content_translation',
    'content_moderation',
    'workflows',
    'views',
    'ai',
    'ai_agents',
    'ai_context',
    'ai_translate',
  ];

  /**
   * The Translate consumer type plugin.
   *
   * @var \Drupal\ai_translate\Plugin\AiContextConsumerType\AiContextConsumerTypeTranslate
   */
  private AiContextConsumerTypeTranslate $plugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ai_translate']);
    $this->plugin = $this->container
      ->get('plugin.manager.ai_context_consumer_type')
      ->createInstance(AiContextConsumerTypeTranslate::PLUGIN_ID);
  }

  /**
   * Tests availability, defaults, and routing tags.
   *
   * The type has no availability gate of its own: it is only discovered
   * once ai_context, which ships the consumer type API, is enabled.
   */
  public function testAvailabilityDefaultsAndRoutingTags(): void {
    $this->assertTrue($this->plugin->isAvailable());
    $this->assertTrue($this->plugin->isEnabled());
    // Translate does not override the base defaults. Push is off. The
    // selection_mode key comes from the AI Context base class: absent
    // on 1.0.0-beta5, inherit (Site default) from #3586535 on. Either
    // way an unsaved row follows the site setting at runtime.
    $defaults = $this->plugin->getInstanceDefaults();
    $this->assertFalse($defaults['push_enabled']);
    if (array_key_exists('selection_mode', $defaults)) {
      $this->assertSame('inherit', $defaults['selection_mode']);
    }
    $declaring = (new \ReflectionMethod($this->plugin, 'getInstanceDefaults'))
      ->getDeclaringClass()
      ->getName();
    $this->assertSame(AiContextConsumerTypeBase::class, $declaring);
    $this->assertSame(
      [AiTranslateRequestTags::ROUTING],
      $this->plugin->getRoutingRequestTags(),
    );
    $this->assertSame(
      ['ai_translate'],
      AiTranslateRequestTags::forChat(),
    );
  }

  /**
   * Tests the type exposes one default instance.
   */
  public function testInstancesExposeSingleDefault(): void {
    $collection = $this->plugin->getInstances();
    $this->assertTrue($collection->has('translate:default'));
    $this->assertCount(1, $collection);

    $instance = $collection->get('translate:default');
    $this->assertSame('default', $instance?->getInstanceId());
    $this->assertSame('AI Translate', $instance?->getLabel());
    $this->assertStringContainsString(
      'Native translate_text',
      (string) $instance?->getDescription(),
    );
    // The single instance is static and nothing gates it on site state,
    // so the collection carries no cache tags of its own.
    $this->assertSame([], $collection->getCacheTags());
  }

  /**
   * Tests the instance name links to AI Translate settings.
   */
  public function testLabelRoutePointsToSettingsForm(): void {
    $this->assertSame([
      'route_name' => 'ai_translate.settings_form',
    ], $this->plugin->getLabelRoute('default'));
    $this->assertNull($this->plugin->getLabelRoute('en-fr'));
  }

  /**
   * Tests matching uses the ai_translate tag.
   */
  public function testResolveConsumerIdRequiresTranslateTag(): void {
    $match = $this->plugin->resolveConsumerId(
      new AiContextProviderRequestContext(
        requestTags: ['ai_translate'],
        operationType: 'chat',
      ),
    );
    $this->assertSame('translate:default', $match?->toString());

    $this->assertNull($this->plugin->resolveConsumerId(
      new AiContextProviderRequestContext(
        requestTags: ['ai_ckeditor'],
        operationType: 'chat',
      ),
    ));
    $this->assertNull($this->plugin->resolveConsumerId(
      new AiContextProviderRequestContext(
        requestTags: [],
        operationType: 'chat',
      ),
    ));
  }

  /**
   * Tests the router keeps agent and assistant requests off this type.
   *
   * The plugin has no excluded-tag check of its own; the CCC router
   * returns no routing types for those tags, so resolveConsumerId() is
   * never asked about them.
   */
  public function testRouterNeverRoutesAgentAndAssistantTagsHere(): void {
    $router = $this->container->get('ai_context.consumer_router');

    foreach ([
      ['ai_agents'],
      ['ai_assistant_api'],
      ['ai_agents', 'ai_translate'],
      ['ai_assistant_api', 'ai_translate'],
    ] as $tags) {
      $result = $router->routeRequest(new AiContextProviderRequestContext(
        requestTags: $tags,
        operationType: 'chat',
      ));
      $this->assertNull($result->getConsumerId(), implode(',', $tags));
      $this->assertNotSame(
        AiContextConsumerRouteResult::RESOLVED,
        $result->getStatus(),
        implode(',', $tags),
      );
    }

    $result = $router->routeRequest(new AiContextProviderRequestContext(
      requestTags: ['ai_translate'],
      operationType: 'chat',
    ));
    $this->assertSame(AiContextConsumerRouteResult::RESOLVED, $result->getStatus());
    $this->assertSame(
      AiContextConsumerTypeTranslate::CONSUMER_ID,
      $result->getConsumerId()?->toString(),
    );
  }

  /**
   * Tests push stays off until an instance override enables it.
   */
  public function testPushDefaultsOff(): void {
    $factory = $this->container->get('ai_context.selection_factory');
    $this->assertInstanceOf(AiContextSelectionFactory::class, $factory);
    $this->assertFalse(
      $factory->isPushAllowed('translate:default'),
    );
    $this->assertNull(
      $factory->resolveConsumerConfig('translate:default')
        ->getSelectionMode(),
    );

    $this->container->get('config.storage')->write('ai_context.consumers', [
      'consumers' => [
        [
          'consumer_id' => 'translate:default',
          'type' => 'translate',
          'push_enabled' => TRUE,
        ],
      ],
    ]);
    $this->container->get('config.factory')->reset('ai_context.consumers');
    $this->assertTrue(
      $factory->isPushAllowed('translate:default'),
    );
    $this->assertNull(
      $factory->resolveConsumerConfig('translate:default')
        ->getSelectionMode(),
    );
  }

}
