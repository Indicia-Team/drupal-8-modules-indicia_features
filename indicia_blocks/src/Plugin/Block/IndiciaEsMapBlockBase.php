<?php

namespace Drupal\indicia_blocks\Plugin\Block;

/**
 * Base class for Elasticsearch map blocks.
 */
abstract class IndiciaEsMapBlockBase extends IndiciaBlockBase {

  /**
   * Adds the base layer control to a block form.
   *
   * @param array $form
   *   Form array.
   * @param array|null $config
   *   Optional block configuration. Loaded from the block if not provided.
   */
  protected function addBaseLayerFormCtrl(&$form, $config = NULL) {
    if ($config === NULL) {
      $config = $this->getConfiguration();
    }

    $form['base_layer'] = [
      '#type' => 'select',
      '#title' => $this->t('Base layer'),
      '#description' => $this->t('Select the base layer to use.'),
      '#options' => $this->getBaseMapOptions(),
      '#default_value' => $config['base_layer'] ?? 'OpenStreetMap',
    ];
  }

  /**
   * Returns the selectable base map options.
   *
   * @return array
   *   Keyed array of base map options.
   */
  protected function getBaseMapOptions() {
    $baseMapOptions = [
      'OpenStreetMap' => $this->t('Open Street Map'),
      'OpenTopoMap' => $this->t('Open Topo Map'),
      'EsriWorldImagery' => $this->t('Esri World Imagery'),
    ];
    if (\Drupal::config('iform.settings')->get('google_api_key')) {
      $baseMapOptions['GoogleSatellite'] = $this->t('Google Satellite');
      $baseMapOptions['GoogleRoadMap'] = $this->t('Google Streets');
      $baseMapOptions['GoogleTerrain'] = $this->t('Google Terrain');
      $baseMapOptions['GoogleHybrid'] = $this->t('Google Hybrid');
    }

    return $baseMapOptions;
  }

  /**
   * Saves base layer config from submitted block form state.
   */
  protected function saveBaseLayerFormCtrl($form_state) {
    $this->setConfigurationValue('base_layer', $form_state->getValue('base_layer'));
  }

  /**
   * Returns Leaflet base layer config for the selected base layer.
   *
   * @param string $layerName
   *   Layer machine name.
   *
   * @return array
   *   Base layer config.
   */
  protected function getBaseLayerConfig($layerName) {
    switch ($layerName) {
      case 'OpenStreetMap':
        return [
          'title' => $this->t('Open Street Map'),
          'type' => 'OpenStreetMap',
        ];

      case 'OpenTopoMap':
        return [
          'title' => $this->t('Open Topo Map'),
          'type' => 'OpenTopoMap',
        ];

      case 'EsriWorldImagery':
        return [
          'title' => $this->t('Esri World Imagery'),
          'type' => 'EsriWorldImagery',
        ];

      case 'GoogleSatellite':
        return [
          'title' => $this->t('Google Satellite'),
          'type' => 'Google',
          'subType' => 'satellite',
        ];

      case 'GoogleRoadMap':
        return [
          'title' => $this->t('Google Streets'),
          'type' => 'Google',
          'subType' => 'roadmap',
        ];

      case 'GoogleTerrain':
        return [
          'title' => $this->t('Google Terrain'),
          'type' => 'Google',
          'subType' => 'terrain',
        ];

      case 'GoogleHybrid':
        return [
          'title' => $this->t('Google Hybrid'),
          'type' => 'Google',
          'subType' => 'hybrid',
        ];

      default:
        return [
          'title' => $this->t('Open Street Map'),
          'type' => 'OpenStreetMap',
        ];
    }
  }

}