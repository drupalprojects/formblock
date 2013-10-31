<?php

namespace Drupal\formblock\Plugin\Block;

use Drupal;
use Drupal\block\BlockBase;
use Drupal\Component\Annotation\Block;
use Drupal\Core\Annotation\Translation;

/**
 * Provides a block for the password reset form.
 *
 * @Block(
 *   id = "formblock_user_password",
 *   admin_label = @Translation("Request new password form"),
 *   provider = "user"
 * )
 *
 * Note that we set module to contact so that blocks will be disabled correctly
 * when the module is disabled.
 */
class UserPasswordBlock extends BlockBase {
  /**
   * Implements \Drupal\block\BlockBase::build().
   */
  public function build() {
    $build = array();

    $build['form'] = \Drupal::formBuilder()->getForm('Drupal\user\Form\UserPasswordForm');

    return $build;
  }
}
