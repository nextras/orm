<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Repository;


use Mockery;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Repository\IdentityMap;
use Nextras\Orm\Repository\IRepository;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class IdentityMapTest extends TestCase
{

	public function testCheck(): void
	{
		$repository = Mockery::mock(IRepository::class);
		$repository->shouldReceive('getEntityClassNames')->andReturn([Author::class]);

		$map = new IdentityMap($repository);
		$map->check($this->e(Author::class));

		Assert::throws(function () use ($map): void {
			$map->check($this->e(Book::class));
		}, InvalidArgumentException::class, "Entity 'NextrasTests\\Orm\\Book' is not accepted by 'Mockery_0_Nextras_Orm_Repository_IRepository' repository.");
	}

}


$test = new IdentityMapTest();
$test->run();
