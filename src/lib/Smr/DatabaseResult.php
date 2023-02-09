<?php declare(strict_types=1);

namespace Smr;

use Exception;
use Generator;
use mysqli_result;
use RuntimeException;

/**
 * Holds the result of a Database query (e.g. read or write).
 */
class DatabaseResult {

	public function __construct(
		private readonly mysqli_result $dbResult
	) {}

	/**
	 * Use to iterate over the records from the result set.
	 * @return \Generator<DatabaseRecord>
	 */
	public function records(): Generator {
		foreach ($this->dbResult as $dbRecord) {
			yield new DatabaseRecord($dbRecord);
		}
	}

	/**
	 * Use when exactly one record is expected from the result set.
	 */
	public function record(): DatabaseRecord {
		if ($this->getNumRecords() != 1) {
			throw new RuntimeException('One record required, but found ' . $this->getNumRecords());
		}
		$record = $this->dbResult->fetch_assoc();
		if ($record === null) {
			throw new Exception('Do not call record twice on the same result');
		}
		return new DatabaseRecord($record);
	}

	public function getNumRecords(): int {
		$numRows = $this->dbResult->num_rows;
		if (is_string($numRows)) {
			throw new Exception('Number of rows is too large to represent as an int: ' . $numRows);
		}
		return $numRows;
	}

	public function hasRecord(): bool {
		return $this->getNumRecords() > 0;
	}

}
