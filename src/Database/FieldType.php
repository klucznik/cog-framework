<?php declare(strict_types=1);

namespace Cog\Database;

abstract class FieldType {
	public const string BLOB = 'Blob';
	public const string VARCHAR = 'VarChar';
	public const string CHAR = 'Char';
	public const string INTEGER = 'Integer';
	public const string DATETIME = 'DateTime';
	public const string DATE = 'Date';
	public const string TIME = 'Time';
	public const string FLOAT = 'Float';
	public const string BIT = 'Bit';
	public const string TIMESTAMP = 'Timestamp';
}
