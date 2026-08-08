<?php

namespace Cog;

use Cog\Enum\Environment;
use ReflectionObject;

class BaseConfig {
	public function __construct(
		public readonly Environment $environment = Environment::DEV,
		public bool $debug = true, // freely writable
		public readonly bool $cache = false, // set once at construction

		public string $dirCache = '',
		public string $dirTemplates = '',
	) {}

	public function dump(): array {
		$toReturn = [];

		foreach ((new ReflectionObject($this))->getProperties() as $prop) {
			$toReturn[$prop->getName()] = $prop->getValue($this);
		}

		return $toReturn;
	}

	public function __debugInfo(): array {
		return $this->dump();
	}
}
