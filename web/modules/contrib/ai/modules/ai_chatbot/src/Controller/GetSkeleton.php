<?php

namespace Drupal\ai_chatbot\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Renderer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Creates a chatbot message skeleton for twig.
 */
class GetSkeleton extends ControllerBase {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * Constructs a new GetSkeleton object.
   */
  public function __construct(Renderer $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * Dependency injection.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer')
    );
  }

  /**
   * Gets the skeleton of a chat message.
   *
   * Because we want to be able to load the messages streamed, an empty skeleton
   * of the markup is needed to be able to render the messages in the chatbot.
   * We do not want to take care of one template in the frontend and one in the
   * backend, so this is a simple way to keep the templates in one place. Its
   * loaded over AJAX, but we could put it in the Drupal settings and load it
   * with the page load.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   Return the message skeleton.
   */
  public function messageSkeleton() {
    $theme = [
      '#theme' => 'ai_chatbot_message',
      '#timestamp' => date('H:i:s'),
    ];
    return new JsonResponse([
      'skeleton' => $this->renderer->render($theme),
    ]);
  }

}
