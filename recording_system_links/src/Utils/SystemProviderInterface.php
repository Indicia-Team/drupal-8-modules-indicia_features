<?php

namespace Drupal\recording_system_links\Utils;

/**
 * Define methods required for a system providers' utility class.
 */
interface SystemProviderInterface {

  /**
   * Is the record valid for this provider's requirements?
   *
   * Messages are displayed for any validation errors.
   *
   * @param array $record
   *   Record data.
   *
   * @return bool
   *   True if valid.
   */
  public function valid(array $record): bool;

  /**
   * Submit a sample.
   *
   * @param object $link
   *   Link information object.
   * @param array $record
   *   Record data.
   *
   * @return bool
   *   True if successfull.
   */
  public function submit($link, array $record): bool;

}
