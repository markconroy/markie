<?php

declare(strict_types=1);

namespace Drupal\Tests\field_widget_actions\Kernel;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\field_widget_actions\Form\FieldWidgetActionFormWrapper;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Route;

/**
 * Tests the access control guarding the modal form submission route.
 *
 * The form wrapper bails when, on the modal submission route, the user does not
 * have update access to the entity the action is attached to — so a field
 * widget action can never reveal sensitive entity data to an unprivileged user.
 *
 * @group field_widget_actions
 */
#[RunTestsInSeparateProcesses]
#[Group('field_widget_actions')]
class FieldWidgetActionAccessTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'field_widget_actions',
    'field_widget_actions_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'node', 'filter', 'user']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
  }

  /**
   * Puts the current request on the modal form submission route.
   */
  protected function enterModalSubmitRoute(): void {
    $route = new Route('/admin/field-widget-action/modal/{plugin_id}/{tempstore_id}');
    $request = Request::create('/admin/field-widget-action/modal/refinable_texts_for_textfield/tid');
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, 'field_widget_actions.modal_form_action');
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    // The form builder needs a session on the request to build form tokens.
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);
  }

  /**
   * Builds the wrapper form and returns its rendered markup, if any.
   *
   * @param array $context_data
   *   The context data passed to the wrapper.
   *
   * @return string
   *   The '#markup' of the built form (empty string if none).
   */
  protected function buildWrapperMarkup(array $context_data): string {
    $form = $this->container->get('form_builder')->getForm(
      FieldWidgetActionFormWrapper::class,
      'refinable_texts_for_textfield',
      $context_data,
    );
    return isset($form['#markup']) ? (string) $form['#markup'] : '';
  }

  /**
   * A user without update access is denied on the modal submission route.
   */
  public function testModalRouteDeniesWithoutUpdateAccess(): void {
    // A user that can view but not edit content.
    $this->setUpCurrentUser([], ['access content']);

    $node = Node::create(['type' => 'article', 'title' => 'Secret']);
    $node->save();
    $this->assertFalse($node->access('update'));

    $this->enterModalSubmitRoute();
    $this->assertSame('Access denied.', $this->buildWrapperMarkup(['current_entity' => $node]));
  }

  /**
   * The route bails clearly when no entity is supplied in the context.
   */
  public function testModalRouteRequiresAnEntity(): void {
    $this->setUpCurrentUser([], ['access content']);
    $this->enterModalSubmitRoute();
    $this->assertSame('No entity specified.', $this->buildWrapperMarkup([]));
  }

}
