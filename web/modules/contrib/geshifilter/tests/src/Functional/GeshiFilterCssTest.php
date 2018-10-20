<?php

namespace Drupal\Tests\geshifilter\Functional;

// Use of base class for the tests.
use Drupal\Tests\BrowserTestBase;

/**
 * Test for css generation and use in GeshiFilter.
 *
 * @group geshifilter
 */
class GeshiFilterCssTest extends BrowserTestBase {

  /**
   * A global filter adminstrator.
   *
   * @var object
   */
  protected $filterAdminUser;

  /**
   * The id of the text format with only GeSHi filter in it.
   *
   * @var object
   */
  protected $inputFormatIid;

  /**
   * List of modules to enable.
   *
   * @var object
   */
  public static $modules = ['node', 'geshifilter', 'filter',
    'file',
  ];

  /**
   * Configuration object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Set up the tests and create the users.
   */
  public function setUp() {
    parent::setUp();

    // Create object with configuration.
    $this->config = \Drupal::configFactory()->getEditable('geshifilter.settings');

    // And set the path to the geshi library.
    $this->config->set('geshi_dir', '/libraries/geshi');

    $settings = [
      // Override default type (a random name).
      'type' => 'geshifilter_content_type',
      'name' => 'Geshifilter Content',
    ];
    $this->drupalCreateContentType($settings);

    // Create a filter admin user.
    $permissions = [
      'administer filters',
      'access administration pages',
      'administer site configuration',
    ];
    $this->filterAdminUser = $this->drupalCreateUser($permissions);

    // Log in with filter admin user.
    $this->drupalLogin($this->filterAdminUser);

    // Add an text format with only geshi filter.
    $this->createTextFormat('geshifilter_text_format', ['filter_geshifilter']);
  }

  /**
   * Test for creation and use of css.
   */
  public function testCss() {
    // Test if we can generate the css.
    $this->drupalGet('admin/config/content/formats/geshifilter/generate_css');
    $this->assertRaw('GeSHi Dynamically Generated Stylesheet', 'Test for geshifilter generate css');
  }

  /**
   * Test the use of css when CSS mode for syntax highlighting is inline.
   *
   * In this case we have geshifilter.css and no geshi-languages.css.
   */
  public function testCssInline() {
    // Node Content.
    $node = [
      'title' => 'Test for GeShi Filter',
      'body' => [
        [
          'value' => "dfgdfg <code language=\"php\">echo(\"hi\");</code> dfgdg",
          'format' => 'geshifilter_text_format',
        ],
      ],
      'type' => 'geshifilter_content_type',
    ];

    // Inline CSS.
    $form_values = [
      'css_mode' => 1,
    ];
    $this->drupalPostForm('admin/config/content/formats/geshifilter', $form_values, t('Save configuration'));

    // Create the node and read it.
    $this->drupalCreateNode($node);
    $this->drupalGet('node/1');

    // Test if we have only geshifilter.css.
    $this->assertRaw('/assets/css/geshifilter.css', 'The CSS file /assets/css/geshifilter.css is present.');
    $this->assertNoRaw('/geshi/geshifilter-languages.css', 'The CSS file /geshi/geshifilter-languages.css is present.');
  }

  /**
   * Test the css when CSS mode for syntax highlighting is external managed.
   *
   * In this case we have both geshifilter.css and geshi-languages.css.
   */
  public function testCssExternal() {
    // Node Content.
    $node = [
      'title' => 'Test for GeShi Filter',
      'body' => [
        [
          'value' => "dfgdfg <code language=\"php\">echo(\"hi\");</code> dfgdg",
          'format' => 'geshifilter_text_format',
        ],
      ],
      'type' => 'geshifilter_content_type',
    ];

    // External managed CSS.
    $form_values = [
      'css_mode' => 2,
    ];
    $this->drupalPostForm('admin/config/content/formats/geshifilter', $form_values, t('Save configuration'));

    // Create the node and read it.
    $this->drupalCreateNode($node);
    $this->drupalGet('node/1');

    // Test if we have both css.
    $this->assertRaw('/assets/css/geshifilter.css', 'The CSS file /assets/css/geshifilter.css is present.');
    $this->assertRaw('/geshi/geshifilter-languages.css', 'The CSS file /geshi/geshifilter-languages.css is present.');
  }

  /**
   * Test the use of css when CSS mode for syntax highlighting is only add css.
   *
   * In this case we do not have both geshifilter.css and geshi-languages.css.
   */
  public function testOnlyCss() {
    // Node Content.
    $node = [
      'title' => 'Test for GeShi Filter',
      'body' => [
        [
          'value' => "dfgdfg <code language=\"php\">echo(\"hi\");</code> dfgdg",
          'format' => 'geshifilter_text_format',
        ],
      ],
      'type' => 'geshifilter_content_type',
    ];
    // Only CSS.
    $form_values = [
      'css_mode' => 3,
    ];
    $this->drupalPostForm('admin/config/content/formats/geshifilter', $form_values, t('Save configuration'));

    // Create the node and read it.
    $this->drupalCreateNode($node);
    $this->drupalGet('node/1');

    // Test if we have both css.
    $this->assertNoRaw('/assets/css/geshifilter.css', 'The CSS file /assets/css/geshifilter.css is present.');
    $this->assertNoRaw('/geshi/geshifilter-languages.css', 'The CSS file /geshi/geshifilter-languages.css is present.');
  }

  /**
   * Create a new text format.
   *
   * @param string $format_name
   *   The name of new text format.
   * @param array $filters
   *   Array with the machine names of filters to enable.
   */
  protected function createTextFormat($format_name, array $filters) {
    $edit = [];
    $edit['format'] = $format_name;
    $edit['name'] = $this->randomMachineName();
    $edit['roles[' . DRUPAL_AUTHENTICATED_RID . ']'] = 1;
    foreach ($filters as $filter) {
      $edit['filters[' . $filter . '][status]'] = TRUE;
    }
    $this->drupalPostForm('admin/config/content/formats/add', $edit, t('Save configuration'));
    $this->assertRaw(t('Added text format %format.', ['%format' => $edit['name']]), 'New filter created.');
    $this->drupalGet('admin/config/content/formats');
  }

}
