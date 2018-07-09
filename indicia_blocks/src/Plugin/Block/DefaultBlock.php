<?php

namespace Drupal\indicia_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'DefaultBlock' block.
 *
 * @Block(
 *  id = "default_block",
 *  admin_label = @Translation("Default block"),
 * )
 */
class DefaultBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
                ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['report_parameters'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Report parameters'),
      '#default_value' => $this->configuration['report_parameters'],
      '#weight' => '0',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['test'] = $form_state->getValue('test');
    $this->configuration['report_parameters'] = $form_state->getValue('report_parameters');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['default_block_test']['#markup'] = '<p>' . $this->configuration['test'] . '</p>';
    $build['default_block_report_parameters']['#markup'] = '<p>' . $this->configuration['report_parameters'] . '</p>';

    return $build;
  }

}
