<?php

namespace Nextras\Orm\Tests\Repository;

use Mockery;
use Nextras\Orm\Repository\IdentityMap;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


/**
 * @testCase
 */
class IdentityMapTest extends TestCase
{

	public function testCheck()
	{
		$repository = Mockery::mock('Nextras\Orm\Repository\IRepository');
		$repository->shouldReceive('getEntityClassNames')->andReturn(['Nextras\Orm\Tests\Author']);

		$map = new IdentityMap($repository);
		$map->check($this->e('Nextras\Orm\Tests\Author'));

		Assert::throws(function() use ($map) {
			$map->check($this->e('Nextras\Orm\Tests\Book'));
		}, 'Nextras\Orm\InvalidArgumentException', "Entity 'Nextras\\Orm\\Tests\\Book' is not accepted by 'Mockery_0_Nextras_Orm_Repository_IRepository' repository.");
	}

}


$test = new IdentityMapTest($dic);
$test->run();
