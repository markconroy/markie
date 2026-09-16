<?php

namespace Drupal\ai\Exception;

/**
 * Error for when the tool is not valid.
 *
 * Extends AiFunctionCallingExecutionError so that runners already catching that
 * pass the validation message back to the model as tool output, instead of
 * letting it abort the run.
 */
class AiToolsValidationException extends AiFunctionCallingExecutionError {
}
