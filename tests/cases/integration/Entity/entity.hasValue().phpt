<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Entity;


use Nextras\Orm\Entity\AbstractEntity;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Model\MetadataStorage;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Ean;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


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

		// avoid default ean constructor not to set the default type
		$ean = new class extends Ean {
			/** @noinspection PhpMissingParentConstructorInspection */
			// @phpstan-ignore-next-line
			public function __construct()
			{
				AbstractEntity::__construct();
			}


			protected function createMetadata(): EntityMetadata
			{
				return MetadataStorage::get(Ean::class);
			}
		};
		Assert::false($ean->hasValue('type'));
	}

}


$test = new EntityHasValueTest();
$test->run();
