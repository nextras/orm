<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Model;


use Nextras\Orm\Entity\IEntity;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\DataTestCase;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';


class ModelRollbackTest extends DataTestCase
{
	protected bool $snapshotQueries = false;


	public function testRollbackDiscardsWritesEntitiesAndFlushNotifications(): void
	{
		$author = new Author();
		$author->name = 'Uncommitted';
		$this->orm->persist($author);
		$id = $author->id;
		$publisher = $this->orm->publishers->getByIdChecked(1);
		$originalName = $publisher->name;
		$publisher->name = 'Uncommitted publisher';
		$this->orm->persist($publisher);
		$this->orm->rollback();
		Assert::false($author->isAttached());
		Assert::false($author->isPersisted());
		Assert::false($publisher->isAttached());
		Assert::null($this->orm->authors->getById($id));
		Assert::same($originalName, $this->orm->publishers->getByIdChecked(1)->name);

		$notifications = [];
		$this->orm->onFlush[] = static function (array $persisted) use (&$notifications): void {
			$notifications[] = count($persisted);
		};
		$this->orm->flush();
		Assert::same([0], $notifications);

		$fresh = new Author();
		$fresh->name = 'Committed';
		$this->orm->persistAndFlush($fresh);
		Assert::true($fresh->isPersisted());
	}


	public function testRollbackInvalidatesRemovedEntityAndRestoresRelationship(): void
	{
		$book = $this->orm->books->getByIdChecked(1);
		$tag = $book->tags->toCollection()->fetchChecked();
		$tagId = $tag->id;
		$book->tags->remove($tag);
		$this->orm->persist($book);
		$this->orm->remove($book, false);
		$this->orm->rollback();
		Assert::false($book->isAttached());
		Assert::false($book->isPersisted());
		$fresh = $this->orm->books->getByIdChecked(1);
		Assert::notSame($book, $fresh);
		Assert::type(IEntity::class, $fresh->tags->toCollection()->getBy(['id' => $tagId]));
	}
}


(new ModelRollbackTest())->run();
