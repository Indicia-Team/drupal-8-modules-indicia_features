<?php

namespace Drupal\locations_shp_importer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Location SHP importer settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'locations_shp_importer_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'locations_shp_importer.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('locations_shp_importer.settings');
    $form = [];
    $form['location_type_terms'] = [
      '#title' => $this->t('Allowed location types'),
      '#type' => 'textarea',
      '#description' => $this->t('If you want to limit the available location types that locations can be imported into, specify them here, one per line.'),
      '#default_value' => $config->get('location_type_terms'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('locations_shp_importer.settings');
    $values = $form_state->getValues();
    $config->set('location_type_terms', $values['location_type_terms']);
    $config->save();
    $this->messenger()->addMessage($this->t('The settings have been saved.'));
  }

}
