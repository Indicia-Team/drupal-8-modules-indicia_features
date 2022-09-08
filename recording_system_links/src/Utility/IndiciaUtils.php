<?php

namespace Drupal\recording_system_links\Utility;

/**
 * Helper class with useful functions for the warehouse interaction.
 */
class IndiciaUtils {

  /**
   * Find the max value in cache_occurrences_functional.tracking.
   *
   * @return int
   *   The max tracking value.
   */
  public static function getCurrentMaxTracking() {
    iform_load_helpers(['report_helper']);
    $conn = iform_get_connection_details();
    $readAuth = \report_helper::get_read_auth($conn['website_id'], $conn['password']);
    $trackingInfo = \report_helper::get_report_data([
      'dataSource' => 'library/occurrences/max_tracking',
      'readAuth' => $readAuth,
    ]);
    return $trackingInfo[0]['max_tracking'];
  }

  /**
   * Retrieve the list of records for a posted sample.
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

  /**
   * Retrieve the list of records changed since a min tracking ID.
   *
   * Provides details required to post on to the destination system.
   *
   * @param int $tracking
   *   Tracking ID.
   * @param array $warehouseUserIds
   *   List of warehouse user IDs if deemed appropriate to apply a filter -
   *   won't be useful if the list of user IDs is too long.
   *
   * @return array
   *   List of record data.
   */
  public static function getRecordsFromTracking($tracking, array $warehouseUserIds) {
    \iform_load_helpers(['report_helper']);
    $conn = \iform_get_connection_details();
    $readAuth = \helper_base::get_read_auth($conn['website_id'], $conn['password']);
    $tracking = $tracking ?? 0;
    $params = ['tracking_from' => $tracking + 1];
    if (!empty($warehouseUserIds)) {
      $params['created_by_id_list'] = implode(',', $warehouseUserIds);
    }
    $options = [
      'dataSource' => 'library/occurrences/filterable_remote_system_occurrences',
      'readAuth' => $readAuth,
      'extraParams' => $params,
      // Set a limit per cron run.
      'itemsPerPage' => 1000,
    ];
    return \report_helper::get_report_data($options);
  }

}
