<?php

namespace Drupal\indicia_blocks\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Base class for providing Indicia blocks with permissions.
 */
abstract class IndiciaBlockBase extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    // Retrieve existing configuration for this block.
    $config = $this->getConfiguration();

    // Add a form field to the existing block configuration form.
    $form['view_permission'] = [
      '#type' => 'textfield',
      '#title' => $this->t('View permission'),
      '#description' => $this->t('Set to the name of an existing permission that is required to view the block content, or leave blank to make the block content publicly accessible.'),
      '#default_value' => isset($config['view_permission']) ? $config['view_permission'] : '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // Save our custom settings when the form is submitted.
    $this->setConfigurationValue('view_permission', trim($form_state->getValue('view_permission')));
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    $viewPermission = $this->getConfiguration()['view_permission'];
    if (!empty($viewPermission)) {
      return AccessResult::allowedIfHasPermission($account, $viewPermission);
    }
    else {
      return AccessResult::allowed();
    }
  }

}
