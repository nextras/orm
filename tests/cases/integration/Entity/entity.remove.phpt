<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Entity;


use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


class EntityRemoveTest extends DataTestCase
{

	public function testRemove(): void
	{
		$book = $this->e(Book::class);
		$this->orm->books->persistAndFlush($book);

		$book = $this->orm->books->getByIdChecked(1);
		Assert::same(1, $book->id);

		$this->orm->books->removeAndFlush($book);
		Assert::null($this->orm->books->findBy(['id' => $book->getPersistedId()])->fetch());
	}

}


$test = new EntityRemoveTest();
$test->run();

