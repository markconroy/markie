<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\ai_automators\Traits\AutomatorFieldWidgetActionTrait;
use Drupal\field_widget_actions\FieldWidgetActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This is an abstract base class for automator actions.
 */
abstract class AutomatorBaseAction extends FieldWidgetActionBase {

  use AutomatorFieldWidgetActionTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    static::initAutomatorServices($instance, $container);
    return $instance;
  }

}
