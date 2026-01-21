<?php

namespace Drupal\indicia_blocks\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;

/**
 * Provides a 'Species catalogue' block.
 *
 * @Block(
 *   id = "species_catalogue_block",
 *   admin_label = @Translation("Species catalogue"),
 * )
 */
class IndiciaSpeciesCatalogue extends IndiciaBlockBase {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    // Taxonomic order control.
    $form['order'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Taxonomic order'),
      '#description' => $this->t('Enter the taxonomic order to catalogue (e.g., Araneae).'),
      '#default_value' => $config['order'] ?? '',
      '#required' => TRUE,
    ];

    // Taxon list ID control.
    $form['taxon_list_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Taxon list id'),
      '#description' => $this->t('Enter the taxon list id.'),
      '#default_value' => $config['taxon_list_id'] ?? '',
      '#required' => TRUE,
    ];

    // Optional: species details page paths.
    $form['species_details_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Species details page path'),
      '#description' => $this->t('Path to the species details page (used for link icons).'),
      '#default_value' => $config['species_details_path'] ?? '',
    ];
    $form['species_details_within_group_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Species details within group page path'),
      '#description' => $this->t('Path to use when showing a single group only.'),
      '#default_value' => $config['species_details_within_group_path'] ?? '',
    ];

    $form['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of species'),
      '#description' => $this->t('Limit the report to this number of species.'),
      '#default_value' => $config['limit'] ?? 300,
      '#min' => 1,
      '#max' => 20000,
      '#step' => 1,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);

    // Save configuration values.
    $this->setConfigurationValue('order', trim((string) $form_state->getValue('order')));
    $this->setConfigurationValue('taxon_list_id', trim((string) $form_state->getValue('taxon_list_id')));
    $this->setConfigurationValue('species_details_path', (string) $form_state->getValue('species_details_path'));
    $this->setConfigurationValue('species_details_within_group_path', (string) $form_state->getValue('species_details_within_group_path'));
    $this->setConfigurationValue('limit', (int) $form_state->getValue('limit'));
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    try {
      $config = $this->getConfiguration();
      $order_name = $config['order'] ?? '';
      $taxon_list_id = $config['taxon_list_id'] ?? '';

      if ($order_name === '' || $taxon_list_id === '') {
        return [
          '#markup' => $this->t('Missing configuration for order or taxon list.'),
          '#cache' => ['max-age' => 0],
        ];
      }

      $root = $this->generateIndex((int) $taxon_list_id, (string) $order_name);
      if ($root === NULL) {
        return [
          '#markup' => $this->t('No results found for the configured order.'),
          '#cache' => ['max-age' => 0],
        ];
      }

      $root = $this->buildTaxonTree($root);
      $children = isset($root['children']) && is_array($root['children']) ? $root['children'] : [];
      $html = $this->renderTaxonTreeHtml($children);
     
      return [
        '#markup' => Markup::create($html),
        '#cache' => ['max-age' => 0],
      ];
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($e->getMessage());
      return [
        '#markup' => $this->t('Failed to load order listing.'),
        '#cache' => ['max-age' => 0],
      ];
    }
  }

  /**
   * Sends an HTTP request to the Indicia endpoint and returns the body.
   *
   * Uses Drupal's HTTP client (Guzzle) and includes Indicia auth headers.
   *
   * @param string $url
   *   The endpoint path, relative to Indicia base URL.
   * @param array|string $params
   *   Query parameters for GET or JSON body for POST.
   * @param string $method
   *   HTTP method, 'GET' or 'POST'.
   *
   * @return string
   *   The raw response body.
   *
   * @throws \InvalidArgumentException
   *   Thrown if an unsupported HTTP method is provided.
   */
  private function fetchData(string $url, array|string $params = [], string $method = 'GET'): string {
    $connection = \iform_get_connection_details();
    if (empty($connection['website_id']) || empty($connection['password']) || empty($connection['base_url'])) {
      $this->messenger()->addWarning($this->t('Indicia configuration incomplete.'));
      return '';
    }

    $full_url = $connection['base_url'] . $url;
    $headers = self::getHttpRequestHeaders('application/json');

    $client = \Drupal::httpClient();
    $options = [
      'headers' => $headers,
    ];

    $upper_method = strtoupper($method);
    if ($upper_method === 'GET') {
      $options['query'] = is_array($params) ? $params : [];
    }
    elseif ($upper_method === 'POST') {
      $options['body'] = is_string($params) ? $params : json_encode($params);
    }
    else {
      throw new \InvalidArgumentException(sprintf('Unsupported HTTP method: %s', $method));
    }

    $response = $client->request($upper_method, $full_url, $options);
    return (string) $response->getBody();
  }

  /**
   * Builds the HTTP request headers for Indicia API calls.
   *
   * @param string $contentType
   *   The Content-Type header value (default: application/json).
   *
   * @return array
   *   An associative array of headers.
   */
  private static function getHttpRequestHeaders(string $contentType = 'application/json'): array {
    $conn = \iform_get_connection_details();
    $tokens = [
      'WEBSITE_ID',
      $conn['website_id'] ?? '',
      'SECRET',
      $conn['password'] ?? '',
      'SCOPE',
      'data_flow',
    ];

    return [
      'Content-Type' => $contentType,
      'Authorization' => implode(':', $tokens),
    ];
  }

  // ---------------------------------------------------------------------------
  // Generic helper methods.
  // ---------------------------------------------------------------------------

  /**
   * Resolve the taxa_taxon_list ID for a preferred scientific name on a list.
   *
   * Uses REST /taxa/search to find the TTL id for the preferred name.
   * If the matched row is a synonym (preferred === 'f'), callers may need to
   * resolve the preferred ID; this method returns the preferred row only.
   *
   * @param int $taxonListId
   *   Species list ID (e.g., 15 or 52).
   * @param string $preferredTaxonName
   *   Exact preferred scientific name (e.g., 'Araneae').
   * @param string|null $expectedRank
   *   Optional rank filter (e.g., 'order'). Case-insensitive.
   *
   * @return array|null
   *   An associative array with keys: name, group_id, id, rank, children; or NULL.
   */
  private function generateIndex(int $taxonListId, string $preferredTaxonName, ?string $expectedRank = NULL): ?array {
    // Normalize inputs.
    $preferredTaxonName = trim($preferredTaxonName);
    $expectedRankNorm = $expectedRank !== NULL ? strtolower(trim($expectedRank)) : NULL;

    // Exact preferred-name search.
    $paramsExact = [
      'taxon_list_id' => $taxonListId,
      'preferred_taxon' => $preferredTaxonName,
      'taxon_rank' => $expectedRank, // e.g., order, genus, family, species
      'preferred' => 't',
      'language' => 'lat',
      'limit' => 1,
    ];

    $rawExact = $this->fetchData('/index.php/services/rest/taxa/search', $paramsExact, 'GET');
    $rowsExact = $this->decodeRows($rawExact);

    if (empty($rowsExact)) {
      return NULL;
    }

    $groupId = isset($rowsExact[0]['taxon_group_id']) && is_numeric($rowsExact[0]['taxon_group_id'])
      ? (int) $rowsExact[0]['taxon_group_id']
      : NULL;
    $id = isset($rowsExact[0]['taxa_taxon_list_id']) && is_numeric($rowsExact[0]['taxa_taxon_list_id'])
      ? (int) $rowsExact[0]['taxa_taxon_list_id']
      : NULL;

    $result = [];
    $result['name'] = $rowsExact[0]['preferred_taxon'] ?? '';
    $result['group_id'] = $groupId;
    $result['id'] = $id;
    $result['rank'] = $rowsExact[0]['taxon_rank'] ?? '';
    $result['children'] = $this->fetchSpecies($taxonListId, (string) $groupId);

    return $result;
  }

  /**
   * Decode a JSON response body into an array of rows under key 'data'.
   *
   * @param mixed $raw
   *   Raw JSON string to decode.
   *
   * @return array
   *   The decoded rows (empty array on failure).
   */
  private function decodeRows($raw): array {
    if (!is_string($raw) || $raw === '') {
      return [];
    }
    $data = json_decode($raw, TRUE);
    if ($data === NULL && json_last_error() !== JSON_ERROR_NONE) {
      // Could log json_last_error_msg() if required.
      return [];
    }
    if (!is_array($data)) {
      return [];
    }
    $rows = $data['data'] ?? [];
    return is_array($rows) ? $rows : [];
  }

  /**
   * Fetch preferred children of a parent taxon (hierarchical API).
   *
   * @param int $taxonListId
   *   The species list id.
   * @param string $id
   *   Parent taxa_taxon_list_id.
   *
   * @return array
   *   Child rows as associative arrays.
   */
  private function fetchChildren(int $taxonListId, string $id): array {
    $params = [
      'taxon_list_id' => $taxonListId,
      'preferred' => 't',
      'language' => 'lat',
      'parent_id' => $id,
      'limit' => 10,
    ];
    $raw = $this->fetchData('/index.php/services/rest/taxa/search', $params, 'GET');
    $rows = $this->decodeRows($raw);

    $result = [];
    foreach ($rows as $row) {
      $groupId = isset($row['taxon_group_id']) && is_numeric($row['taxon_group_id']) ? (int) $row['taxon_group_id'] : NULL;
      $childId = isset($row['taxa_taxon_list_id']) && is_numeric($row['taxa_taxon_list_id']) ? (int) $row['taxa_taxon_list_id'] : NULL;
      $r = [
        'name' => $row['preferred_taxon'] ?? '',
        'group_id' => $groupId,
        'id' => $childId,
        'rank' => $row['taxon_rank'] ?? '',
        'children' => $this->fetchChildren($taxonListId, (string) $childId),
      ];
      $result[] = $r;
    }

    return $result;
  }

  /**
   * Fetch all preferred species in a given taxon group.
   *
   * @param int $taxonListId
   *   The species list id.
   * @param string $groupId
   *   Taxon group id.
   *
   * @return array
   *   Species rows as associative arrays.
   */
  private function fetchSpecies(int $taxonListId, string $groupId): array {
    $params = [
      'taxon_list_id' => $taxonListId,
      'preferred' => 't',
      'language' => 'lat',
      'taxon_group_id' => $groupId,
      'limit' => 10000,
    ];
    $raw = $this->fetchData('/index.php/services/rest/taxa/search', $params, 'GET');
    $rows = $this->decodeRows($raw);

    $result = [];
    foreach ($rows as $row) {
      $groupIdVal = isset($row['taxon_group_id']) && is_numeric($row['taxon_group_id']) ? (int) $row['taxon_group_id'] : NULL;
      $id = isset($row['taxa_taxon_list_id']) && is_numeric($row['taxa_taxon_list_id']) ? (int) $row['taxa_taxon_list_id'] : NULL;
      $parentId = isset($row['parent_id']) && is_numeric($row['parent_id']) ? (int) $row['parent_id'] : NULL;
      $r = [
        'name' => $row['preferred_taxon'] ?? '',
        'group_id' => $groupIdVal,
        'id' => $id,
        'parentId' => $parentId,
        'rank' => $row['taxon_rank'] ?? '',
      ];
      $result[] = $r;
    }

    return $result;
  }

  /**
   * Build a nested hierarchy from a root that contains a flat 'children' list.
   *
   * Each child row should include:
   * - 'id' (int|string)
   * - 'parentId' (int|string|null)
   * - 'name', 'rank', etc. (optional)
   *
   * @param array $root
   *   Root node containing a 'children' key with a flat list.
   *
   * @return array
   *   The root with nested 'children'.
   */
  private function buildTaxonTree(array $root): array {
    $rows = isset($root['children']) && is_array($root['children']) ? $root['children'] : [];

    // Normalize rows: ensure 'id' exists and each has a 'children' array.
    foreach ($rows as $i => $row) {
      if (!is_array($row) || !array_key_exists('id', $row)) {
        unset($rows[$i]);
        continue;
      }
      if (!isset($row['children']) || !is_array($row['children'])) {
        $rows[$i]['children'] = [];
      }
    }

    // Index by id (using references).
    $index = [];
    foreach ($rows as $k => &$row) {
      $id = $row['id'];
      if (isset($index[$id])) {
        // Duplicate id: skip subsequent entries to avoid clobbering.
        continue;
      }
      $index[$id] = &$row;
    }
    unset($row);

    // Attach nodes under their parent if parent exists; otherwise keep at top-level.
    $topLevel = [];
    foreach ($rows as &$node) {
      $pid = $node['parentId'] ?? NULL;
      if ($pid !== NULL && $pid !== $node['id'] && isset($index[$pid])) {
        $index[$pid]['children'][] = &$node;
      }
      else {
        $topLevel[] = &$node;
      }
    }
    unset($node);

    // Optional: sort siblings by rank then name.
    $rankOrder = [
      'Order' => 10,
      'Family' => 20,
      'Genus' => 30,
      'Species' => 40,
      'Subspecies' => 50,
    ];
    $sortTree = function (&$nodes) use (&$sortTree, $rankOrder) {
      usort($nodes, function ($a, $b) use ($rankOrder) {
        $ra = $rankOrder[$a['rank'] ?? ''] ?? PHP_INT_MAX;
        $rb = $rankOrder[$b['rank'] ?? ''] ?? PHP_INT_MAX;
        if ($ra !== $rb) {
          return $ra <=> $rb;
        }
        return strcmp($a['name'] ?? '', $b['name'] ?? '');
      });
      foreach ($nodes as &$child) {
        if (!empty($child['children'])) {
          $sortTree($child['children']);
        }
      }
      unset($child);
    };
    $sortTree($topLevel);

    // Replace root children with the nested structure.
    $root['children'] = $topLevel;
    return $root;
  }

  /**
   * Slugify a species name for the URL.
   *
   * @param string $name
   *   The species name.
   *
   * @return string
   *   URL-encoded slug.
   */
  private function slugifySpeciesName(string $name): string {
    $slug = str_replace(' ', '+', trim($name));
    return rawurlencode($slug);
  }

  /**
   * Recursively render nodes to an HTML <ul> list.
   *
   * @param array<int, array<string, mixed>> $nodes
   *   List of nodes (name, rank, children ...).
   *
   * @return string
   *   HTML markup.
   */
  private function renderTaxonTreeHtml(array $nodes): string {
    if (empty($nodes)) {
      return '';
    }

$html = "\n<ul>\n";

foreach ($nodes as $node) {
    $name = Html::escape((string) ($node['name'] ?? ''));
    $rank = strtolower((string) ($node['rank'] ?? ''));
    $children = (!empty($node['children']) && is_array($node['children']))
        ? $node['children']
        : [];

    $li_class = $rank ? ' class="rank-' . Html::escape($rank) . '"' : '';
    $html .= "\n    <li" . $li_class . ">";

    if ($rank === 'species') {
        $raw_name = (string) ($node['name'] ?? '');
        $name_escaped = Html::escape($raw_name);
        $slug = urlencode($raw_name);
        $url = '/species?species_name=' . $slug;

        $html .= '<a title="' . $name_escaped . '" href="' . Html::escape($url) . '">'
              . $name_escaped
              . '</a>';
    }
    else {
        $html .= $name;
    }

    if (!empty($children)) {
        $html .= $this->renderTaxonTreeHtml($children);
    }

    $html .= "</li>\n";
}

$html .= "</ul>";


    return $html;


  }

}
