<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Repository;

use Mockery;
use Nextras\Orm\Collection\ICollection;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RepositoryMagicMethodsTest extends DataTestCase
{
	public function testDefinedProxyMethods()
	{
		$books = $this->orm->books->findBooksWithEvenId();
		Assert::type(ICollection::class, $books);
		Assert::same(2, $books->count());
	}
}


$test = new RepositoryMagicMethodsTest($dic);
$test->run();
