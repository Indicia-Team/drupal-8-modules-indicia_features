<?php

namespace Drupal\recording_system_links\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A settings form foro the link to another recording system.
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
        ->fields('recording_system_config',   [
          'title',
          'machine_name',
          'description',
          'oauth2_url',
          'client_id',
          'api_provider',
        ])
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
      '#type' => 'textfield',
      '#title' => $this->t('Machine name'),
      '#default_value' => $link['machine_name'],
      '#description' => $this->t('Machine name for this link. Lowercase, alphabetic, numeric and hyphens only.'),
      '#required' => TRUE,
    ];
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Link description'),
      '#description' => $this->t('Decscription of the link.'),
      '#default_value' => $link['description'],
    ];
    $form['oauth2_url'] = [
      '#type' => 'textfield',
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
   * Submit handler to save an key.
   *
   * Implements hook_submit() to submit a form produced by
   * indicia_api_key().
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $formValues = $form_state->getValues();
    // @todo validate
    $values = [
      'title' => $formValues['title'],
      'machine_name' => $formValues['machine_name'],
      'description' => $formValues['description'],
      'oauth2_url' => $formValues['oauth2_url'],
      'client_id' => $formValues['client_id'],
      'api_provider' => $formValues['api_provider'],
      'changed' => time(),
      'changed_by' => time(),

    ];
    $userId = \Drupal::currentUser()->id();
    // Save the link.
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
