<?php

namespace Drupal\Core\Database;

use Drupal\Core\Database\Statement\FetchAs;

/**
 * Provide helper methods for statement fetching.
 */
trait FetchModeTrait {

  /**
   * Map FETCH_* modes to their literal for inclusion in messages.
   *
   * @see https://github.com/php/php-src/blob/master/ext/pdo/php_pdo_driver.h#L65-L80
   */
  protected array $fetchModeLiterals = [
    \PDO::FETCH_DEFAULT => 'FETCH_DEFAULT',
    \PDO::FETCH_LAZY => 'FETCH_LAZY',
    \PDO::FETCH_ASSOC => 'FETCH_ASSOC',
    \PDO::FETCH_NUM => 'FETCH_NUM',
    \PDO::FETCH_BOTH => 'FETCH_BOTH',
    \PDO::FETCH_OBJ => 'FETCH_OBJ',
    \PDO::FETCH_BOUND => 'FETCH_BOUND',
    \PDO::FETCH_COLUMN => 'FETCH_COLUMN',
    \PDO::FETCH_CLASS => 'FETCH_CLASS',
    \PDO::FETCH_INTO => 'FETCH_INTO',
    \PDO::FETCH_FUNC => 'FETCH_FUNC',
    \PDO::FETCH_NAMED => 'FETCH_NAMED',
    \PDO::FETCH_KEY_PAIR => 'FETCH_KEY_PAIR',
    \PDO::FETCH_CLASS | \PDO::FETCH_CLASSTYPE => 'FETCH_CLASS | FETCH_CLASSTYPE',
    \PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE => 'FETCH_CLASS | FETCH_PROPS_LATE',
  ];

  /**
   * The fetch modes supported.
   */
  protected array $supportedFetchModes = [
    \PDO::FETCH_ASSOC,
    \PDO::FETCH_CLASS,
    \PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE,
    \PDO::FETCH_COLUMN,
    \PDO::FETCH_NUM,
    \PDO::FETCH_OBJ,
  ];

  /**
   * Converts a row of data in FETCH_ASSOC format to FETCH_NUM.
   *
   * @param array $rowAssoc
   *   A row of data in FETCH_ASSOC format.
   *
   * @return array
   *   The row in FETCH_NUM format.
   */
  protected function assocToNum(array $rowAssoc): array {
    return array_values($rowAssoc);
  }

  /**
   * Converts a row of data in FETCH_ASSOC format to FETCH_OBJ.
   *
   * @param array $rowAssoc
   *   A row of data in FETCH_ASSOC format.
   *
   * @return object
   *   The row in FETCH_OBJ format.
   */
  protected function assocToObj(array $rowAssoc): \stdClass {
    return (object) $rowAssoc;
  }

  /**
   * Converts a row of data in FETCH_ASSOC format to FETCH_CLASS.
   *
   * @param array $rowAssoc
   *   A row of data in FETCH_ASSOC format.
   * @param string $className
   *   Name of the created class.
   * @param array $constructorArguments
   *   Elements of this array are passed to the constructor.
   *
   * @return object
   *   The row in FETCH_CLASS format.
   */
  protected function assocToClass(array $rowAssoc, string $className, array $constructorArguments): object {
    $classObj = new $className(...$constructorArguments);
    foreach ($rowAssoc as $column => $value) {
      $classObj->$column = $value;
    }
    return $classObj;
  }

  /**
   * Converts a row of data in FETCH_ASSOC format to FETCH_COLUMN.
   *
   * @param array $rowAssoc
   *   A row of data in FETCH_ASSOC format.
   * @param string[] $columnNames
   *   The list of the row columns.
   * @param int $columnIndex
   *   The index of the column to fetch the value of.
   *
   * @return string
   *   The value of the column.
   *
   * @throws \ValueError
   *   If the column index is not defined.
   */
  protected function assocToColumn(array $rowAssoc, array $columnNames, int $columnIndex): mixed {
    if (!isset($columnNames[$columnIndex])) {
      throw new \ValueError('Invalid column index');
    }
    return $rowAssoc[$columnNames[$columnIndex]];
  }

  /**
   * Converts a row of data in associative format to a specified format.
   *
   * @param array $rowAssoc
   *   A row of data in FetchAs::Associative format.
   * @param \Drupal\Core\Database\Statement\FetchAs $mode
   *   The target target mode.
   * @param array $fetchOptions
   *   The fetch mode options.
   *
   * @return array<scalar|null>|object|scalar|null|false
   *   The data in the target mode.
   *
   * @throws \ValueError
   *   If the column index is not defined.
   */
  protected function assocToFetchMode(array $rowAssoc, FetchAs $mode, array $fetchOptions): array|object|int|float|string|bool|NULL {
    return match($mode) {
      FetchAs::Associative => $rowAssoc,
      FetchAs::ClassObject => $this->assocToClass($rowAssoc, $fetchOptions['class'], $fetchOptions['constructor_args']),
      FetchAs::Column => $this->assocToColumn($rowAssoc, array_keys($rowAssoc), $fetchOptions['column']),
      FetchAs::List => $this->assocToNum($rowAssoc),
      FetchAs::Object => $this->assocToObj($rowAssoc),
    };
  }

}
