<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Mapper;


use DateTimeImmutable;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class MapperDateTimeSimpleTest extends DataTestCase
{
	public function testToCollection(): void
	{
		$author = $this->e(
			Author::class,
			[
				'name' => 'Random Author',
				'born' => new DateTimeImmutable('2018-01-09 00:00:00'),
			]
		);
		$this->orm->persistAndFlush($author);
		$id = $author->id;

		$this->orm->clear();
		$author2 = $this->orm->authors->getByIdChecked($id);
		Assert::notNull($author2->born);
		Assert::equal('2018-01-09', $author2->born->format('Y-m-d'));
	}
}


$test = new MapperDateTimeSimpleTest();
$test->run();
