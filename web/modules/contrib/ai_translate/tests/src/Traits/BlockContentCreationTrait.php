<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Traits;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Provides methods for creating block_content entities and types.
 *
 * Copy of \Drupal\Tests\block_content\Traits\BlockContentCreationTrait, which
 * only exists since Drupal 11.3, with the same method signatures. The body
 * field part of core's BodyFieldCreationTrait (also 11.3+) is included, and
 * reuses the body field storage that block_content ships up to Drupal 11.2.
 *
 * @todo Use core's BlockContentCreationTrait instead once support for Drupal
 *   versions below 11.3 is dropped.
 */
trait BlockContentCreationTrait {

  /**
   * Creates a content block.
   *
   * @param bool|string $title
   *   (optional) Title of block. When no value is given uses a random name.
   *   Defaults to FALSE.
   * @param string $bundle
   *   (optional) Bundle name. Defaults to 'basic'.
   * @param bool $save
   *   (optional) Whether to save the block. Defaults to TRUE.
   * @param array $values
   *   (optional) Additional values for the block_content entity.
   *
   * @return \Drupal\block_content\Entity\BlockContent
   *   Created content block.
   */
  protected function createBlockContent(bool|string $title = FALSE, string $bundle = 'basic', bool $save = TRUE, array $values = []): BlockContent {
    $title = $title ?: $this->randomMachineName();
    $values += [
      'info' => $title,
      'type' => $bundle,
      'langcode' => 'en',
    ];
    $block_content = BlockContent::create($values);
    if ($save === TRUE) {
      $block_content->save();
    }
    return $block_content;
  }

  /**
   * Creates a content block type (bundle).
   *
   * @param array $values
   *   The values to create the type with. 'id', 'label' and 'revision'
   *   default to a random machine name, the ID and FALSE.
   * @param bool $create_body
   *   Whether or not to create the body field.
   *
   * @return \Drupal\block_content\Entity\BlockContentType
   *   Created content block type.
   */
  protected function createBlockContentType(array $values, bool $create_body = FALSE): BlockContentType {
    if (!isset($values['id'])) {
      do {
        $id = $this->randomMachineName(8);
      } while (BlockContentType::load($id));
    }
    else {
      $id = $values['id'];
    }
    $values += [
      'id' => $id,
      'label' => $id,
      'revision' => FALSE,
    ];
    $bundle = BlockContentType::create($values);
    $bundle->save();
    if ($create_body) {
      $this->createBlockContentBodyField($bundle->id());
    }
    return $bundle;
  }

  /**
   * Adds the body field to a content block type.
   *
   * Does what core's BodyFieldCreationTrait::createBodyField() does for
   * block_content. Up to Drupal 11.2 block_content ships the body field
   * storage itself, as text_with_summary rather than text_long, so it is
   * reused when it exists.
   *
   * @param string $bundle
   *   The content block type to add the field to.
   */
  protected function createBlockContentBodyField(string $bundle): void {
    $fieldStorage = FieldStorageConfig::loadByName('block_content', 'body');
    if (!$fieldStorage) {
      $fieldStorage = FieldStorageConfig::create([
        'field_name' => 'body',
        'type' => 'text_long',
        'entity_type' => 'block_content',
        'cardinality' => 1,
        'persist_with_no_fields' => TRUE,
      ]);
      $fieldStorage->save();
    }
    if (FieldConfig::loadByName('block_content', $bundle, 'body')) {
      return;
    }
    FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => $bundle,
      'label' => 'Body',
      'settings' => [
        'allowed_formats' => [],
      ],
    ])->save();

    $isSummaryField = $fieldStorage->getType() === 'text_with_summary';

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $displayRepository */
    $displayRepository = \Drupal::service('entity_display.repository');
    $displayRepository->getFormDisplay('block_content', $bundle)
      ->setComponent('body', [
        'type' => $isSummaryField ? 'text_textarea_with_summary' : 'text_textarea',
      ])
      ->save();
    $displayRepository->getViewDisplay('block_content', $bundle)
      ->setComponent('body', [
        'label' => 'hidden',
        'type' => 'text_default',
      ])
      ->save();

    // The teaser view mode is created by the Standard profile and might not
    // exist.
    if (isset($displayRepository->getViewModes('block_content')['teaser'])) {
      $displayRepository->getViewDisplay('block_content', $bundle, 'teaser')
        ->setComponent('body', [
          'label' => 'hidden',
          'type' => $isSummaryField ? 'text_summary_or_trimmed' : 'text_trimmed',
        ])
        ->save();
    }
  }

}
