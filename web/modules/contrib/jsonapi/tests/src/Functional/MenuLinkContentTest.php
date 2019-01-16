<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Core\Url;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Tests\jsonapi\Traits\CommonCollectionFilterAccessTestPatternsTrait;

/**
 * JSON:API integration test for the "MenuLinkContent" content entity type.
 *
 * @group jsonapi
 */
class MenuLinkContentTest extends ResourceTestBase {

  use CommonCollectionFilterAccessTestPatternsTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['menu_link_content'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'menu_link_content';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'menu_link_content--menu_link_content';

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\menu_link_content\MenuLinkContentInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected static $patchProtectedFieldNames = [
    'changed' => NULL,
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    $this->grantPermissionsToTestedRole(['administer menu']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $menu_link = MenuLinkContent::create([
      'id' => 'llama',
      'title' => 'Llama Gabilondo',
      'description' => 'Llama Gabilondo',
      'link' => 'https://nl.wikipedia.org/wiki/Llama',
      'weight' => 0,
      'menu_name' => 'main',
    ]);
    $menu_link->save();

    return $menu_link;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument() {
    $self_url = Url::fromUri('base:/jsonapi/menu_link_content/menu_link_content/' . $this->entity->uuid())->setAbsolute()->toString(TRUE)->getGeneratedUrl();
    return [
      'jsonapi' => [
        'meta' => [
          'links' => [
            'self' => ['href' => 'http://jsonapi.org/format/1.0/'],
          ],
        ],
        'version' => '1.0',
      ],
      'links' => [
        'self' => ['href' => $self_url],
      ],
      'data' => [
        'id' => $this->entity->uuid(),
        'type' => 'menu_link_content--menu_link_content',
        'links' => [
          'self' => ['href' => $self_url],
        ],
        'attributes' => [
          'bundle' => 'menu_link_content',
          'link' => [
            'uri' => 'https://nl.wikipedia.org/wiki/Llama',
            'title' => NULL,
            'options' => [],
          ],
          'changed' => (new \DateTime())->setTimestamp($this->entity->getChangedTime())->setTimezone(new \DateTimeZone('UTC'))->format(\DateTime::RFC3339),
          'default_langcode' => TRUE,
          'description' => 'Llama Gabilondo',
          'enabled' => TRUE,
          'expanded' => FALSE,
          'external' => FALSE,
          'langcode' => 'en',
          'menu_name' => 'main',
          'parent' => NULL,
          'rediscover' => FALSE,
          'title' => 'Llama Gabilondo',
          'weight' => 0,
          'drupal_internal__id' => 1,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getPostDocument() {
    return [
      'data' => [
        'type' => 'menu_link_content--menu_link_content',
        'attributes' => [
          'title' => 'Dramallama',
          'link' => [
            'uri' => 'http://www.urbandictionary.com/define.php?term=drama%20llama',
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    switch ($method) {
      case 'DELETE':
        return floatval(\Drupal::VERSION >= 8.7) ? "The 'administer menu' permission is required." : '';

      default:
        return parent::getExpectedUnauthorizedAccessMessage($method);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function testCollectionFilterAccess() {
    $this->doTestCollectionFilterAccessBasedOnPermissions('title', 'administer menu');
  }

}
