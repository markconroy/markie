<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use Drupal\ai\AiServiceProvider;
use Drupal\ai\Service\HtmlToMarkdown\HtmlToMarkdownConverter;
use Drupal\ai\Service\HtmlToMarkdown\MarkdownifyHtmlToMarkdownAdapter;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @coversDefaultClass \Drupal\ai\AiServiceProvider
 * @group ai
 */
class AiServiceProviderTest extends UnitTestCase {

  /**
   * Builds a container with the ai.html_to_markdown_converter definition.
   */
  protected function createContainer(): ContainerBuilder {
    $container = new ContainerBuilder();
    $container->register('ai.html_to_markdown_converter', HtmlToMarkdownConverter::class)
      ->addArgument(new Reference('config.factory'));
    return $container;
  }

  /**
   * @covers ::alter
   */
  public function testDefaultConverterIsKeptWhenMarkdownifyIsNotInstalled(): void {
    $container = $this->createContainer();

    (new AiServiceProvider())->alter($container);

    $definition = $container->getDefinition('ai.html_to_markdown_converter');
    $this->assertSame(HtmlToMarkdownConverter::class, $definition->getClass());
  }

  /**
   * @covers ::alter
   */
  public function testConverterIsSwappedForMarkdownifyAdapterWhenInstalled(): void {
    $container = $this->createContainer();
    $container->register('markdownify.html_converter', 'Drupal\markdownify\Service\MarkdownifyHtmlConverter');

    (new AiServiceProvider())->alter($container);

    $definition = $container->getDefinition('ai.html_to_markdown_converter');
    $this->assertSame(MarkdownifyHtmlToMarkdownAdapter::class, $definition->getClass());
    $arguments = $definition->getArguments();
    $this->assertCount(2, $arguments);
    $this->assertInstanceOf(Reference::class, $arguments[0]);
    $this->assertSame('markdownify.html_converter', (string) $arguments[0]);
    $this->assertInstanceOf(Reference::class, $arguments[1]);
    $this->assertSame('config.factory', (string) $arguments[1]);
  }

}
