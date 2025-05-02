<?php

namespace Drupal\Tests\entity_usage\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests redirect base fields are tracked automatically.
 *
 * @group entity_usage
 *
 * @see \Drupal\entity_usage\\Drupal\entity_usage\EntityUsageTrackBase::getReferencingFields
 */
class EntityUsageRedirectTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test', 'entity_usage', 'path_alias', 'link', 'redirect'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['entity_usage']);
    $this->config('entity_usage.settings')
      ->set('local_task_enabled_entity_types', ['entity_test'])
      ->set('track_enabled_source_entity_types', ['entity_test', 'redirect'])
      ->set('track_enabled_plugins', ['link'])
      ->set('track_enabled_base_fields', FALSE)
      ->save();
    $this->installEntitySchema('redirect');
    $this->installEntitySchema('path_alias');
    $this->installSchema('entity_usage', ['entity_usage']);
  }

  /**
   * Tests redirect base fields tracked when base field tracking is disabled.
   */
  public function testFindEntityIdByUrlWithRedirect(): void {
    $entity_test = EntityTest::create(['name' => $this->randomMachineName()]);
    $entity_test->save();

    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');

    // Create a redirect that points to the test entity created above.
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $storage->create();
    $redirect->setSource('some-url');
    $redirect->setRedirect($entity_test->toUrl()->toString());
    $redirect->save();

    $usage = $this->container->get('entity_usage.usage')->listSources($entity_test, FALSE);
    $this->assertEquals(1, count($usage));
    $this->assertSame([
      "source_type" => "redirect",
      "source_id" => "1",
      "source_langcode" => "en",
      "source_vid" => "0",
      "method" => "link",
      "field_name" => "redirect_redirect",
      "count" => "1",
    ], $usage[0]);
  }

}
