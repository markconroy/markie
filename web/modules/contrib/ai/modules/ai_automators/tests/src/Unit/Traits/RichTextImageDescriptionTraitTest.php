<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Unit\Traits;

use Drupal\ai_automators\PluginBaseClasses\RuleBase;
use Drupal\ai_automators\Rulehelpers\RichTextImageHelper;
use Drupal\ai_automators\Service\RichTextImageDescriptionService;
use Drupal\ai_automators\Traits\RichTextImageDescriptionTrait;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Tests robust external image fetching in the rich-text image trait.
 *
 * @group ai_automators
 */
class RichTextImageDescriptionTraitTest extends UnitTestCase {

  /**
   * A 1x1 transparent GIF used as a valid image payload.
   */
  protected const VALID_GIF = "GIF89a\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00\x21\xf9\x04\x01\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x01\x44\x00\x3b";

  /**
   * Tests a successful fetch returns binary, mime and filename.
   */
  public function testFetchSuccess(): void {
    $this->setUpContainer(new Response(200, ['Content-Type' => 'image/gif'], self::VALID_GIF));

    $result = $this->callFetch('https://example.com/images/cat.gif');

    $this->assertIsArray($result);
    $this->assertSame(self::VALID_GIF, $result['binary']);
    $this->assertSame('image/gif', $result['mime']);
    $this->assertSame('cat.gif', $result['filename']);
  }

  /**
   * Tests the mime type falls back to the detected image type.
   *
   * When the response omits (or misreports) the content type, the mime is
   * derived from the binary itself rather than trusting the header.
   */
  public function testFetchSuccessFallsBackToDetectedMime(): void {
    $this->setUpContainer(new Response(200, ['Content-Type' => 'application/octet-stream'], self::VALID_GIF));

    $result = $this->callFetch('https://example.com/cat');

    $this->assertIsArray($result);
    $this->assertSame('image/gif', $result['mime']);
    // Empty path basename falls back to a generic filename.
    $this->assertSame('cat', $result['filename']);
  }

  /**
   * Tests a 403 response is rejected and returns NULL.
   */
  public function testFetchRejectsForbidden(): void {
    $this->setUpContainer(new Response(403, [], 'Forbidden'));

    $this->assertNull($this->callFetch('https://example.com/blocked.jpg'));
  }

  /**
   * Tests redirects are rejected and not followed.
   */
  public function testFetchRejectsRedirects(): void {
    $this->setUpContainer(new Response(302, ['Location' => 'https://example.com/real.jpg'], ''));

    $this->assertNull($this->callFetch('https://example.com/redirect.jpg'));
  }

  /**
   * Tests a non-image 200 response (e.g. an HTML error page) returns NULL.
   */
  public function testFetchRejectsNonImage(): void {
    $this->setUpContainer(new Response(200, ['Content-Type' => 'text/html'], '<html><body>Not found</body></html>'));

    $this->assertNull($this->callFetch('https://example.com/oops.jpg'));
  }

  /**
   * Tests an empty body returns NULL.
   */
  public function testFetchRejectsEmptyBody(): void {
    $this->setUpContainer(new Response(200, ['Content-Type' => 'image/gif'], ''));

    $this->assertNull($this->callFetch('https://example.com/empty.gif'));
  }

  /**
   * Builds the Drupal container with a mocked HTTP client and logger.
   *
   * @param \GuzzleHttp\Psr7\Response $response
   *   The canned response the mocked HTTP client returns.
   */
  protected function setUpContainer(Response $response): void {
    $client = new Client([
      'handler' => HandlerStack::create(new MockHandler([$response])),
    ]);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);

    $helper = $this->createMock(RichTextImageHelper::class);
    $service = new RichTextImageDescriptionService($helper, $client, $factory);

    $container = new ContainerBuilder();
    $container->set('ai_automator.rich_text_image_description', $service);
    \Drupal::setContainer($container);
  }

  /**
   * Invokes the protected fetchRemoteImageData() on a trait-using object.
   *
   * @param string $url
   *   The URL to fetch.
   *
   * @return array<string,string>|null
   *   The fetch result.
   */
  protected function callFetch(string $url): ?array {
    $object = (new \ReflectionClass(FetchTestableRule::class))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($object, 'fetchRemoteImageData');
    $method->setAccessible(TRUE);
    return $method->invoke($object, $url);
  }

}

/**
 * Testable rule exposing the real fetchRemoteImageData() from the trait.
 *
 * Extends RuleBase so the trait's other methods resolve against a real host
 * class; only fetchRemoteImageData() is exercised here.
 */
class FetchTestableRule extends RuleBase {

  use RichTextImageDescriptionTrait;

  /**
   * {@inheritdoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
  }

  /**
   * {@inheritdoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    return TRUE;
  }

}
