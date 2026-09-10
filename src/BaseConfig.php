<?php declare(strict_types=1);

namespace Cog;

use Cog\Enum\Environment;
use Cog\Enum\Runtime;
use ReflectionObject;

class BaseConfig {
	public function __construct(
		public readonly Environment $environment = Environment::DEV,
		public bool $debug = true, // freely writable
		public readonly bool $cache = false, // set once at construction

		public readonly string $dirDocRoot = '',
		public readonly string $dirAppRoot = '',
		public readonly string $dirPublic = '',
		public readonly string $dirCache = '',
		public readonly string $dirTemplates = '',

		public readonly Runtime $runtime = Runtime::WEB,
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
