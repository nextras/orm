<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Entity;

use Mockery;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Author;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityHasValueTest extends DataTestCase
{

	public function testHasValue()
	{
		$author = $this->orm->authors->getById(1);
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
