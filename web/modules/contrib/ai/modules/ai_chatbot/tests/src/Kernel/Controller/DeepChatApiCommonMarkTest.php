<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_chatbot\Kernel\Controller;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\CommonMarkConverterFactoryInterface;
use Drupal\ai_chatbot\Controller\DeepChatApi;
use Drupal\ai_chatbot\Plugin\Block\DeepChatFormBlock;

/**
 * Tests the CommonMark converter wiring in the ai_chatbot module.
 *
 * @group ai_chatbot
 */
class DeepChatApiCommonMarkTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'token',
    'key',
    'ai',
    'ai_assistant_api',
    'ai_chatbot',
  ];

  /**
   * The controller uses the shared converter service, not its own instance.
   */
  public function testControllerUsesSharedConverterService(): void {
    $controller = DeepChatApi::create($this->container);
    $this->assertSame($this->container->get('ai.commonmark_converter'), $controller->getCommonMarkConverter());
  }

  /**
   * The injected converter applies the module defaults to model output.
   *
   * Raw HTML and unsafe links from the model are removed, while markdown
   * images are still rendered, so media embedding keeps working.
   */
  public function testConverterAppliesModuleDefaults(): void {
    $controller = DeepChatApi::create($this->container);
    $converter = $controller->getCommonMarkConverter();

    $markdown = "<b>raw</b> [click](javascript:alert(1))\n\n![media](/files/cat.png)";
    $html = $converter->convert($markdown)->getContent();

    $this->assertStringNotContainsString('<b>', $html);
    $this->assertStringNotContainsString('javascript:', $html);
    $this->assertStringContainsString('<img src="/files/cat.png"', $html);
  }

  /**
   * The block gets the converter factory injected through create().
   */
  public function testDeepChatFormBlockReceivesConverterFactory(): void {
    $block = $this->container->get('plugin.manager.block')->createInstance('ai_deepchat_block');
    $this->assertInstanceOf(DeepChatFormBlock::class, $block);

    $property = new \ReflectionProperty(DeepChatFormBlock::class, 'commonMarkConverterFactory');
    $this->assertSame($this->container->get(CommonMarkConverterFactoryInterface::class), $property->getValue($block));
  }

}
