<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Relationships;


use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipsOneHasManyHasTest extends DataTestCase
{

	public function testHasValue(): void
	{
		$author = $this->orm->authors->getByIdChecked(1);
		Assert::false($author->books->has(10));
		Assert::true($author->books->has(1));

		$book = $this->orm->books->getByIdChecked(1);
		Assert::true($author->books->has($book));
		$this->orm->books->remove($book);
		Assert::false($author->books->has($book));
	}

}


$test = new RelationshipsOneHasManyHasTest($dic);
$test->run();
