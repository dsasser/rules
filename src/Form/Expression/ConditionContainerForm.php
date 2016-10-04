<?php

namespace Drupal\rules\Form\Expression;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\rules\Ui\RulesUiHandlerTrait;
use Drupal\rules\Engine\ConditionExpressionContainerInterface;
use Drupal\rules\Engine\RulesComponent;

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
   * The rules ui handler.
   *
   * @var \Drupal\rules\Ui\RulesUiHandlerInterface|null
   */
  protected $rulesUiHandler;

  /**
   * The rules component.
   *
   * @var \Drupal\rules\Engine\RulesComponent
   */
  protected $component;

  /**
   * The rule as an expression.
   *
   * @var \Drupal\rules\Engine\ExpressionInterface
   */
  protected $ruleExpression;

  /**
   * Creates a new object of this class.
   */
  public function __construct(ConditionExpressionContainerInterface $condition_container) {
    $this->conditionContainer = $condition_container;
    $this->rulesUiHandler = $this->getRulesUiHandler();
    $this->component = $this->rulesUiHandler->getComponent();
    $this->ruleExpression = $this->component->getExpression();
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state, $options = []) {
    // By default skip.
    if (!empty($options['init'])) {
      /* @var $config \Drupal\rules\Entity\ReactionRuleConfig */
      $config = $this->rulesUiHandler->getConfig();
      $form['init_help'] = array(
        '#type' => 'container',
        '#id' => 'rules-plugin-add-help',
        'content' => array(
          '#markup' => t('You are about to add a new @plugin to the @config-plugin %label. Use indentation to make conditions a part of this logic group. See <a href="@url">the online documentation</a> for more information on condition sets.',
            array(
              '@plugin' => $this->conditionContainer->getLabel(),
              '@config-plugin' => $config->bundle(),
              '%label' => $config->label(),
              '@url' => 'http://drupal.org/node/1300034',
            )
          ),
        ),
        'submit' => [
          '#type' => 'submit',
          '#value' => 'Continue',
        ],
      );
    }
    else {
      $form['conditions'] = [
        '#type' => 'container',
      ];

      $form['conditions']['table'] = [
        '#type' => 'table',
        '#caption' => $this->t('Conditions'),
        '#header' => [
          $this->t('Elements'),
          [
            'data' => $this->t('Elements'),
          ],
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
            'action' => 'match',
            'relationship' => 'parent',
            'group' => 'condition-parent',
            'subgroup' => 'condition-parent',
            'source' => 'condition-id',
          ],
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
      $depth = 0;
      // Sort conditions by weight.
      @uasort($conditions, [$this->conditionContainer, 'expressionSortHelper']);
      foreach ($conditions as $condition) {
        $this->buildRow($form, $form_state, $condition->getUuid(), $depth);
      }

      $this->buildFooter($form, $form_state);
    }
    return $form;
  }

  /**
   * Build condition container table rows.
   *
   * @param array $form
   *   The form array.
   * @param FormStateInterface $form_state
   *   The current form state.
   * @param string $condition
   *   A condition expression uuid to add to the form table.
   * @param int $depth
   *   The depth of the condition within the condition tree.
   * @param mixed $parent
   *   The parent expression, if it is set.
   */
  private function buildRow(&$form, FormStateInterface &$form_state, $condition, $depth, $parent = NULL) {
    $uuid = $condition;
    /** @var \Drupal\rules\Plugin\RulesExpression\RulesCondition $condition */
    $condition = $this->ruleExpression->getExpression($condition);
    $row = &$form['conditions']['table'][$uuid];

    // TableDrag: Mark the table row as draggable.
    $row['#attributes']['class'][] = 'draggable';
    $row['title'] = [
      [
        '#theme' => 'indentation',
        '#size' => $depth,
      ],
      [
        '#markup' => $condition->getLabel(),
      ],
    ];
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
    $row['parent'] = [
      '#type' => 'hidden',
      '#default_value' => $parent ? $parent->getUuid() : 0,
      '#attributes' => ['class' => ['condition-parent']],
    ];
    if ($condition->getPluginId() === 'rules_condition') {
      $row['#attributes']['class'][] = 'tabledrag-leaf';
    }
    // TableDrag: Sort the table row according to its weight.
    $row['#weight'] = $condition->getWeight();

    $row['id'] = [
      '#type' => 'hidden',
      '#value' => $uuid,
      '#attributes' => ['class' => ['condition-id']],
    ];
    $row['plugin_id'] = [
      '#type' => 'hidden',
      '#default_value' => $condition->getPluginId(),
    ];
    $configuration = $condition->getConfiguration();
    if (!empty($configuration['conditions'])) {
      $depth++;
      foreach ($configuration['conditions'] as $child_condition) {
        $this->buildRow($form, $form_state, $child_condition['uuid'], $depth, $condition);
      }
    }
  }

  /**
   * Build condition container table footer.
   *
   * @param array $form
   *   The form array.
   * @param FormStateInterface $form_state
   *   The current form state.
   */
  private function buildFooter(&$form, FormStateInterface &$form_state) {
    $footer = array(
      '#prefix' => '<ul class="action-links">',
      '#suffix' => '</ul>',
      'links' => array(
        array(
          '#theme' => 'menu_local_action',
          '#attribues' => ['classes' => ['action-links']],
          '#link' => [
            'title' => $this->t('Add condition'),
            'url' => $this->getRulesUiHandler()->getUrlFromRoute('expression.add', [
              'expression_id' => 'rules_condition',
            ]),
          ],
        ),
        array(
          '#theme' => 'menu_local_action',
          '#link' => [
            'title' => $this->t('Add AND'),
            'url' => $this->getRulesUiHandler()->getUrlFromRoute('expression.add', [
              'expression_id' => 'rules_and',
            ]),
          ],
        ),
        array(
          '#theme' => 'menu_local_action',
          '#link' => [
            'title' => $this->t('Add OR'),
            'url' => $this->getRulesUiHandler()->getUrlFromRoute('expression.add', [
              'expression_id' => 'rules_or',
            ]),
          ],
        ),
      ),
    );

    // Table footer.
    $form['conditions']['table']['#footer'] = [
      array(
        'class' => array('footer-class'),
        'data' => array(
          array(
            'data' => $footer,
            'colspan' => 2,
          ),
        ),
      ),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue('table');
    if ($values) {
      $elements = $this->mirrorElements($values);
      $tree = $this->buildElementTree($values, $elements, 'parent', 'id');
      $this->saveElementTree($tree, $elements);
      // Remove the original expressions since we've cloned them to a new tree.
      foreach ($values as $uuid => $old_expression) {
        $this->ruleExpression->deleteExpression($uuid);
      }
      $this->getRulesUiHandler()->updateComponent($this->component);
    }
  }

  /**
   * Build a tree of values from a flat array.
   *
   * @param array $values
   *   An associative array.
   * @param string $pidKey
   *   The parent id key in the values.
   * @param string $idKey
   *   The id key in the values.
   *
   * @return mixed
   *   A heirarchical tree of values.
   */
  private function buildElementTree($values, &$elements, $pidKey, $idKey = NULL) {
    $grouped = array();
    foreach ($values as $sub) {
      $grouped[$sub[$pidKey]][] = $sub;
    }

    $fnBuilder = function ($siblings) use (&$fnBuilder, &$elements, $grouped, $idKey, $elements) {
      foreach ($siblings as $k => $sibling) {
        $id = $sibling[$idKey];
        if (isset($grouped[$id])) {
          $sibling['conditions'] = $fnBuilder($grouped[$id]);
          foreach ($sibling['conditions'] as $condition) {
            // Tell the child who the parent is by setting its root.
            $child = $elements[$condition[$idKey]];
            // Add the child to the parent object.
            $elements[$id]->addExpressionObject($child);
          }
        }
        $siblings[$k] = $sibling;
      }
      return $siblings;
    };
    $tree = $fnBuilder($grouped[0]);
    return $tree;
  }

  /**
   * Creates "clones" of submitted elements for use in a new condition tree.
   *
   * @param array $values
   *   The array of form values.
   *
   * @return array|mixed
   *   The derived condition expression elements.
   */
  private function mirrorElements($values) {
    $elements = NULL;
    $expressionManager = \Drupal::service('plugin.manager.rules_expression');
    foreach ($values as $uuid => $element) {
      $condition = $this->ruleExpression->getExpression($element['id']);
      $configuration = $condition->getConfiguration();
      unset($configuration['uuid']);
      $configuration['weight'] = $element['weight'];
      $elements[$uuid] = $expressionManager->createInstance($condition->getPluginId(), $configuration);
    }

    return $elements;
  }

  /**
   * Process and save expression condition tree.
   *
   * @param array $tree
   *   An associative array of expression elements.
   */
  private function saveElementTree($tree, &$elements) {
    foreach ($tree as $branch) {
      $this->ruleExpression->addExpressionObject($elements[$branch['id']]);
    }
    $this->rulesUiHandler->updateComponent($this->component);
  }

}
