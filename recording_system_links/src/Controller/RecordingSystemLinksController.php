<?php

namespace Drupal\recording_system_links\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\recording_system_links\Utils\RecordingSystemLinkUtils;

/**
 * Controller for endpoints relating to oAuth2 links to other systems.
 */
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
      $editLinkUrl->setOptions(['attributes' => ['class' => ['button']]]);
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
    $url->setOptions(['attributes' => ['class' => ['button']]]);
    $render['add_button'] = [Link::fromTextAndUrl($this->t('Add new link'), $url)->toRenderable()];

    return $render;
  }

  /**
   * Controller action to connect a user account to a recording system.
   *
   * Redirects to the remote systems authorization page via oAuth2.
   *
   * @param string $machineName
   *   Unique identifier for the system being connected to.
   *
   * @return Drupal\Core\Routing\TrustedRedirectResponse
   *   Redirection to remote system.
   */
  public function connect($machineName) {
    $link = RecordingSystemLinkUtils::getLinkConfigFromMachineName($machineName);
    if (empty($link)) {
      throw new NotFoundHttpException();
    }
    $url = $this->getRedirectUri($machineName);
    $response = new TrustedRedirectResponse('https://observation-test.org/api/v1/oauth2/authorize/?response_type=code&client_id=' . $link->client_id . '&redirect_uri=' . $url->getGeneratedUrl());
    $response->addCacheableDependency($url);
    return $response;
  }

  /**
   * Callback which the oAuth2 login form on the server can redirect to.
   *
   * Uses the provided token to obtain an access token to store for the current
   * user.
   *
   * @param string $machineName
   *   Name of the system being redirected from.
   *
   * @todo Remove default param when redirect corrected.
   */
  public function oauth2Callback($machineName = 'observation_org') {
    $linkConfig = RecordingSystemLinkUtils::getLinkConfigFromMachineName($machineName);
    if (empty($linkConfig)) {
      throw new NotFoundHttpException();
    }
    $tokenUrl = "{$linkConfig->oauth2_url}token/";

    $session = curl_init();
    // Set the POST options.
    curl_setopt($session, CURLOPT_URL, $tokenUrl);
    curl_setopt($session, CURLOPT_HEADER, TRUE);
    curl_setopt($session, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($session, CURLOPT_POST, 1);
    $urlString = $this->getRedirectUri($machineName)->getGeneratedUrl();
    curl_setopt($session, CURLOPT_POSTFIELDS, "client_id=$linkConfig->client_id&grant_type=authorization_code&code=$_GET[code]&redirect_uri=$urlString");
    $rawResponse = curl_exec($session);
    $httpCode = curl_getinfo($session, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($session);
    if ($curlErrno || $httpCode !== 200) {
      $errorInfo = ['Request failed when exchanging code for a token.'];
      $errorInfo[] = "URL: $tokenUrl.";
      $errorInfo[] = "POST data: client_id={client_id}&grant_type=authorization_code&code={code}.";
      if ($curlErrno) {
        $errorInfo[] = 'cUrl error: ' . $curlErrno . ': ' . curl_error($session);
      }
      if ($httpCode !== 200) {
        $errorInfo[] = "HTTP status $httpCode.";
      }
      $errorInfo[] = $rawResponse;
      \Drupal::logger('recording_system_links')->error(implode(' ', $errorInfo));
      \Drupal::messenger()->addError($this->t('Request for token from %title failed. More information is in the logs.', ['%title' => $linkConfig->title]));
      return new RedirectResponse(Url::fromRoute('user.page')->toString());
    }
    else {
      $parts = explode("\r\n\r\n", $rawResponse);

      // @todo Why is missing param redirect_uri returned? It wasn't required in my test bed.
      \Drupal::logger('recording_system_links')->notice($rawResponse);
      $responseBody = array_pop($parts);
      // @todo Error handling.
      $authObj = json_decode($responseBody);
      // Store the access token and refresh token in the
      // recording_system_oauth_tokens table.
      $userId = \Drupal::currentUser()->id();
      $database = \Drupal::database();
      $linkConfig = RecordingSystemLinkUtils::getLinkConfigFromMachineName($machineName);
      $database
        ->insert('recording_system_oauth_tokens')
        ->fields([
          'uid',
          'recording_system_config_id',
          'access_token',
          'refresh_token',
        ])
        ->values([
          $userId,
          $linkConfig->id,
          $authObj->access_token,
          $authObj->refresh_token,
        ])
        ->execute();
      \Drupal::messenger()->addMessage($this->t('Your account is now connected to %title.', ['%title' => $linkConfig->title]));
      return new RedirectResponse(Url::fromRoute('user.page')->toString());
    }
  }

  /**
   * Calculate the redirect_uri for a given system machine name.
   *
   * @param string $machineName
   *   Name of the system being redirected from.
   *
   * @return \Drupal\Core\GeneratedUrl
   *   A GeneratedUrl object is returned, containing the generated URL plus
   *   bubbleable metadata.
   */
  private function getRedirectUri($machineName) {
    // Get a trusted response, convoluted way of getting URL to avoid
    // cacheability metadata error.
    $url = Url::fromRoute('recording_system_links.oauth2-callback', ['machineName' => $machineName], ['absolute' => TRUE])->toString(TRUE);

    // @todo Remove this line of code. Only necessary until accepted
    // redirect_uri updated to include machine_name (observation_org) on
    // obs.org. Associated foo route can then also be removed.
    $url = Url::fromRoute('recording_system_links.oauth2-callback-foo', [], ['absolute' => TRUE])->toString(TRUE);

    return $url;
  }

}
