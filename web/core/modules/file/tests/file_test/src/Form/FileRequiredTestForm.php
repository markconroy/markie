<?php

declare(strict_types=1);

namespace Drupal\file_test\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * File required test form class.
 */
class FileRequiredTestForm extends FileTestForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return '_file_required_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $form['file_test_upload']['#required'] = TRUE;
    return $form;
  }

}
