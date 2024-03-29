<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;


use DateTimeImmutable;
use Nextras\Dbal\Drivers\Exception\ForeignKeyConstraintViolationException;
use Nextras\Dbal\IConnection;
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use NextrasTests\Orm\Publisher;
use NextrasTests\Orm\User;
use NextrasTests\Orm\UserStat;
use Tester\Assert;
use Tester\Environment;


require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipsOneHasManyPersistenceTest extends DataTestCase
{
	public function testPersisting(): void
	{
		$publisher = $this->e(Publisher::class, ['name' => 'Publisher']);
		$author1 = $this->e(Author::class, ['name' => 'Persistence Author']);
		$this->e(Book::class, ['author' => $author1, 'title' => 'Book XX', 'publisher' => $publisher]);
		$author2 = $this->e(Author::class, ['name' => 'Persistence Author 2']);
		$this->e(Book::class, ['author' => $author2, 'title' => 'Book YY', 'publisher' => $publisher]);
		$this->orm->authors->persist($author1);
		$this->orm->authors->persist($author2);
		$this->orm->authors->flush();

		$books = [];
		$authors = $this->orm->authors->findAll();
		foreach ($authors as $author) {
			foreach ($author->books as $book) {
				$book->title .= '#';
				$books[] = $book;
				Assert::true($book->isModified());
			}
			$this->orm->authors->persist($author);
		}

		foreach ($books as $book) {
			Assert::false($book->isModified());
		}
	}


	public function testRepeatedPersisting(): void
	{
		$publisher = new Publisher();
		$publisher->name = 'Jupiter Mining Corporation';

		$author = new Author();
		$author->name = 'Arnold Judas Rimmer';

		$book = new Book();
		$book->title = 'Better Than Life';
		$book->publisher = $publisher;
		$book->author = $author;

		$this->orm->persistAndFlush($author);
		Assert::false($book->isModified());

		$book->title = 'Backwards';
		$this->orm->persistAndFlush($author);
		Assert::false($book->isModified());
	}


	public function testCollectionState(): void
	{
		$publisher = new Publisher();
		$publisher->name = 'Jupiter Mining Corporation';

		$author = new Author();
		$author->name = 'Arnold Judas Rimmer';
		$this->orm->persistAndFlush($author);
		Assert::same([], iterator_to_array($author->books));

		$book = new Book();
		$book->title = 'Better Than Life';
		$book->author = $author;
		$book->publisher = $publisher;
		Assert::same([$book], iterator_to_array($author->books));

		$this->orm->persist($book);
		Assert::same([$book], iterator_to_array($author->books));

		$this->orm->flush();
		Assert::same([$book], iterator_to_array($author->books));
	}


	public function testForeignKeyInNonConnectedRelationship(): void
	{
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip('Only for DB with foreign key restriction');
		} else {
			if ($this->section === Helper::SECTION_MSSQL) {
				$connection = $this->container->getByType(IConnection::class);
				$connection->query('SET IDENTITY_INSERT users ON;');
			}
		}

		$user = new User();
		$user->id = 1;
		$user2 = new User();
		$user2->id = 2;
		$user->friendsWithMe->add($user2);
		$userStat = new UserStat();
		$userStat->user = $user;
		$userStat->date = new DateTimeImmutable("2021-12-14 22:03:00");
		$userStat->value = 3;
		$this->orm->persistAndFlush($userStat);

		try {
			$this->orm->removeAndFlush($user);
		} catch (ForeignKeyConstraintViolationException $e) {
			/** @var DbalMapper<UserStat> $mapper */
			$mapper = $this->orm->userStats->getMapper();
			$mapper->rollback();
			$this->orm->refreshAll();
		}

		$friends = iterator_to_array($user->friendsWithMe);
		Assert::same(1, count($friends));
	}


	public function testCollectionCount(): void
	{
		$author2 = $this->orm->authors->getByIdChecked(2);
		$tag2 = $this->orm->tags->getByIdChecked(2);
		foreach ($tag2->books as $book) {
			$book->author = $author2;
		}
		$this->orm->persistAndFlush($tag2);

		$author1 = $this->orm->authors->getByIdChecked(1);
		Assert::same(0, $author1->books->countStored());
	}
}


$test = new RelationshipsOneHasManyPersistenceTest();
$test->run();
