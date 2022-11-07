<?php

namespace Drupal\indicia_blocks\Plugin\Block;

use Drupal\Core\Render\Markup;
use Drupal\Core\Form\FormStateInterface;

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

    // Retrieve existing configuration for this block.
    $config = $this->getConfiguration();

    $form['limit_to_user'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Limit to current user's records"),
      '#description' => $this->t('If ticked, only records for the current user are shown.'),
      '#default_value' => isset($config['limit_to_user']) ? $config['limit_to_user'] : 0,
    ];

    $form['cache_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache timeout'),
      '#description' => $this->t('Minimum number of seconds that the data request will be cached for, resulting in faster loads times.'),
      '#default_value' => isset($config['cache_timeout']) ? $config['cache_timeout'] : 300,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    // Save our custom settings when the form is submitted.
    $this->setConfigurationValue('limit_to_user', $form_state->getValue('limit_to_user'));
    $this->setConfigurationValue('cache_timeout', $form_state->getValue('cache_timeout'));
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    iform_load_helpers(['ElasticsearchReportHelper']);
    $config = $this->getConfiguration();
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
    $options = [
      'id' => 'src-IndiciaEsTotalsBlock',
      'size' => 0,
      'proxyCacheTimeout' => isset($config['cache_timeout']) ? $config['cache_timeout'] : 300,
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
