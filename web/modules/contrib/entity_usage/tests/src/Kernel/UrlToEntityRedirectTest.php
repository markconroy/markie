<?php

namespace Drupal\Tests\entity_usage\Kernel;

use Drupal\entity_usage\UrlToEntityInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;

/**
 * Tests \Drupal\entity_usage\UrlToEntityIntegrations\RedirectIntegration.
 *
 * No entity test entities are created during testing to prove that they are not
 * loaded.
 *
 * @group entity_usage
 *
 * @see \Drupal\entity_usage\UrlToEntityIntegrations\RedirectIntegration
 */
class UrlToEntityRedirectTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test', 'entity_usage', 'path_alias', 'link', 'redirect'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('redirect');
  }

  /**
   * Tests finding a redirect entity by URL.
   */
  public function testFindEntityIdByUrlWithRedirect(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');

    // Create a redirect and test if hash has been generated correctly.
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $storage->create();
    $redirect->setSource('some-url');
    $redirect->setRedirect('entity_test/1');
    $redirect->save();

    $this->assertSame(['type' => 'redirect', 'id' => 1], $this->container->get(UrlToEntityInterface::class)->findEntityIdByUrl('some-url'));

    // Do not track redirects.
    $this->container->get('kernel')->resetContainer();
    $this->config('entity_usage.settings')->set('track_enabled_target_entity_types', ['entity_test'])->save();
    $this->assertNull($this->container->get(UrlToEntityInterface::class)->findEntityIdByUrl('some-url'));
  }

  /**
   * Tests finding a redirect entity by URL.
   */
  public function testFindEntityIdByUrlWithRedirectQueryString(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');

    // Create a redirect and test if hash has been generated correctly.
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $storage->create();
    $redirect->setSource('some-url', ['foo' => 'bar']);
    $redirect->setRedirect('entity_test/1');
    $redirect->save();

    $url_to_entity = $this->container->get(UrlToEntityInterface::class);
    $this->assertSame(['type' => 'redirect', 'id' => 1], $url_to_entity->findEntityIdByUrl('some-url?foo=bar'));
    $this->assertNull($url_to_entity->findEntityIdByUrl('some-url?bar=foo'));
    $this->assertNull($url_to_entity->findEntityIdByUrl('some-url'));

    $redirect->setSource('some-url');
    $redirect->save();

    $this->assertNull($url_to_entity->findEntityIdByUrl('some-url?foo=bar'));
    $this->assertNull($url_to_entity->findEntityIdByUrl('some-url?bar=foo'));
    $this->assertSame(['type' => 'redirect', 'id' => 1], $url_to_entity->findEntityIdByUrl('some-url'));

    // Test redirect.settings:passthrough_querystring set to TRUE.
    $this->config('redirect.settings')->set('passthrough_querystring', TRUE)->save();
    $this->container->get('kernel')->resetContainer();
    $url_to_entity = $this->container->get(UrlToEntityInterface::class);
    $this->assertSame(['type' => 'redirect', 'id' => 1], $url_to_entity->findEntityIdByUrl('some-url?foo=bar'));
    $this->assertSame(['type' => 'redirect', 'id' => 1], $url_to_entity->findEntityIdByUrl('some-url?bar=foo'));
    $this->assertSame(['type' => 'redirect', 'id' => 1], $url_to_entity->findEntityIdByUrl('some-url'));

    $redirect->setSource('some-url', ['foo' => 'bar']);
    $redirect->save();
    $url_to_entity = $this->container->get(UrlToEntityInterface::class);
    $this->assertSame(['type' => 'redirect', 'id' => 1], $url_to_entity->findEntityIdByUrl('some-url?foo=bar'));
    $this->assertNull($url_to_entity->findEntityIdByUrl('some-url?bar=foo'));
    $this->assertNull($url_to_entity->findEntityIdByUrl('some-url'));
  }

  /**
   * Tests finding multilingual redirect entities by URL.
   */
  public function testFindEntityIdByUrlWithMultilingualRedirect(): void {
    $this->enableModules(['language']);
    $this->installConfig(['language']);

    // Create some additional languages.
    foreach (['es', 'ca'] as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }
    // Enable URL language negotiation.
    $this->config('language.types')
      ->set('negotiation.language_content.enabled', [LanguageNegotiationUrl::METHOD_ID => 0])
      ->save();

    $this->container->get('kernel')->rebuildContainer();

    $storage = $this->container->get('entity_type.manager')->getStorage('redirect');

    // Create a redirect and test if hash has been generated correctly.
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $storage->create();
    $redirect->setSource('some-url');
    $redirect->setRedirect('entity_test/3');
    $redirect->setLanguage('es');
    $redirect->save();

    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $storage->create();
    $redirect->setSource('some-url');
    $redirect->setRedirect('entity_test/6');
    $redirect->setLanguage('ca');
    $redirect->save();

    $url_to_entity = $this->container->get(UrlToEntityInterface::class);
    $this->assertSame(['type' => 'redirect', 'id' => 1], $url_to_entity->findEntityIdByUrl('es/some-url'));
    $this->assertSame(['type' => 'redirect', 'id' => 2], $url_to_entity->findEntityIdByUrl('ca/some-url'));
    $this->assertNull($url_to_entity->findEntityIdByUrl('de/some-url'));
  }

}
