<?php

namespace Drupal\recording_system_links\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\recording_system_links\Utils\SqlLiteLookups;
use Drupal\recording_system_links\Utils\ObservationOrgUtils;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A settings form for the link to another recording system.
 */
class LinkSettingsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recording_system_links_link_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $id = \Drupal::request()->query->get('id');
    $existing = !is_null($id);
    if ($existing) {
      // Editing an existing link.
      $link = \Drupal::database()->select('recording_system_config')
        ->fields('recording_system_config')
        ->condition('id', $id)
        ->execute()->fetchAssoc();

      if (empty($link)) {
        // Requested an key with an id that doesn't exist in DB.
        \Drupal::messenger()->addMessage('Unknown recording system link');
        throw new NotFoundHttpException();
      }
      $form['#title'] = $link['title'];
      $form['id'] = [
        '#type' => 'value',
        '#value' => $id,
      ];
    }
    else {
      // New link, set variables to default values.
      $link = [
        'title' => '',
        'machine_name' => '',
        'description' => '',
        'oauth2_url' => '',
        'client_id' => '',
        'api_provider' => '',
        'trigger_on_hooks' => 0,
        'trigger_on_cron' => 0,
      ];
    }

    // Build form.
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $link['title'],
      '#description' => $this->t('Set the human readable title for this link.'),
      '#required' => TRUE,
    ];
    $form['machine_name'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Machine name'),
      '#default_value' => $link['machine_name'],
      '#description' => $this->t('Machine name for this link. It must only contain lowercase letters, numbers, and underscores.'),
      '#required' => TRUE,
      '#machine_name' => [
        'exists' => [
          'Drupal\recording_system_links\Utils\RecordingSystemLinkUtils',
          'getLinkConfigFromMachineName',
        ],
        'source' => [
          'title',
        ],
      ],
    ];
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Link description'),
      '#description' => $this->t('Decscription of the link.'),
      '#default_value' => $link['description'],
    ];
    $form['oauth2_url'] = [
      '#type' => 'url',
      '#title' => $this->t('oAuth2 URL'),
      '#default_value' => $link['oauth2_url'],
      '#description' => $this->t('Root URL of the oAuth2 service, e.g. "token/" will be appended to create the URL to fetch the token.'),
      '#required' => TRUE,
    ];
    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $link['client_id'],
      '#description' => $this->t('Client ID used in calls to the oAuth2 service.'),
      '#required' => TRUE,
    ];
    $form['api_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('API provider'),
      '#default_value' => $link['api_provider'],
      '#options' => [
        'observation_org' => 'Observation.org',
      ],
      '#description' => $this->t('System providing the API, defines how the API calls to submit an occurrence work. Other providers may be added in future.'),
      '#required' => TRUE,
    ];
    $form['trigger_on_hooks'] = [
      '#type' => 'radios',
      '#title' => $this->t('Trigger immediate synchronisation'),
      '#default_value' => $link['trigger_on_hooks'],
      '#options' => [
        0 => 'No immediate synchronisation occurs',
        1 => 'Records are immediately synchronised at the point they are added to the system',
      ],
    ];
    $form['trigger_on_cron'] = [
      '#type' => 'radios',
      '#title' => $this->t('Trigger synchronisation in background'),
      '#default_value' => $link['trigger_on_cron'],
      '#options' => [
        0 => 'No background synchronisation occurs',
        1 => 'New records are synchronised in the background',
        2 => 'Historic and new records are synchronised in the background',
      ],
    ];
    $form['lookups'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Lookups'),
      '#default_value' => $link['lookups'],
      '#description' => $this->t('Lookups to use for fields in this link. Key=value pairs (one per line) where the key is a field name (as loaded from the filterable_remote_system_occurrences_report, e.g. lifeStage) and the value is the name of the lookup table to use for this field.'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    $form['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#href' => Url::fromRoute('recording_system_links.manage_links'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];
    // @todo Delete button
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $formValues = $form_state->getValues();
    // Check title unique.
    $query = \Drupal::database()->select('recording_system_config')
      ->fields('recording_system_config', ['id'])
      ->condition('title', $formValues['title']);
    if (!empty($formValues['id'])) {
      $query->condition('id', $formValues['id'], '<>');
    }
    $existing = $query->execute()->fetchAssoc();
    if (!empty($existing)) {
      $form_state->setErrorByName(
        'title',
        $this->t('This title is already used for an existing link. Please specify a unique title.')
      );
    }
    // Check lookups are key/value pairs with the correct field and table names.
    iform_load_helpers(['helper_base']);
    $lookupLines = \helper_base::explode_lines($formValues['lookups']);
    $lookups = new SqlLiteLookups();
    $lookups->getDatabase();
    $allTables = $lookups->listTables();
    $foundMappingFields = [];
    $requiredMappingFields = $formValues['api_provider'] === 'observation_org' ? ObservationOrgUtils::requiredMappingFields() : [];
    foreach ($lookupLines as $line) {
      // Skip empty lines.
      if (empty(trim($line))) {
        continue;
      }
      if (!preg_match('/^([^=\r\n\s]+)=([^=\r\n\s]+)$/', $line, $matches)) {
        $form_state->setErrorByName(
          'lookups',
          $this->t('The lookups are not specified as key=value pairs, one per line.') . $line . '.'
        );
        break;
      }
      if (!in_array($matches[1], $requiredMappingFields)) {
        $form_state->setErrorByName(
          'lookups',
          $this->t('Unrecognised mapping field name @field.', ['@field' => $matches[1]])
        );
        break;
      }
      if (!in_array($matches[2], $allTables)) {
        $form_state->setErrorByName(
          'lookups',
          $this->t('Unrecognised mapping table @table.', ['@table' => $matches[2]])
        );
        break;
      }
      $foundMappingFields[] = $matches[1];
    }
    if ($formValues['api_provider'] === 'observation_org') {
      if (count(array_diff($requiredMappingFields, $foundMappingFields)) > 0) {
        $form_state->setErrorByName(
          'lookups', var_export(array_keys($lookupLines), TRUE) .
          $this->t('The Observation.org API requires lookups for taxonID and lifeStage.')
        );
      }
    }
  }

  /**
   * Submit handler to save a link form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $formValues = $form_state->getValues();
    $values = [
      'title' => $formValues['title'],
      'machine_name' => $formValues['machine_name'],
      'description' => $formValues['description'],
      'oauth2_url' => $formValues['oauth2_url'],
      'client_id' => $formValues['client_id'],
      'api_provider' => $formValues['api_provider'],
      'trigger_on_hooks' => $formValues['trigger_on_hooks'],
      'trigger_on_cron' => $formValues['trigger_on_cron'],
      'lookups' => $formValues['lookups'],
      'changed' => time(),
      'changed_by' => time(),
    ];
    $userId = \Drupal::currentUser()->id();
    // Save the link with appropriate metadata.
    if (empty($formValues['id'])) {
      $values['created'] = time();
      $values['created_by'] = $userId;
      \Drupal::database()->insert('recording_system_config')
        ->fields($values)
        ->execute();
    }
    else {
      $values['changed'] = time();
      $values['changed_by'] = $userId;
      \Drupal::database()->update('recording_system_config')
        ->fields($values)
        ->condition('id', $formValues['id'])
        ->execute();
    }
    // Inform user and return to dashboard.
    \Drupal::messenger()->addMessage($this->t('Link %title has been saved', ['%title' => $formValues['title']]));
    $url = Url::fromRoute('recording_system_links.manage_links');
    $form_state->setRedirectUrl($url);
  }

}
