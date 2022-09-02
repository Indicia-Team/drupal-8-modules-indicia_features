<?php

namespace Drupal\recording_system_links\Utils;

/**
 * Helper class with useful functions for the recording_system_links module.
 *
 * Mappings for life stage can be obtained from the API, via
 * https://observation-test.org/api/v1/species-groups/ (to get the group IDs)
 * and https://observation-test.org/api/v1/species-groups/4/attributes/ (to get
 * the stages for the group).
 *
 * @todo Implement interface
 */
class ObservationOrgUtils implements SystemProviderInterface {

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
  public function valid(array $record): bool {
    // @todo Any records that don't match species are rejected?
    return TRUE;
  }

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
  public function submit($link, array $record): bool {
    $session = curl_init();
    $url = preg_replace('/oauth2\/$/', '', $link->oauth2_url);
    curl_setopt($session, CURLOPT_URL, "{$url}observations/create-single/");
    curl_setopt($session, CURLOPT_HEADER, TRUE);
    curl_setopt($session, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($session, CURLOPT_POST, 1);
    curl_setopt($session, CURLOPT_POSTFIELDS, $this->getRecordDataAsPostString($record, $link));
    \Drupal::messenger()->addWarning($this->getRecordDataAsPostString($record, $link));
    curl_setopt($session, CURLOPT_HTTPHEADER, [
      "Authorization: Bearer $link->access_token",
      'Content-type: multipart/form-data; boundary=fieldboundary',
    ]);
    $rawResponse = curl_exec($session);
    $arrResponse = explode("\r\n\r\n", $rawResponse);
    // Last part of response is the actual data.
    $responsePayload = array_pop($arrResponse);
    $response = json_decode($responsePayload);
    $result = $this->checkPostErrors($session, $link, $response);
    \Drupal::messenger()->addStatus(var_export($this->getRecordDataAsPostString($record, $link), TRUE));
    \Drupal::messenger()->addStatus(var_export($response, TRUE));
    curl_close($session);
    return $result;
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
   * @return bool
   *   TRUE if request was successful.
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
          $messages[] = "$key: $msg";
        }
      }
      \Drupal::messenger()->addError(implode(' ', $messages));
      \Drupal::logger('recording_system_links')->error(implode(' ', $messages));
      return FALSE;
    }
    return TRUE;
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
      'species' => $lookups->lookup($linkLookupInfo['taxonID'], $record['taxonID']),
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
    // @todo Map lifeStages to the ID values in Observation.org.
    if (!empty($record['lifeStage'])) {
      if (isset($linkLookupInfo['lifeStage'])) {
        $mappedValue = $lookups->lookup($linkLookupInfo['lifeStage'], $record['lifeStage']);
        if ($mappedValue) {
          $fields['life_stage'] = $mappedValue;
        }
      }
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
        $data .= $this->getResizedWarehouseImageData($fileName, 1000, 1000) . "\r\n";
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
   *
   * @return string
   *   Binary image data.
   */
  private function getResizedWarehouseImageData($fileName, $widthDest, $heightDest) {
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
      \Drupal::logger('recording_system_links')->warning("File $fileName could not be accessed from warehouse for upload to Observation.org for record $record[occurrenceID].");
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
