<?php

namespace Drupal\rules\Plugin\RulesExpression;

use Drupal\rules\Engine\ConditionExpressionContainer;
use Drupal\rules\Engine\ExecutionStateInterface;

/**
 * Evaluates a group of conditions with a logical OR.
 *
 * @RulesExpression(
 *   id = "rules_or",
 *   label = @Translation("Condition set (OR)"),
 *   form_class = "\Drupal\rules\Form\Expression\ConditionContainerForm"
 * )
 */
class RulesOr extends ConditionExpressionContainer {

  /**
   * {@inheritdoc}
   */
  public function evaluate(ExecutionStateInterface $state) {
    // Get hold of conditions.
    // @todo See if we can add getExpressions method of ExpressionContainerBase.
    $conditions = [];
    foreach ($this->conditions as $condition) {
      $conditions[] = $condition;
    }

    // Sort conditions by weight.
    @uasort($conditions, [$this->conditions, 'expressionSortHelper']);
    foreach ($conditions as $condition) {
      /* @var $condition \Drupal\rules\Engine\ExpressionInterface */
      if ($condition->executeWithState($state)) {
        return TRUE;
      }
    }
    // An empty OR should return TRUE, otherwise all conditions evaluated to
    // FALSE and we return FALSE.
    return empty($this->conditions);
  }

  /**
   * {@inheritdoc}
   */
  protected function allowsMetadataAssertions() {
    // We cannot garantuee child expressions are executed, thus we cannot allow
    // metadata assertions.
    return FALSE;
  }

}
