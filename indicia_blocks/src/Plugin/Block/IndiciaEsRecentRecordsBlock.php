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
class IndiciaEsRecentRecordsBlock extends IndiciaBlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    iform_load_helpers(['ElasticsearchReportHelper']);
    \ElasticsearchReportHelper::enableElasticsearchProxy();
    $location = hostsite_get_user_field('location');
    $groups = hostsite_get_user_field('taxon_groups');
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
    $options = [
      'id' => 'src-IndiciaEsRecentRecordsBlock',
      'size' => 10,
      'proxyCacheTimeout' => 300,
      'filterPath' => $filterPath,
      'initialMapBounds' => TRUE,
    ];
    // Apply user profile preferences.
    if ($location || $groups) {
      $options['filterBoolClauses'] = ['must' => []];
      if ($location) {
        $options['filterBoolClauses']['must'][] = [
          'query_type' => 'term',
          'nested' => 'location.higher_geography',
          'field' => 'location.higher_geography.id',
          'value' => $location,
        ];
      }
      if ($groups) {
        $options['filterBoolClauses']['must'][] = [
          'query_type' => 'terms',
          'field' => 'taxon.group_id',
          'value' => json_encode(unserialize($groups)),
        ];
      }
    }
    $r = \ElasticsearchReportHelper::source($options);
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
          'indicia_blocks/recent-records-block',
        ],
      ],
      '#cache' => [
        // No cache please.
        'max-age' => 0,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Prevent caching.
   */
  public function getCacheMaxAge() {
    return 0;
  }
}
