<?php

namespace Drupal\indicia_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Render\Markup;

/**
 * Provides a 'Recent Elasticsearch Records Map' block.
 *
 * Relies on the Recent Elasticsearch Records block for population.
 *
 * @Block(
 *   id = "es_recent_records_map_block",
 *   admin_label = @Translation("Recent Elasticsearch records map block"),
 * )
 */
class IndiciaEsRecentRecordsMapBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    iform_load_helpers(['ElasticsearchReportHelper']);
    \ElasticsearchReportHelper::enableElasticsearchProxy();
    $r = \ElasticsearchReportHelper::leafletMap([
      'layerConfig' => [
        'recent-records' => [
          'title' => $this->t('Recent records'),
          'source' => 'src-IndiciaEsRecentRecordsBlock',
          'forceEnabled' => TRUE,
        ],
      ],
    ]);
    return [
      '#markup' => Markup::create($r),
      '#attached' => [
        'library' => [
          'iform/leaflet',
        ],
      ],
    ];
  }

}
