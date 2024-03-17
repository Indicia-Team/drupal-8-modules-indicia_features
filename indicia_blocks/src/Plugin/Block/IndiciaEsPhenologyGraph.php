<?php

namespace Drupal\indicia_blocks\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;

/**
 * Provides an 'Elasticsearch phenology graph' block.
 *
 * @Block(
 *   id = "es_phenology_graph_block",
 *   admin_label = @Translation("Elasticsearch phenology graph block"),
 * )
 */
class IndiciaEsPhenologyGraph extends IndiciaBlockBase {

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
    iform_load_helpers(['ElasticsearchReportHelper']);
    $enabled = \ElasticsearchReportHelper::enableElasticsearchProxy();
    if (!$enabled) {
      global $indicia_templates;
      return [
        '#markup' => str_replace('{message}', $this->t('Service unavailable.'), $indicia_templates['warningBox']),
      ];
    }
    $config = $this->getConfiguration();
    $r = \ElasticsearchReportHelper::source([
      'id' => 'phenologyGraphSource',
      'size' => 0,
      'proxyCacheTimeout' => $config['cache_timeout'] ?? 300,
      'aggregation' => [
        'by_month' => [
          'terms' => [
            'field' => 'event.month',
            'size' => 12,
          ],
        ],
      ],
      'filterBoolClauses' => ['must' => $this->getFilterBoolClauses($config)],
    ]);
    $r .= \ElasticsearchReportHelper::customScript([
      'source' => 'phenologyGraphSource',
      'functionName' => 'handlePhenologyGraphResponse',
    ]);
    $r .= '<div id="phenology-graph" class="indicia-block-visualisation"></div>';

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
