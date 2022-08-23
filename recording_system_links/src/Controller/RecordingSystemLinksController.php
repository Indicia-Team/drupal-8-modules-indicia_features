<?php

namespace Drupal\recording_system_links\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\user\Entity\User;

class RecordingSystemLinksController extends ControllerBase {

  /**
   * An index page for the administration of links to other systems.
   */
  public function manageLinks() {
    $render = [];
    $db = \Drupal::database();
    $links = $db->query("SELECT id, title, description FROM {recording_system_config} ORDER BY title");
    $header = [
      $this->t('Title'),
      $this->t('Description'),
      '',
    ];
    $rows = [];
    foreach ($links as $link) {
      $editLinkUrl = Url::fromRoute('recording_system_links.configure_link', ['id' => $link->id]);
      $rows[] = [
        $link->title,
        $link->description,
        Link::fromTextAndUrl($this->t('Edit'), $editLinkUrl),
      ];
    }
    $render['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];

    $url = Url::fromRoute('recording_system_links.configure_link');
    $linkOptions = [
      'attributes' => [
        'class' => ['button'],
      ],
    ];
    $url->setOptions($linkOptions);
    $render['add_button'] = [Link::fromTextAndUrl($this->t('Add new link'), $url)->toRenderable()];

    return $render;
  }

  public function connect() {
    // @todo Client ID from config for the connection.
    $clientId = 'yq4XnkA5IAR6UbnQMIq7SsjWEwarCSttjBQklmTq';
    $url = Url::fromRoute('recording-system-links.oauth2-callback', [], ['absolute' => TRUE])->toString(TRUE);
    $response = new TrustedRedirectResponse('https://observation-test.org/api/v1/oauth2/authorize/?response_type=code&client_id=' . $clientId . '&redirect_uri=' . $url->getGeneratedUrl());
    $response->addCacheableDependency($url);
    return $response;
  }

  /**
   * Callback which the oAuth2 login form on the server can redirect to.
   *
   * Uses the provided token to obtain an access token to store for the current
   * user.
   *
   * @param string $system
   *   Name of the system being redirected from.
   */
  public function oauth2Callback($system = 'observation_org') {
    // @todo Obtain client ID from system config.
    $clientId = 'yq4XnkA5IAR6UbnQMIq7SsjWEwarCSttjBQklmTq';
    // @todo Obtain token URL from system config.
    $tokenUrl = 'https://observation-test.org/api/v1/oauth2/token/';

    $session = curl_init();
    // Set the POST options.
    curl_setopt($session, CURLOPT_URL, $tokenUrl);
    curl_setopt($session, CURLOPT_HEADER, TRUE);
    curl_setopt($session, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($session, CURLOPT_POST, 1);
    curl_setopt($session, CURLOPT_POSTFIELDS, "client_id=$clientId&grant_type=authorization_code&code=$_GET[code]");
    $rawResponse = curl_exec($session);
    $parts = explode("\r\n\r\n", $rawResponse);
    $responseBody = array_pop($parts);
    // @todo Error handling.
    $authObj = json_decode($responseBody);
    // Store the access token and refresh token in the
    // recording_system_oauth_tokens table.
    $userId = \Drupal::currentUser()->id();
    $database = \Drupal::database();
    $database
      ->insert('recording_system_oauth_tokens')
      ->fields([
        'uid',
        'recording_system',
        'access_token',
        'refresh_token',
      ])
      ->values([
        $userId,
        $system,
        $authObj->access_token,
        $authObj->refresh_token,
      ])
      ->execute();
  }

}
