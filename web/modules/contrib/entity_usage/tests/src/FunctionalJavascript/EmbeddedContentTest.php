<?php

namespace Drupal\Tests\entity_usage\FunctionalJavascript;

use Drupal\Tests\entity_usage\Traits\EntityUsageLastEntityQueryTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

/**
 * Basic functional tests for the usage tracking of embedded content.
 *
 * This should test logic specific for plugins:
 * - Entity Embed
 * - LinkIt
 * - HtmlLink.
 * - MediaMedia WYSIWYG Embed (Core)
 *
 * @package Drupal\Tests\entity_usage\FunctionalJavascript
 *
 * @group entity_usage
 */
class EmbeddedContentTest extends EntityUsageJavascriptTestBase {

  use EntityUsageLastEntityQueryTrait;

  /**
   * Tests the Entity Embed parsing.
   */
  public function testEntityEmbed(): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    /** @var \Drupal\entity_usage\EntityUsage $usage_service */
    $usage_service = \Drupal::service('entity_usage.usage');

    // Create node 1.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 1');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 1 has been created.');
    $node1 = $this->getLastEntityOfType('node', TRUE);

    // Nobody is using this guy for now.
    $usage = $usage_service->listSources($node1);
    $this->assertEquals([], $usage);

    // Create node 2 referencing node 1 using an Entity Embed markup.
    $embedded_text = '<drupal-entity data-embed-button="node" data-entity-embed-display="entity_reference:entity_reference_label" data-entity-embed-display-settings="{&quot;link&quot;:1}" data-entity-type="node" data-entity-uuid="' . $node1->uuid() . '"></drupal-entity>';
    $node2 = Node::create([
      'type' => 'eu_test_ct',
      'title' => 'Node 2',
      'field_eu_test_rich_text' => [
        'value' => $embedded_text,
        'format' => 'eu_test_text_format',
      ],
    ]);
    $node2->save();
    // Check that we correctly registered the relation between N2 and N1.
    $usage = $usage_service->listSources($node1);
    $expected = [
      'node' => [
        $node2->id() => [
          [
            'source_langcode' => $node2->language()->getId(),
            'source_vid' => $node2->getRevisionId(),
            'method' => 'entity_embed',
            'field_name' => 'field_eu_test_rich_text',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // Create node 3 referencing N2 and N1 on the same field.
    $embedded_text .= '<p>Foo bar</p>' . '<drupal-entity data-embed-button="node" data-entity-embed-display="entity_reference:entity_reference_label" data-entity-embed-display-settings="{&quot;link&quot;:1}" data-entity-type="node" data-entity-uuid="' . $node2->uuid() . '"></drupal-entity>';
    $node3 = Node::create([
      'type' => 'eu_test_ct',
      'title' => 'Node 3',
      'field_eu_test_rich_text' => [
        'value' => $embedded_text,
        'format' => 'eu_test_text_format',
      ],
    ]);
    $node3->save();
    // Check that both targets are tracked.
    $usage = $usage_service->listTargets($node3);
    $expected = [
      'node' => [
        $node1->id() => [
          [
            'method' => 'entity_embed',
            'field_name' => 'field_eu_test_rich_text',
            'count' => 1,
          ],
        ],
        $node2->id() => [
          [
            'method' => 'entity_embed',
            'field_name' => 'field_eu_test_rich_text',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);
  }

  /**
   * Tests the Entity Embed plugin parsing does not error with malformed HTML.
   */
  public function testEntityEmbedWithMalformedHtml(): void {
    $embedded_text = '<drupal-entity data-embed-button="node" data-entity-embed-display="entity_reference:entity_reference_label" data-entity-embed-display-settings="{&quot;link&quot;:1}" data-entity-type="" data-entity-uuid=""></drupal-entity>';

    $node = Node::create([
      'type' => 'eu_test_ct',
      'title' => 'This is a node with malformed EntityEmbed HTML',
      'field_eu_test_rich_text' => [
        'value' => $embedded_text,
        'format' => 'eu_test_text_format',
      ],
    ]);

    $node->save();

    $this->drupalGet('/node/' . $node->id());
    $this->assertSession()->pageTextContains('This is a node with malformed EntityEmbed HTML');
  }

  /**
   * Tests the LinkIt parsing.
   */
  public function testLinkIt(): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    /** @var \Drupal\entity_usage\EntityUsage $usage_service */
    $usage_service = \Drupal::service('entity_usage.usage');

    // Create node 1.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 1');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 1 has been created.');
    $node1 = $this->getLastEntityOfType('node', TRUE);

    // Nobody is using this guy for now.
    $usage = $usage_service->listSources($node1);
    $this->assertEquals([], $usage);

    // Create node 2 referencing node 1 using a linkit markup.
    $embedded_text = '<p>foo <a data-entity-substitution="canonical" data-entity-type="node" data-entity-uuid="' . $node1->uuid() . '">linked text</a> bar</p>';
    $node2 = Node::create([
      'type' => 'eu_test_ct',
      'title' => 'Node 2',
      'field_eu_test_rich_text' => [
        'value' => $embedded_text,
        'format' => 'eu_test_text_format',
      ],
    ]);
    $node2->save();
    // Check that we correctly registered the relation between N2 and N1.
    $usage = $usage_service->listSources($node1);
    $expected = [
      'node' => [
        $node2->id() => [
          [
            'source_langcode' => $node2->language()->getId(),
            'source_vid' => $node2->getRevisionId(),
            'method' => 'linkit',
            'field_name' => 'field_eu_test_rich_text',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // Create node 3 referencing N2 and N1 on the same field.
    $embedded_text .= '<p>Foo bar</p>' . '<p>foo2 <a data-entity-substitution="canonical" data-entity-type="node" data-entity-uuid="' . $node2->uuid() . '">linked text 2</a> bar 2</p>';
    $node3 = Node::create([
      'type' => 'eu_test_ct',
      'title' => 'Node 3',
      'field_eu_test_rich_text' => [
        'value' => $embedded_text,
        'format' => 'eu_test_text_format',
      ],
    ]);
    $node3->save();
    // Check that both targets are tracked.
    $usage = $usage_service->listTargets($node3);
    $expected = [
      'node' => [
        $node1->id() => [
          [
            'method' => 'linkit',
            'field_name' => 'field_eu_test_rich_text',
            'count' => 1,
          ],
        ],
        $node2->id() => [
          [
            'method' => 'linkit',
            'field_name' => 'field_eu_test_rich_text',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // Create node 4 referencing a non existing UUID using a linkit markup to
    // test removed entities.
    $embedded_text = '<p>foo <a data-entity-substitution="canonical" data-entity-type="node" data-entity-uuid="c7cae398-3c36-47d4-8ef0-a17902e76ff4">I do not exists</a> bar</p>';
    $node4 = Node::create([
      'type' => 'eu_test_ct',
      'title' => 'Node 4',
      'field_eu_test_rich_text' => [
        'value' => $embedded_text,
        'format' => 'eu_test_text_format',
      ],
    ]);
    $node4->save();
    // Check that the usage for this source is empty.
    $usage = $usage_service->listTargets($node4);
    $this->assertEquals([], $usage);
  }

  /**
   * Tests the LinkIt plugin parsing does not error with malformed HTML.
   */
  public function testLinkItdWithMalformedHtml(): void {
    $embedded_text = '<p>foo <a data-entity-substitution="canonical" data-entity-type="" data-entity-uuid="">linked text</a> bar</p>';

    $node = Node::create([
      'type' => 'eu_test_ct',
      'title' => 'This is a node with malformed LinkIt HTML',
      'field_eu_test_rich_text' => [
        'value' => $embedded_text,
        'format' => 'eu_test_text_format',
      ],
    ]);

    $node->save();

    $this->drupalGet('/node/' . $node->id());
    $this->assertSession()->pageTextContains('This is a node with malformed LinkIt HTML');
  }

  /**
   * Tests the HtmlLink parsing.
   */
  public function testHtmlLink(): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    /** @var \Drupal\entity_usage\EntityUsage $usage_service */
    $usage_service = \Drupal::service('entity_usage.usage');

    // Create node 1.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 1');
    $page->pressButton('Save');
    $session->wait(500);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity Usage test content Node 1 has been created.');
    $node1 = $this->getLastEntityOfType('node', TRUE);

    // Nobody is using this guy for now.
    $usage = $usage_service->listSources($node1);
    $this->assertEquals([], $usage);

    // Create node 2 referencing node 1 using a normal link markup.
    $embedded_text = '<p>foo <a href="/node/' . $node1->id() . '">linked text</a> bar</p>';
    $node2 = Node::create([
      'type' => 'eu_test_ct',
      'title' => 'Node 2',
      'field_eu_test_rich_text' => [
        'value' => $embedded_text,
        'format' => 'eu_test_text_format',
      ],
    ]);
    $node2->save();
    // Check that we correctly registered the relation between N2 and N1.
    $usage = $usage_service->listSources($node1);
    $expected = [
      'node' => [
        $node2->id() => [
          [
            'source_langcode' => $node2->language()->getId(),
            'source_vid' => $node2->getRevisionId(),
            'method' => 'html_link',
            'field_name' => 'field_eu_test_rich_text',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // Create node 3 referencing N2 and N1 on the same field.
    $embedded_text .= '<p>Foo bar</p>' . '<p>foo2 <a href="/node/' . $node2->id() . '">linked text 2</a> bar 2</p>';
    $node3 = Node::create([
      'type' => 'eu_test_ct',
      'title' => 'Node 3',
      'field_eu_test_rich_text' => [
        'value' => $embedded_text,
        'format' => 'eu_test_text_format',
      ],
    ]);
    $node3->save();
    // Check that both targets are tracked.
    $usage = $usage_service->listTargets($node3);
    $expected = [
      'node' => [
        $node1->id() => [
          [
            'method' => 'html_link',
            'field_name' => 'field_eu_test_rich_text',
            'count' => 1,
          ],
        ],
        $node2->id() => [
          [
            'method' => 'html_link',
            'field_name' => 'field_eu_test_rich_text',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // Create node 4 referencing a non existing path to test removed entities.
    $embedded_text = '<p>foo <a href="/node/4324">linked text foo 2</a> bar</p>';
    $node4 = Node::create([
      'type' => 'eu_test_ct',
      'title' => 'Node 4',
      'field_eu_test_rich_text' => [
        'value' => $embedded_text,
        'format' => 'eu_test_text_format',
      ],
    ]);
    $node4->save();
    // Check that the usage for this source is empty.
    $usage = $usage_service->listTargets($node4);
    $this->assertEquals([], $usage);

    // Create node 5 referencing node 4 using an absolute URL.
    $embedded_text = '<p>foo <a href="' . $node4->toUrl()->setAbsolute(TRUE)->toString() . '">linked text</a> bar</p>';
    // Whitelist the local hostname so we can test absolute URLs.
    $current_request = \Drupal::request();
    $config = \Drupal::configFactory()->getEditable('entity_usage.settings');
    $config->set('site_domains', [$current_request->getHttpHost() . $current_request->getBasePath()]);
    $config->save();
    // Changing site domains requires services to be reconstructed.
    $this->rebuildAll();
    $node5 = Node::create([
      'type' => 'eu_test_ct',
      'title' => 'Node 5',
      'field_eu_test_rich_text' => [
        'value' => $embedded_text,
        'format' => 'eu_test_text_format',
      ],
    ]);
    $node5->save();
    // Check that we correctly registered the relation between N5 and N4.
    $usage = $usage_service->listSources($node4);
    $expected = [
      'node' => [
        $node5->id() => [
          [
            'source_langcode' => $node5->language()->getId(),
            'source_vid' => $node5->getRevisionId(),
            'method' => 'html_link',
            'field_name' => 'field_eu_test_rich_text',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);

    // Create a different field and make sure that a plugin tracking two
    // different field types works as expected.
    $storage = FieldStorageConfig::create([
      'field_name' => 'field_eu_test_normal_text',
      'entity_type' => 'node',
      'type' => 'text',
    ]);
    $storage->save();
    FieldConfig::create([
      'bundle' => 'eu_test_ct',
      'entity_type' => 'node',
      'field_name' => 'field_eu_test_normal_text',
      'label' => 'Normal text',
    ])->save();

    // Create node 6 referencing N5 twice, once on each field.
    $embedded_text = '<p>Foo bar</p>' . '<p>foo2 <a href="/node/' . $node5->id() . '">linked text 5</a> bar</p>';
    $node6 = Node::create([
      'type' => 'eu_test_ct',
      'title' => 'Node 6',
      'field_eu_test_rich_text' => [
        'value' => $embedded_text,
        'format' => 'eu_test_text_format',
      ],
      'field_eu_test_normal_text' => [
        'value' => $embedded_text,
        'format' => 'eu_test_text_format',
      ],
    ]);
    $node6->save();
    // Check that both targets are tracked.
    $usages = $usage_service->listTargets($node6);

    // Asserting the whole array directly might fail due to different sort
    // orders, depending on the PHP version.
    $this->assertCount(2, $usages['node'][$node5->id()]);
    foreach ($usages['node'][$node5->id()] as $usage) {
      $this->assertEquals(1, $usage['count']);
      $this->assertEquals('html_link', $usage['method']);
      $this->assertTrue(in_array($usage['field_name'], ['field_eu_test_rich_text', 'field_eu_test_normal_text']));
    }

    // Create node 7 referencing node 6 using an aliased URL.
    $alias_url = '/i-am-an-alias';
    $alias = \Drupal::entityTypeManager()
      ->getStorage('path_alias')
      ->create([
        'path' => '/node/' . $node6->id(),
        'alias' => $alias_url,
        'langcode' => $node6->language()->getId(),
      ]);
    $alias->save();
    $embedded_text = '<p>foo <a href="' . $alias_url . '">linked text</a> bar</p>';
    $node7 = Node::create([
      'type' => 'eu_test_ct',
      'title' => 'Node 7',
      'field_eu_test_rich_text' => [
        'value' => $embedded_text,
        'format' => 'eu_test_text_format',
      ],
    ]);
    $node7->save();
    // Check that we correctly registered the relation between N5 and N4.
    $usage = $usage_service->listSources($node6);
    $expected = [
      'node' => [
        $node7->id() => [
          [
            'source_langcode' => $node7->language()->getId(),
            'source_vid' => $node7->getRevisionId(),
            'method' => 'html_link',
            'field_name' => 'field_eu_test_rich_text',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);
  }

  /**
   * Tests Media embed parsing.
   */
  public function testMediaEmbed(): void {
    // Create media content.
    $file = File::create([
      'uri' => 'public://example.png',
      'filename' => 'example.png',
    ]);

    $file->save();

    $media = Media::create([
      'bundle' => 'eu_test_image',
      'field_media_image_1' => [
        [
          'target_id' => $file->id(),
          'alt' => 'test alt',
          'title' => 'test title',
          'width' => 10,
          'height' => 11,
        ],
      ],
    ]);

    $media->save();

    /** @var \Drupal\entity_usage\EntityUsage $usage_service */
    $usage_service = \Drupal::service('entity_usage.usage');

    $usage = $usage_service->listSources($media);
    $this->assertEquals([], $usage);

    $embedded_text = '<drupal-media data-entity-type="media" data-entity-uuid="' . $media->uuid() . '"></drupal-media>';
    $node1 = Node::create([
      'type' => 'eu_test_ct',
      'title' => 'Node 1',
      'field_eu_test_rich_text' => [
        'value' => $embedded_text,
        'format' => 'eu_test_text_format',
      ],
    ]);

    $node1->save();

    $usage = $usage_service->listSources($media);

    $expected = [
      'node' => [
        $node1->id() => [
          [
            'source_langcode' => $node1->language()->getId(),
            'source_vid' => $node1->getRevisionId(),
            'method' => 'media_embed',
            'field_name' => 'field_eu_test_rich_text',
            'count' => 1,
          ],
        ],
      ],
    ];

    $this->assertEquals($expected, $usage);

    $usage = $usage_service->listTargets($node1);
    $expected = [
      'media' => [
        $media->id() => [
          [
            'method' => 'media_embed',
            'field_name' => 'field_eu_test_rich_text',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $usage);
  }

  /**
   * Tests the MediaEmbed plugin parsing does not error with malformed HTML.
   */
  public function testMediaEmbedWithMalformedHtml(): void {
    $embedded_text = '<drupal-media data-entity-type="media" data-entity-uuid=""></drupal-media>';

    $node = Node::create([
      'type' => 'eu_test_ct',
      'title' => 'This is a node with malformed MediaEmbed HTML',
      'field_eu_test_rich_text' => [
        'value' => $embedded_text,
        'format' => 'eu_test_text_format',
      ],
    ]);

    $node->save();

    $this->drupalGet('/node/' . $node->id());
    $this->assertSession()->pageTextContains('This is a node with malformed MediaEmbed HTML');
  }

}
