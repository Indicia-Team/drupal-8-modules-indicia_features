<?php

namespace Drupal\indicia_blocks\Plugin\Block;

use Drupal\Core\Render\Markup;
use Drupal\Core\Form\FormStateInterface;
use Masterminds\HTML5;

/**
 * Provides a 'Notifications & Welcome Message' block.
 *
 * @Block(
 *   id = "indicia_notifications_welcome_block",
 *   admin_label = @Translation("Notifications & Welcome Message block"),
 * )
 */
class IndiciaNotificationsWelcomeBlock extends IndiciaBlockBase {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    // Retrieve existing configuration for this block.
    $config = $this->getConfiguration();

    // Add a form field to the existing block configuration form.
    $form['notifications_page_path'] = [
      '#type' => 'textbox',
      '#title' => $this->t('Notifications page path'),
      '#description' => $this->t("Path to the page showing a user's notifications."),
      '#default_value' => isset($config['notifications_page_path']) ? $config['notifications_page_path'] : 'notifications',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    // Save our custom settings when the form is submitted.
    $this->setConfigurationValue('notifications_page_path', $form_state->getValue('notifications_page_path'));
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $r = '';
    iform_load_helpers(['data_entry_helper']);
    $connection = iform_get_connection_details();
    $userId = hostsite_get_user_field('indicia_user_id');
    if (empty($connection['website_id']) || empty($connection['password'])) {
      $this->messenger()->addWarning('Indicia configuration incomplete.');
    }
    elseif ($userId) {
      $readAuth = \data_entry_helper::get_read_auth($connection['website_id'], $connection['password']);
      $name = $this->getUserDisplayName();
      $notificationsCount = $this->getNotificationsCount($userId, $readAuth);
      $message = $this->getWelcomeMessage($name, $notificationsCount);
      // @todo Theme function for the following.
      // @todo Configurable notifications link.
      $r = <<<HTML
<div class="alert alert-info alert-dismissible fade in" id="indicia-notifications-welcome-block-container" >
  <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
  $message
</div>
HTML;
    }
    return [
      '#markup' => Markup::create($r),
      '#attached' => [
        'library' => [
          'indicia_blocks/es-blocks',
        ],
      ],
      '#cache' => [
        // No cache please.
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Make a data services request to find the user's notifications count.
   *
   * @param int $userId
   *   Warehouse user ID.
   * @param array $readAuth
   *   Authorisation tokens.
   *
   * @return int
   *   Count of unread notifications.
   */
  private function getNotificationsCount($userId, array $readAuth) {
    $training = hostsite_get_user_field('training') === TRUE ? 't' : 'f';
    $query = json_encode(['in' => ['training' => [$training, NULL]]]);
    $request = \data_entry_helper::$base_url . "index.php/services/data/notification?auth_token=$readAuth[auth_token]&nonce=$readAuth[nonce]";
    $request .= "&query=$query&user_id=$userId&acknowledged=f&wantRecords=0&wantCount=1";
    $notificationsData = \data_entry_helper::http_post($request);
    return json_decode($notificationsData['output'])->count;
  }

  /**
   * Builds a welcome message string including notifications link.
   *
   * @param string $name
   *   User display name.
   * @param int $notificationsCount
   *   Count of unread notifications.
   *
   * @return string
   *   HTML for the message and notifications link if relevant.
   */
  private function getWelcomeMessage($name, $notificationsCount) {
    $message = $this->t('Welcome back @name.', ['@name' => $name]);
    if ($notificationsCount) {
      $config = $this->getConfiguration();
      $link = empty($config['notifications_page_path']) ? 'notifications' : $config['notifications_page_path'];
      $siteRoot = \Drupal::urlGenerator()->generateFromRoute('<front>', [], ['absolute' => TRUE]);
      $notificationsInfo = $notificationsCount === 1 ? $this->t("You have 1 new notification.") : $this->t("You have @count new notifications.", ['@count' => $notificationsCount]);
      $message = "<i class=\"fas fa-envelope\"></i> $message <a href=\"$siteRoot$link\" class=\"alert-link\">$notificationsInfo</a>";
    }
    return $message;
  }

  /**
   * Returns a friendly display label for the logged in user.
   *
   * @return string
   *   Either their first name, or their Drupal display name.
   */
  private function getUserDisplayName() {
    hostsite_get_user_field('first_name');
    if (empty($name)) {
      // Fallback on username.
      $name = hostsite_get_user_field('name');
    }
    return $name;
  }

}
