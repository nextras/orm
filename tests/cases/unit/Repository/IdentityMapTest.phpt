<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Repository;

use Mockery;
use Nextras\Orm\Repository\IdentityMap;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class IdentityMapTest extends TestCase
{

	public function testCheck()
	{
		$repository = Mockery::mock('Nextras\Orm\Repository\IRepository');
		$repository->shouldReceive('getEntityClassNames')->andReturn(['NextrasTests\Orm\Author']);

		$map = new IdentityMap($repository);
		$map->check($this->e('NextrasTests\Orm\Author'));

		Assert::throws(function() use ($map) {
			$map->check($this->e('NextrasTests\Orm\Book'));
		}, 'Nextras\Orm\InvalidArgumentException', "Entity 'NextrasTests\\Orm\\Book' is not accepted by 'Mockery_0_Nextras_Orm_Repository_IRepository' repository.");
	}

}


$test = new IdentityMapTest($dic);
$test->run();
