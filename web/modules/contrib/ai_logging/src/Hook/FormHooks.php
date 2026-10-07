<?php

declare(strict_types=1);

namespace Drupal\ai_logging\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Form hook implementations for AI Logging.
 *
 * @internal
 */
class FormHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new FormHooks object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly AccountInterface $currentUser,
  ) {
  }

  /**
   * Implements hook_form_FORM_ID_alter() for views_exposed_form.
   *
   * Adds a help message below the AI Logs view's exposed filters pointing
   * users who can access the site-wide logs there, since errors AI Logging
   * does not itself capture (e.g. rate-limit responses) may still be recorded
   * there by the provider or HTTP client. See #3611898.
   */
  #[Hook('form_views_exposed_form_alter')]
  public function formViewsExposedFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\views\ViewExecutable|null $view */
    $view = $form_state->get('view');
    if (!$view || $view->id() !== 'ai_logs' || $view->current_display !== 'page_1') {
      return;
    }

    $dblog_enabled = $this->moduleHandler->moduleExists('dblog');
    $access = AccessResult::allowedIf($dblog_enabled)
      ->andIf(AccessResult::allowedIfHasPermission($this->currentUser, 'access site reports'));

    $build = [
      '#type' => 'container',
      '#weight' => 100,
      '#access' => $access,
      '#attributes' => [
        'class' => [
          'ai-logging-dblog-help',
          'form-item__description',
        ],
      ],
      '#attached' => [
        'library' => ['ai_logging/dblog-help'],
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
      ],
    ];
    if ($dblog_enabled) {
      $build['message'] = [
        '#markup' => $this->t('Further logs and errors may be found in the <a href=":url">site-wide logs</a>.', [
          ':url' => Url::fromRoute('dblog.overview')->toString(),
        ]),
      ];
    }
    $form['ai_logging_dblog_help'] = $build;
  }

}
