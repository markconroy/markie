<?php

namespace Drupal\Tests\entity_usage\Kernel;

use Drupal\Core\Url;
use Drupal\entity_usage\UrlToEntityInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\path_alias\Entity\PathAlias;

/**
 * Tests the \Drupal\entity_usage\UrlToEntity service.
 *
 * No entity test entities are created during testing to prove that they are not
 * loaded.
 *
 * @group entity_usage
 *
 * @see \Drupal\entity_usage\UrlToEntity
 */
class UrlToEntityTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test', 'entity_usage', 'path_alias'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');
  }

  /**
   * Tests finding an entity by a URL.
   */
  public function testFindEntityIdByUrl(): void {
    // Service to test.
    $url_to_entity = $this->container->get(UrlToEntityInterface::class);

    $this->assertSame(['type' => 'entity_test', 'id' => '1'], $url_to_entity->findEntityIdByUrl('entity_test/1'));

    $this->assertNull($url_to_entity->findEntityIdByUrl('node/1'), 'Entity URLs for non-existing entity types do not resolve.');

    $path_alias = PathAlias::create([
      'alias' => '/testing_alias',
      'path' => '/entity_test/1',
    ]);
    $path_alias->save();

    $this->assertSame(['type' => 'entity_test', 'id' => '1'], $url_to_entity->findEntityIdByUrl('/testing_alias'));
  }

  /**
   * Tests finding an entity by a routed URL.
   */
  public function testFindEntityIdByRoutedUrl(): void {
    // Service to test.
    $url_to_entity = $this->container->get(UrlToEntityInterface::class);

    $this->assertSame(['type' => 'entity_test', 'id' => '1'], $url_to_entity->findEntityIdByRoutedUrl(Url::fromUri('entity:entity_test/1')));

    // Even though node does not exist the routed URL still works because we do
    // no entity loading.
    $this->assertSame(['type' => 'node', 'id' => '1'], $url_to_entity->findEntityIdByRoutedUrl(Url::fromUri('entity:node/1')));

    $this->container->get('kernel')->resetContainer();
    $this->config('entity_usage.settings')->set('track_enabled_target_entity_types', ['entity_test'])->save();
    $url_to_entity = $this->container->get(UrlToEntityInterface::class);
    $this->assertSame(['type' => 'entity_test', 'id' => '1'], $url_to_entity->findEntityIdByRoutedUrl(Url::fromUri('entity:entity_test/1')));
    $this->assertNull($url_to_entity->findEntityIdByRoutedUrl(Url::fromUri('entity:node/1')));
  }

}
