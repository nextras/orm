<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Entity;


use Nextras\Orm\Entity\ToArrayConverter;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class EntityToArrayTest extends DataTestCase
{

	public function testSkipPropertiesParameter(): void
	{
		$author = $this->orm->authors->getByIdChecked(1);

		Assert::same(
			[
				'id',
				'name',
				'bornOn',
				'web',
				'favoriteAuthor',
				'favoredBy',
				'books',
				'translatedBooks',
				'tagFollowers',
				'age'
			],
			array_keys($author->toArray(ToArrayConverter::RELATIONSHIP_AS_ID, []))
		);

		Assert::same(
			[
				'id',
				'name',
				'bornOn',
				'favoriteAuthor',
				'favoredBy',
				'translatedBooks',
				'age'
			],
			array_keys($author->toArray(ToArrayConverter::RELATIONSHIP_AS_ID, [
				'web',
				'books',
				'tagFollowers'
			]))
		);

		Assert::same(
			[],
			array_keys($author->toArray(ToArrayConverter::RELATIONSHIP_AS_ID, [
				'id',
				'name',
				'bornOn',
				'web',
				'favoriteAuthor',
				'favoredBy',
				'books',
				'translatedBooks',
				'tagFollowers',
				'age'
			]))
		);
	}

}


$test = new EntityToArrayTest();
$test->run();
