<?php

namespace Drupal\formblock\Plugin\Block;

use Drupal\block\BlockBase;
use Drupal\Component\Annotation\Block;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

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
class UserRegisterBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity manager
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface.
   */
  protected $entityManager;

  /**
   * The entity form builder
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface.
   */
  protected $entityFormBuilder;

  /**
   * The request
   *
   * @var \Symfony\Component\HttpFoundation\Request.
   */
  protected $request;

  /**
   * Constructs a new UserRegisterBlock plugin
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entityManager
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entityFormBuilder
   *   The entity form builder.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManagerInterface $entityManager, EntityFormBuilderInterface $entityFormBuilder, Request $request) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityManager = $entityManager;
    $this->entityFormBuilder = $entityFormBuilder;
    $this->request = $request;
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
      $container->get('entity.form_builder'),
      $container->get('request')
    );
  }

  /**
   * Implements \Drupal\block\BlockBase::build().
   */
  public function build() {
    $build = array();

    $account = $this->entityManager->getStorage('user') ->create(array());
    $build['form'] = $this->entityFormBuilder->getForm($account, 'register');

    return $build;
  }

  /**
   *Implements \Drupal\block\BlockBase::blockAccess().
   */
  public function blockAccess(AccountInterface $account) {
    return ($this->request->attributes->get('_menu_admin') || $account->isAnonymous()) && (\Drupal::config('user.settings')->get('register') != USER_REGISTER_ADMINISTRATORS_ONLY);
  }
}
