<?php

namespace Drupal\formblock\Plugin\block\block;

use Drupal\block\BlockBase;
use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Provides a block for contact form.
 *
 * @Plugin(
  *   id = "formblock_contact",
  *   admin_label = @Translation("Site-wide contact form"),
  *   module = "formblock"
  * )
 */
class ContactFormBlock extends BlockBase {
  /**
   * Overrides \Drupal\block\BlockBase::settings().
   */
  public function settings() {
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
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function build() {
    $build = array();

    if (!user_access('administer contact forms')) {
      module_load_include('inc', 'contact', 'contact.pages');
      contact_flood_control();
    }


    if (!isset($this->configuration['category'])) {
      $categories = entity_load_multiple('contact_category');
      $default_category = config('contact.settings')->get('default_category');
      if (isset($categories[$default_category])) {
        $category = $categories[$default_category];
      }
      // If there are no categories, do not display the form.
      else {
        if (user_access('administer contact forms')) {
          drupal_set_message(t('The contact form has not been configured. <a href="@add">Add one or more categories</a> to the form.', array('@add' => url('admin/structure/contact/add'))), 'error');
          return array();
        }
        else {
          throw new NotFoundHttpException();
        }
      }
    }
    else {
      $category = entity_load('contact_category', $this->configuration['category']);
    }
    $message = entity_create('contact_message', array(
      'category' => $category->id(),
    ));

    $build['form'] = entity_get_form($message);

    return $build;
  }

  /**
   * Impelements \Drupal\block\BLockBase::blockAccess().
   */
  public function blockAccess() {
    return user_access('access site-wide contact form');
  }
}
