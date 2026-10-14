<?php

declare(strict_types=1);

namespace Drupal\ai_translate_test\Plugin\FieldTextExtractor;

use Drupal\ai_translate\Attribute\FieldTextExtractor;
use Drupal\ai_translate\Plugin\FieldTextExtractor\FieldExtractorBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Test field extractor with the default configurable-field signature.
 */
#[FieldTextExtractor(
  id: 'ai_translate_test',
  label: new TranslatableMarkup('AI Translate test'),
  field_types: ['ai_translate_test'],
)]
class AiTranslateTestFieldExtractor extends FieldExtractorBase {

  /**
   * {@inheritdoc}
   */
  public function getColumns(): array {
    return ['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function shouldExtract(ContentEntityInterface $entity, FieldConfigInterface $fieldDefinition): bool {
    return $fieldDefinition->isTranslatable();
  }

  /**
   * {@inheritdoc}
   */
  public function setValue(ContentEntityInterface $entity, string $fieldName, array $textMeta): void {
    $entity->set($fieldName, $textMeta);
  }

}
