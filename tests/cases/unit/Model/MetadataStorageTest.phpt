<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Model;


use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Exception\InvalidArgumentException;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Model\MetadataStorage;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class MetadataStorageTest extends TestCase
{
	protected function setUp()
	{
		// do not call default setup which instantiate ORM model
	}


	public function testExceptions(): void
	{
		Assert::throws(function (): void {
			MetadataStorage::get(Book::class);
		}, InvalidStateException::class);

		parent::setUp();

		$metadata = MetadataStorage::get(Book::class);
		Assert::type(EntityMetadata::class, $metadata);

		Assert::throws(function (): void {
			MetadataStorage::get('NextrasTests\Orm\InvalidEntityName');
		}, InvalidArgumentException::class);
	}

}


$test = new MetadataStorageTest();
$test->run();
