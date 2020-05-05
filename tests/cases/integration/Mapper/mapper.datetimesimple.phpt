<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Mapper;


use DateTimeImmutable;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class MapperDateTimeSimpleTest extends DataTestCase
{
	public function testToCollection()
	{
		$author = $this->e(
			Author::class,
			[
				'born' => new DateTimeImmutable('2018-01-09 00:00:00'),
			]
		);
		$this->orm->persistAndFlush($author);
		$id = $author->id;

		$this->orm->clear();
		$author2 = $this->orm->authors->getById($id);
		Assert::equal('2018-01-09', $author2->born->format('Y-m-d'));
	}
}


$test = new MapperDateTimeSimpleTest($dic);
$test->run();
