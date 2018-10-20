<?php

namespace Drupal\geshifield\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geshifilter\GeshiFilter;

/**
 * Plugin implementation of the 'geshifield_default' widget.
 *
 * @FieldWidget(
 *   id = "geshifield_default",
 *   label = @Translation("GeshiField default"),
 *   field_types = {
 *     "geshifield"
 *   }
 * )
 */
class GeshiFieldDefaultWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $enabled_languages = GeshiFilter::getEnabledLanguages();

    $element['sourcecode'] = [
      '#title' => $this->t('Code'),
      '#type' => 'textarea',
      '#default_value' => isset($items[$delta]->sourcecode) ? $items[$delta]->sourcecode : NULL,
    ];
    $element['language'] = [
      '#title' => $this->t('Language'),
      '#type' => 'select',
      '#default_value' => isset($items[$delta]->language) ? $items[$delta]->language : NULL,
      '#options' => $enabled_languages,
    ];
    return $element;
  }

}
