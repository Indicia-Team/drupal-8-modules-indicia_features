<?php

namespace Drupal\indicia_blocks\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;

/**
 * Provides a 'Recent Elasticsearch photos' block.
 *
 * @Block(
 *   id = "es_recent_photos",
 *   admin_label = @Translation("Recent Elasticsearch photos block"),
 * )
 */
class IndiciaEsRecentPhotosBlock extends IndiciaBlockBase {

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
      'id' => 'es-photos',
      'proxyCacheTimeout' => 1800,
      'filterBoolClauses' => [
        'must' => array_merge(
          $this->getFilterBoolClauses($config),
          [
            [
              'nested' => 'occurrence.media',
              'query_type' => 'exists',
              'field' => 'occurrence.media',
            ],
          ]
        ),
      ],
      'size' => 6,
      'sort' => ['metadata.created_on' => 'desc'],
    ]);
    $r .= \ElasticsearchReportHelper::cardGallery([
      'id' => 'photo-cards',
      'source' => 'es-photos',
      'columns' => [
        [
          'field' => '#taxon_label#',
        ],
      ],
      'includeFullScreenTool' => FALSE,
    ]);
    return [
      '#markup' => Markup::create($r),
      '#attached' => [
        'library' => [
          'naturespot_blocks/es-blocks',
        ],
      ],
      '#cache' => [
        // No cache please.
        'max-age' => 0,
      ],
    ];
  }

}
