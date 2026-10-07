<?php

namespace Drupal\ai_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Renders a single built UI widget in isolation for visual regression tests.
 *
 * This form has no access check (see ai_test.routing.yml) so the Playwright
 * visual regression tests (tests/visual-regression) can render the built
 * front-end bundles anonymously and screenshot them. It renders exactly one
 * widget, selected by the ?vr_widget= query parameter, in either an empty or a
 * populated state (?vr_state=empty|default), wrapped in a fixed-width element
 * carrying a data-vr-target attribute used as the screenshot selector.
 */
class VisualRegressionForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_test_visual_regression_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $request = $this->getRequest();
    $widget = (string) $request->query->get('vr_widget', '');
    $state = (string) $request->query->get('vr_state', 'empty');
    $default = ($state === 'default');

    // A fixed-width, white wrapper keeps the screenshot crop stable regardless
    // of the surrounding theme. The data-vr-target attribute is the selector
    // the Playwright tests use to screenshot just this widget.
    $form['vr'] = [
      '#type' => 'container',
      '#attributes' => [
        'data-vr-target' => $widget . '-' . $state,
        'style' => 'box-sizing: border-box; width: 760px; padding: 16px; background: #ffffff;',
      ],
    ];

    switch ($widget) {
      case 'mdxeditor':
        // The MDXEditor bundle (ai/mdx_editor) is attached by an
        // element_info_alter process to any textarea carrying a data-mdxeditor
        // attribute (see \Drupal\ai\Hook\FormElement). Its JS then replaces the
        // textarea with the React editor.
        $form['vr']['widget'] = [
          '#type' => 'textarea',
          '#title' => $this->t('MDX editor'),
          '#default_value' => $default ? "# Hello world\n\nSome **bold** text, _italics_ and a list:\n\n- First item\n- Second item\n" : '',
          '#attributes' => [
            'data-mdxeditor' => 'vr-mdxeditor',
          ],
        ];
        break;

      case 'json_schema':
        $form['vr']['widget'] = [
          '#type' => 'ai_json_schema',
          '#title' => $this->t('JSON schema'),
          '#default_value' => $default ? '{"type":"object","properties":{"name":{"type":"string"},"age":{"type":"integer"}},"required":["name"]}' : '',
        ];
        break;

      case 'default_tools':
        // Nothing attaches ai/default_tools_editor automatically, so attach it
        // here. The bundle mounts on the first [data-default-tools-editor]
        // element and reads its value as a YAML map of tools.
        $form['vr']['widget'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Default tools editor'),
          '#default_value' => $default ? "get_weather:\n  label: 'Get weather'\n  description: 'Returns the weather for a location.'\n  tool: 'weather'\n  parameters:\n    location: 'string'\n" : '',
          '#attributes' => [
            'data-default-tools-editor' => 'vr-default-tools',
          ],
          '#attached' => [
            'library' => ['ai/default_tools_editor'],
          ],
        ];
        break;

      default:
        $form['vr']['widget'] = [
          '#type' => 'markup',
          '#markup' => '<p>Unknown visual regression widget requested.</p>',
        ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // No submission handling: this form only renders widgets for screenshots.
  }

}
