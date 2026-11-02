<?php

namespace Drupal\Tests\metatag\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\metatag\MetatagManagerInterface;
use Drupal\metatag\MetatagSeparator;

/**
 * Test the Metatag Manager class.
 *
 * @group metatag
 */
class MetatagManagerTest extends KernelTestBase {

  use MetatagSeparator;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Core modules.
    'system',
    'field',
    'text',
    'user',

    // Contrib modules.
    'token',

    // This module.
    'metatag',
    'metatag_open_graph',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The metatag manager.
   *
   * @var \Drupal\metatag\MetatagManagerInterface
   */
  protected MetatagManagerInterface $metatagManager;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->metatagManager = $this->container->get('metatag.manager');
    $this->configFactory = $this->container->get('config.factory');

    $this->installConfig([
      'system',
      'field',
      'text',
      'user',
      'metatag',
      'metatag_open_graph',
    ]);
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
  }

  /**
   * Tests default tags for user entity.
   */
  public function testDefaultTagsFromEntity() {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->entityTypeManager->getStorage('user')->create();

    $default_tags = $this->metatagManager->defaultTagsFromEntity($user);
    $expected_tags = [
      'canonical_url' => '[user:url]',
      'title' => '[user:display-name] | [site:name]',
      'description' => '[site:name]',
    ];

    $this->assertSame($expected_tags, $default_tags);
  }

  /**
   * Test the order of the meta tags as they are output.
   */
  public function testMetatagOrder() {
    $tags = $this->metatagManager->generateElements([
      'og_image_width' => 100,
      'og_image_height' => 100,
      // @todo Update this to use the metatag-logo.png file.
      'og_image_url' => 'https://www.example.com/example/foo.png',
    ]);

    $expected = [
      '#attached' => [
        'html_head' => [
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'og:image:url',
                'content' => 'https://www.example.com/example/foo.png',
              ],
            ],
            'og_image_url_0',
          ],
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'og:image:width',
                'content' => 100,
              ],
            ],
            'og_image_width',
          ],
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'og:image:height',
                'content' => 100,
              ],
            ],
            'og_image_height',
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $tags);
  }

  /**
   * Tests metatags with multiple values return multiple metatags.
   */
  public function testMetatagMultiple() {
    $tags = $this->metatagManager->generateElements([
      'og_image_width' => 100,
      'og_image_height' => 100,
      'og_image_url' => 'https://www.example.com/example/foo.png,https://www.example.com/example/foo2.png',
    ]);

    $expected = [
      '#attached' => [
        'html_head' => [
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'og:image:url',
                'content' => 'https://www.example.com/example/foo.png',
              ],
            ],
            'og_image_url_0',
          ],
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'og:image:url',
                'content' => 'https://www.example.com/example/foo2.png',
              ],
            ],
            'og_image_url_1',
          ],
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'og:image:width',
                'content' => 100,
              ],
            ],
            'og_image_width',
          ],
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'og:image:height',
                'content' => 100,
              ],
            ],
            'og_image_height',
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $tags);
  }

  /**
   * Tests the default settings to make sure they load as expected.
   */
  public function testDefaultSettings() {
    $config = $this->configFactory->get('metatag.settings');
    $this->assertEquals($config->get('entity_type_groups'), []);
    $this->assertEquals($config->get('separator'), '');
    $this->assertEquals($config->get('tag_trim_method'), 'beforeValue');
    $this->assertEquals($config->get('tag_trim_maxlength'), []);
    $this->assertEquals($config->get('tag_scroll_max_height'), '');
  }

  /**
   * Tests separator configuration and handling of multiple values.
   */
  public function testSeparator() {
    // Get the initial value of the separator, which is empty.
    $value = $this->configFactory->get('metatag.settings')->get('separator');
    $expected = '';
    $this->assertEquals($expected, $value);

    // Confirm that if it's empty it falls back to ','.
    $value = $this->getSeparator();
    $expected = ',';
    $this->assertEquals($expected, $value);

    // Make sure the separator works.
    $tags = $this->metatagManager->generateElements([
      'og_image_width' => 100,
      'og_image_height' => 100,
      'og_image_url' => 'https://www.example.com/example/foo.png' . $this->getSeparator() . 'https://www.example.com/example/foo2.png',
    ]);

    $expected = [
      '#attached' => [
        'html_head' => [
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'og:image:url',
                'content' => 'https://www.example.com/example/foo.png',
              ],
            ],
            'og_image_url_0',
          ],
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'og:image:url',
                'content' => 'https://www.example.com/example/foo2.png',
              ],
            ],
            'og_image_url_1',
          ],
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'og:image:width',
                'content' => 100,
              ],
            ],
            'og_image_width',
          ],
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'og:image:height',
                'content' => 100,
              ],
            ],
            'og_image_height',
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $tags);

    // Change the value of the separator.
    $config = $this->configFactory->getEditable('metatag.settings');
    $config->set('separator', '||')->save();
    $value = $this->configFactory->get('metatag.settings')->get('separator');
    $expected = '||';
    $this->assertEquals($expected, $value);

    // Make sure Metatag Manager correctly picks up the new value.
    $value = $this->getSeparator();
    $expected = '||';
    $this->assertEquals($expected, $value);

    // Make sure the new value works.
    $tags = $this->metatagManager->generateElements([
      'og_image_width' => 100,
      'og_image_height' => 100,
      'og_image_url' => 'https://www.example.com/example/foo.png' . $this->getSeparator() . 'https://www.example.com/example/foo2.png',
    ]);

    $expected = [
      '#attached' => [
        'html_head' => [
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'og:image:url',
                'content' => 'https://www.example.com/example/foo.png',
              ],
            ],
            'og_image_url_0',
          ],
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'og:image:url',
                'content' => 'https://www.example.com/example/foo2.png',
              ],
            ],
            'og_image_url_1',
          ],
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'og:image:width',
                'content' => 100,
              ],
            ],
            'og_image_width',
          ],
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'og:image:height',
                'content' => 100,
              ],
            ],
            'og_image_height',
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $tags);

  }

  /**
   * Tests that empty values are removed from multi-value meta tags.
   *
   * @covers \Drupal\metatag\Plugin\metatag\Tag\MetaNameBase::output
   *
   * @see https://www.drupal.org/project/metatag/issues/3378434
   */
  public function testMetatagMultipleRemovesEmptyValues() {
    // A multi-value tag where some of the values are empty or whitespace-only,
    // as happens when one of several tokens resolves to nothing. The empty and
    // whitespace-only segments must not generate empty meta tags. This also
    // covers leading and trailing empty segments collapsing away.
    $tags = $this->metatagManager->generateElements([
      'article_tag' => ',,tag1, ,tag2,,',
    ]);

    $expected = [
      '#attached' => [
        'html_head' => [
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'article:tag',
                'content' => 'tag1',
              ],
            ],
            'article_tag_0',
          ],
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'article:tag',
                'content' => 'tag2',
              ],
            ],
            'article_tag_1',
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $tags);
  }

  /**
   * Tests that a literal "0" is preserved in a multi-value meta tag.
   *
   * "Empty" must mean a blank/whitespace-only string, not any PHP-falsy value;
   * the number zero is a legitimate value and must still be rendered. This is
   * the case an array_filter()-based fix gets wrong.
   *
   * @covers \Drupal\metatag\Plugin\metatag\Tag\MetaNameBase::output
   *
   * @see https://www.drupal.org/project/metatag/issues/3378434
   */
  public function testMetatagMultiplePreservesZero() {
    $tags = $this->metatagManager->generateElements([
      'article_tag' => '0,5',
    ]);

    $expected = [
      '#attached' => [
        'html_head' => [
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'article:tag',
                'content' => '0',
              ],
            ],
            'article_tag_0',
          ],
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'article:tag',
                'content' => '5',
              ],
            ],
            'article_tag_1',
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $tags);
  }

  /**
   * Tests that empty value removal respects the configured separator.
   *
   * @covers \Drupal\metatag\Plugin\metatag\Tag\MetaNameBase::output
   *
   * @see https://www.drupal.org/project/metatag/issues/3378434
   */
  public function testMetatagMultipleRemovesEmptyValuesWithCustomSeparator() {
    $this->configFactory->getEditable('metatag.settings')
      ->set('separator', '||')
      ->save();

    $tags = $this->metatagManager->generateElements([
      'article_tag' => 'tag1|| ||tag2',
    ]);

    $expected = [
      '#attached' => [
        'html_head' => [
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'article:tag',
                'content' => 'tag1',
              ],
            ],
            'article_tag_0',
          ],
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'property' => 'article:tag',
                'content' => 'tag2',
              ],
            ],
            'article_tag_1',
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $tags);
  }

  /**
   * Tests that single-value tags are unaffected by empty value removal.
   *
   * A single-value tag whose value legitimately contains the separator
   * character must be rendered unchanged, i.e. the multi-value splitting and
   * empty-removal logic must not leak into single-value handling.
   *
   * @covers \Drupal\metatag\Plugin\metatag\Tag\MetaNameBase::output
   *
   * @see https://www.drupal.org/project/metatag/issues/3378434
   */
  public function testMetatagSingleValueWithSeparatorCharUnaffected() {
    $tags = $this->metatagManager->generateElements([
      'description' => 'Hello, world',
    ]);

    $expected = [
      '#attached' => [
        'html_head' => [
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'name' => 'description',
                'content' => 'Hello, world',
              ],
            ],
            'description',
          ],
        ],
      ],
    ];
    $this->assertEquals($expected, $tags);
  }

}
