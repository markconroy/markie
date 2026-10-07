<?php

declare(strict_types=1);

namespace Drupal\ai_validations_test\Plugin\AiProvider;

use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\OperationType\TextClassification\TextClassificationInput;
use Drupal\ai\OperationType\TextClassification\TextClassificationItem;
use Drupal\ai\OperationType\TextClassification\TextClassificationOutput;
use Drupal\ai_test\Plugin\AiProvider\EchoProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A text-classification provider whose output is driven by state.
 *
 * The bundled EchoProvider only echoes back the labels supplied on the input,
 * and the text classification validator supplies none, so it can never return
 * a classification to match against. This provider lets a test set the exact
 * label/confidence pairs (or force a failure) via state, so the validator's
 * match, confidence-threshold and not-available behavior can be exercised
 * through the real provider manager.
 */
#[AiProvider(
  id: 'classifier_test',
  label: new TranslatableMarkup('Classifier Test'),
)]
class ClassifierTestProvider extends EchoProvider {

  /**
   * State key holding the classifications (array of label/confidence rows).
   */
  public const STATE_CLASSIFICATIONS = 'ai_validations_test.classifications';

  /**
   * State key that, when TRUE, makes the provider throw.
   */
  public const STATE_THROW = 'ai_validations_test.throw';

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->state = $container->get('state');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function textClassification(string|TextClassificationInput $input, string $model_id, array $tags = []): TextClassificationOutput {
    if ($this->state->get(self::STATE_THROW, FALSE)) {
      throw new \RuntimeException('Simulated provider failure.');
    }

    $items = [];
    $response = [];
    foreach ($this->state->get(self::STATE_CLASSIFICATIONS, []) as $row) {
      $items[] = new TextClassificationItem($row['label'], $row['confidence']);
      $response[] = $row;
    }

    return new TextClassificationOutput($items, $response, []);
  }

}
