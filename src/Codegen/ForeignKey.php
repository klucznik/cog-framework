<?php

namespace Cog\Codegen;

use Cog;
use Cog\Exceptions\CogException;

/**
 * 	@property-read string $keyName
 * 	@property-read string[] $columnNameArray
 * 	@property-read string $referenceTableName
 * 	@property-read string[] $referenceColumnNameArray
 */
class ForeignKey extends Cog\Base {

	protected string $keyName;
	/** @var string[] */
	protected array $columnNameArray;
	protected string $referenceTableName;
	/** @var string[] */
	protected array $referenceColumnNameArray;

	public function __construct(string $keyName, array $columnNameArray, string $referenceTableName, array $referenceColumnNameArray) {
		$this->keyName = $keyName;
		$this->columnNameArray = $columnNameArray;
		$this->referenceTableName = $referenceTableName;
		$this->referenceColumnNameArray = $referenceColumnNameArray;
	}

	public function __get($name) {
		switch ($name) {
			case 'keyName':
				return $this->keyName;
			case 'columnNameArray':
				return $this->columnNameArray;
			case 'referenceTableName':
				return $this->referenceTableName;
			case 'referenceColumnNameArray':
				return $this->referenceColumnNameArray;
			default:
				try {
					return parent::__get($name);
				} catch (CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
		}
	}
}
