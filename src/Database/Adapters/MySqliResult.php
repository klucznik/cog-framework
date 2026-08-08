<?php

namespace Cog\Database\Adapters;

use Cog;
use mysqli_result;

class MySqliResult extends Cog\Database\ResultBase {

	protected mysqli_result $mySqliResult;
	protected MySqliAdapter $database;

	public function __construct(mysqli_result $result, MySqliAdapter $database) {
		$this->mySqliResult = $result;
		$this->database = $database;
	}

	public function fetchArray(): ?array {
		return $this->mySqliResult->fetch_array();
	}

	public function fetchArrayAssoc(): ?array {
		return $this->mySqliResult->fetch_assoc();
	}

	/**
	 * @return MySqliField[]
	 */
	public function fetchFields(): array {
		$toReturn = [];

		while ($field = $this->fetchField()) {
			$toReturn[] = $field;
		}

		return $toReturn;
	}

	public function fetchField(): ?MySqliField {
		if ($field = $this->mySqliResult->fetch_field()) {
			return new MySqliField($field, $this->database);
		}

		return null;
	}

	public function fetchRow(): ?array {
		return $this->mySqliResult->fetch_row();
	}

	public function countRows(): int {
		return $this->mySqliResult->num_rows;
	}

	public function close(): void {
		$this->mySqliResult->free();
	}

	/**
	 * @return MySqliRow|null
	 */
	public function getNextRow(): ?object {
		$columnArray = $this->fetchArray();

		if ($columnArray) {
			return new MySqliRow($columnArray);
		}

		return null;
	}

	public function getRows(): array {
		$rows = [];

		while ($row = $this->getNextRow()) {
			$rows[] = $row;
		}

		return $rows;
	}
}
