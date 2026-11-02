<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorProcess;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\AiAutomatorRuleRunner;
use Drupal\ai_automators\AiAutomatorStatusField;
use Drupal\ai_automators\Attribute\AiAutomatorProcessRule;
use Drupal\ai_automators\AutomatorFallbackUser;
use Drupal\ai_automators\Exceptions\AiAutomatorRequestErrorException;
use Drupal\ai_automators\Exceptions\AiAutomatorResponseErrorException;
use Drupal\ai_automators\Exceptions\AiAutomatorRuleNotFoundException;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorFieldProcessInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The direct processor.
 */
#[AiAutomatorProcessRule(
  id: 'direct',
  title: new TranslatableMarkup('Direct'),
  description: new TranslatableMarkup('Processes and saves the value directly.'),
)]
class DirectSaveProcessing implements AiAutomatorFieldProcessInterface, ContainerFactoryPluginInterface {

  /**
   * Direct Saving.
   */
  protected AiAutomatorRuleRunner $aiRunner;

  /**
   * The Drupal logger factory.
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The Drupal messenger service.
   */
  protected MessengerInterface $messenger;

  /**
   * The current user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The account switcher.
   */
  protected AccountSwitcherInterface $accountSwitcher;

  /**
   * The user to run as when there is no current user.
   */
  protected AutomatorFallbackUser $fallbackUser;

  /**
   * Constructor.
   */
  final public function __construct(AiAutomatorRuleRunner $aiRunner, LoggerChannelFactoryInterface $logger, MessengerInterface $messenger, AccountProxyInterface $currentUser, AccountSwitcherInterface $accountSwitcher, AutomatorFallbackUser $fallbackUser) {
    $this->aiRunner = $aiRunner;
    $this->loggerFactory = $logger;
    $this->messenger = $messenger;
    $this->currentUser = $currentUser;
    $this->accountSwitcher = $accountSwitcher;
    $this->fallbackUser = $fallbackUser;
  }

  /**
   * {@inheritDoc}
   */
  final public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('ai_automator.rule_runner'),
      $container->get('logger.factory'),
      $container->get('messenger'),
      $container->get('current_user'),
      $container->get('account_switcher'),
      $container->get('ai_automator.fallback_user'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function modify(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Saves without a current user, for example from cron or drush, run as the
    // fallback user from the settings, the same as queued automators.
    $account = $this->currentUser->isAnonymous() ? $this->fallbackUser->getAccount() : NULL;
    if (!$account) {
      return $this->generateResponse($entity, $fieldDefinition, $automatorConfig);
    }
    $this->accountSwitcher->switchTo($account);
    try {
      return $this->generateResponse($entity, $fieldDefinition, $automatorConfig);
    }
    finally {
      $this->accountSwitcher->switchBack();
    }
  }

  /**
   * Generates the response as the current user.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   * @param array $automatorConfig
   *   The automator config.
   *
   * @return bool
   *   The result of the runner, or FALSE on failure.
   */
  protected function generateResponse(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    try {
      return $this->aiRunner->generateResponse($entity, $fieldDefinition, $automatorConfig);
    }
    catch (AiAutomatorRuleNotFoundException $e) {
      $this->loggerFactory->get('ai_automator')->warning('A rule was not found, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    catch (AiAutomatorRequestErrorException $e) {
      $this->loggerFactory->get('ai_automator')->warning('A request error happened, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    catch (AiAutomatorResponseErrorException $e) {
      $this->loggerFactory->get('ai_automator')->warning('A response was not correct, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('ai_automator')->warning('A general error happened why trying to interpolate, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    $this->messenger->addWarning($e->getMessage());
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function preProcessing(EntityInterface $entity) {
    $entity->ai_automator_status = AiAutomatorStatusField::STATUS_PROCESSING;
  }

  /**
   * {@inheritDoc}
   */
  public function postProcessing(EntityInterface $entity) {
    $entity->ai_automator_status = AiAutomatorStatusField::STATUS_FINISHED;
  }

  /**
   * {@inheritDoc}
   */
  public function processorIsAllowed(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return TRUE;
  }

}
