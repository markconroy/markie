<?php
// phpcs:ignoreFile -- This is intended to match the file its extending as close
// as possible, and therefore avoids coding standards changes from source.

namespace Drupal\test_ai_vdb_provider_mysql;

use Drupal\Component\Serialization\Json;
use Drupal\search_api\Query\QueryInterface;
use MHz\MysqlVector\VectorTable;

/**
 * Subclass of VectorTable that integrates with our Drupal mapping.
 */
class TestVectorTable extends VectorTable {

  /**
   * The constructor now matches the parent, ensuring the object is built correctly.
   */
  public function __construct(\mysqli $mysqli, string $name, int $dimension = 384, string $engine = 'InnoDB') {
    // We call the parent constructor to initialize this object's properties.
    parent::__construct($mysqli, $name, $dimension, $engine);
  }

  /**
   * Performs an accurate, brute-force vector search.
   *
   * @param array $vector
   *   The vector to search for.
   * @param int $n
   *   The number of results to return.
   * @param \Drupal\search_api\Query\QueryInterface|null $query
   *   (Optional) The Search API query object, used here for excluded IDs.
   *
   * @return array
   *   The search results.
   */
  public function search(array $vector, int $n = 10, ?QueryInterface $query = NULL): array {

    // Since normalize() is a private method on the parent VectorTable class,
    // we use reflection to call it on the current object instance ($this).
    $reflectionClass = new \ReflectionClass(VectorTable::class);
    $normalizeMethod = $reflectionClass->getMethod('normalize');
    $normalizeMethod->setAccessible(true);
    $normalizedVector = $normalizeMethod->invoke($this, $vector);

    // We can now use this object's properties and methods directly.
    $mysqli = $this->getConnection();
    $tableName = $this->getVectorTableName();
    $mapTableName = str_replace('_vectors', '_map', $tableName);

    // Handle excluded entity IDs from the query object.
    $exclusion_clause = '';
    $excluded_entity_ids = [];
    if ($query) {
      $excluded_entity_ids = $query->getOption('search_api_ai_excluded_entity_ids', []);
      if (!empty($excluded_entity_ids)) {
        // Create placeholders and a type string for binding.
        $placeholders = implode(',', array_fill(0, count($excluded_entity_ids), '?'));
        $exclusion_clause = "AND _map.drupal_entity_id NOT IN ($placeholders)";
      }
    }

    // More accurate single-step query.
    $sql = "
      SELECT
        id, vector, normalized_vector, magnitude, similarity, drupal_entity_id, drupal_long_id
      FROM (
        SELECT
          _vec.id, _vec.vector, _vec.normalized_vector, _vec.magnitude,
          _map.drupal_entity_id, _map.drupal_long_id,
          COSIM(_vec.normalized_vector, ?) AS similarity,
          ROW_NUMBER() OVER(PARTITION BY _map.drupal_entity_id ORDER BY COSIM(_vec.normalized_vector, ?) DESC) as rn
        FROM $tableName AS _vec
        INNER JOIN $mapTableName AS _map ON _vec.id = _map.vector_table_id
        WHERE 1=1 {$exclusion_clause}
      ) AS ranked_results
      WHERE rn = 1
      ORDER BY similarity DESC
      LIMIT ?
    ";

    $statement = $mysqli->prepare($sql);
    if (!$statement) {
      throw new \Exception("Prepare failed for search query: " . $mysqli->error);
    }

    $jsonEncodedNormalizedVector = json_encode($normalizedVector);

    // Build the types and params for binding.
    $types = 'ss';
    $params = [$jsonEncodedNormalizedVector, $jsonEncodedNormalizedVector];
    if (!empty($excluded_entity_ids)) {
      $types .= str_repeat('s', count($excluded_entity_ids));
      $params = array_merge($params, $excluded_entity_ids);
    }
    $types .= 'i';
    $params[] = $n;

    $statement->bind_param($types, ...$params);
    $statement->execute();
    $statement->bind_result($id, $v, $nv, $mag, $sim, $drupal_entity_id, $drupal_long_id);

    $results = [];
    while ($statement->fetch()) {
      $results[] = [
        'id' => $id,
        'vector' => json_decode($v, TRUE),
        'normalized_vector' => json_decode($nv, TRUE),
        'magnitude' => $mag,
        'similarity' => $sim,
        'distance' => $sim,
        'drupal_entity_id' => $drupal_entity_id,
        'drupal_long_id' => $drupal_long_id,
      ];
    }
    $statement->close();
    return $results;
  }

}