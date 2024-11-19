<?php

namespace Drupal\ai\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Changes or Exceptions before a AI request is triggered can be done here.
 */
class PreGenerateResponseEvent extends Event {

  // The event name.
  const EVENT_NAME = 'ai.pre_generate_response';

  /**
   * The provider to process.
   *
   * @var string
   */
  protected $providerId;

  /**
   * The configuration of the provider.
   *
   * @var array
   */
  protected $configuration;

  /**
   * The operation type for the request.
   *
   * @var string
   */
  protected $operationType;

  /**
   * The model id.
   *
   * @var string
   */
  protected $modelId;

  /**
   * The input for the request.
   *
   * @var mixed
   */
  protected $input;

  /**
   * The output for the request.
   *
   * @var mixed
   */
  protected $output;

  /**
   * The tags for the request.
   *
   * @var array
   */
  protected $tags;

  /**
   * The authentication.
   *
   * @var mixed
   */
  protected $authentication;

  /**
   * The debug data.
   *
   * @var array
   */
  protected $debugData;

  /**
   * Constructs the object.
   *
   * @param string $provider_id
   *   The provider to process.
   * @param string $operation_type
   *   The operation type for the request.
   * @param array $configuration
   *   The configuration of the provider.
   * @param mixed $input
   *   The input for the request.
   * @param string $model_id
   *   The model ID for the request.
   * @param array $tags
   *   The tags for the request.
   * @param array $debug_data
   *   The debug data for the request.
   */
  public function __construct(string $provider_id, string $operation_type, array $configuration, mixed $input, string $model_id, array $tags = [], array $debug_data = []) {
    $this->providerId = $provider_id;
    $this->configuration = $configuration;
    $this->operationType = $operation_type;
    $this->modelId = $model_id;
    $this->input = $input;
    $this->tags = $tags;
    $this->debugData = $debug_data;
  }

  /**
   * Gets the provider.
   *
   * @return string
   *   The provider id.
   */
  public function getProviderId() {
    return $this->providerId;
  }

  /**
   * Gets the configuration.
   *
   * @return array
   *   The configuration.
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * Gets the operation type.
   *
   * @return string
   *   The operation type.
   */
  public function getOperationType() {
    return $this->operationType;
  }

  /**
   * Gets the model ID.
   *
   * @return string
   *   The model ID.
   */
  public function getModelId() {
    return $this->modelId;
  }

  /**
   * Gets the input.
   *
   * @return mixed
   *   The input.
   */
  public function getInput() {
    return $this->input;
  }

  /**
   * Gets the tags.
   *
   * @return array
   *   The tags.
   */
  public function getTags() {
    return $this->tags;
  }

  /**
   * Gets the debug data.
   *
   * @return array
   *   The debug data.
   */
  public function getDebugData() {
    return $this->debugData;
  }

  /**
   * Set extra debug data.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   */
  public function setDebugData(string $key, mixed $value) {
    $this->debugData[$key] = $value;
  }

  /**
   * Sets the input.
   *
   * @param mixed $input
   *   The input.
   */
  public function setInput(mixed $input) {
    $this->input = $input;
  }

  /**
   * Sets a new authentication layer.
   *
   * @param mixed $authentication
   *   The authentication.
   */
  public function setAuthentication(mixed $authentication) {
    $this->authentication = $authentication;
  }

  /**
   * Sets a new configuration.
   *
   * @param array $configuration
   *   The configuration.
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * Gets the authentication.
   *
   * Note: This only gets a new authentication layer if set. It does not return
   * the default authentication.
   *
   * @return mixed
   *   The authentication.
   */
  public function getAuthentication() {
    return $this->authentication;
  }

}
