<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Collection;

use DateTimeImmutable;
use Nextras\Orm\Collection\ICollection;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Ean;
use NextrasTests\Orm\EanType;
use NextrasTests\Orm\Helper;
use Tester\Assert;
use Tester\Environment;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class CollectionWhereTest extends DataTestCase
{
	public function testFindByAndOr()
	{
		$all = $this->orm->authors->findBy([
			ICollection::OR,
			'name' => 'Writer 1',
			'web' => 'http://example.com/2',
		])->fetchAll();
		Assert::count(2, $all);


		$all = $this->orm->authors->findBy([
			ICollection::OR, // operator is irrelevant
			'name' => ['Writer 1', 'Writer 2'],
		])->fetchAll();
		Assert::count(2, $all);


		$all = $this->orm->books->findBy([
			ICollection::AND,
			'author' => 1,
			'nextPart' => null,
		])->fetchAll();
		Assert::count(2, $all);


		$all = $this->orm->books->findBy([
			ICollection::AND,
			'author' => 1,
			'nextPart' => null,
			'translator' => null,
		])->fetchAll();
		Assert::count(1, $all);


		$all = $this->orm->tags->findBy([
			ICollection::OR,
			[ICollection::AND, 'name' => 'Tag 1', 'isGlobal' => true], // match
			[ICollection::AND, 'name' => 'Tag 2', 'isGlobal' => false], // no-match
			[ICollection::AND, 'name' => 'Tag 3', 'isGlobal' => false], // match
		])->fetchAll();
		Assert::count(2, $all);


		$all = $this->orm->books->findBy([
			ICollection::AND, // match 2
			[ICollection::OR, 'title' => 'Book 1', 'author' => 1], // match 1, 2
			[ICollection::OR, 'translator' => null, 'nextPart' => 3], // match 2, 4
		])->fetchAll();
		Assert::count(1, $all);
	}


	public function testFindByAndOrOldSyntax()
	{
		$all = $this->orm->books->findBy([
			'author' => 1,
			'nextPart' => null,
		])->fetchAll();
		Assert::count(2, $all);


		$all = $this->orm->books->findBy([
			'author' => 1,
			'nextPart' => null,
			'translator' => null,
		])->fetchAll();
		Assert::count(1, $all);


		$all = $this->orm->tags->findBy([
			ICollection::OR,
			['name' => 'Tag 1', 'isGlobal' => true], // match
			['name' => 'Tag 2', 'isGlobal' => false], // no-match
			['name' => 'Tag 3', 'isGlobal' => false], // match
		])->fetchAll();
		Assert::count(2, $all);
	}


	public function testFilterByPropertyContainer()
	{
		$ean8 = new Ean(EanType::EAN8());
		$ean8->code = '123';
		$ean8->book = $this->orm->books->getById(1);
		$this->orm->persist($ean8);

		$ean13 = new Ean(EanType::EAN13());
		$ean13->code = '456';
		$ean13->book = $this->orm->books->getById(2);
		$this->orm->persistAndFlush($ean13);

		Assert::count(2, $this->orm->eans->findAll());

		$eans = $this->orm->eans->findBy(['type' => EanType::EAN8()]);
		Assert::count(1, $eans);
		Assert::equal('123', $eans->fetch()->code);

		$eans = $this->orm->eans->findBy(['type' => EanType::EAN13()]);
		Assert::count(1, $eans);
		Assert::equal('456', $eans->fetch()->code);
	}


	public function testFilterByDateTime()
	{
		if ($this->section === Helper::SECTION_MSSQL) {
			Environment::skip('MSSQL does not handle timzones as we need. Maybe we should investiage more this test.');
		}

		$followers = $this->orm->tagFollowers->findBy([
			'createdAt' => [
				new DateTimeImmutable('2014-01-01T01:10:00'),
				$this->moveToDifferentZone(new DateTimeImmutable('2014-01-02T01:10:00')),
			]
		]);

		Assert::equal(2, $followers->countStored());
		Assert::equal(2, $followers->count());
	}


	private function moveToDifferentZone(DateTimeImmutable $dateTime): DateTimeImmutable
	{
		return $dateTime->setTimezone(new \DateTimeZone("UTC"));
	}
}


$test = new CollectionWhereTest($dic);
$test->run();
