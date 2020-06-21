<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Entity;


use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class UpdateEntityTest extends DataTestCase
{

	public function testUpdate(): void
	{
		$author = $this->orm->authors->getByIdChecked(1);
		$author->name = 'Test Testcase';

		Assert::true($author->isPersisted());
		Assert::true($author->isModified());
		Assert::same(1, $author->id);

		$this->orm->authors->persistAndFlush($author);

		Assert::true($author->isPersisted());
		Assert::false($author->isModified());
		Assert::same(1, $author->id);

		$author = $this->orm->authors->getByIdChecked(1);
		Assert::same('Test Testcase', $author->name);
		Assert::same(1, $author->id);
	}

}


$test = new UpdateEntityTest($dic);
$test->run();
