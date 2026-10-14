<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../databases.ini
 */

namespace NextrasTests\Orm\Integration\Entity;


use DateTimeImmutable;
use inc\model\book\GenreEnum;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\Currency;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Money;
use ReflectionClass;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


/**
 * Locks in the hydration behavior: values loaded from storage, lazily initialized properties & wrappers,
 * modification and persistence of hydrated entities.
 */
class EntityHydrationTest extends DataTestCase
{
	public function testHydratedValues(): void
	{
		$this->orm->clear();
		$books = $this->orm->books->findAll()->orderBy('id')->fetchAll();
		Assert::count(4, $books);

		$expected = [
			// id, title, genre, published minutes:seconds, price cents, author, translator, publisher, nextPart
			[1, 'Book 1', GenreEnum::SCIFI, '10:04', 50, 1, 1, 1, null],
			[2, 'Book 2', GenreEnum::HORROR, '10:02', 150, 1, null, 2, null],
			[3, 'Book 3', GenreEnum::THRILLER, '10:03', 20, 2, 2, 3, null],
			[4, 'Book 4', GenreEnum::ROMANCE, '10:01', 220, 2, 2, 1, 3],
		];

		foreach ($books as $i => $book) {
			[$id, $title, $genre, $publishedAt, $cents, $author, $translator, $publisher, $nextPart] = $expected[$i];
			Assert::false($book->isModified());
			Assert::true($book->isPersisted());
			Assert::same($id, $book->getPersistedId());
			Assert::same($id, $book->id);
			Assert::same($title, $book->title);
			Assert::same($genre, $book->genre);
			Assert::type(DateTimeImmutable::class, $book->publishedAt);
			Assert::same($publishedAt, $book->publishedAt->format('i:s'));
			Assert::null($book->printedAt);
			Assert::false($book->hasValue('printedAt'));
			Assert::type(Money::class, $book->price);
			Assert::same($cents, $book->price->cents);
			Assert::same(Currency::CZK, $book->price->currency);
			Assert::null($book->origPrice);
			Assert::false($book->hasValue('origPrice'));
			Assert::same($author, $book->author->id);
			Assert::same($translator, $book->translator?->id);
			Assert::same($translator !== null, $book->hasValue('translator'));
			Assert::same($publisher, $book->publisher->publisherId);
			Assert::same($nextPart, $book->nextPart?->id);
			Assert::false($book->isModified());
			Assert::same([], $book->getRawValues(modifiedOnly: true));
		}

		$raw = $books[0]->getRawValues();
		Assert::same(1, $raw['id']);
		Assert::same('Book 1', $raw['title']);
		Assert::same('sciFi', $raw['genre']);
		Assert::same(1, $raw['author']);
		Assert::same(1, $raw['translator']);
		Assert::null($raw['printedAt']);
		Assert::same(['cents' => 50, 'currency' => 'CZK'], $raw['price']);
		Assert::same(['cents' => null, 'currency' => null], $raw['origPrice']);

		$author = $this->orm->authors->getByIdChecked(1);
		Assert::same('Writer 1', $author->name);
		Assert::same('http://example.com/1', $author->web);
		Assert::null($author->bornOn); // nullable DateWrapper with null storage value, default is not applied
		Assert::same(0, $author->age); // virtual property
		Assert::false($author->isModified());
	}


	public function testModifyAndPersistLazyProperties(): void
	{
		$this->orm->clear();
		$book = $this->orm->books->getByIdChecked(2);

		$book->printedAt = new DateTimeImmutable('2022-01-02 03:04:05');
		$book->genre = GenreEnum::FANTASY;
		$book->origPrice = new Money(99, Currency::EUR);
		$book->translator = 2;
		Assert::true($book->isModified('printedAt'));
		Assert::true($book->isModified('genre'));
		Assert::true($book->isModified('origPrice'));
		Assert::true($book->isModified('translator'));
		Assert::false($book->isModified('title'));
		Assert::false($book->isModified('price'));

		$author = $book->author;
		$author->bornOn = new \Nextras\Dbal\Utils\DateTimeImmutable('2000-02-03 12:13:14');
		Assert::same('2000-02-03 00:00:00', $author->bornOn?->format('Y-m-d H:i:s'));
		$this->orm->persistAndFlush($book);
		Assert::false($book->isModified());

		$expectedPrintedAt = (new DateTimeImmutable('2022-01-02 03:04:05'))->getTimestamp();
		$this->orm->clear();

		$book = $this->orm->books->getByIdChecked(2);
		Assert::same($expectedPrintedAt, $book->printedAt?->getTimestamp());
		Assert::same(GenreEnum::FANTASY, $book->genre);
		Assert::same(99, $book->origPrice?->cents);
		Assert::same(Currency::EUR, $book->origPrice?->currency);
		Assert::same(150, $book->price?->cents);
		Assert::same(2, $book->translator?->id);
		Assert::same('Book 2', $book->title);
		Assert::same('2000-02-03', $book->author->bornOn?->format('Y-m-d'));
		Assert::false($book->isModified());

		// resetting a lazily loaded value back to null
		$book->printedAt = null;
		$book->origPrice = null;
		$this->orm->persistAndFlush($book);
		$this->orm->clear();
		$book = $this->orm->books->getByIdChecked(2);
		Assert::null($book->printedAt);
		Assert::null($book->origPrice);
	}


	public function testOnLoadIgnoresVirtualAndUnknownKeys(): void
	{
		$author = (new ReflectionClass(Author::class))->newInstanceWithoutConstructor();
		$this->orm->authors->attach($author);
		$author->onLoad([
			'id' => 99,
			'name' => 'Loaded',
			'web' => null,
			'bornOn' => null,
			'age' => 123, // virtual
			'unknown' => 'x',
		]);

		Assert::same(99, $author->getPersistedId());
		Assert::same('Loaded', $author->name);
		Assert::false($author->hasValue('web')); // null from storage, default is not applied
		Assert::null($author->getRawValue('bornOn'));
		Assert::same(0, $author->age);
		Assert::false($author->isModified());
		$this->orm->authors->detach($author);
	}


	public function testOnLoadKeepsPresetValuesForNullData(): void
	{
		// onLoad() may be called on an entity which already has some data (e.g. set in an overridden onLoad())
		$author = (new ReflectionClass(Author::class))->newInstanceWithoutConstructor();
		$this->orm->authors->attach($author);
		$author->setRawValue('web', 'http://preset.example.com');
		$author->onLoad([
			'id' => 98,
			'name' => 'Loaded',
			'web' => null,
		]);

		Assert::same(98, $author->getPersistedId());
		Assert::same('Loaded', $author->name);
		Assert::same('http://preset.example.com', $author->web);
		$this->orm->authors->detach($author);
	}


	public function testMetadataNonVirtualPropertyNames(): void
	{
		$metadata = $this->orm->authors->getEntityMetadata();
		$names = $metadata->getNonVirtualPropertyNames();
		Assert::false(isset($names['age']));
		$expected = [];
		foreach ($metadata->getProperties() as $name => $property) {
			if (!$property->isVirtual) $expected[$name] = true;
		}
		Assert::same($expected, $names);

		$bookMetadata = $this->orm->books->getEntityMetadata();
		Assert::true(isset($bookMetadata->getNonVirtualPropertyNames()['price']));
		Assert::false(isset($bookMetadata->getNonVirtualPropertyNames()['tags'])); // m:m is virtual
		Assert::type(Book::class, $this->orm->books->getByIdChecked(1));
	}
}


$test = new EntityHydrationTest();
$test->run();
