<?php

namespace Drupal\Tests\metatag\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\metatag\Entity\MetatagDefaults;

/**
 * Tests _metatag_remove_duplicate_entity_tags().
 *
 * @group metatag
 */
class MetatagRemoveDuplicateEntityTagsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'token',
    'metatag',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['metatag']);
  }

  /**
   * Tests that <none> for canonical_url and shortlink removes core links.
   */
  public function testRemoveDuplicateEntityTagsNone(): void {
    $base_build = [
      '#attached' => [
        'html_head_link' => [
          [
            [
              'rel' => 'canonical',
              'href' => 'https://example.com/canonical',
            ],
            TRUE,
          ],
          [
            [
              'rel' => 'shortlink',
              'href' => 'https://example.com/shortlink',
            ],
            TRUE,
          ],
        ],
      ],
    ];

    /** @var \Drupal\metatag\Entity\MetatagDefaults $global */
    $global = MetatagDefaults::load('global');

    // 1. With default configuration, canonical_url is provided so Core
    // canonical and shortlink are removed.
    $build = $base_build;
    _metatag_remove_duplicate_entity_tags($build);
    $this->assertEmpty($build['#attached']['html_head_link']);

    // 2. Set canonical_url to <none>, leave shortlink unset.
    drupal_static_reset('metatag_attachments');
    $global->set('tags', ['canonical_url' => '<none>']);
    $global->save();

    $build = $base_build;
    _metatag_remove_duplicate_entity_tags($build);
    $rel_values = array_column(array_column($build['#attached']['html_head_link'], 0), 'rel');
    $this->assertNotContains('canonical', $rel_values);
    $this->assertContains('shortlink', $rel_values);

    // 3. Set shortlink to <none>, leave canonical_url unset.
    drupal_static_reset('metatag_attachments');
    $global->set('tags', ['shortlink' => '<none>']);
    $global->save();

    $build = $base_build;
    _metatag_remove_duplicate_entity_tags($build);
    $rel_values = array_column(array_column($build['#attached']['html_head_link'], 0), 'rel');
    $this->assertContains('canonical', $rel_values);
    $this->assertNotContains('shortlink', $rel_values);

    // 4. Set both to <none>.
    drupal_static_reset('metatag_attachments');
    $global->set('tags', [
      'canonical_url' => '<none>',
      'shortlink' => '<none>',
    ]);
    $global->save();

    $build = $base_build;
    _metatag_remove_duplicate_entity_tags($build);
    $this->assertEmpty($build['#attached']['html_head_link']);

    // 5. Normal override for canonical_url.
    drupal_static_reset('metatag_attachments');
    $global->set('tags', ['canonical_url' => 'https://example.com/custom']);
    $global->save();

    $build = $base_build;
    _metatag_remove_duplicate_entity_tags($build);
    $this->assertEmpty($build['#attached']['html_head_link']);
  }

}
