<?php

namespace Drupal\ai\OperationType\ExtractiveQuestionAnswering;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for extractive question answering models.
 */
#[OperationType(
  id: 'extractive_question_answering',
  label: new TranslatableMarkup('Extractive Question Answering'),
)]
interface ExtractiveQuestionAnsweringInterface extends OperationTypeInterface {

  /**
   * Extract answers from a context passage given a question.
   *
   * @param string|\Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringInput $input
   *   The extractive question answering input.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\ExtractiveQuestionAnswering\ExtractiveQuestionAnsweringOutput
   *   The extractive question answering output.
   */
  public function extractiveQuestionAnswering(string|ExtractiveQuestionAnsweringInput $input, string $model_id, array $tags = []): ExtractiveQuestionAnsweringOutput;

}
