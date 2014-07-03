<?php

namespace Drupal\formblock\Plugin\Block;

use Drupal\block\BlockBase;
use Drupal\Component\Annotation\Block;
use Drupal\Core\Session\AccountInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;

/**
 * Provides a block for node forms.
 *
 * @Block(
 *   id = "formblock_node",
 *   admin_label = @Translation("Node form"),
 *   provider = "node"
 * )
 *
 * Note that we set module to node so that blocks will be disabled correctly
 * when the module is disabled.
 */
class NodeFormBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface.
   */
  protected $currentUser;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface.
   */
  protected $moduleHandler;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface.
   */
  protected $languageManager;

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface.
   */
  protected $entityFormBuilder;

  /**
   * Constructs a new NodeFormBlock plugin
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entityManger
   *   The database connection.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entityFormBuilder
   *   The entity form builder.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManagerInterface $entityManager, AccountInterface $currentUser, ModuleHandlerInterface $moduleHandler, LanguageManagerInterface $languageManger, EntityFormBuilderInterface $entityFormBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);

    $this->entityManager = $entityManager;
    $this->currentUser = $currentUser;
    $this->moduleHandler = $moduleHandler;
    $this->languageManager = $languageManger;
    $this->entityFormBuilder = $entityFormBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager'),
      $container->get('current_user'),
      $container->get('module_handler'),
      $container->get('language_manager'),
      $container->get('entity.form_builder')
    );
  }

  /**
   * Overrides \Drupal\block\BlockBase::settings().
   */
  public function defaultConfiguration() {
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
  public function build() {
    $build = array();

    $node_type = entity_load('node_type', $this->configuration['type']);

    if ($this->configuration['show_help']) {
      $build['help'] = array('#markup' => !empty($node_type->help) ? '<p>' . Xss::filterAdmin($node_type->help) . '</p>' : '');
    }

    $account = $this->currentUser;
    $langcode = $this->moduleHandler->invoke('language', 'get_default_langcode', array('node', $node_type->type));

    $node = $this->entityManager->getStorage('node')->create(array(
      'uid' => $account->id(),
      'name' => $account->getUsername() ?: '',
      'type' => $node_type->type,
      'langcode' => $langcode ? $langcode : $this->languageManager->getCurrentLanguage()->id,
    ));

    $build['form'] = $this->entityFormBuilder->getForm($node);


    return $build;
  }

  /**
   * Implements \Drupal\block\BLockBase::blockAccess().
   */
  public function access(AccountInterface $account) {
    return $this->entityManager->getAccessController('node')->createAccess($this->configuration['type'], $account);
  }
}
