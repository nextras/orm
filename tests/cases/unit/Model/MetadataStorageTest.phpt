<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Model;

use Mockery;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\Repository\IdentityMap;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class MetadataStorageTest extends TestCase
{
	protected function setUp()
	{
		// do not call default setup which instantiate ORM model
	}


	public function testExceptions()
	{
		Assert::throws(function() {
			MetadataStorage::get('NextrasTests\Orm\Book');
		}, 'Nextras\Orm\InvalidStateException');

		parent::setUp();

		$metadata = MetadataStorage::get('NextrasTests\Orm\Book');
		Assert::type('Nextras\Orm\Entity\Reflection\EntityMetadata', $metadata);

		Assert::throws(function() {
			MetadataStorage::get('NextrasTests\Orm\InvalidEntityName');
		}, 'Nextras\Orm\InvalidArgumentException');
	}

}


$test = new MetadataStorageTest($dic);
$test->run();
