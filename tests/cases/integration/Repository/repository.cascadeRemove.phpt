<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Repository;

use Mockery;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;
use Tester\Environment;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RepostiroyCascadeRemoveTest extends DataTestCase
{
	public function testBasicCascadeRemove()
	{
		$author = $this->orm->authors->getById(2);

		$bookDiff = new Book();
		$bookDiff->author = $this->orm->authors->getById(1);
		$bookDiff->translator = $author;
		$bookDiff->publisher = 1;
		$bookDiff->title = 'Book 4';

		$this->orm->books->persistAndFlush($bookDiff);

		$bookSame = $this->orm->books->getById(3);

		$this->orm->authors->removeAndFlush($author, TRUE);

		Assert::true($bookDiff->isPersisted());
		Assert::null($bookDiff->translator);
		Assert::notEqual(NULL, $bookDiff->author);

		Assert::false($bookSame->isPersisted());
	}
}


$test = new RepostiroyCascadeRemoveTest($dic);
$test->run();
