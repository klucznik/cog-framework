<?php

namespace Cog\Test;

use App\Data\Asset;
use App\Data\Obj;
use App\Data\Person;
use Cog\Query\QQ;
use Cog\Query\QQExpandVirtualNode;
use Generated\Node\QQNodeAsset;
use Generated\Node\QQNodeObj;
use Generated\Node\QQNodePerson;

/**
 * The clause objects that sit beside the conditions covered by TestQuery:
 * aggregates, having, exists, ordering and the virtual-node machinery that
 * carries a computed column back onto the loaded object.
 *
 * Fixture data these assertions lean on: obj 1 owns assets of 20480 and 512000
 * bytes, obj 2 owns none; obj 1 has two tags and obj 2 has one.
 */
class TestQueryClauses extends QueryTestCase {

	private function obj(): QQNodeObj {
		return new QQNodeObj();
	}

	private function asset(): QQNodeAsset {
		return new QQNodeAsset();
	}

	private function person(): QQNodePerson {
		return new QQNodePerson();
	}

	//////////////////////////////
	// Aggregation clauses
	//////////////////////////////

	public function testCount() {
		$obj = $this->obj();

		$objs = Obj::queryArray(QQ::all(), QQ::clause(
			QQ::expand($obj->asset),
			QQ::groupBy($obj->id),
			QQ::count($obj->asset->id, 'asset_count')
		));

		$this->assertQueryContains('COUNT(');
		$this->assertQueryContains('COUNT(`t1`.`id`) AS `__asset_count`');
		$this->assertSame('2', $objs[0]->getVirtualAttribute('asset_count'));
		$this->assertSame('0', $objs[1]->getVirtualAttribute('asset_count'));
	}

	public function testSum() {
		$obj = $this->obj();

		$objs = Obj::queryArray(QQ::equal($obj->id, 1), QQ::clause(
			QQ::expand($obj->asset),
			QQ::groupBy($obj->id),
			QQ::sum($obj->asset->size, 'total_size')
		));

		$this->assertQueryContains('SUM(');
		$this->assertSame('532480', $objs[0]->getVirtualAttribute('total_size'));
	}

	public function testMinimum() {
		$obj = $this->obj();

		$objs = Obj::queryArray(QQ::equal($obj->id, 1), QQ::clause(
			QQ::expand($obj->asset),
			QQ::groupBy($obj->id),
			QQ::minimum($obj->asset->size, 'smallest')
		));

		$this->assertQueryContains('MIN(');
		$this->assertSame('20480', $objs[0]->getVirtualAttribute('smallest'));
	}

	public function testMaximum() {
		$obj = $this->obj();

		$objs = Obj::queryArray(QQ::equal($obj->id, 1), QQ::clause(
			QQ::expand($obj->asset),
			QQ::groupBy($obj->id),
			QQ::maximum($obj->asset->size, 'largest')
		));

		$this->assertQueryContains('MAX(');
		$this->assertSame('512000', $objs[0]->getVirtualAttribute('largest'));
	}

	public function testAverage() {
		$obj = $this->obj();

		$objs = Obj::queryArray(QQ::equal($obj->id, 1), QQ::clause(
			QQ::expand($obj->asset),
			QQ::groupBy($obj->id),
			QQ::average($obj->asset->size, 'mean_size')
		));

		$this->assertQueryContains('AVG(');
		$this->assertSame(266240.0, (float)$objs[0]->getVirtualAttribute('mean_size'));
	}

	//////////////////////////////
	// Ordering clauses
	//////////////////////////////

	//////////////////////////////
	// Sub queries
	//////////////////////////////

	public function testExists() {
		$obj = $this->obj();

		$objs = Obj::queryArray(QQ::exists(
			QQ::subSql('SELECT 1 FROM `asset` WHERE `asset`.`obj_id` = {1}', $obj->id)
		));

		$this->assertQueryContains('EXISTS (');
		$this->assertSame(['First object'], self::pluck($objs, 'label'));
	}

	public function testNotExists() {
		$obj = $this->obj();

		$objs = Obj::queryArray(QQ::notExists(
			QQ::subSql('SELECT 1 FROM `asset` WHERE `asset`.`obj_id` = {1}', $obj->id)
		));

		$this->assertQueryContains('NOT EXISTS (');
		$this->assertSame(['Second object'], self::pluck($objs, 'label'));
	}

	/** A sub-select expanded onto the object under a name of its own. */
	public function testVirtualNodeFromSubQuery() {
		$obj = $this->obj();

		$objs = Obj::queryArray(QQ::equal($obj->id, 1), QQ::clause(
			QQ::expand(QQ::virtual(
				'asset_total',
				QQ::subSql('SELECT SUM(`size`) FROM `asset` WHERE `asset`.`obj_id` = {1}', $obj->id)
			))
		));

		$this->assertQueryContains('AS `__asset_total`');
		$this->assertSame('532480', $objs[0]->getVirtualAttribute('asset_total'));
	}

	/** Once defined, a virtual node can be referred to again by name alone. */
	public function testVirtualNodeReferencedByName() {
		$obj = $this->obj();

		$objs = Obj::queryArray(QQ::all(), QQ::clause(
			QQ::expand(QQ::virtual(
				'asset_total',
				QQ::subSql('SELECT COALESCE(SUM(`size`), 0) FROM `asset` WHERE `asset`.`obj_id` = {1}', $obj->id)
			)),
			QQ::orderBy(QQ::virtual('asset_total'))
		));

		$this->assertQueryContains('ORDER BY');
		$this->assertSame(['Second object', 'First object'], array_map(
			static fn(Obj $item) => $item->label,
			$objs
		));
	}

	public function testHaving() {
		$obj = $this->obj();

		$objs = Obj::queryArray(QQ::all(), QQ::clause(
			QQ::expand($obj->asset),
			QQ::groupBy($obj->id),
			QQ::count($obj->asset->id, 'asset_count'),
			QQ::having(QQ::subSql('COUNT({1}) > 1', $obj->asset->id))
		));

		// the builder emits the keyword capitalised as "Having"
		$this->assertQueryContains('Having (COUNT(`t1`.`id`) > 1)');
		$this->assertSame(['First object'], self::pluck($objs, 'label'));
	}

	//////////////////////////////
	// Aggregation clause guards
	//////////////////////////////

	public function testAggregateRejectsATableNode() {
		$this->expectException(\Cog\Exceptions\InvalidCastException::class);

		QQ::count($this->obj(), 'nope');
	}

	public function testAggregateRejectsAnAssociationNode() {
		$this->expectException(\Cog\Exceptions\CogException::class);

		QQ::count($this->obj()->tag, 'nope');
	}

	public function testAggregateRejectsANonNode() {
		$this->expectException(\TypeError::class);

		QQ::count('not a node', 'nope');
	}

	/** The count comes back as a string from the driver, like any other column. */
	public function testVirtualAttributeIsNullWhenNotSelected() {
		$assets = Asset::queryArray(QQ::equal($this->asset()->id, 1));

		$this->assertNull($assets[0]->getVirtualAttribute('asset_count'));
	}

	//////////////////////////////
	// Clause identity
	//////////////////////////////

	/**
	 * Every clause names itself, which is what turns up in the exception the
	 * builder throws when one is used somewhere it does not belong.
	 */
	public function testClausesDescribeThemselves() {
		$size = $this->obj()->asset->size;

		$this->assertSame('Cog\Query\QQCount Clause', (string)QQ::count($size, 'a'));
		$this->assertSame('Cog\Query\QQSum Clause', (string)QQ::sum($size, 'a'));
		$this->assertSame('Cog\Query\QQAverage Clause', (string)QQ::average($size, 'a'));
		$this->assertSame('Cog\Query\QQMinimum Clause', (string)QQ::minimum($size, 'a'));
		$this->assertSame('Cog\Query\QQMaximum Clause', (string)QQ::maximum($size, 'a'));
		$this->assertSame('Having Clause', (string)QQ::having(QQ::subSql('1')));
		$this->assertSame(
			'Cog\Query\QQExpandVirtualNode Clause',
			(string)new QQExpandVirtualNode(QQ::virtual('v', QQ::subSql('1')))
		);
	}

	/** A having clause built without a name reports an empty one. */
	public function testHavingClauseAttributeNameDefaultsToEmpty() {
		$this->assertSame('', QQ::having(QQ::subSql('1'))->getAttributeName());
	}

	public function testOrderByPersonNameStillWorksAlongsideClauses() {
		$people = Person::queryArray(QQ::all(), QQ::clause(QQ::orderBy($this->person()->name)));

		$this->assertSame(['Adam Kluczyk', 'Maria Nowak', 'Piotr Lewandowski'], array_map(
			static fn(Person $item) => $item->name,
			$people
		));
	}
}
