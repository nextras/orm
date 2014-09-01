<?php

namespace Nextras\Orm\Tests\Entity\Collection;

use Mockery;
use Nextras\Orm\Entity\Collection\EntityContainer;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../../bootstrap.php';


/**
 * @testCase
 */
class EntityContainerTest extends TestCase
{

	public function testBasic()
	{
		$data = [10 => Mockery::mock(), Mockery::mock(), Mockery::mock()];
		$data[10]->shouldReceive('getRawValue')->with('id')->andReturn(123);
		$data[11]->shouldReceive('getRawValue')->with('id')->andReturn(321);
		$data[12]->shouldReceive('getRawValue')->with('id')->andReturn(456);

		$container = new EntityContainer($data);

		$data[10]->shouldReceive('setPreloadContainer')->once()->with($container);
		$data[11]->shouldReceive('setPreloadContainer')->once()->with($container);
		$data[12]->shouldReceive('setPreloadContainer')->once()->with($container);

		Assert::same($data[10], $container->getEntity(10));
		Assert::same($data[11], $container->getEntity(11));
		Assert::same($data[12], $container->getEntity(12));
		Assert::null($container->getEntity(13));

		Assert::same([123, 321, 456], $container->getPreloadValues('id'));
	}

}


$test = new EntityContainerTest($dic);
$test->run();
