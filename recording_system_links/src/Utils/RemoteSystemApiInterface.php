<?php

namespace Drupal\recording_system_links\Utils;

/**
 * Define methods required for an API providers' utility class.
 */
interface RemoteSystemApiInterface {

  /**
   * Retrieve a list of fields that need a value mapping for this API.
   *
   * @return array
   *   List of field names.
   */
  public static function requiredMappingFields() : array;

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
