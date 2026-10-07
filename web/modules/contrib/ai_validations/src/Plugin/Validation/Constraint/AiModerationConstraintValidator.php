<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Drupal\ai\OperationType\Moderation\ModerationInput;
use Drupal\ai_validations\AiConstraintValidatorBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;

/**
 * AiModeration constraint validator.
 */
final class AiModerationConstraintValidator extends AiConstraintValidatorBase {

  /**
   * Logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->logger = $container->get('logger.factory')->get('ai_validations');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $data, Constraint $constraint): void {
    $option = $this->resolveProviderOption(
      $constraint->provider,
      'moderation',
      'No AI provider specified to do moderation validation',
    );
    if ($option === NULL) {
      return;
    }

    if ($data === NULL || (string) $data === '') {
      return;
    }

    $provider = $this->aiPluginManager->loadProviderFromSimpleOption($option);
    $model = $this->aiPluginManager->getModelNameFromSimpleOption($option);

    try {
      $response = $provider->moderation(new ModerationInput((string) $data), $model)->getNormalized();
    }
    catch (\Exception $e) {
      $this->logger->error('AI moderation provider error: @message', ['@message' => $e->getMessage()]);
      $this->context->addViolation($constraint->message, []);
      return;
    }

    if (!$response->isFlagged()) {
      return;
    }

    if ($constraint->categories === '') {
      $this->context->addViolation($constraint->message, []);
      return;
    }

    $watched = array_map('trim', explode(',', $constraint->categories));
    $information = $response->getInformation();
    $threshold = (float) $constraint->threshold;
    foreach ($watched as $category) {
      $score = $information[$category] ?? 0.0;
      if (is_numeric($score) && (float) $score > $threshold) {
        $this->context->addViolation($constraint->message, []);
        return;
      }
    }
  }

}
