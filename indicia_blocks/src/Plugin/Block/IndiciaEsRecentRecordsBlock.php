<?php

namespace Drupal\indicia_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Render\Markup;

/**
 * Provides a 'Recent Elasticsearch Records' block.
 *
 * @Block(
 *   id = "es_recent_records_block",
 *   admin_label = @Translation("Recent Elasticsearch records block"),
 * )
 */
class IndiciaEsRecentRecordsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    iform_load_helpers(['ElasticsearchReportHelper']);
    \ElasticsearchReportHelper::enableElasticsearchProxy();
    $fields = [
      'id',
      'taxon.accepted_name',
      'taxon.vernacular_name',
      'event.date_start',
      'event.date_end',
      'event.recorded_by',
      'location.output_sref',
      'occurrence.media',
      'location.point',
    ];
    $filterPath = 'hits.hits._source.' . implode(',hits.hits._source.', $fields);
    $r = \ElasticsearchReportHelper::source([
      'id' => 'src-IndiciaEsRecentRecordsBlock',
      'size' => 10,
      'proxyCacheTimeout' => 300,
      'filterPath' => $filterPath,
      'initialMapBounds' => TRUE,
    ]);
    // Totally exclude sensitive records.
    $r .= <<<HTML
<input type="hidden" class="es-filter-param" data-es-query-type="term" data-es-field="metadata.sensitive" data-es-bool-clause="must" value="false" />
HTML;
    $r .= \ElasticsearchReportHelper::customScript([
      'source' => 'src-IndiciaEsRecentRecordsBlock',
      'functionName' => 'handleEsRecentRecordsResponse',
    ]);
    return [
      '#markup' => Markup::create($r),
      '#attached' => [
        'library' => [
          'indicia_blocks/es-blocks',
        ],
      ],
    ];
  }

}
