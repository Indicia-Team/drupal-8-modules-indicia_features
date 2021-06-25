<?php

namespace Drupal\indicia_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Render\Markup;

/**
 * Provides a 'Elasticsearch Totals' block.
 *
 * @Block(
 *   id = "es_totals_block",
 *   admin_label = @Translation("Elasticsearch totals block"),
 * )
 */
class IndiciaEsTotalsBlock extends IndiciaBlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    iform_load_helpers(['ElasticsearchReportHelper']);
    \helper_base::add_resource('fontawesome');
    \ElasticsearchReportHelper::enableElasticsearchProxy();
    \helper_base::addLanguageStringsToJs('esTotalsBlock', [
      'speciesSingle' => '{1} species',
      'speciesMulti' => '{1} species',
      'occurrencesSingle' => '{1} record',
      'occurrencesMulti' => '{1} records',
      'photosSingle' => '{1} photo',
      'photosMulti' => '{1} photos',
      'recordersSingle' => '{1} recorder',
      'recordersMulti' => '{1} recorders',
    ]);
    $r = \ElasticsearchReportHelper::source([
      'id' => 'src-IndiciaEsTotalsBlock',
      'size' => 0,
      'proxyCacheTimeout' => 300,
      'aggregation' => [
        'species_count' => [
          'cardinality' => [
            'field' => 'taxon.species_taxon_id',
          ],
        ],
        'photo_count' => [
          'nested' => ['path' => 'occurrence.media'],
        ],
        'recorder_count' => [
          'cardinality' => [
            'field' => 'event.recorded_by.keyword',
          ],
        ],
      ],
    ]);
    $template = <<<HTML
<div id="indicia-es-totals-block-container" class="row">
  <div class="col-sm-3"><div class="count occurrences"><i class="fas fa-map-marker-alt"></i></div></div>
  <div class="col-sm-3"><div class="count species"><i class="fas fa-sitemap"></i></div></div>
  <div class="col-sm-3"><div class="count photos"><i class="fas fa-camera"></i></div></div>
  <div class="col-sm-3"><div class="count recorders"><i class="fas fa-user-friends"></i></div></div>
</div>

HTML;
    $r .= \ElasticsearchReportHelper::customScript([
      'id' => 'indicia-es-totals-block',
      'source' => 'src-IndiciaEsTotalsBlock',
      'functionName' => 'handleEsTotalsResponse',
      'template' => $template,
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
