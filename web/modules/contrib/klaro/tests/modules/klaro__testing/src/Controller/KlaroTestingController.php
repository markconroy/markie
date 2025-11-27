<?php

declare(strict_types=1);

namespace Drupal\klaro__testing\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Theme\Registry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for Klaro Consent Manager Testing routes.
 */
final class KlaroTestingController extends ControllerBase {

  /**
   * The theme registry used to determine which stage to use.
   *
   * @var \Drupal\Core\Theme\Registry
   */
  protected $themeRegistry;

  /**
   * Constructs a new ThemeRegistryLoader object.
   *
   * @param \Drupal\Core\Theme\Registry $theme_registry
   *   The theme registry.
   */
  public function __construct(Registry $theme_registry) {
    $this->themeRegistry = $theme_registry;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('theme.registry')
    );
  }

  /**
   * Prepare stage for rendering through normal pipeline.
   */
  public function __invoke($stage): array {
    $stages = $this->themeRegistry->get();

    $expected_stage = 'STAGE__' . $stage;
    if (isset($stages[$expected_stage]) == FALSE) {
      throw new NotFoundHttpException();
    }

    $build['content'] = [
      '#theme' => $expected_stage,
    ];
    return $build;
  }

}
