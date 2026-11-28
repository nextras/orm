<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Entity;


use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Document;
use NextrasTests\Orm\Folder;
use NextrasTests\Orm\Identifier;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';


class EntityPkWrapperTest extends DataTestCase
{
	private const Id1 = '0192f0a8-7c8e-7000-8000-000000000001';
	private const Id2 = '0192f0a8-7c8e-7000-8000-000000000002';


	public function testPersistAndLoad(): void
	{
		$folder = $this->createFolder(self::Id1, 'Inbox');
		Assert::same(self::Id1, $folder->getPersistedId());
		Assert::same(self::Id1, $folder->getRawValue('id'));
		Assert::type(Identifier::class, $folder->id);

		Assert::same($folder, $this->orm->folders->getById(new Identifier(self::Id1)));
		Assert::same($folder, $this->orm->folders->getById(self::Id1));

		$this->orm->clear();

		$folder = $this->orm->folders->getByIdChecked(new Identifier(self::Id1));
		Assert::type(Identifier::class, $folder->id);
		Assert::same(self::Id1, $folder->id->value);
		Assert::same($folder, $this->orm->folders->getById(new Identifier(self::Id1)));
		Assert::same($folder, $this->orm->folders->getBy(['id' => new Identifier(self::Id1)]));
		Assert::same([$folder], $this->orm->folders->findByIds([new Identifier(self::Id1)])->fetchAll());
		Assert::null($this->orm->folders->getById(new Identifier(self::Id2)));
	}


	public function testUpdateAndRemove(): void
	{
		$this->createFolder(self::Id1, 'Inbox');
		$this->orm->clear();

		$folder = $this->orm->folders->getByIdChecked(new Identifier(self::Id1));
		$folder->name = 'Archive';
		$this->orm->persistAndFlush($folder);
		$this->orm->clear();

		$folder = $this->orm->folders->getByIdChecked(new Identifier(self::Id1));
		Assert::same('Archive', $folder->name);

		$this->orm->removeAndFlush($folder);
		Assert::null($this->orm->folders->getById(new Identifier(self::Id1)));
		$this->orm->clear();
		Assert::null($this->orm->folders->getById(new Identifier(self::Id1)));
	}


	public function testRelationships(): void
	{
		$folder1 = $this->createFolder(self::Id1, 'Inbox');
		$this->createFolder(self::Id2, 'Archive');

		$document = new Document();
		$document->name = 'Letter';
		$document->folder = $folder1;
		$this->orm->persistAndFlush($document);
		Assert::same(self::Id1, $document->getRawValue('folder'));
		$documentId = $document->id;
		Assert::type(Identifier::class, $documentId);
		Assert::true(is_int($documentId->value));
		Assert::same($documentId->value, $document->getPersistedId());

		$this->orm->clear();

		$document = $this->orm->documents->getByIdChecked($documentId);
		Assert::same($documentId->value, $document->id->value);
		Assert::same(self::Id1, $document->folder->id->value);
		Assert::same(
			['Letter'],
			$this->orm->folders->getByIdChecked(new Identifier(self::Id1))->documents->toCollection()->fetchPairs(null, 'name'),
		);
		Assert::same(1, $this->orm->documents->findBy(['folder' => new Identifier(self::Id1)])->count());
		Assert::same(1, $this->orm->documents->findBy(['folder->id' => new Identifier(self::Id1)])->count());
		Assert::same(1, $this->orm->documents->findBy(['folder->name' => 'Inbox'])->count());

		$document->folder = $this->orm->folders->getByIdChecked(new Identifier(self::Id2));
		$this->orm->persistAndFlush($document);
		$this->orm->clear();

		$folders = $this->orm->folders->findAll()->orderBy('id')->fetchAll();
		Assert::count(2, $folders);
		Assert::same(0, $folders[0]->documents->count());
		Assert::same(1, $folders[1]->documents->count());
		Assert::same(0, $folders[0]->documents->countStored());
		Assert::same(1, $folders[1]->documents->countStored());
	}


	private function createFolder(string $id, string $name): Folder
	{
		$folder = new Folder();
		$folder->id = new Identifier($id);
		$folder->name = $name;
		$this->orm->persistAndFlush($folder);
		return $folder;
	}
}


$test = new EntityPkWrapperTest();
$test->run();
