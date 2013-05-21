<?php

namespace Drupal\formblock\Plugin\block;

use Drupal\block\BlockBase;
use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Provides a block for the user registration form.
 *
 * @Plugin(
 *   id = "formblock_user_register",
 *   admin_label = @Translation("User registration form"),
 *   module = "user"
 * )
 */
class UserRegisterBlock extends BlockBase {
  /**
   * Implements \Drupal\block\BlockBase::build().
   */
  public function blockBuild() {
    $build = array();

    $account = entity_create('user', array());
    $build['form'] = entity_get_form($account, 'register');

    return $build;
  }

  /**
   *Implements \Drupal\block\BlockBase::access().
   */
  public function access() {
    return user_is_anonymous() && (config('user.settings')->get('register') != USER_REGISTER_ADMINISTRATORS_ONLY);
  }
}
