<?php

namespace Drupal\entity_usage;

/**
 * Entity usage interface with bulk loading capabilities.
 */
interface EntityUsageBulkInterface extends EntityUsageInterface {

  /**
   * Enables bulk inserting.
   *
   * @param string|null $table_name
   *   (Optional) If passed in, will use this table as a temporary table to
   *   store usage data while doing the bulk inserts. This is useful when doing
   *   long-lasting batch processing of a lot of entities, and new entity
   *   relationships could be created during that process. By using a different
   *   table, we can more easily merge the data from the bulk insert with the
   *   data that has been created in the meantime.
   *   Defaults to NULL, meaning the main {entity_usage} table will be used.
   *
   * @return $this
   */
  public function enableBulkInsert(?string $table_name = NULL): static;

  /**
   * Determines if the entity usage service is doing a bulk insert.
   *
   * @return bool
   *   TRUE if in bulk mode, FALSE if not.
   */
  public function isBulkInserting(): bool;

  /**
   * Performs the bulk insert.
   *
   * Once the bulk insert is performed entity usage must be set in non-bulk mode
   * to ensure regular operation.
   *
   * @return $this
   */
  public function bulkInsert(): static;

  /**
   * Truncates the table.
   *
   * @return $this
   */
  public function truncateTable(): static;

}
