<?php

namespace Drupal\recording_system_links\Utils;

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

}
