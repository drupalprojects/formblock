<?php

namespace Drupal\formblock\Plugin\Block;

use Drupal\block\BlockBase;
use Drupal\Component\Annotation\Block;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\Date;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
class ContactFormBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface.
   */
  protected $entityManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface.
   */
  protected $currentUser;

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface.
   */
  protected $entityFormBuilder;

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface.
   */
  protected $configFactory;

  /**
   * The flood service.
   *
   * @var \Drupal\Core\Flood\FloodInterface.
   */
  protected $flood;

  /**
   * The contact category that corresponds to this block.
   *
   * @var \Drupal\contact\Entity\Category.
   */
  protected $contactCategory;

  /**
   * The date service.
   *
   * @var \Drupal\Core\Datetime\Date.
   */
  protected $dateService;

  /**
   * Constructs a new ContactFormBlock plugin
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entityManager
   *   The entity manager.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entityFormBuilder
   *   The entity form builder interface.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood service.
   * @param \Drupal\Core\DateTime\Date $dateService
   *   The date service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManagerInterface $entityManager, AccountInterface $currentUser, EntityFormBuilderInterface $entityFormBuilder, ConfigFactoryInterface $configFactory, FloodInterface $flood, Date $dateService) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityManager = $entityManager;
    $this->currentUser = $currentUser;
    $this->entityFormBuilder = $entityFormBuilder;
    $this->configFactory = $configFactory;
    $this->flood = $flood;
    $this->dateService = $dateService;
    $this->contactCategory = $this->entityManager->getStorage('contact_category')->load($this->configuration['category']);
  }


  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager'),
      $container->get('current_user'),
      $container->get('entity.form_builder'),
      $container->get('config.factory'),
      $container->get('flood'),
      $container->get('date')
    );
  }


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
    $categories = $this->entityManager->getStorage('contact_category')->loadMultiple();

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

    // Check if flood control has been activated for sending emails.
    if (!$this->currentUser->hasPermission('administer contact forms') && $message = $this->floodControl()) {
      $build['message'] = array(
        '#markup' => $message,
      );
      return $build;
    }

    $message = $this->entityManager
      ->getStorage('contact_message')
      ->create(array(
        'category' => $this->contactCategory->id(),
      ));

    $build['form'] = $this->entityFormBuilder->getForm($message);

    return $build;
  }

  /**
   * Implements \Drupal\block\BLockBase::blockAccess().
   */
  public function access(AccountInterface $account) {
    return ($this->contactCategory->access('view', $account) && $account->hasPermission('access site-wide contact form'));
  }

  /**
   * Returns the current status of flood control.
   *
   * @return
   */
  protected function floodControl() {
    $limit = $this->configFactory->get('contact.settings')->get('flood.limit');
    $interval = $this->configFactory->get('contact.settings')->get('flood.interval');
    if (!$this->flood->isAllowed('contact', $limit, $interval)) {
      return $this->t('You cannot send more than %limit messages in @interval. Try again later.', array(
        '%limit' => $limit,
        '@interval' => $this->dateService->formatInterval($interval),
      ));
    }
    return FALSE;
  }

}
