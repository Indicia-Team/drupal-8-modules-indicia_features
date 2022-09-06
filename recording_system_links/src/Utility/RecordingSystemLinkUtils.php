<?php

namespace Drupal\recording_system_links\Utility;

/**
 * Helper class with useful functions for the recording_system_links module.
 */
class RecordingSystemLinkUtils {

  /**
   * Find details of a link using it's machine name to locate it.
   *
   * @param string $machineName
   *   Machine name of the link to find.
   *
   * @return obj
   *   Link object.
   *
   * @todo Move to a helper class?
   */
  public static function getLinkConfigFromMachineName($machineName) {
    $results = \Drupal::database()->query(
      'SELECT * FROM {recording_system_config} WHERE machine_name = :machine_name',
      [':machine_name' => $machineName]);
    $link = $results->fetch();
    return $link;
  }


  /**
   * Retrieve a list of the recording system links from the database.
   *
   * @param string $type
   *   Filter on the trigger on settings - one of 'all', 'hook' or 'cron'.
   *
   * @return array
   *   List of links and access tokens.
   */
  public static function getAllSystemLinkList($type = 'all') {
    $query = \Drupal::database()->select('recording_system_config', 'rsc');
    $query->fields('rsc');
    if ($type === 'hook') {
      $query->condition('trigger_on_hooks', 0, '<>');
    }
    elseif ($type === 'cron') {
      $query->condition('trigger_on_cron', 0, '<>');
    }
    $query->orderBy('title');
    return $query->execute()->fetchAll();
  }

  /**
   * Retrieve a list of the recording system links from the database.
   *
   * Includes the user's access tokens.
   *
   * @param bool $includeUnlinked
   *   Set to true to include system links that the user has not connected
   *   their account to.
   * @param string $type
   *   Filter on the trigger on settings - one of 'all', 'hook' or 'cron'.
   *
   * @return array
   *   List of links and access tokens.
   */
  public static function getUsersSystemLinkList($includeUnlinked, $type = 'all') {
    $query = \Drupal::database()->select('recording_system_config', 'rsc');
    $query->addJoin($includeUnlinked ? 'LEFT OUTER' : 'INNER', 'recording_system_oauth_tokens', 'rst', 'rst.recording_system_config_id=rsc.id AND rst.uid=' . \Drupal::currentUser()->id());
    $query->fields('rsc');
    $query->addField('rst', 'uid', 'rst_uid');
    $query->fields('rst', ['access_token', 'refresh_token']);
    if ($type === 'hook') {
      $query->condition('trigger_on_hooks', 0, '<>');
    }
    elseif ($type === 'cron') {
      $query->condition('trigger_on_cron', 0, '<>');
    }
    $query->orderBy('title');
    return $query->execute()->fetchAll();
  }

  /**
   * Retreive the list of records for a posted sample.
   *
   * Provides details required to post on to the destination system.
   *
   * @param int $sampleId
   *   Sample ID.
   *
   * @return array
   *   List of record data.
   */
  public static function getRecordsForSample($sampleId) {
    \iform_load_helpers(['report_helper']);
    $conn = \iform_get_connection_details();
    $readAuth = \helper_base::get_read_auth($conn['website_id'], $conn['password']);
    $options = [
      'dataSource' => 'library/occurrences/filterable_remote_system_occurrences',
      'readAuth' => $readAuth,
      'extraParams' => ['smp_id' => $sampleId],
    ];
    return \report_helper::get_report_data($options);
  }

}
