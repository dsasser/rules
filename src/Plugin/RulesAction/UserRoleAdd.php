<?php

namespace Drupal\rules\Plugin\RulesAction;

use Drupal\rules\Core\RulesActionBase;
use Drupal\rules\Exception\InvalidArgumentException;
use Drupal\user\UserInterface;
use Drupal\user\Entity\Role;

/**
 * Provides a 'Add user role' action.
 *
 * @RulesAction(
 *   id = "rules_user_role_add",
 *   label = @Translation("Add user role"),
 *   category = @Translation("User"),
 *   context = {
 *     "user" = @ContextDefinition("entity:user",
 *       label = @Translation("User")
 *     ),
 *     "roles" = @ContextDefinition("entity:user_role",
 *       label = @Translation("Roles"),
 *       multiple = TRUE
 *     )
 *   }
 * )
 *
 * @todo: Add access callback information from Drupal 7.
 * @todo: Add port for rules_user_roles_options_list.
 */
class UserRoleAdd extends RulesActionBase {

  /**
   * Flag that indicates if the entity should be auto-saved later.
   *
   * @var bool
   */
  protected $saveLater = FALSE;

  /**
   * Assign role to a user.
   *
   * @param \Drupal\user\UserInterface $account
   *   User object.
   * @param \Drupal\user\RoleInterface[] $roles
   *   Array of UserRoles to assign.
   *
   * @throws \Drupal\rules\Exception\InvalidArgumentException
   */
  protected function doExecute(UserInterface $account, array $roles) {
    $drupal_roles = Role::loadMultiple();
    foreach ($roles as $role) {
      // If the role is not a role entity yet, load it if we can.
      if (!$role instanceof Role) {
        // Try to load the role by role id.
        if ($r = Role::load($role)) {
          $role = $r;
        }
        else {
          // Try to load the role by role label.
          foreach ($drupal_roles as $drupal_role) {
            if ($drupal_role->get('label') == $role) {
              $role = $drupal_role;
              break;
            }
          }
        }
      }
      // Skip adding the role to the user if they already have it.
      if (!$account->hasRole($role->id())) {
        // If you try to add anonymous or authenticated role to user, Drupal
        // will throw an \InvalidArgumentException. Anonymous or authenticated
        // role ID must not be assigned manually.
        try {
          $account->addRole($role->id());
        }
        catch (\InvalidArgumentException $e) {
          throw new InvalidArgumentException($e->getMessage());
        }
        // Set flag that indicates if the entity should be auto-saved later.
        $this->saveLater = TRUE;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function autoSaveContext() {
    if ($this->saveLater) {
      return ['user'];
    }
    return [];
  }

}
