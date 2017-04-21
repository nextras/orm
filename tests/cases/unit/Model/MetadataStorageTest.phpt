<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Model;

use Mockery;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\Repository\IdentityMap;
use NextrasTests\Orm\Book;
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
		Assert::throws(function () {
			MetadataStorage::get(Book::class);
		}, InvalidStateException::class);

		parent::setUp();

		$metadata = MetadataStorage::get(Book::class);
		Assert::type(EntityMetadata::class, $metadata);

		Assert::throws(function () {
			MetadataStorage::get('NextrasTests\Orm\InvalidEntityName');
		}, InvalidArgumentException::class);
	}

}


$test = new MetadataStorageTest($dic);
$test->run();
