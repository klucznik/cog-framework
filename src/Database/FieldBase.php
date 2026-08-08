<?php

namespace Cog\Database;

use Cog;
use Cog\Exceptions\CogException;

/**
 * @property-read string $name field name
 * @property-read string $originalName original field name
 * @property-read string $table table name the fields belong to
 * @property-read string $originalTable original table name the fields belong to
 * @property-read string $default default field value
 * @property-read integer|null $maxLength maximum length of the field
 * @property-read bool $identity
 * @property-read bool $notNull
 * @property-read bool $primaryKey
 * @property-read bool $unique
 * @property-read bool $timestamp
 * @property-read string $type
 * @property-read string|null $comment
 */

abstract class FieldBase extends Cog\Base {

	protected string $name;
	protected string $originalName;
	protected string $table;
	protected string $originalTable;
	protected ?string $default = null;
	protected ?int $maxLength = null;
	protected string $comment = '';

	protected bool $identity = false;
	protected bool $notNull = false;
	protected bool $primaryKey = false;
	protected bool $unique = false;
	protected bool $timestamp = false;

	protected string $type;

	public function __get($name) {
		switch ($name) {
			case 'name':
				return $this->name;
			case 'originalName':
				return $this->originalName;
			case 'table':
				return $this->table;
			case 'originalTable':
				return $this->originalTable;
			case 'default':
				return $this->default;
			case 'maxLength':
				return $this->maxLength;
			case 'identity':
				return $this->identity;
			case 'notNull':
				return $this->notNull;
			case 'primaryKey':
				return $this->primaryKey;
			case 'unique':
				return $this->unique;
			case 'timestamp':
				return $this->timestamp;
			case 'type':
				return $this->type;
			case 'comment':
				return $this->comment;
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
