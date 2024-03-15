<?php

namespace Drupal\indicia_blocks\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;

/**
 * Provides a 'Elasticsearch records by taxon group pie' block.
 *
 * @Block(
 *   id = "es_records_by_taxon_group_pie_block",
 *   admin_label = @Translation("Elasticsearch records by taxon group pie"),
 * )
 */
class IndiciaEsRecordsByTaxonGroupPie extends IndiciaBlockBase {

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
    $enabled = \ElasticsearchReportHelper::enableElasticsearchProxy();
    if (!$enabled) {
      global $indicia_templates;
      return [
        '#markup' => str_replace('{message}', $this->t('Service unavailable.'), $indicia_templates['warningBox']),
      ];
    }
    $config = $this->getConfiguration();
    $r = \ElasticsearchReportHelper::source([
      'id' => 'recordsByTaxonGroupPieSource',
      'size' => 0,
      'proxyCacheTimeout' => $config['cache_timeout'] ?? 300,
      'aggregation' => [
        'by_group' => [
          'terms' => [
            'field' => 'taxon.group.keyword',
            'size' => 8,
          ],
        ],
      ],
      'filterBoolClauses' => ['must' => $this->getFilterBoolClauses($config)],
    ]);
    $r .= \ElasticsearchReportHelper::customScript([
      'source' => 'recordsByTaxonGroupPieSource',
      'functionName' => 'handleRecordsByTaxonGroupPieResponse',
    ]);
    $r .= '<div id="records-by-taxon-groups-pie" class="indicia-block-visualisation"></div>';

    return [
      '#markup' => Markup::create($r),
      '#attached' => [
        'library' => [
          'indicia_blocks/es-blocks',
          'iform/brc_charts',
        ],
      ],
      '#cache' => [
        // No cache please.
        'max-age' => 0,
      ],
    ];
  }

}
