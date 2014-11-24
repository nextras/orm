<?php

/**
 * @testCase
 */

namespace Nextras\Orm\Tests\Entity\PropertyContianers;

use Mockery;
use Nette\Utils\DateTime;
use Nextras\Orm\Entity\PropertyContainers\DateTimePropertyContainer;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../../bootstrap.php';


class DateTimePropertyContainerTest extends TestCase
{

	public function testNotNullable()
	{
		$entity = Mockery::mock('Nextras\Orm\Entity\IEntity');
		$metadata = Mockery::mock('Nextras\Orm\Entity\Reflection\PropertyMetadata');
		$metadata->isNullable = FALSE;
		$metadata->name = 'when';

		$container = new DateTimePropertyContainer($entity, $metadata, '2013-01-01 20:00:00');
		Assert::equal(new DateTime('2013-01-01 20:00:00'), $container->getInjectedValue());

		$container->setInjectedValue('now');
		Assert::type('Nette\Utils\DateTime', $container->getInjectedValue());

		Assert::throws(function () use ($container) {
			$container->setInjectedValue(NULL);
		}, 'Nextras\Orm\NullValueException');

		Assert::throws(function() use ($entity, $metadata) {
			new DateTimePropertyContainer($entity, $metadata, NULL);
		}, 'Nextras\Orm\NullValueException');
	}


	public function testNullable()
	{
		$entity = Mockery::mock('Nextras\Orm\Entity\IEntity');
		$metadata = Mockery::mock('Nextras\Orm\Entity\Reflection\PropertyMetadata');
		$metadata->isNullable = TRUE;
		$metadata->name = 'when';

		$container = new DateTimePropertyContainer($entity, $metadata, '2013-01-01 20:00:00');
		Assert::equal(new DateTime('2013-01-01 20:00:00'), $container->getInjectedValue());

		$container->setInjectedValue(NULL);
		Assert::null($container->getInjectedValue());

		$container = new DateTimePropertyContainer($entity, $metadata, NULL);
		Assert::null($container->getInjectedValue());
	}

}


$test = new DateTimePropertyContainerTest($dic);
$test->run();
