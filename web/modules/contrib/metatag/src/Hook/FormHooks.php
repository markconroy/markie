<?php

namespace Drupal\metatag\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Form hook implementations for Metatag.
 */
class FormHooks {

  /**
   * Implements hook_form_FORM_ID_alter() for 'field_config_edit_form'.
   *
   * Configuration defaults are handled via a different mechanism, so do not
   * allow any values to be saved.
   */
  #[Hook('form_field_config_edit_form_alter')]
  public function formFieldConfigEditFormAlter(&$form, FormStateInterface $form_state) {
    // @todo Does this actually work?
    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\field\FieldConfigInterface $field_config */
    $field_config = $form_object->getEntity();
    if ($field_config->getType() == 'metatag') {
      // Hide the required and default value fields.
      $form['required']['#access'] = FALSE;
      $form['required']['#disabled'] = TRUE;
      $form['default_value']['#access'] = FALSE;
      $form['default_value']['#disabled'] = TRUE;
      // Step through the default value structure and erase any '#default_value'
      // items that are found.
      foreach ($form['default_value']['widget'][0] as &$outer) {
        if (is_array($outer)) {
          foreach ($outer as &$inner) {
            if (is_array($inner) && isset($inner['#default_value'])) {
              if (is_array($inner['#default_value'])) {
                $inner['#default_value'] = [];
              }
              else {
                $inner['#default_value'] = NULL;
              }
            }
          }
        }
      }
    }
  }

}
