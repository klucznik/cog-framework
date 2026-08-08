<?php declare(strict_types=1);

namespace Cog\Database;

abstract class FieldType {
	public const BLOB = 'Blob';
	public const VARCHAR = 'VarChar';
	public const CHAR = 'Char';
	public const INTEGER = 'Integer';
	public const DATETIME = 'DateTime';
	public const DATE = 'Date';
	public const TIME = 'Time';
	public const FLOAT = 'Float';
	public const BIT = 'Bit';
	public const TIMESTAMP = 'Timestamp';
}
