<?php

namespace Drupal\rules\Plugin\RulesExpression;

use Drupal\rules\Engine\ActionExpressionContainer;
use Drupal\rules\Engine\ExecutionStateInterface;

/**
 * Holds a set of actions and executes all of them.
 *
 * @RulesExpression(
 *   id = "rules_action_set",
 *   label = @Translation("Action set"),
 *   form_class = "\Drupal\rules\Form\Expression\ActionContainerForm"
 * )
 */
class ActionSet extends ActionExpressionContainer {

  /**
   * {@inheritdoc}
   */
  protected function allowsMetadataAssertions() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function executeWithState(ExecutionStateInterface $state) {
    // Get hold of actions.
    // @todo See if we can add getExpressions method of ExpressionContainerBase.
    $actions = [];
    foreach ($this->actions as $action) {
      $actions[] = $action;
    }

    // Sort actions by weight.
    @uasort($actions, [$this->actions, 'expressionSortHelper']);
    foreach ($actions as $action) {
      /* @var $action \Drupal\rules\Engine\ExpressionInterface */
      $action->executeWithState($state);
    }
  }

}
