<?php

namespace Drupal\formblock\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\Annotation\Block;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\node\Entity\NodeType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;

/**
 * Provides a block for entity forms.
 *
 * @Block(
 *   id = "formblock_entity",
 *   admin_label = @Translation("Entity form")
 * )
 */
class EntityFormBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface.
   */
  protected $entityFormBuilder;

  /**
   * Constructs a new EntityFormBlock plugin
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entityManger
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entityFormBuilder
   *   The entity form builder.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManagerInterface $entityManager, EntityFormBuilderInterface $entityFormBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);

    $this->entityManager = $entityManager;
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
      $container->get('entity.form_builder')
    );
  }

  /**
   * Overrides \Drupal\block\BlockBase::settings().
   */
  public function defaultConfiguration() {
    return array(
      'entity_type' => NULL,
      'bundle' => NULL,
      'operation' => NULL,
    );
  }

  /**
   * Overrides \Drupal\block\BlockBase::blockForm().
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['entity_type'] = [
      '#title' => $this->t('Entity type'),
      '#description' => $this->t('Select the entity type whose form will be shown in the block.'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => $this->getSupportedEntityOptions(),
      '#default_value' => $this->configuration['entity_type'],
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxBundleCallback'],
        'wrapper' => 'formblock-bundle-container',
      ],
    ];

    if ($form_state->hasValue('settings') && isset($form_state->getValue('settings')['entity_type'])) {
      $bundles = $this->getBundlesForEntity($form_state->getValue('settings')['entity_type']);
      $operations = $this->getFormOperations($form_state->getValue('settings')['entity_type']);
    }
    else {
      if (!is_null($this->configuration['entity_type'])) {
        $bundles = $this->getBundlesForEntity($this->configuration['entity_type']);
        $operations = $this->getFormOperations($this->configuration['entity_type']);
      }
      else {
        $bundles = $operations = [];
      }
    }


    $form['entity_dependent'] = [
      '#prefix' => '<div id="formblock-bundle-container">',
      '#suffix' => '</div>'
    ];
    $form['entity_dependent']['bundle'] = [
      '#title' => t('Bundle'),
      '#type' => 'select',
      '#options' => $bundles,
      '#required' => TRUE,
      '#default_value' => $this->configuration['bundle'],
    ];

    $form['entity_dependent']['operation'] = [
      '#title' => t('Operation'),
      '#type' => 'select',
      '#options' => $operations,
      '#required' => TRUE,
      '#default_value' => $this->configuration['operation'],
    ];

    return $form;
  }

  /**
   * Overrides \Drupal\block\BlockBase::blockSubmit().
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['entity_type'] = $form_state->getValue('entity_type');
    $this->configuration['bundle'] = $form_state->getValue('entity_dependent')['bundle'];
    $this->configuration['operation'] = $form_state->getValue('entity_dependent')['operation'];
  }

  /**
   * Static callback for ajax form rebuild.
   */
  public static function ajaxBundleCallback($form, FormStateInterface $form_state) {
    return $form['settings']['entity_dependent'];
  }

  /**
   * Get the allowed operations for this form.
   */
  public function getFormOperations($entity_type) {
    $definition = $this->entityManager->getDefinition($entity_type);
    $handlers = $definition->getHandlerClasses();
    $form_operations = array_combine(array_keys($handlers['form']), array_keys($handlers['form']));

    $allowed_operations = $this->supportedOperations();
    return array_filter($form_operations, function($operation) use ($allowed_operations) {
      return in_array($operation, $allowed_operations);
    });
  }

  /**
   * Return an array of form operations that this block does supports.
   */
  protected function supportedOperations() {
    return array('default', 'add', 'register');
  }

  /**
   * Get a list of bundles for a specific entity type.
   *
   * @param $entity_type
   *   The entity type.
   *
   * @return array
   *   A list of bundle labels keyed by bundle ID.
   */
  protected function getBundlesForEntity($entity_type) {
    $bundles = $this->entityManager->getBundleInfo($entity_type);

    return array_map(function ($bundle) {
      return $bundle['label'];
    }, $bundles);
  }

  /**
   * Get a list entity types that have form classes.
   *
   * @return \Drupal\Core\Entity\EntityType[]
   *   An array of entity types.
   */
  protected function getSupportedEntityTypes() {
    $definitions = $this->entityManager->getDefinitions();

    return array_filter($definitions, function($entity_type) {
      return $entity_type->hasFormClasses();
    });
  }

  /**
   * Get a list of supported
   *
   * @return array
   *   List of entity type labels keyed by entity type ID.
   */
  protected function getSupportedEntityOptions() {
    $types = $this->getSupportedEntityTypes();

    $options = [];
    foreach ($types as $type) {
      $options[$type->get('id')] = $type->get('label');
    }
    asort($options);
    return $options;
  }



  /**
   * Implements \Drupal\block\BlockBase::build().
   */
  public function build() {
    $values = [];

    $definition = $this->entityManager->getDefinition($this->configuration['entity_type']);
    $bundle_key = $definition->get('entity_keys')['bundle'];
    if (!empty($bundle_key)) {
      $values[$bundle_key] = $this->configuration['bundle'];
    }
    $entity = $this->entityManager->getStorage($definition->get('id'))->create($values);

    return $this->entityFormBuilder->getForm($entity, $this->configuration['operation']);
  }

  /**
   * Implements \Drupal\block\BlockBase::blockAccess().
   */
  public function blockAccess(AccountInterface $account) {
    $access_control_handler = $this->entityManager->getAccessControlHandler($this->configuration['entity_type']);
    return $access_control_handler->createAccess($this->configuration['bundle'], $account);
  }
}
