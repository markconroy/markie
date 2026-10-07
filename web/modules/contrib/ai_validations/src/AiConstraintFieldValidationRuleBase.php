<?php

declare(strict_types=1);

namespace Drupal\ai_validations;

use Drupal\Core\Utility\Token;
use Drupal\ai\AiProviderPluginManager;
use Drupal\field_validation\ConstraintFieldValidationRuleBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for AI constraint field validation rules.
 *
 * Centralizes the AI provider service injection and the boilerplate needed
 * to render and persist an "ai_provider_configuration" form element across
 * all AI-backed validation rules.
 *
 * Concrete plugins must declare their own @FieldValidationRule annotation;
 * this base class intentionally has none so it is not discovered as a plugin.
 *
 * @phpstan-consistent-constructor
 */
abstract class AiConstraintFieldValidationRuleBase extends ConstraintFieldValidationRuleBase {

  /**
   * The AI provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProvider;

  /**
   * {@inheritdoc}
   */
  final public function __construct(
    $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerInterface $logger,
    Token $token_service,
    AiProviderPluginManager $aiProvider,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger, $token_service);
    $this->aiProvider = $aiProvider;
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('field_validation'),
      $container->get('token'),
      $container->get('ai.provider'),
    );
  }

  /**
   * Returns the AI operation type used by the plugin.
   *
   * Used as the #operation_type for the ai_provider_configuration element.
   *
   * @return string
   *   Operation type machine name (e.g. "chat", "image_classification").
   */
  abstract protected function getOperationType(): string;

  /**
   * Returns the configuration key used to store the chosen provider/model.
   *
   * Existing rules historically use either "model" or "provider"; subclasses
   * override to preserve their stored configuration key.
   *
   * @return string
   *   The configuration key, e.g. "model" or "provider".
   */
  protected function getModelConfigKey(): string {
    return 'model';
  }

  /**
   * Builds the default value array for an ai_provider_configuration element.
   *
   * Decodes the stored "provider__model" string back into the structured
   * value expected by the form element.
   *
   * @param string $configKey
   *   The configuration key holding the stored "provider__model" string.
   *
   * @return array|null
   *   A structured array with "provider", "model", and "config" keys, or NULL
   *   when no valid stored value is found.
   */
  protected function buildProviderDefaultValue(string $configKey): ?array {
    $stored = $this->configuration[$configKey] ?? '';
    if (!is_string($stored) || $stored === '') {
      return NULL;
    }
    $parts = explode('__', $stored);
    if (count($parts) !== 2) {
      return NULL;
    }
    return [
      'provider' => $parts[0],
      'model' => $parts[1],
      'config' => [],
    ];
  }

  /**
   * Builds the default value for the primary provider/model form element.
   *
   * @return array|null
   *   A structured array with "provider", "model", and "config" keys, or NULL
   *   when no valid stored value is found.
   */
  protected function buildModelDefaultValue(): ?array {
    return $this->buildProviderDefaultValue($this->getModelConfigKey());
  }

  /**
   * Normalizes a submitted ai_provider_configuration value to a stored string.
   *
   * @param mixed $value
   *   The raw form state value for the provider/model element.
   *
   * @return string
   *   A "provider__model" string, or an empty string when not selected.
   */
  protected function extractStoredModel(mixed $value): string {
    if (is_array($value)) {
      if (!empty($value['provider']) && !empty($value['model'])) {
        return $value['provider'] . '__' . $value['model'];
      }
      if (!empty($value['provider_model']) && is_string($value['provider_model'])) {
        return $value['provider_model'];
      }
      return '';
    }
    return is_string($value) ? $value : '';
  }

}
