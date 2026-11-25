<?php

declare(strict_types=1);

namespace Drupal\Tests\ctools_views\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\ctools_views_test_views\Form\BlockPluginSubmitForm;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\views\Tests\ViewTestData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests saving exposed filter overrides under both submit form conventions.
 *
 * @group ctools_views
 */
#[Group('ctools_views')]
#[RunTestsInSeparateProcesses]
final class CToolsViewsBlockSubmitSubformTest extends KernelTestBase {

  use EntityReferenceFieldCreationTrait;
  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'filter',
    'text',
    'datetime',
    'options',
    'taxonomy',
    'block',
    'views',
    'entity_test',
    'ctools_views',
    'ctools_views_test_views',
  ];

  /**
   * Views used by this test.
   *
   * @var string[]
   */
  public static $testViews = ['ctools_views_entity_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['filter', 'node']);

    NodeType::create(['type' => 'ctools_views', 'name' => 'CTools Views'])->save();
    Vocabulary::create(['name' => 'Tags', 'vid' => 'tags'])->save();
    $this->createEntityReferenceField('node', 'ctools_views', 'field_ctools_views_tags', 'Tags', 'taxonomy_term', 'default', ['target_bundles' => ['tags' => 'tags']], FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    ViewTestData::createTestViews(static::class, ['ctools_views_test_views']);
  }

  /**
   * Tests the autocomplete filter value is saved whichever form is submitted.
   *
   * @param bool $pass_subform
   *   TRUE to pass the 'settings' subform to submitConfigurationForm(), as
   *   Block field does; FALSE to pass the complete form, as core does.
   *
   * @dataProvider providerSubmitConvention
   */
  #[DataProvider('providerSubmitConvention')]
  public function testAutocompleteFilterSaved(bool $pass_subform): void {
    $term = $this->createTerm(Vocabulary::load('tags'));
    $filter_key = 'filter-field_ctools_views_tags_target_id';
    $identifier = 'tags_autocomplete_alternate_identifier';

    $form_state = (new FormState())
      ->addBuildInfo('args', ['views_block:ctools_views_entity_test-block_filter_auto', $pass_subform])
      ->setValues([
        'settings' => [
          'label' => 'Tags block',
          'exposed' => [
            $filter_key => [
              $identifier => $term->label() . ' (' . $term->id() . ')',
            ],
          ],
        ],
      ]);
    $this->container->get('form_builder')->submitForm(BlockPluginSubmitForm::class, $form_state);
    $this->assertSame([], $form_state->getErrors());

    $configuration = $form_state->get('block')->getConfiguration();
    $this->assertSame('entity_autocomplete', $configuration['exposed'][$filter_key]['type']);
    $this->assertEquals([$term->id()], $configuration['exposed'][$filter_key]['value']);
  }

  /**
   * Data provider for testAutocompleteFilterSaved().
   */
  public static function providerSubmitConvention(): array {
    return [
      'complete form (core)' => [FALSE],
      'settings subform (Block field)' => [TRUE],
    ];
  }

}
