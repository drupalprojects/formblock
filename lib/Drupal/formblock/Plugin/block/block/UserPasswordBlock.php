<?php

namespace Drupal\formblock\Plugin\block\block;

use Drupal\block\BlockBase;
use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Provides a block for the password reset form.
 *
 * @Plugin(
  *   id = "formblock_user_password",
  *   admin_label = @Translation("Request new password form"),
  *   module = "formblock"
  * )
 */
class UserPasswordBlock extends BlockBase {
  /**
   * Implements \Drupal\block\BlockBase::build().
   */
  public function build() {
    $build = array();

    module_load_include('inc', 'user', 'user.pages');
    $build['form'] = drupal_get_form('user_pass');

    return $build;
  }
}
