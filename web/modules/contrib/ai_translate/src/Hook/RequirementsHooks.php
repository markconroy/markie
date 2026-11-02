<?php

declare(strict_types=1);

namespace Drupal\ai_translate\Hook;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Requirements hook implementations for ai_translate.
 */
class RequirementsHooks {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'ai.provider')]
    protected readonly AiProviderPluginManager $aiProvider,
  ) {}

  /**
   * Implements hook_runtime_requirements().
   *
   * Only invoked on Drupal 11.2 and later. Older versions use the procedural
   * ai_translate_requirements() in ai_translate.install.
   */
  #[Hook('runtime_requirements')]
  public function runtime(): array {
    $requirements = [];
    $defaultProvider = $this->aiProvider->getDefaultProviderForOperationType('translate_text');
    if (empty($defaultProvider)) {
      $requirements['ai_translate_translate'] = [
        'title' => $this->t('AI Default Translate text Provider'),
        'value' => $this->t('Please provide a default provider for the text translation'),
        'description' => $this->t('The AI Translate module needs the default Translate Text provider to be configured in the <a href=":url">AI module settings</a>. Please configure a default Chat AI system.',
          [':url' => Url::fromRoute('ai.settings_form')->toString()]),
        'severity' => RequirementSeverity::Error,
      ];
    }
    if (empty($defaultProvider) && empty($this->aiProvider->getDefaultProviderForOperationType('chat'))) {
      $requirements['ai_translate_chat'] = [
        'title' => $this->t('AI Default Chat Provider'),
        'value' => $this->t('Please provide a default Chat AI provider'),
        'description' => $this->t('The AI Translate module needs a default Chat provider to be setup to be able to proxy translation requests. This can be configured in the <a href=":url">AI module settings</a>.',
          [':url' => Url::fromRoute('ai.settings_form')->toString()]),
        'severity' => RequirementSeverity::Warning,
      ];
    }
    return $requirements;
  }

}
