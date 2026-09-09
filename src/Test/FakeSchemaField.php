<?php declare(strict_types=1);

namespace Cog\Test;

use Cog\Database\FieldBase;

/**
 * A column description for FakeSchemaAdapter. FieldBase is a read-only property
 * bag filled in by the real adapters from a result set; this fills it in from
 * constructor arguments instead.
 */
class FakeSchemaField extends FieldBase {

	public function __construct(
		string $name,
		string $type,
		bool $primaryKey = false,
		bool $notNull = false,
		bool $unique = false,
		bool $identity = false,
		bool $timestamp = false,
		?string $default = null,
		?int $maxLength = null,
		string $table = ''
	) {
		$this->name = $name;
		$this->originalName = $name;
		$this->table = $table;
		$this->originalTable = $table;
		$this->type = $type;
		$this->primaryKey = $primaryKey;
		$this->notNull = $notNull;
		$this->unique = $unique;
		$this->identity = $identity;
		$this->timestamp = $timestamp;
		$this->default = $default;
		$this->maxLength = $maxLength;
	}
}
