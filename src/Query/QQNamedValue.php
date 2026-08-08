<?php

namespace Cog\Query;

class QQNamedValue extends QQNode {

	public const DELIMITER_CODE = 3;

	public function __construct($name) {
		$this->name = $name;
	}

	/**
	 * @param boolean|null $equalityType
	 * @return string
	 */
	public function parameter($equalityType = null): string {
		if ($equalityType === null) {
			return \chr(self::DELIMITER_CODE) . '{' . $this->name . '}';
		}

		if ($equalityType === true) {
			return \chr(self::DELIMITER_CODE) . '{=' . $this->name . '=}';
		}

		if ($equalityType === false) {
			return \chr(self::DELIMITER_CODE) . '{!' . $this->name . '!}';
		}

		return '';
	}
}
