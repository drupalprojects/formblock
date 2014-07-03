<?php

namespace Drupal\formblock\Plugin\Block;

use Drupal\block\BlockBase;
use Drupal\Component\Annotation\Block;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a block for the user registration form.
 *
 * @Block(
 *   id = "formblock_user_register",
 *   admin_label = @Translation("User registration form"),
 *   provider = "user"
 * )
 *
 * Note that we set module to contact so that blocks will be disabled correctly
 * when the module is disabled.
 */
class UserRegisterBlock extends BlockBase {
  /**
   * Implements \Drupal\block\BlockBase::build().
   */
  public function build() {
    $build = array();

    $account = \Drupal::entityManager()
        ->getStorageController('user')
        ->create(array());
    $build['form'] = \Drupal::entityManager()->getForm($account, 'register');

    return $build;
  }

  /**
   *Implements \Drupal\block\BlockBase::access().
   */
  public function access(AccountInterface $account) {
    return (user_is_anonymous() && (\Drupal::config('user.settings')->get('register') != USER_REGISTER_ADMINISTRATORS_ONLY));
  }
}
