<?php

declare(strict_types=1);

namespace Drupal\Tests\simple_sitemap_engines\Kernel;

use Drupal\Core\CronInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\State\StateInterface;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;

/**
 * Tests search engine sitemap submission.
 *
 * @group simple_sitemap_engines
 */
class SitemapSubmitterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'simple_sitemap',
    'simple_sitemap_engines',
    'simple_sitemap_engines_test',
  ];

  /**
   * The cron service.
   */
  protected CronInterface $cron;

  /**
   * The queue of search engines to submit sitemaps.
   */
  protected QueueInterface $queue;

  /**
   * The state service.
   */
  protected StateInterface $state;

  /**
   * The sitemap submission URL.
   */
  protected string $url;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('simple_sitemap', 'simple_sitemap');
    $this->installConfig(['system', 'simple_sitemap', 'simple_sitemap_engines', 'simple_sitemap_engines_test']);

    $this->cron = \Drupal::service('cron');
    $this->queue = \Drupal::queue('simple_sitemap_engine_submit');
    $this->state = \Drupal::state();

    /** @var \Drupal\simple_sitemap_engines\Entity\SimpleSitemapEngine $engine */
    $engine = \Drupal::entityTypeManager()->getStorage('simple_sitemap_engine')->load('test');
    $sitemap = \Drupal::entityTypeManager()->getStorage('simple_sitemap')->load('default');
    $this->url = str_replace('[sitemap]', $sitemap->toUrl()->toString(), $engine->url);
  }

  /**
   * Tests sitemap submission URLs and last submission status.
   */
  public function testSubmission(): void {
    // Create a mock HTTP client.
    $http_client = $this->prophesize(ClientInterface::class);
    // Make mock HTTP requests always succeed.
    $http_client->request('GET', Argument::any())->willReturn(new Response());
    // Replace the default HTTP client service with the mock.
    $this->container->set('http_client', $http_client->reveal());

    // Run cron to trigger submission.
    $this->cron->run();

    // Check that Test was marked as submitted and Bing was not.
    $this->assertNotEmpty($this->getLastSubmitted('test'));
    $this->assertEmpty($this->getLastSubmitted('bing'));

    // Check that exactly 1 HTTP request was sent to the correct URL.
    $http_client->request('GET', $this->url)->shouldBeCalled();
    $http_client->request('GET', Argument::any())->shouldBeCalledTimes(1);
  }

  /**
   * Tests that sitemaps are not submitted every time cron runs.
   */
  public function testNoDoubleSubmission(): void {
    // Create a mock HTTP client.
    $http_client = $this->prophesize(ClientInterface::class);
    // Make mock HTTP requests always succeed.
    $http_client->request('GET', Argument::any())->willReturn(new Response());
    // Replace the default HTTP client service with the mock.
    $this->container->set('http_client', $http_client->reveal());

    // Run cron to trigger submission.
    $this->cron->run();

    // Check that Test was submitted and store its last submitted time.
    $http_client->request('GET', $this->url)->shouldBeCalledTimes(1);
    $last_submitted = $this->getLastSubmitted('test');
    $this->assertNotEmpty($last_submitted);

    // Make sure enough time passes between cron runs to guarantee that they
    // do not run within the same second, since timestamps are compared below.
    sleep(2);
    $this->cron->run();

    // Check that the last submitted time was not updated on the second cron
    // run.
    $this->assertEquals($last_submitted, $this->getLastSubmitted('test'));
    // Check that no duplicate request was sent.
    $http_client->request('GET', $this->url)->shouldBeCalledTimes(1);
  }

  /**
   * Tests that failed sitemap submissions are handled properly.
   */
  public function testFailedSubmission(): void {
    // Create a mock HTTP client.
    $http_client = $this->prophesize(ClientInterface::class);
    // Make mock HTTP requests always fail.
    $http_client->request('GET', Argument::any())->willThrow(RequestException::class);
    // Replace the default HTTP client service with the mock.
    $this->container->set('http_client', $http_client->reveal());

    // Run cron to trigger submission.
    $this->cron->run();

    // Check that one request was attempted.
    $http_client->request('GET', Argument::any())->shouldBeCalledTimes(1);
    // Check the last submission time is still empty.
    $this->assertEmpty($this->getLastSubmitted('test'));
    // Check that the submission was removed from the queue despite failure.
    $this->assertEquals(0, $this->queue->numberOfItems());
  }

  /**
   * Gets the last submission time for the given engine.
   *
   * @param string $engine_id
   *   ID of search engine.
   *
   * @return int|null
   *   The last submission time or NULL if there was no submission.
   */
  protected function getLastSubmitted(string $engine_id): ?int {
    return $this->state->get("simple_sitemap_engines.simple_sitemap_engine.$engine_id.last_submitted");
  }

}
