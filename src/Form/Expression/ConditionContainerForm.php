<?php

namespace Drupal\rules\Form\Expression;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\rules\Ui\RulesUiHandlerTrait;
use Drupal\rules\Engine\ConditionExpressionContainerInterface;

/**
 * Form view structure for Rules condition containers.
 */
class ConditionContainerForm implements ExpressionFormInterface {

  use ExpressionFormTrait;
  use RulesUiHandlerTrait;
  use StringTranslationTrait;

  /**
   * The rule expression object this form is for.
   *
   * @var \Drupal\rules\Engine\ConditionExpressionContainerInterface
   */
  protected $conditionContainer;

  /**
   * Creates a new object of this class.
   */
  public function __construct(ConditionExpressionContainerInterface $condition_container) {
    $this->conditionContainer = $condition_container;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form['conditions'] = [
      '#type' => 'container',
    ];

    $form['conditions']['table'] = [
      '#type' => 'table',
      '#caption' => $this->t('Conditions'),
      '#header' => [
        $this->t('Elements'),
        $this->t('Weight'),
        [
          'data' => $this->t('Operations'),
          'colspan' => 3,
        ],
      ],
      '#attributes' => [
        'id' => 'rules_conditions_table',
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'condition-weight',
        ],
      ],
    ];

    $form['conditions']['table']['#empty'] = $this->t('None');

    // Get hold of conditions.
    // @todo See if we can add getExpressions method of ExpressionContainerBase.
    $conditions = [];
    foreach ($this->conditionContainer as $condition) {
      $conditions[] = $condition;
    }

    // Sort conditions by weight.
    @uasort($conditions, [$this->conditionContainer, 'expressionSortHelper']);

    foreach ($conditions as $condition) {
      /* @var $condition \Drupal\rules\Engine\ExpressionInterface */
      $uuid = $condition->getUuid();
      $row = &$form['conditions']['table'][$uuid];

      // TableDrag: Mark the table row as draggable.
      $row['#attributes']['class'][] = 'draggable';

      // TableDrag: Sort the table row according to its weight.
      $row['#weight'] = $condition->getWeight();
      $row['title'] = ['#markup' => $condition->getLabel()];

      $row['weight'] = [
        '#type' => 'weight',
        '#delta' => 50,
        '#default_value' => $condition->getWeight(),
        '#attributes' => ['class' => ['condition-weight']],
      ];

      // Operations (dropbutton) column.
      $rules_ui_handler = $this->getRulesUiHandler();
      $row['operations'] = [
        'data' => [
          '#type' => 'dropbutton',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => $rules_ui_handler->getUrlFromRoute('expression.edit', [
                'uuid' => $condition->getUuid(),
              ]),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => $rules_ui_handler->getUrlFromRoute('expression.delete', [
                'uuid' => $condition->getUuid(),
              ]),
            ],
          ],
        ],
      ];
    }

    // @todo Put this into the table as last row and style it like it was in
    // Drupal 7 Rules.
    $form['add_condition'] = [
      '#theme' => 'menu_local_action',
      '#link' => [
        'title' => $this->t('Add condition'),
        'url' => $this->getRulesUiHandler()->getUrlFromRoute('expression.add', [
          'expression_id' => 'rules_condition',
        ]),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue('table');
    $component = $this->getRulesUiHandler()->getComponent();
    /* @var $rule_expression \Drupal\rules\Plugin\RulesExpression\Rule */
    $rule_expression = $component->getExpression();

    if ($values) {
      foreach ($values as $uuid => $expression) {
        $action = $rule_expression->getExpression($uuid);
        $action->setWeight($expression['weight']);
        $action->setConfiguration($action->getConfiguration());
      }
    }

    $this->getRulesUiHandler()->updateComponent($component);
  }

}
