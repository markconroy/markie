<?php

namespace Drupal\metatag_extended_perms\Hook;

use Drupal\metatag\Plugin\Field\FieldWidget\MetatagFirehose;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\metatag\MetatagGroupPluginManager;

/**
 * Field hook implementations for metatag_extended_perms.
 */
class FieldHooks {

  /**
   * The group plugin manager.
   *
   * @var \Drupal\metatag\MetatagGroupPluginManager
   */
  protected readonly MetatagGroupPluginManager $groupManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected readonly AccountInterface $currentUser;

  /**
   * Constructs a new FieldHooks object.
   *
   * @param \Drupal\metatag\MetatagGroupPluginManager $group_manager
   *   The group plugin manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(
    MetatagGroupPluginManager $group_manager,
    AccountInterface $current_user,
  ) {
    $this->groupManager = $group_manager;
    $this->currentUser = $current_user;
  }

  /**
   * Implements hook_field_widget_single_element_form_alter().
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(&$element, FormStateInterface $form_state, $context) {
    if ($context['widget'] instanceof MetatagFirehose) {
      $group_manager = $this->groupManager;
      // Prevent access to the element until at least one permission is granted.
      $element['#access'] = FALSE;
      foreach (Element::children($element) as $group_id) {
        $group = $group_manager->getDefinition($group_id, FALSE);
        if ($group === NULL) {
          continue;
        }
        // By default restrict access to group and regain access when user has
        // access to at least one tag in group; this prevents displaying empty
        // groups.
        $element[$group_id]['#access'] = FALSE;
        // Check through each meta tag field on the field widget.
        foreach (Element::children($element[$group_id]) as $tag_id) {
          // Check tag permission.
          $element[$group_id][$tag_id]['#access'] = $this->currentUser->hasPermission('access metatag ' . $group_id . '__' . $tag_id);
          // Make the parent and group accessible if user has access to the tag.
          if ($element[$group_id][$tag_id]['#access']) {
            $element[$group_id]['#access'] = TRUE;
            $element['#access'] = TRUE;
          }
        }
      }
    }
  }

}
