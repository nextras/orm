<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Collection;


use inc\model\book\GenreEnum;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class CollectionEnumOnEntitiesTest extends DataTestCase
{
	public function testEntityEnumType(): void
	{
		$collection = $this->orm->books->findBy([
			'genre' => [
				GenreEnum::HORROR,
				GenreEnum::THRILLER,
				GenreEnum::SCIFI,
				GenreEnum::FANTASY,
			],
		]);
		$collection = $collection->orderBy('id');
		Assert::same(3, $collection->countStored());

		foreach ($collection as $book) {
			Assert::type(GenreEnum::class, $book->genre);
		}
	}

}


$test = new CollectionEnumOnEntitiesTest();
$test->run();
