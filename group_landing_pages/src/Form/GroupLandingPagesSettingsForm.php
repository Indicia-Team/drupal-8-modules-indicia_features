<?php

namespace Drupal\group_landing_pages\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure settings for this module.
 */
class GroupLandingPagesSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'group_landing_pages.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'group_landing_pages_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['group_edit_alias'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Group edit alias'),
      '#description' => $this->t('Alias of the group edit page'),
      '#default_value' => $config->get('group_edit_alias') ?? '/groups/edit',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->config(static::SETTINGS)
      // Set the submitted configuration setting.
      ->set('group_edit_alias', $form_state->getValue('group_edit_alias'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
