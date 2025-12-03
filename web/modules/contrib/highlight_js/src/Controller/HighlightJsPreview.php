<?php

namespace Drupal\highlight_js\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\editor\Entity\Editor;
use Drupal\highlight_js\HighlightJsPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns the preview for highlight js.
 */
class HighlightJsPreview extends ControllerBase {

  use StringTranslationTrait;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * The highlight js plugin manager.
   *
   * @var \Drupal\highlight_js\HighlightJsPluginManager
   */
  protected $highlightJsPluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.ckedito5_highlight_js'),
      $container->get('renderer')
    );
  }

  /**
   * The controller constructor.
   *
   * @param \Drupal\highlight_js\HighlightJsPluginManager $highlight_js_plugin_manager
   *   The highlight js plugin manager.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   */
  public function __construct(HighlightJsPluginManager $highlight_js_plugin_manager, Renderer $renderer) {
    $this->highlightJsPluginManager = $highlight_js_plugin_manager;
    $this->renderer = $renderer;
  }

  /**
   * Controller callback that renders the preview for CKeditor.
   */
  public function preview(Request $request, Editor $editor) {
    $plugin_id = $request->query->get('plugin_id');
    $plugin_config = $request->query->get('plugin_config');

    try {
      if (!$plugin_config || !$plugin_id) {
        throw new \Exception();
      }

      $plugin_config = Xss::filter($plugin_config);
      $plugin_config = Json::decode($plugin_config);
      $plugin_id = Xss::filter($plugin_id);

      /** @var \Drupal\highlight_js\HighlightJsInterface $instance */
      $instance = $this->highlightJsPluginManager->createInstance($plugin_id, $plugin_config);
      $build = $instance->build();
    }
    catch (\Exception $e) {
      $build = [
        'markup' => [
          '#type' => 'markup',
          '#markup' => $this->t('Incorrect configuration. Please recreate this highlight js.'),
        ],
      ];
    }
    return new Response($this->renderer->renderRoot($build));
  }

  /**
   * Access callback for viewing the preview.
   *
   * @param \Drupal\editor\Entity\Editor $editor
   *   The editor.
   * @param \Drupal\Core\Session\AccountProxy $account
   *   The current user.
   *
   * @return \Drupal\Core\Access\AccessResult|\Drupal\Core\Access\AccessResultReasonInterface
   *   The acccess result.
   */
  public function checkAccess(Editor $editor, AccountProxy $account) {
    return AccessResult::allowedIfHasPermission($account, 'use text format ' . $editor->getFilterFormat()->id());
  }

}
