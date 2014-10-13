<?php

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Tests\DatabaseTestCase;
use Nextras\Orm\Tests\Author;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class NewEntityTest extends DatabaseTestCase
{

	public function testInsert()
	{
		$author = new Author();
		$author->name = 'Jon Snow';
		$author->web = 'http://nextras.cz';

		Assert::false($author->isPersisted());
		Assert::true($author->isModified());
		Assert::null($author->id);

		$this->orm->authors->persistAndFlush($author);

		Assert::true($author->isPersisted());
		Assert::false($author->isModified());
		Assert::same(3, $author->id);
	}


	public function testInsertWithPrimaryKey()
	{
		$author = new Author();
		$author->id = 555;
		$author->name = 'Jon Snow';
		$author->web = 'http://nextras.cz';

		Assert::false($author->isPersisted());
		Assert::true($author->isModified());
		Assert::same(555, $author->id);

		$this->orm->authors->persistAndFlush($author);

		$author = $this->orm->authors->findBy(['id' => 555])->fetch();
		Assert::true($author->isPersisted());
		Assert::false($author->isModified());
		Assert::same(555, $author->id);
	}

}


$test = new NewEntityTest($dic);
$test->run();
