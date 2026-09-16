<?php

namespace Drupal\ai\Exception;

/**
 * This happens when something goes wrong while executing.
 *
 * Tool runners turn this into tool output so the model can try again.
 */
class AiFunctionCallingExecutionError extends \Exception implements AiToolsRecoverableExceptionInterface {
}
