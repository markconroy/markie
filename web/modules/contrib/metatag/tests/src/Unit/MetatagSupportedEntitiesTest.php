<?php

namespace Drupal\Tests\metatag\Unit;

use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\metatag\Form\MetatagDefaultsForm;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * This class validates all the entities types that are supported by metatag.
 *
 * @group metatag
 */
class MetatagSupportedEntitiesTest extends UnitTestCase {

  /**
   * Tests the getSupportedEntityTypes method from MetatagDefaultsForm.
   */
  public function testGetSupportedEntityTypes() {
    // Create a mock entity type manager.
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);

    // Create a mock entity type definition.
    $bad_entity_type = $this->createMock(ContentEntityType::class);
    $bad_entity_type
      ->method('get')
      ->withAnyParameters()
      ->willReturn([
        'canonical' => '/hello/mum',
        'edit-form' => '/edit/{entity}',
      ]);
    $bad_entity_type
      ->method('getLabel')
      ->withAnyParameters()
      ->willReturn('Example entity type');
    $bad_entity_type
      ->method('isInternal')
      ->willReturn(FALSE);

    // Good-ish entity types might be supported, but some are manually excluded
    // on purpose.
    $goodish_entity_type = clone $bad_entity_type;

    // Bad entity types won't be render-cacheable.
    $bad_entity_type
      ->method('isRenderCacheable')
      ->willReturn(FALSE);

    // Good entity types will be render-cacheable.
    $goodish_entity_type
      ->method('isRenderCacheable')
      ->willReturn(TRUE);

    $entity_type_manager->method('getDefinitions')
      ->withAnyParameters()
      ->willReturn([
        // The only supported entity type.
        'page' => $goodish_entity_type,

        // The list bellow is the same as the one in the original method.
        'block_content' => $goodish_entity_type,
        'contact_message' => $goodish_entity_type,
        'menu_link_content' => $goodish_entity_type,
        'path_alias' => $goodish_entity_type,
        'shortcut' => $goodish_entity_type,
        'feeds_feed' => $goodish_entity_type,
        'commerce_order' => $goodish_entity_type,
        'commerce_payment' => $goodish_entity_type,
        'commerce_payment_method' => $goodish_entity_type,
        'commerce_promotion' => $goodish_entity_type,
        'commerce_promotion_coupon' => $goodish_entity_type,
        'commerce_shipment' => $goodish_entity_type,
        'commerce_shipping_method' => $goodish_entity_type,
        'commerce_stock_location' => $goodish_entity_type,
        'linkcheckerlink' => $goodish_entity_type,
        'redirect' => $goodish_entity_type,
        'salesforce_mapped_object' => $goodish_entity_type,
        'webform_submission' => $goodish_entity_type,

        // Entity types that won't be supported.
        'uncacheable_entity_type' => $bad_entity_type,
      ]);

    // Instantiate the entity type manager container.
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($container);

    // Call the method under test.
    $result = MetatagDefaultsForm::getSupportedEntityTypes();

    // Assert that the result contains the expected supported entity types.
    $this->assertEquals(['page' => 'Example entity type'], $result, 'Only the page entity type should be supported.');

    // Only one supported entity type should be returned.
    $this->assertCount(1, $result, 'Only one entity type should be supported.');
  }

}
