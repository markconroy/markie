<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Service;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\CommonMarkConverterFactoryInterface;
use League\CommonMark\CommonMarkConverter;

/**
 * Tests CommonMark converter service registration.
 *
 * @group ai
 */
class CommonMarkConverterServiceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ai'];

  /**
   * The factory and default converter are registered and aliased.
   */
  public function testServicesAreRegistered(): void {
    $factory = $this->container->get('ai.commonmark_converter_factory');
    $this->assertInstanceOf(CommonMarkConverterFactoryInterface::class, $factory);
    $this->assertSame($factory, $this->container->get(CommonMarkConverterFactoryInterface::class));

    $converter = $this->container->get('ai.commonmark_converter');
    $this->assertInstanceOf(CommonMarkConverter::class, $converter);
    $this->assertSame($converter, $this->container->get(CommonMarkConverter::class));
  }

  /**
   * The default converter uses the module's default options.
   */
  public function testDefaultConverterUsesDefaultOptions(): void {
    /** @var \League\CommonMark\CommonMarkConverter $converter */
    $converter = $this->container->get('ai.commonmark_converter');
    $html = $converter->convert('<b>raw</b> [x](javascript:alert(1))')->getContent();
    $this->assertStringNotContainsString('<b>', $html);
    $this->assertStringNotContainsString('javascript:', $html);
  }

}
