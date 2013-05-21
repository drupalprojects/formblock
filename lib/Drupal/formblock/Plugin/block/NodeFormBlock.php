<?php

namespace Drupal\formblock\Plugin\block;

use Drupal\block\BlockBase;
use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Provides a block for node forms.
 *
 * @Plugin(
 *   id = "formblock_node",
 *   admin_label = @Translation("Node form"),
 *   module = "node"
 * )
 */
class NodeFormBlock extends BlockBase {
  /**
   * Overrides \Drupal\block\BlockBase::settings().
   */
  public function settings() {
    return array(
      'type' => NULL,
      'show_help' => FALSE,
    );
  }

  /**
   * Overrides \Drupal\block\BlockBase::blockForm().
   */
  public function blockForm($form, &$form_state) {
    $form['formblock_node_type'] = array(
      '#title' => t('Node type'),
      '#description' => t('Select the node type whose form will be shown in the block.'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => node_type_get_names(),
      '#default_value' => $this->configuration['type'],
    );
    $form['formblock_show_help'] = array(
      '#type' => 'checkbox',
      '#title' => t('Show submission guidelines'),
      '#default_value' => $this->configuration['show_help'],
      '#description' => t('Enable this option to show the submission guidelines in the block above the form.'),
    );

    return $form;
  }

  /**
   * Overrides \Drupal\block\BlockBase::blockSubmit().
   */
  public function blockSubmit($form, &$form_state) {
    $this->configuration['type'] = $form_state['values']['formblock_node_type'];
    $this->configuration['show_help'] = $form_state['values']['formblock_show_help'];
  }

  /**
   * Implements \Drupal\block\BlockBase::build().
   */
  public function blockBuild() {
    global $user;
    $build = array();

    $node_type = node_type_load($this->configuration['type']);

    if ($this->configuration['show_help']) {
      $build['help'] = array('#markup' => !empty($node_type->help) ? '<p>' . filter_xss_admin($node_type->help) . '</p>' : '');
    }

    $langcode = module_invoke('language', 'get_default_langcode', 'node', $this->configuration['type']);
    $node = entity_create('node', array(
      'uid' => $user->uid,
      'name' => (isset($user->name) ? $user->name : ''),
      'type' => $this->configuration['type'],
      'langcode' => $langcode ? $langcode : language_default()->langcode,
    ));

    $build['form'] = entity_get_form($node);

    return $build;
  }

  /**
   * Impelements \Drupal\block\BLockBase::blockAccess().
   */
  public function access() {
    return node_access('create', $this->configuration['type']);
  }
}
