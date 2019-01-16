<?php

namespace Drupal\Tests\jsonapi\Kernel\Controller;

use Drupal\jsonapi\Controller\EntryPoint;
use Drupal\Tests\jsonapi\Kernel\JsonapiKernelTestBase;

/**
 * @coversDefaultClass \Drupal\jsonapi\Controller\EntryPoint
 * @group jsonapi
 * @group legacy
 *
 * @internal
 */
class EntryPointTest extends JsonapiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'node',
    'jsonapi',
    'serialization',
    'system',
    'user',
  ];

  /**
   * @covers ::index
   */
  public function testIndex() {
    $controller = new EntryPoint(
      \Drupal::service('jsonapi.resource_type.repository'),
      \Drupal::service('renderer'),
      \Drupal::service('current_user')
    );
    $processed_response = $controller->index();
    $this->assertEquals(
      [
        'url.site',
        'user.roles:authenticated',
      ],
      $processed_response->getCacheableMetadata()->getCacheContexts()
    );
    $links = $processed_response->getResponseData()->getLinks();
    $this->assertRegExp('/.*\/jsonapi/', $links['self']['href']);
    $this->assertRegExp('/.*\/jsonapi\/user\/user/', $links['user--user']['href']);
    $this->assertRegExp('/.*\/jsonapi\/node_type\/node_type/', $links['node_type--node_type']['href']);
    $this->assertSame([], $processed_response->getResponseData()->getMeta());
  }

}
