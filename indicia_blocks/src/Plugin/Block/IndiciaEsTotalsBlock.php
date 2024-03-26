<?php

namespace Drupal\indicia_blocks\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
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
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $this->addDefaultEsFilterFormCtrls($form);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->saveDefaultEsFilterFormCtrls($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    self::$blockCount++;
    iform_load_helpers(['ElasticsearchReportHelper']);
    $enabled = \ElasticsearchReportHelper::enableElasticsearchProxy();
    if (!$enabled) {
      global $indicia_templates;
      return [
        '#markup' => str_replace('{message}', $this->t('Service unavailable.'), $indicia_templates['warningBox']),
      ];
    }
    $config = $this->getConfiguration();
    \helper_base::add_resource('fontawesome');
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
    $options = [
      'id' => 'src-IndiciaEsTotalsBlock-' . self::$blockCount,
      'size' => 0,
      'proxyCacheTimeout' => $config['cache_timeout'] ?? 300,
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
    ];
    // Other record filters.
    if (!empty($config['sensitive_records']) && $config['sensitive_records'] === 0) {
      $options['filterBoolClauses']['must'][] = [
        'query_type' => 'term',
        'field' => 'metadata.sensitive',
        'value' => 'false',
      ];
    }
    if (!empty($config['unverified_records']) && $config['unverified_records'] === 0) {
      $options['filterBoolClauses']['must'][] = [
        'query_type' => 'term',
        'field' => 'identification.verification_status',
        'value' => 'V',
      ];
    }
    if (!empty($config['limit_to_user'])) {
      $warehouseUserId = $this->getWarehouseUserId();
      if (empty($warehouseUserId)) {
        // Not linked to the warehouse so force report to be blank.
        $warehouseUserId = -9999;
      }
      $options['filterBoolClauses'] = ['must' => []];
      $options['filterBoolClauses']['must'][] = [
        'query_type' => 'term',
        'field' => 'metadata.created_by_id',
        'value' => $warehouseUserId,
      ];
      $recordersDiv = '';
    }
    else {
      $recordersDiv = '<div><div class="count recorders"><i class="fas fa-user-friends"></i></div></div>';
    }
    $r = \ElasticsearchReportHelper::source($options);
    $template = <<<HTML
<div id="indicia-es-totals-block-container">
  <div><div class="count occurrences"><i class="fas fa-map-marker-alt"></i></div></div>
  <div><div class="count species"><i class="fas fa-sitemap"></i></div></div>
  <div><div class="count photos"><i class="fas fa-camera"></i></div></div>
  $recordersDiv
</div>

HTML;
    $r .= \ElasticsearchReportHelper::customScript([
      'id' => 'indicia-es-totals-block-' . self::$blockCount,
      'source' => 'src-IndiciaEsTotalsBlock-' . self::$blockCount,
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
