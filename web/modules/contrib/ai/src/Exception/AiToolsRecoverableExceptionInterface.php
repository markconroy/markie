<?php

declare(strict_types=1);

namespace Drupal\ai\Exception;

/**
 * Marker interface for tool failures the model is expected to correct.
 *
 * A tool runner should catch this and hand the message back to the model as
 * tool output, so it can fix its arguments and call the tool again. These are
 * never meant to reach the end user or abort the run.
 *
 * Do not implement this on failures that should stop everything, such as
 * \Drupal\ai\Exception\AiRateLimitException or
 * \Drupal\ai\Exception\AiRequestErrorException. Retrying those only wastes
 * requests.
 */
interface AiToolsRecoverableExceptionInterface extends AiExceptionInterface {}
