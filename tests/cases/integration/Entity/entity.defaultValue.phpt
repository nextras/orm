<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Entity;

use DateTimeImmutable;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class EntityDefaultValueTest extends TestCase
{
	public function testGetValue()
	{
		/** @var Author $author */
		$author = $this->e(Author::class);
		Assert::same('http://www.example.com', $author->web);

		$author->web = 'http://www.nextras.cz';
		Assert::same('http://www.nextras.cz', $author->web);
	}


	public function testSetValue()
	{
		/** @var Author $author */
		$author = $this->e(Author::class);
		$author->web = 'http://www.nextras.cz';

		Assert::same('http://www.nextras.cz', $author->web);
	}


	public function testSetNULLValue()
	{
		/** @var Author $author */
		$author = $this->e(Author::class);
		Assert::type(DateTimeImmutable::class, $author->born);

		$author->born = null;
		Assert::null($author->born);
	}


	public function testGetRawValue()
	{
		/** @var Author $author */
		$author = $this->e(Author::class);
		Assert::same('http://www.example.com', $author->getRawValue('web'));
	}


	public function testGetProperty()
	{
		/** @var Author $author */
		$author = $this->e(Author::class);
		Assert::same('http://www.example.com', $author->getProperty('web'));
	}


	public function testNullPersist()
	{
		$author = new Author();
		$author->name = 'Test';
		$this->orm->authors->persistAndFlush($author);

		Assert::true($author->born instanceof \DateTimeImmutable);
		Assert::same('http://www.example.com', $author->web);

		$author = new Author();
		$author->name = 'Test';
		$author->born = null;
		$this->orm->authors->persistAndFlush($author);

		Assert::null($author->born);
		Assert::same('http://www.example.com', $author->web);
	}


	public function testDefaultAndNullForPersisted()
	{
		$author = new Author();
		$author->name = 'Test';
		$author->born = null;
		$this->orm->authors->persistAndFlush($author);
		Assert::null($author->born);
		$id = $author->getPersistedId();

		$this->orm->clear();

		$author = $this->orm->authors->getById($id);
		Assert::null($author->born);
	}
}


$test = new EntityDefaultValueTest($dic);
$test->run();
