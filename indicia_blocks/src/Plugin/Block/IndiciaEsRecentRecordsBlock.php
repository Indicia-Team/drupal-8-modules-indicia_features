<?php

namespace Drupal\indicia_blocks\Plugin\Block;

use Drupal\Core\Render\Markup;
use Drupal\Core\Form\FormStateInterface;

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
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    // Retrieve existing configuration for this block.
    $config = $this->getConfiguration();

    // Add a form field to the existing block configuration form.
    $form['sensitive_records'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include sensitive records'),
      '#description' => $this->t('Unless this box is ticked, sensitive records are completely excluded.'),
      '#default_value' => isset($config['sensitive_records']) ? $config['sensitive_records'] : 0,
    ];

    // Add a form field to the existing block configuration form.
    $form['unverified_records'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include unverified records'),
      '#description' => $this->t('Unless this box is ticked, unverified (pending) records are completely excluded.'),
      '#default_value' => isset($config['unverified_records']) ? $config['unverified_records'] : 0,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    // Save our custom settings when the form is submitted.
    $this->setConfigurationValue('sensitive_records', $form_state->getValue('sensitive_records'));
    $this->setConfigurationValue('unverified_records', $form_state->getValue('unverified_records'));
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    iform_load_helpers(['ElasticsearchReportHelper']);
    \ElasticsearchReportHelper::enableElasticsearchProxy();
    $config = $this->getConfiguration();
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
      'sort' => ['id' => 'desc'],
    ];
    $options['filterBoolClauses'] = ['must' => []];
    // Apply user profile preferences.
    if ($location || $groups) {
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
    // Other record filters.
    if (empty($config['sensitive_records'])) {
      $options['filterBoolClauses']['must'][] = [
        'query_type' => 'term',
        'field' => 'metadata.sensitive',
        'value' => 'false',
      ];
    }
    // Other record filters.
    if (empty($config['unverified_records'])) {
      $options['filterBoolClauses']['must'][] = [
        'query_type' => 'term',
        'field' => 'identification.verification_status',
        'value' => 'V',
      ];
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
