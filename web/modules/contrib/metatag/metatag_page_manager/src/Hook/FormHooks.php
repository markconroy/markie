<?php

namespace Drupal\metatag_page_manager\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for metatag_page_manager.
 */
class FormHooks {

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * @deprecated in metatag:2.2.2 and is removed from metatag:3.0.0. No replacement is available.
   *
   * @see https://www.drupal.org/node/3633239
   */
  #[Hook('form_metatag_defaults_add_form_alter')]
  public function formMetatagDefaultsAddFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    $variants_options['Page Variants'] = _metatag_page_manager_get_variants();
    $form['id']['#options'] = array_merge($form['id']['#options'], $variants_options);
  }

}
