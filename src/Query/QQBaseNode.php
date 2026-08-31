<?php

namespace Cog\Query;

use Cog;
use Cog\Type;

/**
 * The abstract Cog\Query\QQBaseNode class
 *
 * @property-read QQBaseNode $parentNode
 * @property-read string $name
 * @property string $alias
 * @property-read string $propertyName
 * @property-read string $type
 * @property-read string $rootTableName
 * @property-read string $tableName
 * @property-read string $primaryKey
 * @property-read string $className
 * @property-read string $classNameQualified
 * @property-read QQBaseNode $primaryKeyNode
 * @property bool $expandAsArray true if this node should be array expanded.
 * @property-read bool $isType Is a type table node. For association type arrays.
 * @property QQBaseNode[] childNodeArray
 */
abstract class QQBaseNode extends Cog\Base {

	/** @var QQBaseNode */
	protected $parentNode;
	/** @var string */
	protected $type;
	/** @var string */
	protected $name;
	/** @var string */
	protected $alias;
	/** @var string */
	protected $propertyName;
	/** @var string */
	protected $rootTableName;

	/** @var string */
	protected $tableName;
	/** @var string */
	protected $primaryKey;
	/** @var string */
	protected $className = '';
	protected $classNameQualified = '';

	/** @var boolean used by expansion nodes */
	protected $expandAsArray;

	/**
	 * Child nodes, keyed by name, as they are read off this one. Defaults to an
	 * empty array rather than null: mergeExpansionNode() guards on count(), which
	 * a null would fatal on instead of taking the guard.
	 *
	 * @var QQBaseNode[]
	 */
	protected array $childNodeArray = [];
	/** @var boolean */
	protected $isType;

	/**
	 * @param string $name
	 * @return mixed
	 * @throws \Cog\Exceptions\CogException
	 */
	public function __get($name) {
		switch ($name) {
			case 'parentNode':
				return $this->parentNode;
			case 'name':
				return $this->name;
			case 'alias':
				return $this->alias;
			case 'propertyName':
				return $this->propertyName;
			case 'propertyNameUppercase':
				return ucfirst($this->propertyName);
			case 'type':
				return $this->type;
			case 'rootTableName':
				return $this->rootTableName;
			case 'tableName':
				return $this->tableName;
			case 'primaryKey':
				return $this->primaryKey;
			case 'className':
				return $this->className;
			case 'classNameQualified':
				return $this->classNameQualified;
			case 'primaryKeyNode':
				return null;

			case 'expandAsArray':
				return $this->expandAsArray;
			case 'isType':
				return $this->isType;

			case 'childNodeArray':
				return $this->childNodeArray;

			default:
				try {
					return parent::__get($name);
				} catch (Cog\Exceptions\CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
		}
	}

	public function __set($name, $value) {
		switch ($name) {
			case 'alias':
				/**
				 * Sets the value for strAlias
				 * @param string $value
				 * @return string
				 */
				try {
					return ($this->alias = Type::cast($value, Type::STRING));
				} catch (Cog\Exceptions\CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}

			case 'expandAsArray':
				try {
					return ($this->expandAsArray = Type::cast($value, Type::BOOLEAN));
				} catch (Cog\Exceptions\CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}

			default:
				try {
					return parent::__set($name, $value);
				} catch (Cog\Exceptions\CogException $exception) {
					$exception->incrementOffset();
					throw $exception;
				}
		}
	}

	/**
	 * @param QueryBuilder $queryBuilder
	 * @param boolean $expandSelection
	 * @param QQSelect|null $select
	 * @return mixed
	 */
	abstract public function getColumnAliasHelper(QueryBuilder $queryBuilder, bool $expandSelection, ?QQSelect $select = null);

	/**
	 * @param QueryBuilder $queryBuilder
	 * @param bool $expandSelection
	 * @param QQCondition|null $joinCondition
	 * @param QQSelect|null $select
	 * @return mixed
	 */
	abstract public function getColumnAlias(QueryBuilder $queryBuilder, bool $expandSelection = false, ?QQCondition $joinCondition = null, ?QQSelect $select = null);

	/**
	 * Merges a node tree into this node, building the child nodes. The node being received
	 * is assumed to be specially built node such that only one child node exists, if any,
	 * and the last node in the chain is designated as array expansion. The goal of all of this
	 * is to set up a node chain where intermediate nodes can be designated as being array
	 * expansion nodes, as well as the leaf nodes.
	 *
	 * @param QQBaseNode $newNode
	 * @throws \Cog\Exceptions\CogException
	 */
	public function mergeExpansionNode(QQBaseNode $newNode) {
		if (!$newNode || 0 === \count($newNode->childNodeArray)) {
			return;
		}

		if ($newNode->name !== $this->name) {
			throw new Cog\Exceptions\CogException('Expansion node tables must match.');
		}

		if (!$this->childNodeArray) {
			$this->childNodeArray = $newNode->childNodeArray;
		} else {
			$childNode = reset($newNode->childNodeArray);
			if (array_key_exists($childNode->name, $this->childNodeArray)) {
				if ($childNode->expandAsArray) {
					$this->childNodeArray[$childNode->name]->expandAsArray = true;
					// assume this is a leaf node, so don't follow any more.
				} else {
					$this->childNodeArray[$childNode->name]->mergeExpansionNode($childNode);
				}
			} else {
				$this->childNodeArray[$childNode->name] = $childNode;
			}
		}
	}

	/**
	 * The node's own name.
	 *
	 * Generated table nodes resolve properties through __get(), so a table with
	 * a column called `name` shadows this node's own name with a child QQNode.
	 * Reading $node->name from outside the class then yields an object, which is
	 * why the "not a column" error messages call this instead.
	 *
	 * @return string
	 */
	public function getNodeName(): string {
		return (string)$this->name;
	}

	public function extendedAlias() {
		$extendedAlias = $this->alias;
		$node = $this;

		while ($node->parentNode) {
			$node = $node->parentNode;
			$extendedAlias = $node->alias . '__' . $extendedAlias;
		}
		return $extendedAlias;
	}

	public function firstChild() {
		$array = $this->childNodeArray;
		if ($array) {
			return reset($array);
		}
		return null;
	}
}
