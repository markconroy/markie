<?php

namespace Drupal\token\Hook;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;

class TokenRequirementsHooks {

  use StringTranslationTrait;

  public function __construct(
    protected Token $token,
    protected RendererInterface $renderer,
  ) {

  }

  /**
   * Implements hook_runtime_requirements()
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements() {
    $requirements = [];
    // Check for various token definition problems.
    $token_problems = $this->getTokenProblems();
    // Format and display each token problem.
    foreach ($token_problems as $problem_key => $problem) {
      if (!empty($problem['problems'])) {
        $problems = array_unique($problem['problems']);

        $build = [
          '#theme' => 'item_list',
          '#items' => $problems,
        ];

        $requirements['token-' . $problem_key] = [
          'title' => $problem['label'],
          'value' => $this->renderer->renderInIsolation($build),
          'severity' => $problem['severity'],
        ];
      }
    }

    return $requirements;
  }


  /**
   * Get token problems.
   */
  protected function getTokenProblems(): array {
    // @todo Improve the duplicate checking to report which modules are the offenders.
    //$token_info = [];
    //foreach (module_implements('token_info') as $module) {
    //  $module_token_info = module_invoke($module, 'token_info');
    //  if (in_array($module, _token_core_supported_modules())) {
    //    $module .= '/token';
    //  }
    //  if (isset($module_token_info['types'])) {
    //    if (is_array($module_token_info['types'])) {
    //      foreach (array_keys($module_token_info['types']) as $type) {
    //        if (is_array($module_token_info['types'][$type])) {
    //          $module_token_info['types'][$type] += ['module' => $module];
    //        }
    //      }
    //    }
    //  }
    //  if (isset($module_token_info['tokens'])) {
    //    if (is_array($module_token_info['tokens'])) {
    //
    //    }
    //  }
    //  if (is_array($module_token_info)) {
    //    $token_info = array_merge_recursive($token_info, $module_token_info);
    //  }
    //}

    $token_info = $this->token->getInfo();
    $token_problems = [
      'not-array' => [
        'label' => $this->t('Tokens or token types not defined as arrays'),
        'severity' => class_exists(RequirementSeverity::class) ? RequirementSeverity::Error : REQUIREMENT_ERROR,
      ],
      'missing-info' => [
        'label' => $this->t('Tokens or token types missing name property'),
        'severity' => class_exists(RequirementSeverity::class) ? RequirementSeverity::Warning : REQUIREMENT_WARNING,
      ],
      'type-no-tokens' => [
        'label' => $this->t('Token types do not have any tokens defined'),
        'severity' => class_exists(RequirementSeverity::class) ? RequirementSeverity::Info : REQUIREMENT_INFO,
      ],
      'tokens-no-type' => [
        'label' => $this->t('Token types are not defined but have tokens'),
        'severity' => class_exists(RequirementSeverity::class) ? RequirementSeverity::Info : REQUIREMENT_INFO,
      ],
      'duplicate' => [
        'label' => $this->t('Token or token types are defined by multiple modules'),
        'severity' => class_exists(RequirementSeverity::class) ? RequirementSeverity::Error : REQUIREMENT_ERROR,
      ],
    ];

    // Check token types for problems.
    foreach ($token_info['types'] as $type => $type_info) {
      $real_type = !empty($type_info['type']) ? $type_info['type'] : $type;
      if (!is_array($type_info)) {
        $token_problems['not-array']['problems'][] = "\$info['types']['$type']";
      }
      elseif (!isset($type_info['name'])) {
        $token_problems['missing-info']['problems'][] = "\$info['types']['$type']";
      }
      elseif (is_array($type_info['name'])) {
        $token_problems['duplicate']['problems'][] = "\$info['types']['$type']";
      }
      elseif (empty($token_info['tokens'][$real_type])) {
        $token_problems['type-no-tokens']['problems'][] = "\$info['types']['$real_type']";
      }
    }

    // Check tokens for problems.
    foreach ($token_info['tokens'] as $type => $tokens) {
      if (!is_array($tokens)) {
        $token_problems['not-array']['problems'][] = "\$info['tokens']['$type']";
        continue;
      }
      else {
        foreach (array_keys($tokens) as $token) {
          if (!is_array($tokens[$token])) {
            $token_problems['not-array']['problems'][] = "\$info['tokens']['$type']['$token']";
          }
          elseif (!isset($tokens[$token]['name'])) {
            $token_problems['missing-info']['problems'][] = "\$info['tokens']['$type']['$token']";
          }
          elseif (is_array($tokens[$token]['name'])) {
            $token_problems['duplicate']['problems'][] = "\$info['tokens']['$type']['$token']";
          }
        }
      }
      if (!isset($token_info['types'][$type])) {
        $token_problems['tokens-no-type']['problems'][] = "\$info['types']['$type']";
      }
    }

    return $token_problems;
  }

}
