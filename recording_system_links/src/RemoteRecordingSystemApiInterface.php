<?php

namespace Drupal\recording_system_links;

/**
 * Define methods required for an API providers' utility class.
 */
interface RemoteRecordingSystemApiInterface {

  /**
   * Retrieve a list of fields that need a value mapping for this API.
   *
   * @return array
   *   List of field names.
   */
  public function requiredMappingFields() : array;

  /**
   * Is the record valid for this provider's requirements?
   *
   * Messages are returned for any validation errors.
   *
   * @param object $link
   *   Link information object.
   * @param array $record
   *   Record data.
   *
   * @return array
   *   List of errors, empty if valid.
   */
  public function getValidationErrors($link, array $record): array;

  /**
   * Submit a sample.
   *
   * @param object $link
   *   Link information object.
   * @param array $record
   *   Record data.
   *
   * @return array
   *   Contains status (OK or fail), plus identifier (on success) or errors (on
   *   fail).
   */
  public function submit($link, array $record): array;

}
