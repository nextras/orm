<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Entity;


use NextrasTests\Orm\Author;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityHasValueTest extends DataTestCase
{

	public function testHasValue(): void
	{
		$author = $this->orm->authors->getByIdChecked(1);
		Assert::true($author->hasValue('name'));
		Assert::true($author->hasValue('age'));

		$author = new Author();
		Assert::false($author->hasValue('name'));
		Assert::true($author->hasValue('web'));
		Assert::true($author->hasValue('age'));
	}

}


$test = new EntityHasValueTest($dic);
$test->run();
