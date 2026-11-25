<?php

declare(strict_types=1);

namespace Drupal\ctools_views_test_views\Form;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Embeds a block plugin's configuration form under a 'settings' element.
 *
 * Core's BlockForm passes the complete form to submitConfigurationForm(),
 * while other integrations (e.g. Block field) pass the 'settings' subform,
 * matching build and validate. The 'pass_subform' build argument selects the
 * convention so both can be tested.
 */
final class BlockPluginSubmitForm extends FormBase {

  public function __construct(
    protected BlockManagerInterface $blockManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('plugin.manager.block'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ctools_views_test_block_plugin_submit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $plugin_id = '', bool $pass_subform = FALSE): array {
    $block = $form_state->get('block') ?? $this->blockManager->createInstance($plugin_id);
    $form_state->set('block', $block);
    $form_state->set('pass_subform', $pass_subform);

    $form['#tree'] = TRUE;
    $form['settings'] = [];
    $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $form['settings'] = $block->buildConfigurationForm($form['settings'], $subform_state);
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Save',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $form_state->get('block')->validateConfigurationForm($form['settings'], $subform_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $submitted_form = $form_state->get('pass_subform') ? $form['settings'] : $form;
    $form_state->get('block')->submitConfigurationForm($submitted_form, $subform_state);
  }

}
