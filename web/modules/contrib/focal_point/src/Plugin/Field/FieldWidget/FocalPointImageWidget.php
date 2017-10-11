<?php

namespace Drupal\focal_point\Plugin\Field\FieldWidget;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\crop\Entity\Crop;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Plugin\Field\FieldWidget\ImageWidget;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'image_fp' widget.
 *
 * The annotation has been intentionally omitted. Rather than create an entirely
 * separate widget for image fields, this class is used to supplant the existing
 * widget that comes with the core image module.
 *
 * @see focal_point_field_widget_form_alter
 */
class FocalPointImageWidget extends ImageWidget {

  const PREVIEW_TOKEN_NAME = 'focal_point_preview';

  /**
   * {@inheritdoc}
   *
   * Form API callback: Processes an image_fp field element.
   *
   * Expands the image_fp type to include the focal_point field.
   *
   * This method is assigned as a #process callback in formElement() method.
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $element = parent::process($element, $form_state, $form);

    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];
    $element_selector = 'focal-point-' . implode('-', $element['#parents']);

    $default_focal_point_value = isset($item['focal_point']) ? $item['focal_point'] : \Drupal::config('focal_point.settings')->get('default_value');

    // Add the focal point indicator to preview.
    if (isset($element['preview'])) {
      $indicator = array(
        '#theme_wrappers' => array('container'),
        '#attributes' => array(
          'class' => array('focal-point-indicator'),
          'data-selector' => $element_selector,
          'data-delta' => $element['#delta'],
        ),
        '#markup' => '',
      );

      $preview = array(
        'indicator' => $indicator,
        'thumbnail' => $element['preview'],
      );

      $display_preview_link = \Drupal::config('focal_point.preview')->get('display_link');

      // Even for image fields with a cardinality higher than 1 the correct fid
      // can always be found in $item['fids'][0].
      $fid = isset($item['fids'][0]) ? $item['fids'][0] : '';
      if ($display_preview_link && !empty($fid)) {
        // Replace comma (,) with an x to make javascript handling easier.
        $preview_focal_point_value = str_replace(',', 'x', $default_focal_point_value);

        // Create a token to be used during an access check on the preview page.
        $token = self::getPreviewToken();

        $preview_link = [
          '#type' => 'link',
          '#title' => t('Preview'),
          '#url' => new Url('focal_point.preview',
            [
              'fid' => $fid,
              'focal_point_value' => $preview_focal_point_value,
            ],
            [
              'query' => array('focal_point_token' => $token),
            ]),
          '#attributes' => [
            'class' => array('focal-point-preview-link'),
            'data-selector' => $element_selector,
            'data-field-name' => $element['#field_name'],
            'target' => '_blank',
          ],
        ];

        $preview['preview_link'] = $preview_link;
      }

      // Use the existing preview weight value so that the focal point indicator
      // and thumbnail appear in the correct order.
      $preview['#weight'] = isset($element['preview']['#weight']) ? $element['preview']['#weight'] : 0;
      unset($preview['thumbnail']['#weight']);

      $element['preview'] = $preview;
    }

    // Add the focal point field.
    $element_selector = 'focal-point-' . implode('-', $element['#parents']);
    $element['focal_point'] = array(
      '#type' => 'textfield',
      '#title' => 'Focal point',
      '#description' => new TranslatableMarkup('Specify the focus of this image in the form "leftoffset,topoffset" where offsets are in percents. Ex: 25,75'),
      '#default_value' => $default_focal_point_value,
      '#element_validate' => array('\Drupal\focal_point\Plugin\Field\FieldWidget\FocalPointImageWidget::validateFocalPoint'),
      '#attributes' => array(
        'class' => array('focal-point', $element_selector),
        'data-selector' => $element_selector,
        'data-field-name' => $element['#field_name'],
      ),
      '#attached' => array(
        'library' => array('focal_point/drupal.focal_point'),
      ),
    );

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * Form API callback. Retrieves the value for the file_generic field element.
   *
   * This method is assigned as a #value_callback in formElement() method.
   */
  public static function value($element, $input, FormStateInterface $form_state) {
    $return = parent::value($element, $input, $form_state);

    // When an element is loaded, focal_point needs to be set. During a form
    // submission the value will already be there.
    if (isset($return['target_id']) && !isset($return['focal_point'])) {
      /** @var \Drupal\file\FileInterface $file */
      $file = \Drupal::service('entity_type.manager')
        ->getStorage('file')
        ->load($return['target_id']);
      if ($file) {
        $crop_type = \Drupal::config('focal_point.settings')->get('crop_type');
        $crop = Crop::findCrop($file->getFileUri(), $crop_type);
        if ($crop) {
          $anchor = \Drupal::service('focal_point.manager')
            ->absoluteToRelative($crop->x->value, $crop->y->value, $return['width'], $return['height']);
          $return['focal_point'] = "{$anchor['x']},{$anchor['y']}";
        }
      }
      else {
        \Drupal::logger('focal_point')->notice("Attempted to get a focal point value for an invalid or temporary file.");
        $return['focal_point'] = \Drupal::config('focal_point.settings')->get('default_value');
      }
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   *
   * Validate callback for the focal point field.
   */
  public static function validateFocalPoint($element, FormStateInterface $form_state) {
    if (empty($element['#value']) || (FALSE === \Drupal::service('focal_point.manager')->validateFocalPoint($element['#value']))) {
      $replacements = ['@title' => strtolower($element['#title'])];
      $form_state->setError($element, new TranslatableMarkup('The @title field should be in the form "leftoffset,topoffset" where offsets are in percentages. Ex: 25,75.', $replacements));
    }
  }

  /**
   * Create and return a token to use for accessing the preview page.
   *
   * @return string
   *   A valid token.
   *
   * @codeCoverageIgnore
   */
  public static function getPreviewToken() {
    return \Drupal::csrfToken()->get(self::PREVIEW_TOKEN_NAME);
  }

  /**
   * Validate a preview token.
   *
   * @param string $token
   *   A drupal generated token.
   *
   * @return bool
   *   True if the token is valid.
   *
   * @codeCoverageIgnore
   */
  public static function validatePreviewToken($token) {
    return \Drupal::csrfToken()->validate($token, self::PREVIEW_TOKEN_NAME);
  }

}
