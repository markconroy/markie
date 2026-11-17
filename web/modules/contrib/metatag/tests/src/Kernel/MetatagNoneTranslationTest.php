<?php

namespace Drupal\Tests\metatag\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests that `<none>` in the 403/404 defaults is never translated by locale.
 *
 * The shipped `canonical_url` / `shortlink` value `<none>` ("do not output the
 * tag") matched the interface string `<none>`, translated e.g. into Dutch as
 * `-geen-`, which then was output as a link.
 *
 * @see https://www.drupal.org/project/metatag/issues/3633586
 *
 * @group metatag
 */
class MetatagNoneTranslationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'language',
    'locale',
    'token',
    'metatag',
  ];

  /**
   * The error page defaults.
   */
  protected const NAMES = ['metatag.metatag_defaults.403', 'metatag.metatag_defaults.404'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('locale', ['locales_source', 'locales_target', 'locales_location', 'locale_file']);
    $this->installConfig(['language', 'locale']);
    // A Dutch site with the interface translation of `<none>` imported.
    ConfigurableLanguage::createFromLangcode('nl')->save();
    $this->config('system.site')->set('default_langcode', 'nl')->save();
    $storage = $this->container->get('locale.storage');
    $source = $storage->createString(['source' => '<none>', 'context' => ''])->save();
    $storage->createTranslation(['lid' => $source->lid, 'language' => 'nl', 'translation' => '-geen-'])->save();
  }

  /**
   * Translating the shipped defaults keeps `<none>`.
   */
  public function testNoneIsNotTranslated(): void {
    $this->installConfig(['metatag']);
    // What locale does when metatag is installed on a Dutch site.
    $locale = $this->container->get('locale.config_manager');
    $locale->updateDefaultConfigLangcodes();
    $locale->updateConfigTranslations(static::NAMES, ['nl']);

    foreach (static::NAMES as $name) {
      $config = $this->config($name);
      $this->assertSame('nl', $config->get('langcode'), $name);
      $this->assertSame('<none>', $config->get('tags.canonical_url'), $name);
      $this->assertSame('<none>', $config->get('tags.shortlink'), $name);
    }

    // Still translatable: a real canonical URL may differ per language.
    $data = $locale->getTranslatableDefaultConfig('metatag.metatag_defaults.404');
    $this->assertSame('Metatag URL', $data['tags']['canonical_url']->getOption('context'));
  }

  /**
   * The update puts `<none>` back where locale translated it.
   */
  public function testUpdateRestoresNone(): void {
    $this->installConfig(['metatag']);
    $this->container->get('locale.config_manager')->updateDefaultConfigLangcodes();
    $this->config('metatag.metatag_defaults.404')
      ->set('tags.canonical_url', '-geen-')
      ->set('tags.shortlink', '-geen-')
      ->save();
    // Set on purpose: an update must not touch it.
    $this->config('metatag.metatag_defaults.403')
      ->set('tags.canonical_url', 'https://example.com/forbidden')
      ->save();

    \Drupal::moduleHandler()->loadInclude('metatag', 'php', 'metatag.post_update');
    metatag_post_update_v2_07_error_page_defaults_untranslated_none();

    $this->assertSame('<none>', $this->config('metatag.metatag_defaults.404')->get('tags.canonical_url'));
    $this->assertSame('<none>', $this->config('metatag.metatag_defaults.404')->get('tags.shortlink'));
    $this->assertSame('https://example.com/forbidden', $this->config('metatag.metatag_defaults.403')->get('tags.canonical_url'));
    $this->assertSame('<none>', $this->config('metatag.metatag_defaults.403')->get('tags.shortlink'));
  }

}
