<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_ckeditor\Kernel;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the Tone and Translate plugins respect taxonomy term access.
 *
 * @group ai_ckeditor
 */
#[RunTestsInSeparateProcesses]
class AiCKEditorTermAccessTest extends KernelTestBase {

  use TaxonomyTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'text',
    'filter',
    'editor',
    'ckeditor5',
    'taxonomy',
    'ai',
    'ai_test',
    'ai_ckeditor',
  ];

  /**
   * A published term.
   */
  protected TermInterface $published;

  /**
   * An unpublished term.
   */
  protected TermInterface $unpublished;

  /**
   * A published term in another vocabulary.
   */
  protected TermInterface $foreign;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['filter', 'user', 'ai', 'ai_ckeditor']);
    // Create the root user (uid=1) so test users don't bypass permissions.
    $this->createUser();

    $vocabulary = $this->createVocabulary(['vid' => 'tones']);
    $this->published = $this->createTerm($vocabulary, [
      'name' => 'Published term',
      'description' => ['value' => 'Published description'],
    ]);
    $this->unpublished = $this->createTerm($vocabulary, [
      'name' => 'Secret term',
      'description' => ['value' => 'Secret description'],
      'status' => 0,
    ]);
    $this->foreign = $this->createTerm($this->createVocabulary(), [
      'name' => 'Foreign term',
      'description' => ['value' => 'Foreign description'],
    ]);

    $this->setCurrentUser($this->createUser(['access content', 'use ai ckeditor']));
  }

  /**
   * Provides the plugin ID, configuration and form element key.
   */
  public static function pluginProvider(): array {
    return [
      'tone' => [
        'ai_ckeditor_tone',
        ['tone_vocabulary' => 'tones', 'use_description' => TRUE],
        'tone',
      ],
      'translate' => [
        'ai_ckeditor_translate',
        [
          'translate_vocabulary' => 'tones',
          'language_source' => 'tax',
          'use_description' => TRUE,
        ],
        'language',
      ],
    ];
  }

  /**
   * Tests that inaccessible terms are not offered as options.
   */
  #[DataProvider('pluginProvider')]
  public function testOptionsExcludeInaccessibleTerms(string $plugin_id, array $configuration, string $key): void {
    $plugin = $this->container->get('plugin.manager.ai_ckeditor')->createInstance($plugin_id, $configuration);
    $form_state = new FormState();
    $form_state->setStorage(['selected_text' => 'hi']);
    $form = $plugin->buildCkEditorModalForm([], $form_state);

    $this->assertSame([(int) $this->published->id() => 'Published term'], array_map('strval', $form[$key]['#options']));
  }

  /**
   * Tests that a submitted term must be in the vocabulary and accessible.
   */
  #[DataProvider('pluginProvider')]
  public function testGenerateRejectsInaccessibleTerms(string $plugin_id, array $configuration, string $key): void {
    $plugin = $this->container->get('plugin.manager.ai_ckeditor')->createInstance($plugin_id, $configuration);

    $generate = function (TermInterface $term) use ($plugin, $key) {
      $form = [];
      $form_state = new FormState();
      $form_state->setValues([
        'editor_id' => 'basic_html',
        'plugin_config' => [$key => $term->id(), 'selected_text' => 'hi'],
      ]);
      return $plugin->ajaxGenerate($form, $form_state);
    };

    $response = $generate($this->published);
    $this->assertInstanceOf(AjaxResponse::class, $response);
    $this->assertStringContainsString('Published description', $response->getCommands()[0]['prompt']);

    foreach ([$this->unpublished, $this->foreign] as $term) {
      $response = $generate($term);
      $this->assertIsString($response);
      $this->assertStringNotContainsString((string) $term->getDescription(), $response);
    }
  }

}
