<?php

namespace Drupal\recording_system_links\Plugin\RemoteRecordingSystemApi;

use Drupal\recording_system_links\RemoteRecordingSystemApiInterface;
use Drupal\recording_system_links\Utility\SqlLiteLookups;

/**
 * Plugin for interaction with Observation.org's API.
 *
 * Mappings for life stage can be obtained from the API, via
 * https://observation-test.org/api/v1/species-groups/ (to get the group IDs)
 * and https://observation-test.org/api/v1/species-groups/4/attributes/ (to get
 * the stages for the group).
 *
 * @RemoteRecordingSystemApi(
 *   id = "observation_org",
 *   title = @Translation("Observation.org")
 * )
 */
class ObservationOrg implements RemoteRecordingSystemApiInterface {

  /**
   * Retrieve a list of fields that need a value mapping for this API.
   *
   * @return array
   *   List of field names.
   */
  public function requiredMappingFields() : array {
    return ['taxonID', 'lifeStage'];
  }

  /**
   * Adds mapped values to the record for mapped fields.
   *
   * For fields where the data value is mapped to a value in the destination
   * API, adds the mapped values to the record.
   *
   * @param object $link
   *   Link information object.
   * @param array $record
   *   Record data which will have additional keys added for the mapped values.
   *
   * @todo Should this be in a base class for API providers?
   */
  public function addMappedValues($link, array &$record) {
    $linkLookupInfo = \helper_base::explode_lines_key_value_pairs($link->lookups);
    $lookups = new SqlLiteLookups();
    $lookups->getDatabase();
    foreach (self::requiredMappingFields() as $field) {
      $record["$field-mapped"] = $lookups->lookup($linkLookupInfo[$field], $record[$field]);
    }
  }

  /**
   * Is the record valid for this provider's requirements?
   *
   * Messages are displayed for any validation errors.
   *
   * @param object $link
   *   Link information object.
   * @param array $record
   *   Record data.
   *
   * @return array
   *   List of errors, empty if valid.
   */
  public function getValidationErrors($link, array $record): array {
    $errors = [];
    $requiredFields = ['taxonID', 'eventDate', 'decimalLatitude', 'decimalLongitude'];
    foreach ($requiredFields as $field) {
      if (empty($record[$field])) {
        $errors[$field] = t('The @field field is required.', ['@field' => $field]);
      }
    }
    foreach (self::requiredMappingFields() as $field) {
      if (!empty($record[$field]) && empty($record["$field-mapped"])) {
        $errors[$field] = t('The @field field value "@value" cannot be mapped to the destination system.', [
          '@field' => $field,
          '@value' => $record[$field],
        ]);
      }
    }
    return $errors;
  }

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
  public function submit($link, array $record): array {
    $session = curl_init();
    $url = preg_replace('/oauth2\/$/', '', $link->oauth2_url);
    curl_setopt($session, CURLOPT_URL, "{$url}observations/create-single/");
    curl_setopt($session, CURLOPT_HEADER, TRUE);
    curl_setopt($session, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($session, CURLOPT_POST, 1);
    curl_setopt($session, CURLOPT_POSTFIELDS, $this->getRecordDataAsPostString($record, $link));
    curl_setopt($session, CURLOPT_HTTPHEADER, [
      "Authorization: Bearer $link->access_token",
      'Content-type: multipart/form-data; boundary=fieldboundary',
    ]);
    $rawResponse = curl_exec($session);
    $arrResponse = explode("\r\n\r\n", $rawResponse);
    // Last part of response is the actual data.
    $responsePayload = array_pop($arrResponse);
    $response = json_decode($responsePayload);
    $errors = $this->checkPostErrors($session, $link, $response);
    curl_close($session);
    if (count($errors) > 0) {
      return [
        'status' => 'fail',
        'errors' => $errors,
      ];
    }
    else {
      return [
        'status' => 'OK',
        'identifier' => $response->permalink,
      ];
    }

  }

  /**
   * Checks the results of a cUrl POST and displays errors.
   *
   * @param object $session
   *   cUrl session.
   * @param object $link
   *   Link information object.
   * @param object $response
   *   Response from Observation.org.
   *
   * @return array
   *   List of errors messages, or empty array.
   */
  private function checkPostErrors($session, $link, $response) {
    $httpCode = curl_getinfo($session, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($session);
    // Check for an error, or check if the http response was not OK.
    if ($curlErrno || $httpCode != 201) {
      $messages = [
        t('Error sending data to @title.', ['@title' => $link->title]),
      ];
      if ($curlErrno) {
        $messages[] = t('Error number @errNo', ['@errNo' => $curlErrno]) . ' ' . curl_error($session);
      }
      if ($httpCode !== 200) {
        $messages[] = t('Response status: @code', ['@code' => $httpCode]);
      }
      if ($response) {
        foreach ($response as $key => $msg) {
          $messages[] = "$key: " . json_encode($msg);
        }
      }
      \Drupal::logger('recording_system_links')->error(implode(' ', $messages));
      return $messages;
    }
    return [];
  }

  /**
   * Converts record data loaded from the warehouse to POST data.
   *
   * @param array $record
   *   Record data loaded from the warehouse.
   * @param object $link
   *   Link information object.
   *
   * @return string
   *   POST data string in Observation.org's format.
   */
  private function getRecordDataAsPostString(array $record, $link) {
    iform_load_helpers(['helper_base']);
    $linkLookupInfo = \helper_base::explode_lines_key_value_pairs($link->lookups);
    $lookups = new SqlLiteLookups();
    $lookups->getDatabase();
    // @todo consider vague dates.
    $fields = [
      'external_reference' => $record['occurrenceID'],
      'point' => "POINT($record[decimalLongitude] $record[decimalLatitude])",
      'species' => $record['taxonID-mapped'],
      'date' => $record['eventDate'],
      'accuracy' => $record['coordinateUncertaintyInMeters'],
    ];
    if (preg_match('/^\d+$/', $record['organismQuantity'])) {
      $fields['number'] = $record['organismQuantity'];
    }
    // @todo Dependency injection for t().
    if (in_array(strtolower($record['sex']), [t('male'), substr(t('male'), 0, 1)])) {
      $fields['sex'] = 'M';
    }
    elseif (in_array(strtolower($record['sex']), [t('female'), substr(t('female'), 0, 1)])) {
      $fields['sex'] = 'F';
    }
    if (!empty($record['occurrencRemarks']) || !empty($record['eventRemarks'])) {
      $notes = [];
      if (!empty($record['occurrencRemarks'])) {
        $notes[] = $record['occurrencRemarks'];
      }
      if (!empty($record['eventRemarks'])) {
        $notes[] = $record['eventRemarks'];
      }
      $fields['notes'] = implode(' ', $notes);
    }
    if (!empty($record['lifeStage-mapped'])) {
      $fields['life_stage'] = $record['lifeStage-mapped'];
    }
    return $this->fieldsToRawPostString($fields, $record);
  }

  /**
   * Creates the raw POST payload for a record.
   *
   * This approach required due to incompatibilities between PHP's cUrl
   * multiple file handling and the Observation.org web service.
   */
  private function fieldsToRawPostString($fields, $record) {
    $delimiter = 'fieldboundary';
    $data = '';
    // Create form data as in raw form, as we can then add multiple
    // upload_photos which other methods don't allow.
    foreach ($fields as $name => $content) {
      $data .= "--$delimiter\r\n";
      $data .= "Content-Disposition: form-data; name=\"$name\"\r\n";
      // End of the headers.
      $data .= "\r\n";
      // Data value.
      $data .= "$content\r\n";
    }
    if (!empty($record['media'])) {
      $files = explode(',', $record['media']);
      // Allow GD to load image data from warehouse URL.
      ini_set("allow_url_fopen", TRUE);
      foreach ($files as $fileName) {
        $ext = strtolower(substr($fileName, strrpos($fileName, '.') + 1));
        if (!in_array($ext, ['jpg', 'jpeg'])) {
          // @todo Check Observation.org API to see if it supports other formats.
          \Drupal::logger('recording_system_links')->warning("File $fileName format not supported for upload to Observation.org for record $record[occurrenceID].");
          continue;
        }
        $data .= "--$delimiter\r\n";
        $data .= "Content-Disposition: form-data; name=\"upload_photos\"; filename=\"$fileName\"\r\n";
        $data .= "Content-Type: image/jpeg\r\n";
        $data .= "Content-Transfer-Encoding: binary\r\n";
        // End of the headers.
        $data .= "\r\n";
        // Add file content.
        $data .= $this->getResizedWarehouseImageData($fileName, 1000, 1000, $record['occurrenceID']) . "\r\n";
      }
    }
    $data .= "--$delimiter--\r\n";
    return $data;
  }

  /**
   * Retrieve resized binary data for an image from the warehouse.
   *
   * @param string $fileName
   *   File name from the upload folder on the warehouse.
   * @param int $widthDest
   *   Maximum final image width.
   * @param int $heightDest
   *   Maximum final image height.
   * @param int $recordId
   *   Indicia record ID (for error reporting only).
   *
   * @return string
   *   Binary image data.
   */
  private function getResizedWarehouseImageData($fileName, $widthDest, $heightDest, $recordId) {
    list($widthSrc, $heightSrc, $type) = getimagesize("http://localhost/warehouse/upload/$fileName");
    $ratio = $widthSrc / $heightSrc;
    if ($widthDest / $heightDest > $ratio) {
      $widthDest = floor($heightDest * $ratio);
    }
    else {
      $heightDest = floor($widthDest / $ratio);
    }
    $imgSrc = imagecreatefromjpeg("http://localhost/warehouse/upload/$fileName");
    if ($imgSrc === FALSE) {
      \Drupal::logger('recording_system_links')->warning("File $fileName could not be accessed from warehouse for upload to Observation.org for record $recordId.");
    }
    $imgDest = imagecreatetruecolor($widthDest, $heightDest);
    imagecopyresampled($imgDest, $imgSrc, 0, 0, 0, 0, $widthDest, $heightDest, $widthSrc, $heightSrc);
    ob_start();
    imagejpeg($imgDest);
    $imageAsString = ob_get_contents();
    ob_end_clean();
    imagedestroy($imgSrc);
    imagedestroy($imgDest);
    return $imageAsString;
  }

}
