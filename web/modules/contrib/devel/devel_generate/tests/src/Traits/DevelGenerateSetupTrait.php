<?php

namespace Drupal\Tests\devel_generate\Traits;

use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Language\Language;
use Drupal\taxonomy\Entity\Vocabulary;

trait DevelGenerateSetupTrait
{

  use CommentTestTrait;
  use EntityReferenceTestTrait;

  /**
   * Vocabulary for testing.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  public function setUpData() {
    // Create Basic page and Article node types.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(array('type' => 'page', 'name' => 'Basic Page'));
      $this->drupalCreateContentType(array('type' => 'article', 'name' => 'Article'));
      $this->addDefaultCommentField('node', 'article');
    }

    // Creating a vocabulary to associate taxonomy terms generated.
    $this->vocabulary = Vocabulary::create([
      'name' => $this->randomMachineName(),
      'description' => $this->randomMachineName(),
      'vid' => mb_strtolower($this->randomMachineName()),
      'langcode' => Language::LANGCODE_NOT_SPECIFIED,
      'weight' => mt_rand(0, 10),
    ]);
    $this->vocabulary->save();

    // Creates a field of an entity reference field storage on article.
    $field_name = 'taxonomy_' . $this->vocabulary->id();

    $handler_settings = array(
      'target_bundles' => array(
        $this->vocabulary->id() => $this->vocabulary->id(),
      ),
      'auto_create' => TRUE,
    );
    $this->createEntityReferenceField('node', 'article', $field_name, NULL, 'taxonomy_term', 'default', $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    entity_get_form_display('node', 'article', 'default')
      ->setComponent($field_name, array(
        'type' => 'options_select',
      ))
      ->save();

    entity_get_display('node', 'article', 'default')
      ->setComponent($field_name, array(
        'type' => 'entity_reference_label',
      ))
      ->save();
  }
}
