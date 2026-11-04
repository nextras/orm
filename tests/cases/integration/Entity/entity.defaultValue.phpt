<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Entity;


use DateTimeImmutable;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\TestCase;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';


class EntityDefaultValueTest extends TestCase
{
	public function testGetValue(): void
	{
		$author = $this->e(Author::class);
		assert($author instanceof Author);
		Assert::same('http://www.example.com', $author->web);

		$author->web = 'http://www.nextras.cz';
		Assert::same('http://www.nextras.cz', $author->web);
	}


	public function testSetValue(): void
	{
		$author = $this->e(Author::class);
		assert($author instanceof Author);
		$author->web = 'http://www.nextras.cz';

		Assert::same('http://www.nextras.cz', $author->web);
	}


	public function testSetNULLValue(): void
	{
		$author = $this->e(Author::class);
		assert($author instanceof Author);
		Assert::type(DateTimeImmutable::class, $author->bornOn);

		$author->bornOn = null;
		Assert::null($author->bornOn);
	}


	public function testGetRawValue(): void
	{
		$author = $this->e(Author::class);
		assert($author instanceof Author);
		Assert::same('http://www.example.com', $author->getRawValue('web'));
	}


	public function testNullPersist(): void
	{
		$author = new Author();
		$author->name = 'Test';
		$this->orm->authors->persistAndFlush($author);

		Assert::true($author->bornOn instanceof DateTimeImmutable);
		Assert::same('http://www.example.com', $author->web);

		$author = new Author();
		$author->name = 'Test';
		$author->bornOn = null;
		$this->orm->authors->persistAndFlush($author);

		Assert::null($author->bornOn);
		Assert::same('http://www.example.com', $author->web);
	}


	public function testDefaultAndNullForPersisted(): void
	{
		$author = new Author();
		$author->name = 'Test';
		$author->bornOn = null;
		$this->orm->authors->persistAndFlush($author);
		Assert::null($author->bornOn);
		$id = $author->getPersistedId();

		$this->orm->clear();

		$author = $this->orm->authors->getByIdChecked($id);
		Assert::null($author->bornOn);
	}
}


$test = new EntityDefaultValueTest();
$test->run();
