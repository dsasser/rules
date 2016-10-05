<?php

namespace Drupal\rules\Form\Expression;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\rules\Ui\RulesUiHandlerTrait;
use Drupal\rules\Engine\ActionExpressionContainerInterface;

/**
 * Form handler for action containers.
 */
class ActionContainerForm implements ExpressionFormInterface {

  use StringTranslationTrait;
  use RulesUiHandlerTrait;
  use ExpressionFormTrait;

  /**
   * The rule expression object this form is for.
   *
   * @var \Drupal\rules\Engine\ActionExpressionContainerInterface
   */
  protected $actionSet;

  /**
   * Creates a new object of this class.
   */
  public function __construct(ActionExpressionContainerInterface $action_set) {
    $this->actionSet = $action_set;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form['action_table_container'] = [
      '#type' => 'container',
    ];

    $form['action_table_container']['action_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Elements'),
        $this->t('Weight'),
        [
          'data' => $this->t('Operations'),
          'colspan' => 3,
        ],
      ],
      '#attributes' => [
        'id' => 'rules_actions_table',
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'action-weight',
        ],
      ],
    ];

    $form['action_table']['table']['#empty'] = $this->t('None');

    // Get hold of actions.
    // @todo See if we can add getExpressions method of ExpressionContainerBase.
    $actions = [];
    foreach ($this->actionSet as $action) {
      $actions[] = $action;
    }

    // Sort actions by weight.
    @uasort($actions, [$this->actionSet, 'expressionSortHelper']);

    foreach ($actions as $action) {
      /* @var $action \Drupal\rules\Engine\ExpressionInterface */
      $uuid = $action->getUuid();
      $row = &$form['action_table_container']['action_table'][$uuid];

      // TableDrag: Mark the table row as draggable.
      $row['#attributes']['class'][] = 'draggable';

      // TableDrag: Sort the table row according to its existing weight.
      $row['#weight'] = $action->getWeight();
      $row['title'] = ['#markup' => $action->getLabel()];

      $row['weight'] = [
        '#type' => 'weight',
        '#delta' => 50,
        '#default_value' => $action->getWeight(),
        '#attributes' => ['class' => ['action-weight']],
      ];

      // Operations column.
      $rules_ui_handler = $this->getRulesUiHandler();
      $row['operations'] = [
        'data' => [
          '#type' => 'operations',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => $rules_ui_handler->getUrlFromRoute('expression.edit', [
                'uuid' => $uuid,
              ]),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => $rules_ui_handler->getUrlFromRoute('expression.delete', [
                'uuid' => $uuid,
              ]),
            ],
          ],
        ],
      ];
    }

    // @todo Put this into the table as last row and style it like it was in
    // Drupal 7 Rules.
    $form['add_action'] = [
      '#theme' => 'menu_local_action',
      '#link' => [
        'title' => $this->t('Add action'),
        'url' => $this->getRulesUiHandler()->getUrlFromRoute('expression.add', [
          'expression_id' => 'rules_action',
        ]),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue('action_table');
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
