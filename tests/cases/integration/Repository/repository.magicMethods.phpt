<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Repository;


use Nextras\Orm\Collection\ICollection;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class RepositoryMagicMethodsTest extends DataTestCase
{
	public function testDefinedProxyMethods(): void
	{
		$books = $this->orm->books->findBooksWithEvenId();
		Assert::type(ICollection::class, $books);
		Assert::same(2, $books->count());
	}
}


$test = new RepositoryMagicMethodsTest();
$test->run();
