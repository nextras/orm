<?php

/**
 * @dataProvider ../../../databases.ini
 */

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Tests\DatabaseTestCase;
use Tester\Assert;
use Tester\Environment;

$dic = require_once __DIR__ . '/../../../bootstrap.php';
Environment::lock('integration', TEMP_DIR);


/**
 * @testCase
 */
class UpdateEntityTest extends DatabaseTestCase
{

	public function testUpdate()
	{
		$author = $this->orm->authors->getById(1);
		$author->name = 'Test Testcase';

		Assert::true($author->isPersisted());
		Assert::true($author->isModified());
		Assert::same(1, $author->id);

		$this->orm->authors->persistAndFlush($author);

		Assert::true($author->isPersisted());
		Assert::false($author->isModified());
		Assert::same(1, $author->id);

		$author = $this->orm->authors->findBy(['id' => 1])->fetch();
		Assert::same('Test Testcase', $author->name);
		Assert::same(1, $author->id);
	}

}


$test = new UpdateEntityTest($dic);
$test->run();
