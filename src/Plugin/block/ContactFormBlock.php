<?php

namespace Drupal\formblock\Plugin\Block;

use Drupal\block\BlockBase;
use Drupal\Component\Annotation\Block;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a block for contact form.
 *
 * @Block(
 *   id = "formblock_contact",
 *   admin_label = @Translation("Site-wide contact form"),
 *   provider = "contact"
 * )
 *
 * Note that we set module to contact so that blocks will be disabled correctly
 * when the module is disabled.
 */
class ContactFormBlock extends BlockBase {
  /**
   * Overrides \Drupal\block\BlockBase::settings().
   */
  public function defaultConfiguration() {
    return array(
      'category' => NULL,
    );
  }

  /**
   * Overrides \Drupal\block\BlockBase::blockForm().
   */
  public function blockForm($form, &$form_state) {
    $categories = entity_load_multiple('contact_category');

    $options = array();
    foreach ($categories as $category) {
      $options[$category->id] = $category->label;
    }
    $form['formblock_category'] = array(
      '#type' => 'select',
      '#title' => t('Category'),
      '#default_value' => $this->configuration['category'],
      '#description' => t('Select the category to show.'),
      '#options' => $options,
      '#required' => TRUE,
    );

    return $form;
  }

  /**
   * Overrides \Drupal\block\BlockBase::blockSubmit().
   */
  public function blockSubmit($form, &$form_state) {
    $this->configuration['category'] = $form_state['values']['formblock_category'];
  }

  /**
   * Implements \Drupal\block\BlockBase::build().
   */
  public function build() {
    $build = array();

    // Check if flood control has been activated for sending e-mails.
    // @TODO Change permission check to match contact controller
    if (!Drupal::currentUser()->hasPermission('administer contact forms')) {
      module_load_include('inc', 'contact', 'contact.pages');
      contact_flood_control();
    }

    if (!isset($this->configuration['category'])) {
      $categories = entity_load_multiple('contact_category');
      $default_category = \Drupal::config('contact.settings')->get('default_category');
      if (isset($categories[$default_category])) {
        $category = $categories[$default_category];
      }
      // If there are no categories, do not display the form.
      else {
        return $build;
      }
    }
    else {
      $category = entity_load('contact_category', $this->configuration['category']);
    }
    $message = entity_create('contact_message', array(
      'category' => $category->id(),
    ));
    $build['form'] = \Drupal::entityManager()->getForm($message);

    return $build;
  }

  /**
   * Impelements \Drupal\block\BLockBase::blockAccess().
   */
  public function access(AccountInterface $account) {
    return $account->hasPermission('access site-wide contact form');
  }
}
