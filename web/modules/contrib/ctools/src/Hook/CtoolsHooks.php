<?php

declare(strict_types=1);

namespace Drupal\ctools\Hook;

use Drupal\Core\Entity\Plugin\Condition\EntityBundle as CoreEntityBundle;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\ctools\Plugin\Condition\EntityBundle;

/**
 * Hook implementations for ctools.
 */
final class CtoolsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path): array {
    return [
      'ctools_wizard_trail' => [
        'variables' => [
          'wizard' => NULL,
          'cached_values' => [],
          'trail' => [],
          'divider' => ' » ',
          'step' => NULL,
        ],
        // Core before 11.2 ignores 'initial preprocess' and calls
        // template_preprocess_ctools_wizard_trail() instead.
        'initial preprocess' => static::class . ':preprocessWizardTrail',
      ],
      'ctools_wizard_trail_links' => [
        'variables' => [
          'wizard' => NULL,
          'cached_values' => [],
          'trail' => [],
          'divider' => ' » ',
          'step' => NULL,
        ],
        // Core before 11.2 ignores 'initial preprocess' and calls
        // template_preprocess_ctools_wizard_trail_links() instead.
        'initial preprocess' => static::class . ':preprocessWizardTrailLinks',
      ],
    ];
  }

  /**
   * Prepares variables for ctools wizard trail templates.
   *
   * @param array $variables
   *   The variables.
   */
  public function preprocessWizardTrail(array &$variables): void {
    /** @var \Drupal\ctools\Wizard\FormWizardInterface|\Drupal\ctools\Wizard\EntityFormWizardInterface $wizard */
    $wizard = $variables['wizard'];
    $cached_values = $variables['cached_values'];
    $trail = $variables['trail'];
    $variables['step'] = $wizard->getStep($cached_values);
    foreach ($wizard->getOperations($cached_values) as $step => $operation) {
      $trail[$step] = !empty($operation['title']) ? $operation['title'] : '';
    }
    $variables['trail'] = $trail;
  }

  /**
   * Prepares variables for ctools wizard trail links templates.
   *
   * @param array $variables
   *   The variables.
   */
  public function preprocessWizardTrailLinks(array &$variables): void {
    /** @var \Drupal\ctools\Wizard\FormWizardInterface|\Drupal\ctools\Wizard\EntityFormWizardInterface $wizard */
    $wizard = $variables['wizard'];
    $cached_values = $variables['cached_values'];
    $trail = $variables['trail'];
    $variables['step'] = $wizard->getStep($cached_values);
    foreach ($wizard->getOperations($cached_values) as $step => $operation) {
      $parameters = $wizard->getNextParameters($cached_values);
      // Override step to be the step we want.
      $parameters['step'] = $step;
      $trail[$step] = [
        'title' => !empty($operation['title']) ? $operation['title'] : '',
        'url' => new Url($wizard->getRouteName(), $parameters),
      ];
    }
    $variables['trail'] = $trail;
  }

  /**
   * Implements hook_condition_info_alter().
   */
  #[Hook('condition_info_alter')]
  public function conditionInfoAlter(array &$definitions): void {
    // Replace all generic entity bundle condition classes if they are
    // unaltered.
    foreach ($definitions as $id => $definition) {
      if (str_starts_with($id, 'entity_bundle:') && ($definition['class'] == CoreEntityBundle::class || $definition['class'] == 'Drupal\ctools\Plugin\Condition\EntityBundleConstraint')) {
        $definitions[$id]['class'] = EntityBundle::class;
      }
    }
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.ctools':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('This suite is primarily a set of APIs and tools to improve the developer experience. It also contains a module called the Page Manager whose job is to manage pages. In particular it manages panel pages, but as it grows it will be able to manage far more than just Panels.') . '</p>';

        $output .= '<p>' . $this->t('For the moment, it includes the following tools:') . '</p>';
        $output .= '<ul>';
        $output .= '<li>' . $this->t('Plugins -- tools to make it easy for modules to let other modules implement plugins from .inc files.') . '</li>';
        $output .= '<li>' . $this->t('Exportables -- tools to make it easier for modules to have objects that live in database or live in code, such as "default views".') . '</li>';
        $output .= '<li>' . $this->t('AJAX responder -- tools to make it easier for the server to handle AJAX requests and tell the client what to do with them.') . '</li>';
        $output .= '<li>' . $this->t('Form tools -- tools to make it easier for forms to deal with AJAX.') . '</li>';
        $output .= '<li>' . $this->t('Object caching -- tool to make it easier to edit an object across multiple page requests and cache the editing work.') . '</li>';
        $output .= '<li>' . $this->t('Contexts -- the notion of wrapping objects in a unified wrapper and providing an API to create and accept these contexts as input.') . '</li>';
        $output .= '<li>' . $this->t('Modal dialog -- tool to make it simple to put a form in a modal dialog.') . '</li>';
        $output .= '<li>' . $this->t('Dependent -- a simple form widget to make form items appear and disappear based upon the selections in another item.') . '</li>';
        $output .= '<li>' . $this->t('Content -- pluggable content types used as panes in Panels and other modules like Dashboard.') . '</li>';
        $output .= '<li>' . $this->t('Form wizard -- an API to make multi-step forms much easier.') . '</li>';
        $output .= '<li>' . $this->t('CSS tools -- tools to cache and sanitize CSS easily to make user-input CSS safe.') . '</li>';
        $output .= '</ul>';
        return $output;
    }
    return NULL;
  }

}
