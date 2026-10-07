<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Drupal\ai_validations\AiConstraintValidatorBase;
use Symfony\Component\Validator\Constraint;

/**
 * AiAudio constraint validator.
 */
final class AiAudioConstraintValidator extends AiConstraintValidatorBase {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $data, Constraint $constraint): void {
    $sttOption = $this->resolveProviderOption(
      $constraint->provider,
      'speech_to_text',
      'No speech-to-text AI provider specified for audio validation',
    );
    if ($sttOption === NULL) {
      return;
    }

    $chatOption = $this->resolveProviderOption(
      $constraint->chat_provider,
      'chat',
      'No chat AI provider specified for transcript evaluation',
    );
    if ($chatOption === NULL) {
      return;
    }

    $transcript = $this->transcribeAudio($data, $sttOption);
    if ($transcript === NULL) {
      return;
    }

    $prompt = $constraint->prompt . PHP_EOL . $constraint->message;
    if (!$this->runBooleanChat($chatOption, $prompt, $transcript)) {
      $this->context->addViolation($constraint->message, []);
    }
  }

}
