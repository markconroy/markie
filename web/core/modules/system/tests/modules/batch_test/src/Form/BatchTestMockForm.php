<?php

declare(strict_types=1);

namespace Drupal\batch_test\Form;

use Drupal\batch_test\BatchTestHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Generate form of id batch_test_mock_form.
 *
 * @internal
 */
class BatchTestMockForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'batch_test_mock_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['test_value'] = [
      '#title' => $this->t('Test value'),
      '#type' => 'textfield',
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch_test_helper = new BatchTestHelper();
    $batch_test_helper->stack('mock form submitted with value = ' . $form_state->getValue('test_value'));
  }

}
