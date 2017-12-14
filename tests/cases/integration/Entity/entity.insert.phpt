<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Entity;

use Nextras\Dbal\IConnection;
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class NewEntityTest extends DataTestCase
{
	public function testInsert()
	{
		$author = new Author();
		$author->name = 'Jon Snow';
		$author->web = 'http://nextras.cz';

		Assert::false($author->isPersisted());
		Assert::true($author->isModified());
		Assert::false($author->hasValue('id'));

		$this->orm->authors->persistAndFlush($author);

		Assert::true($author->isPersisted());
		Assert::false($author->isModified());
		Assert::same(3, $author->id);
	}


	public function testInsertWithPrimaryKey()
	{
		if ($this->section === Helper::SECTION_MSSQL) {
			$connection = $this->container->getByType(IConnection::class);
			$connection->query('SET IDENTITY_INSERT authors ON;');
		}

		$author = new Author();
		$author->id = 555;
		$author->name = 'Jon Snow';
		$author->web = 'http://nextras.cz';

		Assert::false($author->isPersisted());
		Assert::true($author->isModified());
		Assert::same(555, $author->id);

		$this->orm->authors->persistAndFlush($author);

		$author = $this->orm->authors->findBy(['id' => 555])->fetch();
		assert($author instanceof Author);
		Assert::true($author->isPersisted());
		Assert::false($author->isModified());
		Assert::same(555, $author->id);
	}


	public function testDuplicatePrimaryKey()
	{
		if ($this->section === Helper::SECTION_MSSQL) {
			$connection = $this->container->getByType(IConnection::class);
			$connection->query('SET IDENTITY_INSERT authors ON;');
		}

		$author1 = new Author();
		$author1->id = 444;
		$author1->name = 'Jon Snow';
		$author1->web = 'http://nextras.cz';

		$this->orm->authors->persistAndFlush($author1);

		$author2 = new Author();
		$author2->id = 444;
		$author2->name = 'The Imp';
		$author2->web = 'http://nextras.cz/imp';

		try {
			$this->orm->authors->persistAndFlush($author2);
			Assert::fail('Duplicit PK exception expected.');
		} catch (\Exception $e) { // general because of different mapper impl.
		}

		if ($this->orm->authors->getMapper() instanceof DbalMapper) {
			$this->orm->authors->getMapper()->rollback();
		}

		Assert::false($author2->isPersisted());
		Assert::true($author2->isModified());

		$author2->id = 445;
		$this->orm->authors->persistAndFlush($author2);

		Assert::true($author2->isPersisted());
		Assert::false($author2->isModified());
		Assert::same(445, $author2->getPersistedId());
	}
}


$test = new NewEntityTest($dic);
$test->run();
